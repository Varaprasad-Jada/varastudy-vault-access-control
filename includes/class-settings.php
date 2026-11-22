<?php
/**
 * Settings for VaraStudy Vault Access Control.
 *
 * Lets the site owner choose which post types, taxonomy and custom fields the
 * plugin works with, so it does not depend on any particular theme.
 */

if (!defined('ABSPATH')) { exit; }

final class VaraStudy_Vault_Settings {

    const OPTION = 'vsvac_settings';
    const GROUP  = 'vsvac_settings_group';
    const PAGE   = 'vsvac-settings';

    private static $cache = null;

    public static function init() {
        add_action('plugins_loaded', array(__CLASS__, 'maybe_seed'), 1);
        add_action('update_option_' . self::OPTION, array(__CLASS__, 'flush_cache'));
        add_action('add_option_' . self::OPTION, array(__CLASS__, 'flush_cache'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_menu', array(__CLASS__, 'menu'), 15);
        add_action('admin_notices', array(__CLASS__, 'unconfigured_notice'));
        if (defined('VSVAC_FILE')) {
            add_filter('plugin_action_links_' . plugin_basename(VSVAC_FILE), array(__CLASS__, 'action_links'));
            add_filter('plugin_row_meta', array(__CLASS__, 'row_meta'), 10, 2);
        }
    }

    /* ---------------------------------------------------------------
     * Defaults and access
     * ------------------------------------------------------------- */

    /** Fresh installs start unconfigured, so the plugin does nothing until set up. */
    public static function defaults() {
        return array(
            'subject_type'        => '',
            'resource_types'      => array(),
            'taxonomy'            => '',
            'subject_field'       => 'subject',
            'semester_field'      => 'semester',
            'semester_count'      => 6,
            'own_semester_types'  => array(),
            'protect_files'       => 0,
        );
    }

    /** The values the plugin used before Settings existed (VaraStudy Vault). */
    public static function legacy() {
        return array(
            'subject_type'        => 'subjects',
            'resource_types'      => array('study-materials', 'question-papers'),
            'taxonomy'            => 'course_group',
            'subject_field'       => 'subject',
            'semester_field'      => 'semester',
            'semester_count'      => 6,
            'own_semester_types'  => array('question-papers'),
            'protect_files'       => 0,
        );
    }

    /**
     * First load after an update: sites that already had the plugin keep their old
     * behaviour; brand-new installs start empty.
     */
    public static function maybe_seed() {
        if (get_option(self::OPTION, null) !== null) return;
        $had_plugin = get_role('varastudy_contributor');
        add_option(self::OPTION, $had_plugin ? self::legacy() : self::defaults());
    }

    public static function flush_cache() {
        self::$cache = null;
    }

    public static function all() {
        if (self::$cache === null) {
            $saved = get_option(self::OPTION, array());
            self::$cache = wp_parse_args(is_array($saved) ? $saved : array(), self::defaults());
        }
        return self::$cache;
    }

    public static function get($key) {
        $all = self::all();
        return isset($all[$key]) ? $all[$key] : null;
    }

    public static function is_configured() {
        return self::get('subject_type') !== '' && self::get('taxonomy') !== '';
    }

    /* ---------------------------------------------------------------
     * Admin UI
     * ------------------------------------------------------------- */

    public static function register() {
        register_setting(self::GROUP, self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public static function menu() {
        add_submenu_page(
            'vsvac-access',
            'Access Settings',
            'Settings',
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'page')
        );
    }

    public static function action_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE)) . '">Settings</a>');
        return $links;
    }

    /**
     * "About the developer" card, shown on this plugin's own admin pages only.
     * No external requests: the icons are inline SVG (Feather icons, MIT licence).
     */
    public static function links_box() {
        $items = array(
            array('label' => 'Website',   'text' => 'varastudyvault.site.je',    'url' => 'https://varastudyvault.site.je',             'icon' => 'globe'),
            array('label' => 'Portfolio', 'text' => 'varaprasad-jada.github.io', 'url' => 'https://varaprasad-jada.github.io/',         'icon' => 'briefcase'),
            array('label' => 'GitHub',    'text' => '@varaprasad-jada',          'url' => 'https://github.com/varaprasad-jada',         'icon' => 'github'),
            array('label' => 'LinkedIn',  'text' => 'in/varaprasadjada',         'url' => 'https://www.linkedin.com/in/varaprasadjada', 'icon' => 'linkedin'),
        );
        $icons = array(
            'globe'     => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'github'    => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>',
            'linkedin'  => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
        );
        ?>
        <style>
            .vsvac-about{max-width:760px;margin:28px 0 8px;border:1px solid #dcdcde;border-radius:14px;background:#fff;overflow:hidden;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .vsvac-about-head{display:flex;align-items:center;gap:14px;padding:20px 24px;background:linear-gradient(110deg,#052d25,#0b4936);color:#fff}
            .vsvac-avatar{display:grid;place-items:center;flex-shrink:0;width:48px;height:48px;border-radius:50%;background:#98e3a8;color:#062f27;font-size:17px;font-weight:800}
            .vsvac-about-head h3{margin:0;color:#fff;font-size:17px;line-height:1.3}
            .vsvac-about-head p{margin:2px 0 0;color:#cfe6d6;font-size:13px}
            .vsvac-tiles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:20px 24px}
            .vsvac-tile{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid #dcdcde;border-radius:10px;color:#1d2327;text-decoration:none;transition:border-color .15s,box-shadow .15s,transform .15s}
            .vsvac-tile:hover{border-color:#075c43;box-shadow:0 4px 14px rgba(7,92,67,.12);transform:translateY(-1px);color:#1d2327}
            .vsvac-tile:focus-visible{outline:2px solid #075c43;outline-offset:2px}
            .vsvac-ico{display:grid;place-items:center;flex-shrink:0;width:38px;height:38px;border-radius:10px;background:#edf5ed;color:#075c43}
            .vsvac-ico svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
            .vsvac-txt{display:grid;min-width:0}
            .vsvac-txt strong{font-size:14px}
            .vsvac-txt small{overflow:hidden;color:#646970;font-size:12px;text-overflow:ellipsis;white-space:nowrap}
            .vsvac-arrow{margin-left:auto;color:#075c43;font-weight:700}
            .vsvac-foot{padding:12px 24px;border-top:1px solid #f0f0f1;background:#f6f7f7;color:#646970;font-size:12px}
            @media (max-width:600px){.vsvac-tiles{grid-template-columns:1fr}}
        </style>
        <div class="vsvac-about">
            <div class="vsvac-about-head">
                <span class="vsvac-avatar" aria-hidden="true">VP</span>
                <div>
                    <h3>Vara Prasad Jada</h3>
                    <p>Developer of VaraStudy Vault</p>
                </div>
            </div>
            <div class="vsvac-tiles">
                <?php foreach ($items as $item): ?>
                    <a class="vsvac-tile" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener">
                        <span class="vsvac-ico"><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $icons[$item['icon']]; // phpcs:ignore WordPress.Security.EscapeOutput -- static trusted SVG ?></svg></span>
                        <span class="vsvac-txt"><strong><?php echo esc_html($item['label']); ?></strong><small><?php echo esc_html($item['text']); ?></small></span>
                        <span class="vsvac-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="vsvac-foot">Thank you for using VaraStudy Vault Access Control &middot; Version <?php echo esc_html(VaraStudy_Vault_Access_Control::VERSION); ?></div>
        </div>
        <?php
    }

    /** A plain link next to "Visit plugin site" on the Plugins list. No banner, no nag. */
    public static function row_meta($links, $file) {
        if (defined('VSVAC_FILE') && $file === plugin_basename(VSVAC_FILE)) {
            $links[] = '<a href="https://varaprasad-jada.github.io/" target="_blank" rel="noopener">Developer portfolio</a>';
        }
        return $links;
    }

    public static function unconfigured_notice() {
        if (!current_user_can('manage_options') || self::is_configured()) return;
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen) return;
        if (!in_array($screen->id, array('plugins', 'dashboard'), true) && strpos($screen->id, 'vsvac') === false) return;
        if ($screen->id === 'vsvac-access_page_' . self::PAGE) return;

        printf(
            '<div class="notice notice-warning"><p><strong>VaraStudy Vault Access Control</strong> is not set up yet, so it is not restricting anything. <a href="%s">Choose your post types and fields</a>.</p></div>',
            esc_url(admin_url('admin.php?page=' . self::PAGE))
        );
    }

    private static function selectable_post_types() {
        $skip = array('attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face');
        $out = array();
        foreach (get_post_types(array('show_ui' => true), 'objects') as $slug => $obj) {
            if (in_array($slug, $skip, true)) continue;
            $out[$slug] = $obj->labels->name . ' (' . $slug . ')';
        }
        return $out;
    }

    private static function selectable_taxonomies() {
        $skip = array('post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category');
        $out = array();
        foreach (get_taxonomies(array('show_ui' => true), 'objects') as $slug => $obj) {
            if (in_array($slug, $skip, true)) continue;
            $out[$slug] = $obj->labels->name . ' (' . $slug . ')' . ($obj->hierarchical ? '' : ' - not hierarchical');
        }
        return $out;
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;

        $s     = self::all();
        $types = self::selectable_post_types();
        $taxes = self::selectable_taxonomies();
        $name  = self::OPTION;
        ?>
        <div class="wrap">
            <h1>VaraStudy Access: Settings</h1>
            <?php settings_errors(); ?>
            <p>Tell the plugin which content your site uses. Contributors will be limited to their assigned course group and semesters for these post types, and everything they submit goes to the Review Queue for approval.</p>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>
                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="vsvac_subject_type">Subject post type</label></th>
                        <td>
                            <select id="vsvac_subject_type" name="<?php echo esc_attr($name); ?>[subject_type]">
                                <option value="">Select a post type</option>
                                <?php foreach ($types as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($s['subject_type'], $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">The post type that represents a subject or course unit. Each subject has a course group and a semester.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Resource post types</th>
                        <td>
                            <?php foreach ($types as $slug => $label): ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[resource_types][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $s['resource_types'], true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Post types that contributors submit (notes, question papers, and so on). Each one links to a subject. Do not tick the subject post type again here.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="vsvac_taxonomy">Course group taxonomy</label></th>
                        <td>
                            <select id="vsvac_taxonomy" name="<?php echo esc_attr($name); ?>[taxonomy]">
                                <option value="">Select a taxonomy</option>
                                <?php foreach ($taxes as $slug => $label): ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($s['taxonomy'], $slug); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Each contributor is assigned one term of this taxonomy (for example a course or class). A hierarchical taxonomy is recommended, because the editor checkbox is locked only for those.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="vsvac_subject_field">Subject field name</label></th>
                        <td>
                            <input id="vsvac_subject_field" class="regular-text" type="text" name="<?php echo esc_attr($name); ?>[subject_field]" value="<?php echo esc_attr($s['subject_field']); ?>">
                            <p class="description">The custom field on each resource that stores the linked subject (a post ID). With Advanced Custom Fields, use the field's name of type Post Object. Default: <code>subject</code></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="vsvac_semester_field">Semester field name</label></th>
                        <td>
                            <input id="vsvac_semester_field" class="regular-text" type="text" name="<?php echo esc_attr($name); ?>[semester_field]" value="<?php echo esc_attr($s['semester_field']); ?>">
                            <p class="description">The custom field that stores a semester number (1, 2, 3...). It is read on subjects, and on resource types ticked below. Default: <code>semester</code></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="vsvac_semester_count">Number of semesters</label></th>
                        <td>
                            <input id="vsvac_semester_count" type="number" min="1" max="12" name="<?php echo esc_attr($name); ?>[semester_count]" value="<?php echo (int) $s['semester_count']; ?>">
                            <p class="description">How many semesters (or terms) a contributor can be assigned, from 1 to 12.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Resources with their own semester</th>
                        <td>
                            <?php foreach ($types as $slug => $label): ?>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[own_semester_types][]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, (array) $s['own_semester_types'], true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">For these resource types, the semester field on the resource itself is required and must match the linked subject. Leave others unticked and the subject's semester is used.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Protect uploaded files</th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($name); ?>[protect_files]" value="1" <?php checked(!empty($s['protect_files'])); ?>>
                                Keep contributor uploads (PDFs, documents) private until the post is approved
                            </label>
                            <p class="description">Works on Apache and LiteSpeed hosts only. On Nginx the files stay reachable by direct link. Images are not protected. Test on a staging copy first.</p>
                        </td>
                    </tr>

                </table>
                <?php submit_button('Save settings'); ?>
            </form>

            <?php self::links_box(); ?>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------
     * Sanitize
     * ------------------------------------------------------------- */

    private static function clean_field_name($value, $fallback) {
        $value = is_string($value) ? preg_replace('/[^A-Za-z0-9_\-]/', '', $value) : '';
        return $value !== '' ? $value : $fallback;
    }

    public static function sanitize($in) {
        $in  = is_array($in) ? $in : array();
        $out = self::defaults();

        $types = array_keys(self::selectable_post_types());
        $taxes = array_keys(self::selectable_taxonomies());

        $subject = isset($in['subject_type']) ? sanitize_key($in['subject_type']) : '';
        $out['subject_type'] = in_array($subject, $types, true) ? $subject : '';

        $resources = isset($in['resource_types']) ? array_map('sanitize_key', (array) $in['resource_types']) : array();
        $resources = array_intersect($resources, $types);
        $out['resource_types'] = array_values(array_diff($resources, array($out['subject_type'])));

        $tax = isset($in['taxonomy']) ? sanitize_key($in['taxonomy']) : '';
        $out['taxonomy'] = in_array($tax, $taxes, true) ? $tax : '';

        $out['subject_field']  = self::clean_field_name(isset($in['subject_field']) ? $in['subject_field'] : '', 'subject');
        $out['semester_field'] = self::clean_field_name(isset($in['semester_field']) ? $in['semester_field'] : '', 'semester');

        $count = isset($in['semester_count']) ? absint($in['semester_count']) : 6;
        $out['semester_count'] = $count >= 1 ? min(12, $count) : 6;

        $own = isset($in['own_semester_types']) ? array_map('sanitize_key', (array) $in['own_semester_types']) : array();
        $out['own_semester_types'] = array_values(array_intersect($own, $out['resource_types']));

        $out['protect_files'] = empty($in['protect_files']) ? 0 : 1;

        if ($out['subject_type'] === '' || $out['taxonomy'] === '') {
            add_settings_error(self::OPTION, 'vsvac_incomplete', 'Choose both a subject post type and a course group taxonomy. Until then the plugin does not restrict anything.', 'warning');
        }

        return $out;
    }
}
