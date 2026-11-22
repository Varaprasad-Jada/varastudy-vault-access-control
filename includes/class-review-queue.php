<?php
/**
 * Review Queue for VaraStudy Vault Access Control.
 *
 * Adds "VaraStudy Access -> Review Queue": one admin-only screen listing
 * contributor submissions across Subjects, Study Materials and Question Papers,
 * with filters, counts and Approve / Request changes / Edit / View actions.
 */

if (!defined('ABSPATH')) { exit; }

final class VaraStudy_Vault_Review_Queue {

    const SLUG        = 'vsvac-review';
    const PER_PAGE    = 20;
    const META_STATUS = '_vsvac_review_status';
    const META_NOTE   = '_vsvac_review_note';

    /** Post types managed by the plugin (from Settings), slug => singular label. */
    private static function types() {
        $out = array();
        foreach (VaraStudy_Vault_Access_Control::cpts() as $slug) {
            $obj = get_post_type_object($slug);
            if ($obj) $out[$slug] = $obj->labels->singular_name;
        }
        return $out;
    }

    private static function tax() {
        return VaraStudy_Vault_Access_Control::tax();
    }

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 20);
        add_action('admin_post_vsvac_review_approve', array(__CLASS__, 'handle_approve'));
        add_action('admin_post_vsvac_review_changes', array(__CLASS__, 'handle_changes'));
        add_action('transition_post_status', array(__CLASS__, 'on_transition'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'editor_notice'));
    }

    /* ---------------------------------------------------------------
     * Menu
     * ------------------------------------------------------------- */

    public static function menu() {
        $pending = 0;
        foreach (array_keys(self::types()) as $type) {
            $c = wp_count_posts($type);
            $pending += isset($c->pending) ? (int) $c->pending : 0;
        }
        $bubble = $pending ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '';

        add_submenu_page(
            'vsvac-access',
            'Review Queue',
            'Review Queue' . $bubble,
            'manage_options',
            self::SLUG,
            array(__CLASS__, 'page')
        );
    }

    /* ---------------------------------------------------------------
     * Data helpers
     * ------------------------------------------------------------- */

    private static function get_int($key) {
        return isset($_GET[$key]) ? absint(wp_unslash($_GET[$key])) : 0;
    }

    private static function filters() {
        $type = isset($_GET['type']) ? sanitize_key(wp_unslash($_GET['type'])) : '';
        if (!array_key_exists($type, self::types())) $type = '';

        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if (!in_array($status, array('pending', 'needs_changes', 'published'), true)) $status = '';

        return array(
            'type'        => $type,
            'group'       => self::get_int('group'),
            'semester'    => self::get_int('semester'),
            'subject'     => self::get_int('subject'),
            'contributor' => self::get_int('contributor'),
            'status'      => $status,
        );
    }

    private static function contributor_ids() {
        $ids = get_users(array('role' => VaraStudy_Vault_Access_Control::ROLE, 'fields' => 'ID'));
        return array_map('intval', $ids);
    }

    private static function subject_id($post_id) {
        $v = get_post_meta($post_id, VaraStudy_Vault_Settings::get('subject_field'), true);
        if (is_array($v)) $v = reset($v);
        if (is_object($v) && isset($v->ID)) return (int) $v->ID;
        return is_numeric($v) ? (int) $v : 0;
    }

    private static function semester($post) {
        if ($post->post_type === VaraStudy_Vault_Settings::get('subject_type')) {
            return (int) get_post_meta($post->ID, VaraStudy_Vault_Settings::get('semester_field'), true);
        }
        if (in_array($post->post_type, (array) VaraStudy_Vault_Settings::get('own_semester_types'), true)) {
            $own = (int) get_post_meta($post->ID, VaraStudy_Vault_Settings::get('semester_field'), true);
            if ($own) return $own;
        }
        $sid = self::subject_id($post->ID);
        return $sid ? (int) get_post_meta($sid, VaraStudy_Vault_Settings::get('semester_field'), true) : 0;
    }

    private static function review_status($post) {
        if ($post->post_status === 'publish') return 'published';
        if ($post->post_status === 'pending') return 'pending';
        if ($post->post_status === 'draft') {
            if (get_post_meta($post->ID, self::META_STATUS, true) === 'needs_changes'
                || get_post_meta($post->ID, '_vsvac_scope_error', true)) {
                return 'needs_changes';
            }
            return 'draft';
        }
        return 'other';
    }

    private static function status_label($status) {
        $labels = array(
            'pending'       => 'Pending',
            'needs_changes' => 'Needs changes',
            'published'     => 'Approved',
            'draft'         => 'Draft',
        );
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }

    private static function status_color($status) {
        $colors = array(
            'pending'       => '#996800',
            'needs_changes' => '#b32d2e',
            'published'     => '#1a7f37',
            'draft'         => '#646970',
        );
        return isset($colors[$status]) ? $colors[$status] : '#646970';
    }

    /** Build the filtered rows and the status counts. */
    private static function collect($f) {
        $out = array(
            'rows'   => array(),
            'counts' => array('all' => 0, 'pending' => 0, 'needs_changes' => 0, 'published' => 0),
        );

        if (!self::types()) return $out;

        $authors = $f['contributor'] ? array($f['contributor']) : self::contributor_ids();
        if (!$authors) return $out;

        $args = array(
            'post_type'      => $f['type'] ? array($f['type']) : array_keys(self::types()),
            'post_status'    => array('pending', 'draft', 'publish'),
            'author__in'     => $authors,
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        );
        if ($f['group']) {
            $args['tax_query'] = array(array(
                'taxonomy' => self::tax(),
                'field'    => 'term_id',
                'terms'    => array($f['group']),
            ));
        }

        $q = new WP_Query($args);

        foreach ($q->posts as $p) {
            $sid = $p->post_type === VaraStudy_Vault_Settings::get('subject_type') ? (int) $p->ID : self::subject_id($p->ID);
            if ($f['subject'] && $sid !== $f['subject']) continue;

            $sem = self::semester($p);
            if ($f['semester'] && $sem !== $f['semester']) continue;

            $status = self::review_status($p);

            // Counts ignore the status filter so the tabs always show totals.
            $out['counts']['all']++;
            if (isset($out['counts'][$status])) $out['counts'][$status]++;

            if ($f['status'] && $status !== $f['status']) continue;

            $groups = wp_get_post_terms($p->ID, self::tax(), array('fields' => 'names'));
            $author = get_userdata($p->post_author);
            $note   = get_post_meta($p->ID, self::META_NOTE, true);
            if (!$note) $note = get_post_meta($p->ID, '_vsvac_scope_error', true);

            $out['rows'][] = array(
                'id'       => (int) $p->ID,
                'title'    => $p->post_title !== '' ? $p->post_title : '(no title)',
                'type'     => self::types()[$p->post_type],
                'group'    => is_wp_error($groups) ? '' : implode(', ', $groups),
                'subject'  => ($p->post_type !== VaraStudy_Vault_Settings::get('subject_type') && $sid) ? get_the_title($sid) : '',
                'semester' => $sem,
                'author'   => $author ? $author->display_name : 'Unknown',
                'date'     => $p->post_modified,
                'status'   => $status,
                'note'     => $status === 'needs_changes' ? $note : '',
                'post'     => $p,
            );
        }
        return $out;
    }

    /* ---------------------------------------------------------------
     * Page
     * ------------------------------------------------------------- */

    private static function select($name, $options, $current, $all_label) {
        echo '<select name="' . esc_attr($name) . '"><option value="">' . esc_html($all_label) . '</option>';
        foreach ($options as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected((string) $current, (string) $value, false),
                esc_html($label)
            );
        }
        echo '</select> ';
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;

        $f   = self::filters();
        $res = self::collect($f);
        $rows   = $res['rows'];
        $counts = $res['counts'];

        // Options for the filter dropdowns.
        $group_opts = array();
        $terms = get_terms(array('taxonomy' => self::tax(), 'hide_empty' => false));
        if (!is_wp_error($terms)) foreach ($terms as $t) $group_opts[$t->term_id] = $t->name;

        $sem_opts = array();
        $sem_total = max(1, (int) VaraStudy_Vault_Settings::get('semester_count'));
        for ($s = 1; $s <= $sem_total; $s++) $sem_opts[$s] = 'Semester ' . $s;

        $subject_opts = array();
        $subjects = get_posts(array(
            'post_type' => VaraStudy_Vault_Settings::get('subject_type'), 'post_status' => array('publish', 'pending', 'draft'),
            'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC',
        ));
        foreach ($subjects as $s) $subject_opts[$s->ID] = $s->post_title;

        $contrib_opts = array();
        foreach (get_users(array('role' => VaraStudy_Vault_Access_Control::ROLE, 'orderby' => 'display_name')) as $u) {
            $contrib_opts[$u->ID] = $u->display_name;
        }

        $type_opts = self::types();

        // Notices after an action.
        $notices = array(
            'approved' => array('success', 'Approved and published.'),
            'changes'  => array('success', 'Sent back to the contributor as a draft with your note.'),
            'nonote'   => array('error', 'Please write a short note so the contributor knows what to fix.'),
        );
        $code = isset($_GET['vsvac_rq']) ? sanitize_key(wp_unslash($_GET['vsvac_rq'])) : '';

        echo '<div class="wrap"><h1>Review Queue</h1>';
        if (isset($notices[$code])) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($notices[$code][0]), esc_html($notices[$code][1]));
        }

        // Filters.
        echo '<form method="get" style="margin:14px 0;">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        if ($f['status']) echo '<input type="hidden" name="status" value="' . esc_attr($f['status']) . '">';
        self::select('type', $type_opts, $f['type'], 'All types');
        self::select('group', $group_opts, $f['group'], 'All course groups');
        self::select('semester', $sem_opts, $f['semester'], 'All semesters');
        self::select('subject', $subject_opts, $f['subject'], 'All subjects');
        self::select('contributor', $contrib_opts, $f['contributor'], 'All contributors');
        echo '<button class="button">Filter</button> ';
        echo '<a class="button-link" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG)) . '">Reset</a>';
        echo '</form>';

        // Status tabs with counts (they keep the other filters).
        $keep = array();
        foreach (array('type', 'group', 'semester', 'subject', 'contributor') as $k) {
            if ($f[$k]) $keep[$k] = $f[$k];
        }
        $base = add_query_arg(array_merge(array('page' => self::SLUG), $keep), admin_url('admin.php'));
        $tabs = array(
            ''              => array('All', 'all'),
            'pending'       => array('Pending', 'pending'),
            'needs_changes' => array('Needs changes', 'needs_changes'),
            'published'     => array('Approved', 'published'),
        );
        $links = array();
        foreach ($tabs as $key => $tab) {
            $url   = $key ? add_query_arg('status', $key, $base) : $base;
            $label = sprintf('%s (%d)', $tab[0], $counts[$tab[1]]);
            $links[] = ($f['status'] === $key)
                ? '<strong>' . esc_html($label) . '</strong>'
                : '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '<p class="subsubsub" style="float:none;margin:0 0 12px;">' . implode(' | ', $links) . '</p>';

        // Pagination.
        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $paged = min($pages, max(1, self::get_int('paged')));
        $slice = array_slice($rows, ($paged - 1) * self::PER_PAGE, self::PER_PAGE);

        echo '<table class="widefat striped"><thead><tr>';
        foreach (array('Title', 'Type', 'Course Group', 'Subject', 'Sem', 'Contributor', 'Submitted / updated', 'Status', 'Actions') as $h) {
            echo '<th>' . esc_html($h) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if (!$slice) {
            echo '<tr><td colspan="9">No submissions match these filters.</td></tr>';
        }

        foreach ($slice as $r) {
            $id = $r['id'];
            echo '<tr>';

            echo '<td><strong><a href="' . esc_url(get_edit_post_link($id)) . '">' . esc_html($r['title']) . '</a></strong>';
            if ($r['note']) echo '<br><small style="color:#b32d2e;">' . esc_html($r['note']) . '</small>';
            echo '</td>';

            echo '<td>' . esc_html($r['type']) . '</td>';
            echo '<td>' . esc_html($r['group'] ?: '—') . '</td>';
            echo '<td>' . esc_html($r['subject'] ?: '—') . '</td>';
            echo '<td>' . ($r['semester'] ? (int) $r['semester'] : '—') . '</td>';
            echo '<td>' . esc_html($r['author']) . '</td>';
            echo '<td>' . esc_html(mysql2date('j M Y, g:i a', $r['date'])) . '</td>';
            echo '<td><strong style="color:' . esc_attr(self::status_color($r['status'])) . ';">' . esc_html(self::status_label($r['status'])) . '</strong></td>';

            echo '<td>';
            if ($r['status'] === 'pending') {
                $approve = wp_nonce_url(
                    add_query_arg(array('action' => 'vsvac_review_approve', 'post_id' => $id), admin_url('admin-post.php')),
                    'vsvac_review_' . $id
                );
                echo '<a href="' . esc_url($approve) . '">Approve</a> | ';
            }
            echo '<a href="' . esc_url(get_edit_post_link($id)) . '">Edit</a> | ';
            $view = $r['status'] === 'published' ? get_permalink($id) : get_preview_post_link($id);
            echo '<a href="' . esc_url($view) . '" target="_blank" rel="noopener">View</a>';

            if ($r['status'] === 'pending') {
                echo '<details style="margin-top:6px;"><summary style="cursor:pointer;color:#b32d2e;">Request changes</summary>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">';
                echo '<input type="hidden" name="action" value="vsvac_review_changes">';
                echo '<input type="hidden" name="post_id" value="' . (int) $id . '">';
                wp_nonce_field('vsvac_review_' . $id);
                echo '<textarea name="note" rows="3" style="width:220px;" required placeholder="What should the contributor fix?"></textarea><br>';
                echo '<button class="button button-small">Send back</button></form></details>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(paginate_links(array(
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $paged,
                'total'   => $pages,
            )));
            echo '</div></div>';
        }

        echo '<p class="description" style="margin-top:12px;">Only submissions from users with the VaraStudy Contributor role are listed. "Needs changes" includes drafts that were returned by you or by the automatic scope check.</p>';
        echo '</div>';
    }

    /* ---------------------------------------------------------------
     * Actions
     * ------------------------------------------------------------- */

    private static function back_url() {
        $ref = wp_get_referer();
        return $ref ? remove_query_arg('vsvac_rq', $ref) : admin_url('admin.php?page=' . self::SLUG);
    }

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die('Permission check failed.');
        $id = isset($_REQUEST['post_id']) ? absint(wp_unslash($_REQUEST['post_id'])) : 0;
        check_admin_referer('vsvac_review_' . $id);
        $post = $id ? get_post($id) : null;
        if (!$post || !array_key_exists($post->post_type, self::types())) wp_die('Invalid item.');
        return $post;
    }

    public static function handle_approve() {
        $post = self::guard();
        wp_update_post(array('ID' => $post->ID, 'post_status' => 'publish'));
        delete_post_meta($post->ID, self::META_STATUS);
        delete_post_meta($post->ID, self::META_NOTE);
        delete_post_meta($post->ID, '_vsvac_scope_error');
        wp_safe_redirect(add_query_arg('vsvac_rq', 'approved', self::back_url()));
        exit;
    }

    public static function handle_changes() {
        $post = self::guard();
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        if ($note === '') {
            wp_safe_redirect(add_query_arg('vsvac_rq', 'nonote', self::back_url()));
            exit;
        }

        wp_update_post(array('ID' => $post->ID, 'post_status' => 'draft'));
        update_post_meta($post->ID, self::META_STATUS, 'needs_changes');
        update_post_meta($post->ID, self::META_NOTE, $note);

        // Email is a bonus: some free hosts block wp_mail(), so failure is ignored.
        $author = get_userdata($post->post_author);
        if ($author && $author->user_email) {
            $subject = 'Changes requested: ' . wp_strip_all_tags($post->post_title);
            $body = "Hi " . $author->display_name . ",\n\nYour submission needs a few changes before it can be published.\n\n"
                . "Note from the reviewer:\n" . $note . "\n\n"
                . "Edit it here and submit it for review again:\n" . get_edit_post_link($post->ID, 'raw') . "\n";
            wp_mail($author->user_email, $subject, $body);
        }

        wp_safe_redirect(add_query_arg('vsvac_rq', 'changes', self::back_url()));
        exit;
    }

    /** When a contributor resubmits (Pending), clear the old "needs changes" flag. */
    public static function on_transition($new_status, $old_status, $post) {
        if ($new_status === 'pending' && $old_status !== 'pending' && array_key_exists($post->post_type, self::types())) {
            delete_post_meta($post->ID, self::META_STATUS);
            delete_post_meta($post->ID, self::META_NOTE);
        }
    }

    /** Show the reviewer's note at the top of the editor so the contributor sees it. */
    public static function editor_notice() {
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post' || !array_key_exists($screen->post_type, self::types())) return;

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$post_id) return;

        if (get_post_meta($post_id, self::META_STATUS, true) !== 'needs_changes') return;
        $note = get_post_meta($post_id, self::META_NOTE, true);
        if (!$note) return;

        echo '<div class="notice notice-warning"><p><strong>Changes requested by the reviewer:</strong> '
            . esc_html($note) . '</p></div>';
    }
}
