<?php
/**
 * Optional file protection for VaraStudy Vault Access Control.
 *
 * When enabled in Settings, non-image files uploaded by contributors are stored in
 * uploads/vsvac-protected/ (blocked from direct access by .htaccess) and served by PHP.
 *
 * A protected file is public only while at least one managed post that uses it is
 * currently published. This is checked live on every request, so unpublishing,
 * trashing or deleting the post makes the file private again immediately.
 *
 * A post can only make a file public if the file was uploaded by that post's author,
 * so a contributor cannot expose another contributor's file by guessing its ID.
 *
 * Requires Apache or LiteSpeed (.htaccess). On Nginx the folder is not blocked.
 */

if (!defined('ABSPATH')) { exit; }

final class VaraStudy_Vault_File_Protection {

    const DIR            = 'vsvac-protected';
    const META_PROTECTED = '_vsvac_protected';   // on the attachment: stored in the protected folder
    const META_VIA       = '_vsvac_public_via';  // on the attachment: one value per post that uses it
    const META_FILES     = '_vsvac_public_files'; // on the post: attachment IDs it currently uses

    private static $uploading = false;

    public static function init() {
        // Always on: files already stored in the protected folder must keep working
        // even if the setting is switched off later.
        add_filter('wp_get_attachment_url', array(__CLASS__, 'filter_url'), 10, 2);
        add_action('init', array(__CLASS__, 'maybe_serve'), 5);
        add_action('save_post', array(__CLASS__, 'sync_public_files'), 99, 2);

        // Only new uploads depend on the setting.
        if (VaraStudy_Vault_Settings::get('protect_files')) {
            add_filter('wp_handle_upload_prefilter', array(__CLASS__, 'prefilter'));
            add_action('add_attachment', array(__CLASS__, 'tag_attachment'));
        }
    }

    /* ---------------------------------------------------------------
     * Upload handling
     * ------------------------------------------------------------- */

    private static function uploader_is_contributor() {
        if (!is_user_logged_in() || current_user_can('manage_options')) return false;
        $user = wp_get_current_user();
        return in_array(VaraStudy_Vault_Access_Control::ROLE, (array) $user->roles, true);
    }

    public static function prefilter($file) {
        self::$uploading = false;

        if (!self::uploader_is_contributor()) return $file;

        // Images stay in the normal uploads folder (thumbnails and galleries keep working).
        $check = wp_check_filetype(isset($file['name']) ? $file['name'] : '');
        if (!empty($check['type']) && strpos($check['type'], 'image/') === 0) return $file;

        if (!self::ensure_dir()) return $file;

        self::$uploading = true;
        add_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        add_filter('wp_handle_upload', array(__CLASS__, 'finish_upload'));
        return $file;
    }

    /** Only the sub-folder changes, so WordPress' relative paths stay consistent. */
    public static function filter_upload_dir($dirs) {
        $sub = '/' . self::DIR . $dirs['subdir'];
        $dirs['subdir'] = $sub;
        $dirs['path']   = $dirs['basedir'] . $sub;
        $dirs['url']    = $dirs['baseurl'] . $sub;
        return $dirs;
    }

    public static function finish_upload($result) {
        remove_filter('upload_dir', array(__CLASS__, 'filter_upload_dir'));
        return $result;
    }

    public static function tag_attachment($attachment_id) {
        if (self::$uploading) {
            update_post_meta($attachment_id, self::META_PROTECTED, '1');
        }
    }

    private static function ensure_dir() {
        $uploads = wp_upload_dir();
        if (!empty($uploads['error'])) return false;

        $dir = trailingslashit($uploads['basedir']) . self::DIR;
        if (!wp_mkdir_p($dir)) return false;

        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n";
            file_put_contents($htaccess, $rules);
        }
        if (!file_exists($dir . '/index.html')) {
            file_put_contents($dir . '/index.html', '');
        }
        return true;
    }

    /* ---------------------------------------------------------------
     * Public URL and serving
     * ------------------------------------------------------------- */

    /** Protected files are always linked through the PHP handler. */
    public static function filter_url($url, $attachment_id) {
        if (get_post_meta($attachment_id, self::META_PROTECTED, true)) {
            return add_query_arg('vsvac_file', (int) $attachment_id, home_url('/'));
        }
        return $url;
    }

    /** Live check: is any managed post using this file currently published? */
    private static function is_public($attachment_id) {
        $cpts = VaraStudy_Vault_Access_Control::cpts();
        if (!$cpts) return false;

        foreach ((array) get_post_meta($attachment_id, self::META_VIA) as $post_id) {
            $post = get_post((int) $post_id);
            if (
                $post
                && $post->post_status === 'publish'
                && $post->post_password === ''
                && in_array($post->post_type, $cpts, true)
            ) {
                return true;
            }
        }
        return false;
    }

    private static function can_read($attachment) {
        if (self::is_public($attachment->ID)) return true;
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;
        return (int) $attachment->post_author === get_current_user_id();
    }

    private static function not_found() {
        status_header(404);
        nocache_headers();
        echo 'File not found.';
        exit;
    }

    public static function maybe_serve() {
        if (!isset($_GET['vsvac_file'])) return;

        $id = absint(wp_unslash($_GET['vsvac_file']));
        $attachment = $id ? get_post($id) : null;

        if (!$attachment || $attachment->post_type !== 'attachment' || !get_post_meta($id, self::META_PROTECTED, true)) {
            self::not_found();
        }
        // Same answer for "missing" and "not allowed", so file IDs cannot be probed.
        if (!self::can_read($attachment)) {
            self::not_found();
        }

        $uploads = wp_get_upload_dir();
        $base = realpath(trailingslashit($uploads['basedir']) . self::DIR);
        $file = get_attached_file($id);
        $real = $file ? realpath($file) : false;

        if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || !is_readable($real)) {
            self::not_found();
        }

        $mime = get_post_mime_type($id);
        if (!$mime) $mime = 'application/octet-stream';
        $public = self::is_public($id);

        while (ob_get_level()) { ob_end_clean(); }

        status_header(200);
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($real)) . '"');
        header('X-Content-Type-Options: nosniff');
        // Short public cache, so a file that was just unpublished does not linger in caches.
        header('Cache-Control: ' . ($public ? 'public, max-age=300' : 'private, no-store'));

        readfile($real);
        exit;
    }

    /* ---------------------------------------------------------------
     * Which files does a published post use?
     * ------------------------------------------------------------- */

    private static function collect_ids($value, &$ids) {
        if (is_array($value)) {
            foreach ($value as $item) self::collect_ids($item, $ids);
        } elseif (is_numeric($value)) {
            $ids[] = (int) $value;
        }
    }

    /**
     * With ACF, only file, image and gallery fields count, so numbers in other fields
     * (semester, counts, ordering, subject IDs) can never be mistaken for a file.
     * Without ACF, any custom field is considered, and the author check below applies.
     */
    private static function is_file_field($post_id, $key) {
        if (!function_exists('acf_get_field')) return true;

        $reference = get_post_meta($post_id, '_' . $key, true);
        if (!is_string($reference) || strpos($reference, 'field_') !== 0) return true; // not an ACF field

        $field = acf_get_field($reference);
        return $field && !empty($field['type']) && in_array($field['type'], array('file', 'image', 'gallery'), true);
    }

    private static function referenced_ids($post) {
        $ids = array();

        foreach (get_post_meta($post->ID) as $key => $values) {
            if (strpos($key, '_') === 0) continue;
            if (!self::is_file_field($post->ID, $key)) continue;
            foreach ((array) $values as $value) {
                self::collect_ids(maybe_unserialize($value), $ids);
            }
        }

        // Links inserted in the content through the media button.
        if (preg_match_all('/vsvac_file=(\d+)/', (string) $post->post_content, $matches)) {
            foreach ($matches[1] as $found) $ids[] = (int) $found;
        }

        // Files attached to this post.
        $children = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_parent'    => $post->ID,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ));
        foreach ($children as $child) $ids[] = (int) $child;

        return array_values(array_unique($ids));
    }

    /** A post may only publish a protected file that its own author uploaded. */
    private static function is_own_protected_file($attachment_id, $post) {
        if ($attachment_id <= 0 || !get_post_meta($attachment_id, self::META_PROTECTED, true)) return false;

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') return false;

        return (int) $attachment->post_author === (int) $post->post_author;
    }

    /**
     * Keep the "used by" records in step with what a published post references.
     * Unpublishing needs no cleanup here, because is_public() checks the status live.
     */
    public static function sync_public_files($post_id, $post) {
        if (!$post || wp_is_post_revision($post_id) || $post->post_status !== 'publish') return;
        if (!in_array($post->post_type, VaraStudy_Vault_Access_Control::cpts(), true)) return;

        $now = array();
        foreach (self::referenced_ids($post) as $attachment_id) {
            if (self::is_own_protected_file($attachment_id, $post)) $now[] = $attachment_id;
        }

        $previous = array_filter(array_map('intval', (array) get_post_meta($post_id, self::META_FILES, true)));

        // Files this post no longer uses stop being public through it.
        foreach (array_diff($previous, $now) as $gone) {
            delete_post_meta($gone, self::META_VIA, $post_id);
        }

        foreach ($now as $attachment_id) {
            $via = array_map('intval', (array) get_post_meta($attachment_id, self::META_VIA));
            if (!in_array((int) $post_id, $via, true)) {
                add_post_meta($attachment_id, self::META_VIA, $post_id);
            }
        }

        update_post_meta($post_id, self::META_FILES, $now);
    }
}
