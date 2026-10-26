<?php
/**
 * Plugin Name: VaraStudy Vault Access Control
 * Description: Course, semester and contributor access controls with administrator moderation for VaraStudy Vault.
 * Version: 1.0.5
 * Author: Vara Prasad Jada
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: varastudy-access-control
 */

if (!defined('ABSPATH')) { exit; }

final class VaraStudy_Vault_Access_Control {
    const VERSION = '1.0.5';
    const ROLE = 'varastudy_contributor';
    const META_GROUPS = '_vsvac_course_groups';
    const META_SEMESTERS = '_vsvac_semesters';
    const META_ACTIVE = '_vsvac_contributor_active';
    const CPTS = array('subjects', 'study-materials', 'question-papers');

    public static function init() {
        add_action('init', array(__CLASS__, 'register_role'), 1);
        add_action('admin_init', array(__CLASS__, 'sync_contributor_capabilities'), 1);
        add_action('show_user_profile', array(__CLASS__, 'profile_fields'));
        add_action('edit_user_profile', array(__CLASS__, 'profile_fields'));
        add_action('personal_options_update', array(__CLASS__, 'save_profile_fields'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_profile_fields'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_post_vsvac_save_rollover', array(__CLASS__, 'save_rollover'));
        add_action('admin_notices', array(__CLASS__, 'admin_notices'));
        add_action('admin_init', array(__CLASS__, 'protect_admin_screens'));
        add_action('save_post', array(__CLASS__, 'validate_save'), 1, 3);
        // Restrict ACF editor choices for contributors; server-side validation runs after ACF saves fields.
        add_filter('acf/load_field/key=field_6ad34225ddea5', array(__CLASS__, 'filter_semester_choices'));
        add_filter('acf/load_field/key=field_6ad33bd28e7e0', array(__CLASS__, 'filter_semester_choices'));
        add_filter('acf/fields/post_object/query/key=field_6ad3405a22db5', array(__CLASS__, 'filter_subject_choices'), 10, 3);
        add_filter('acf/fields/post_object/query/key=field_6ad33d167a5ce', array(__CLASS__, 'filter_subject_choices'), 10, 3);
        add_action('save_post', array(__CLASS__, 'validate_saved_scope'), 30, 3);
        add_filter('map_meta_cap', array(__CLASS__, 'map_post_caps'), 10, 4);
        add_action('add_meta_boxes', array(__CLASS__, 'add_scope_metabox'));
        add_action('save_post', array(__CLASS__, 'save_scope_metabox'), 20, 3);
        add_filter('wp_insert_post_data', array(__CLASS__, 'force_moderation_status'), 20, 2);
        add_filter('posts_results', array(__CLASS__, 'hide_unapproved_direct_queries'), 10, 2);
        add_action('admin_bar_menu', array(__CLASS__, 'hide_admin_bar_items'), 999);
    }

    public static function register_role() {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, 'VaraStudy Contributor', array('read' => true, 'upload_files' => true));
        }
        self::sync_contributor_capabilities();
    }

    /**
     * Add the edit capabilities expected by the site's actual custom post type registrations.
     * Never grant publish or edit-others capabilities. Per-post scope checks remain enforced
     * by map_post_caps() and save validation.
     */
    public static function sync_contributor_capabilities() {
        $role = get_role(self::ROLE);
        if (!$role) return;
        $role->add_cap('read');
        $role->add_cap('upload_files');
        foreach (self::CPTS as $type) {
            $obj = get_post_type_object($type);
            if (!$obj || empty($obj->cap)) continue;
            // Required for the CPT menu/list and creating/editing own submissions.
            foreach (array('edit_posts', 'edit_post', 'create_posts', 'read_post') as $key) {
                if (!empty($obj->cap->$key) && strpos($obj->cap->$key, 'edit_others') !== 0) {
                    $role->add_cap($obj->cap->$key);
                }
            }
            // Explicitly remove powerful capabilities if an earlier role setup added them.
            foreach (array('publish_posts', 'edit_others_posts', 'delete_others_posts', 'edit_private_posts', 'delete_private_posts', 'publish_pages') as $key) {
                if (!empty($obj->cap->$key)) $role->remove_cap($obj->cap->$key);
            }
        }
        // Remove the common publish caps used by default WordPress post-type mappings.
        foreach (array('publish_posts', 'publish_pages', 'edit_others_posts', 'delete_others_posts') as $cap) {
            $role->remove_cap($cap);
        }
    }

    private static function is_admin_user($user_id = 0) {
        return user_can($user_id ?: get_current_user_id(), 'manage_options');
    }

    private static function contributor($user_id = 0) {
        $user = get_userdata($user_id ?: get_current_user_id());
        return $user && in_array(self::ROLE, (array) $user->roles, true);
    }

    private static function groups() {
        $terms = get_terms(array('taxonomy' => 'course_group', 'hide_empty' => false));
        return is_wp_error($terms) ? array() : $terms;
    }

    public static function profile_fields($user) {
        if (!self::is_admin_user()) return;
        $groups = array_map('intval', (array) get_user_meta($user->ID, self::META_GROUPS, true));
        $semesters = array_map('intval', (array) get_user_meta($user->ID, self::META_SEMESTERS, true));
        $active = get_user_meta($user->ID, self::META_ACTIVE, true);
        if ($active === '') $active = '1';
        ?>
        <h2>VaraStudy Vault Contributor Access</h2>
        <p>Assign a course group and the semesters this account may manage. Contributors can submit content, but only an administrator can publish it.</p>
        <table class="form-table" role="presentation">
          <tr><th><label for="vsvac_groups">Allowed course groups</label></th><td>
            <select id="vsvac_groups" name="vsvac_groups[]" multiple size="6" style="min-width:300px;">
            <?php foreach (self::groups() as $term): ?><option value="<?php echo esc_attr($term->term_id); ?>" <?php selected(in_array((int)$term->term_id, $groups, true)); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; ?>
            </select><p class="description">Use Ctrl (Windows) or Command (Mac) to select multiple groups.</p>
          </td></tr>
          <tr><th>Allowed semesters</th><td>
            <?php for ($s=1; $s<=6; $s++): ?><label style="display:inline-block;margin:0 14px 8px 0;"><input type="checkbox" name="vsvac_semesters[]" value="<?php echo $s; ?>" <?php checked(in_array($s, $semesters, true)); ?>> Semester <?php echo $s; ?></label><?php endfor; ?>
            <p class="description">Semesters 1–2 = first year; 3–4 = second year; 5–6 = third year.</p>
          </td></tr>
          <tr><th>Contributor access</th><td><label><input type="checkbox" name="vsvac_active" value="1" <?php checked($active === '1'); ?>> Account is active for contributor work</label><p class="description">Uncheck to suspend this contributor without deleting their account or content.</p></td></tr>
        </table>
        <?php wp_nonce_field('vsvac_profile_save', 'vsvac_profile_nonce');
    }

    public static function save_profile_fields($user_id) {
        if (!self::is_admin_user() || !current_user_can('edit_user', $user_id)) return;
        if (!isset($_POST['vsvac_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vsvac_profile_nonce'])), 'vsvac_profile_save')) return;
        $groups = isset($_POST['vsvac_groups']) ? array_values(array_filter(array_map('absint', (array) wp_unslash($_POST['vsvac_groups'])))) : array();
        $valid_groups = array_map('intval', wp_list_pluck(self::groups(), 'term_id'));
        $groups = array_values(array_intersect($groups, $valid_groups));
        $semesters = isset($_POST['vsvac_semesters']) ? array_values(array_intersect(array(1,2,3,4,5,6), array_map('absint', (array) wp_unslash($_POST['vsvac_semesters'])))) : array();
        update_user_meta($user_id, self::META_GROUPS, $groups);
        update_user_meta($user_id, self::META_SEMESTERS, $semesters);
        update_user_meta($user_id, self::META_ACTIVE, isset($_POST['vsvac_active']) ? '1' : '0');
    }

    public static function admin_menu() {
        add_menu_page('VaraStudy Access', 'VaraStudy Access', 'manage_options', 'vsvac-access', array(__CLASS__, 'settings_page'), 'dashicons-lock', 59);
    }

    public static function settings_page() {
        if (!self::is_admin_user()) return;
        $users = get_users(array('role' => self::ROLE, 'orderby' => 'display_name', 'order' => 'ASC'));
        $year = get_option('vsvac_academic_year', '2026-2027');
        ?>
        <div class="wrap"><h1>VaraStudy Vault Access Control</h1>
        <p>Public visitors can browse all published resources. Contributors are restricted to the course groups and semesters assigned on their user profile. Administrators approve submissions.</p>
        <h2>Contributor assignments</h2><p>Create a WordPress user with the role <strong>VaraStudy Contributor</strong>, then open Users → Edit and assign groups and semesters.</p>
        <?php if (!$users): ?><p>No contributor accounts found yet.</p><?php else: ?><table class="widefat striped"><thead><tr><th>Contributor</th><th>Groups</th><th>Semesters</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($users as $u): $gids=(array)get_user_meta($u->ID,self::META_GROUPS,true); $sn=(array)get_user_meta($u->ID,self::META_SEMESTERS,true); $gn=array(); foreach($gids as $gid){$t=get_term((int)$gid,'course_group'); if($t && !is_wp_error($t))$gn[]=$t->name;} $active=get_user_meta($u->ID,self::META_ACTIVE,true); ?>
        <tr><td><?php echo esc_html($u->display_name); ?> (<?php echo esc_html($u->user_email); ?>)</td><td><?php echo esc_html($gn ? implode(', ',$gn) : 'Not assigned'); ?></td><td><?php echo esc_html($sn ? implode(', ',array_map(function($n){return 'Sem '.$n;},$sn)) : 'Not assigned'); ?></td><td><?php echo $active === '0' ? 'Suspended' : 'Active'; ?></td><td><a href="<?php echo esc_url(get_edit_user_link($u->ID)); ?>">Edit assignment</a></td></tr>
        <?php endforeach; ?></tbody></table><?php endif; ?>
        <hr><h2>Academic year rollover</h2><p>This records the current academic year and offers a manual review point. It does not automatically change assignments or move resources; confirm each contributor's next assignment below via their profile.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vsvac_save_rollover"><?php wp_nonce_field('vsvac_rollover'); ?><label for="vsvac_year">Current academic year </label> <input id="vsvac_year" name="vsvac_year" value="<?php echo esc_attr($year); ?>" placeholder="2027-2028" required> <button class="button button-primary">Save academic year</button></form>
        <hr><h2>Moderation</h2><p>Review submissions in <a href="<?php echo esc_url(admin_url('edit.php?post_status=pending&post_type=study-materials')); ?>">Study Materials → Pending</a>, <a href="<?php echo esc_url(admin_url('edit.php?post_status=pending&post_type=subjects')); ?>">Subjects → Pending</a>, and <a href="<?php echo esc_url(admin_url('edit.php?post_status=pending&post_type=question-papers')); ?>">Question Papers → Pending</a>. Only administrators can publish.</p>
        <p><strong>Note:</strong> for resource posts, the contributor must link the resource to a subject that belongs to their assigned course group and semester. Subject records must have the course_group taxonomy and semester field set.</p>
        </div><?php
    }

    public static function save_rollover() {
        if (!self::is_admin_user() || !check_admin_referer('vsvac_rollover')) wp_die('Permission check failed.');
        $year = isset($_POST['vsvac_year']) ? sanitize_text_field(wp_unslash($_POST['vsvac_year'])) : '';
        if (!preg_match('/^\d{4}-\d{4}$/', $year)) { wp_safe_redirect(add_query_arg('vsvac_notice','badyear',admin_url('admin.php?page=vsvac-access'))); exit; }
        update_option('vsvac_academic_year', $year);
        wp_safe_redirect(add_query_arg('vsvac_notice','year',admin_url('admin.php?page=vsvac-access'))); exit;
    }

    public static function admin_notices() {
        if (!isset($_GET['vsvac_notice'])) return;
        $notice = sanitize_key(wp_unslash($_GET['vsvac_notice']));
        if ($notice === 'year') echo '<div class="notice notice-success is-dismissible"><p>Academic year saved. Review contributor assignments manually; existing resources were not changed.</p></div>';
        if ($notice === 'badyear') echo '<div class="notice notice-error"><p>Enter the academic year in YYYY-YYYY format, for example 2027-2028.</p></div>';
    }

    public static function protect_admin_screens() {
        if (!is_admin() || !self::contributor() || self::is_admin_user()) return;
        if (get_user_meta(get_current_user_id(), self::META_ACTIVE, true) === '0') wp_die('Your contributor access is currently suspended. Contact the site administrator.');
        global $pagenow;
        if ($pagenow === 'edit.php' && isset($_GET['post_type']) && in_array(sanitize_key(wp_unslash($_GET['post_type'])), self::CPTS, true)) return;
        if ($pagenow === 'post-new.php' && isset($_GET['post_type']) && in_array(sanitize_key(wp_unslash($_GET['post_type'])), self::CPTS, true)) return;
        if ($pagenow === 'post.php' && isset($_GET['post'])) {
            $post = get_post(absint($_GET['post']));
            if ($post && in_array($post->post_type, self::CPTS, true)) return;
        }
        // Avoid access to settings, user administration, plugins, themes, and other admin areas.
        $allowed = array('index.php', 'profile.php', 'edit.php', 'post.php', 'post-new.php', 'upload.php', 'async-upload.php', 'admin-ajax.php', 'admin-post.php');
        if (!in_array($pagenow, $allowed, true)) wp_die('You do not have permission to access this administration page.');
        if ($pagenow === 'edit.php' && (!isset($_GET['post_type']) || !in_array(sanitize_key(wp_unslash($_GET['post_type'])), self::CPTS, true))) wp_die('You do not have permission to access this content list.');
    }

    /** Return the current contributor's assigned group and semester IDs. */
    private static function current_assignment() {
        $user_id = get_current_user_id();
        return array(
            'groups' => array_values(array_filter(array_map('intval', (array) get_user_meta($user_id, self::META_GROUPS, true)))),
            'semesters' => array_values(array_filter(array_map('intval', (array) get_user_meta($user_id, self::META_SEMESTERS, true)))),
        );
    }

    /** Limit ACF semester dropdowns to assigned semesters for active contributors. */
    public static function filter_semester_choices($field) {
        if (self::is_admin_user() || !self::contributor()) return $field;
        $assignment = self::current_assignment();
        $choices = array();
        if (get_user_meta(get_current_user_id(), self::META_ACTIVE, true) !== '0') {
            foreach ((array) $field['choices'] as $value => $label) {
                if (in_array((int) $value, $assignment['semesters'], true)) $choices[$value] = $label;
            }
        }
        $field['choices'] = $choices;
        return $field;
    }

    /** Limit ACF Subject Post Object fields to subjects in assigned course groups and semesters. */
    public static function filter_subject_choices($args, $field, $post_id) {
        if (self::is_admin_user() || !self::contributor()) return $args;
        $assignment = self::current_assignment();
        if (get_user_meta(get_current_user_id(), self::META_ACTIVE, true) === '0' || !$assignment['groups'] || !$assignment['semesters']) {
            $args['post__in'] = array(0);
            return $args;
        }
        $args['post_type'] = array('subjects');
        // Contributors may link only published subjects; administrators bypass this filter above.
        $args['post_status'] = array('publish');
        $args['tax_query'] = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : array();
        $args['tax_query'][] = array(
            'taxonomy' => 'course_group',
            'field' => 'term_id',
            'terms' => $assignment['groups'],
            'operator' => 'IN',
        );
        $args['meta_query'] = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : array();
        $semester_queries = array('relation' => 'OR');
        foreach ($assignment['semesters'] as $semester) {
            $semester_queries[] = array(
                'key' => 'semester',
                'value' => (string) $semester,
                'compare' => '=',
            );
        }
        $args['meta_query'][] = $semester_queries;
        return $args;
    }

    /** Validate saved ACF values after ACF's save_post handler has persisted them. */
    public static function validate_saved_scope($post_id, $post, $update) {
        if (!$post || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) return;
        if (!in_array($post->post_type, self::CPTS, true) || self::is_admin_user() || !self::contributor()) return;
        if (get_user_meta(get_current_user_id(), self::META_ACTIVE, true) === '0') {
            self::reject_scope($post_id, 'Your contributor access is suspended. This submission was kept as a draft.');
            return;
        }

        $assignment = self::current_assignment();
        if (!$assignment['groups'] || !$assignment['semesters']) {
            self::reject_scope($post_id, 'No course group or semester assignment is configured for your account.');
            return;
        }

        if ($post->post_type === 'subjects') {
            $scope = self::post_scope($post_id, 'subjects');
            $valid = $scope['valid']
                && (bool) array_intersect($assignment['groups'], $scope['groups'])
                && (bool) array_intersect($assignment['semesters'], $scope['semesters']);
        } else {
            $subject_id = self::linked_subject_id($post_id);
            $subject_scope = $subject_id ? self::post_scope($subject_id, 'subjects') : array('groups'=>array(), 'semesters'=>array(), 'valid'=>false);
            $valid = $subject_id && $subject_scope['valid']
                && (bool) array_intersect($assignment['groups'], $subject_scope['groups'])
                && (bool) array_intersect($assignment['semesters'], $subject_scope['semesters']);

            // Question Papers have their own semester field; it must match the linked subject.
            if ($valid && $post->post_type === 'question-papers') {
                $paper_semester = get_field('semester', $post_id, false);
                if ($paper_semester === null || $paper_semester === '') $paper_semester = get_post_meta($post_id, 'semester', true);
                $paper_semester = is_numeric($paper_semester) ? (int) $paper_semester : 0;
                $valid = $paper_semester > 0
                    && in_array($paper_semester, $assignment['semesters'], true)
                    && in_array($paper_semester, $subject_scope['semesters'], true);
            }
        }

        if (!$valid) {
            self::reject_scope($post_id, 'This submission uses a course group, subject, or semester outside your assigned scope. It has been kept as a draft for correction.');
            return;
        }
        update_post_meta($post_id, '_vsvac_scope_was_valid', '1');
        delete_post_meta($post_id, '_vsvac_scope_error');
    }

    private static function reject_scope($post_id, $message) {
        $post = get_post($post_id);
        if ($post && !in_array($post->post_status, array('trash', 'auto-draft'), true) && $post->post_status !== 'draft') {
            remove_action('save_post', array(__CLASS__, 'validate_saved_scope'), 30);
            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
            add_action('save_post', array(__CLASS__, 'validate_saved_scope'), 30, 3);
        }
        update_post_meta($post_id, '_vsvac_scope_error', $message);
    }

    private static function linked_subject_id($post_id) {
        $subject = function_exists('get_field') ? get_field('subject', $post_id, false) : get_post_meta($post_id, 'subject', true);
        if (is_object($subject) && isset($subject->ID)) return (int)$subject->ID;
        if (is_array($subject)) { if (isset($subject['ID'])) return (int)$subject['ID']; if (isset($subject[0])) return (int)(is_object($subject[0]) ? $subject[0]->ID : $subject[0]); }
        return is_numeric($subject) ? (int)$subject : 0;
    }

    private static function post_scope($post_id, $post_type = '') {
        $post = get_post($post_id);
        if (!$post) return array('groups'=>array(), 'semesters'=>array(), 'valid'=>false);
        $post_type = $post_type ?: $post->post_type;
        $subject_id = $post_type === 'subjects' ? (int)$post_id : self::linked_subject_id($post_id);
        $groups = array(); $semesters = array();
        if ($subject_id) {
            $terms = wp_get_object_terms($subject_id, 'course_group', array('fields'=>'ids'));
            if (!is_wp_error($terms)) $groups = array_map('intval', $terms);
            $sem = function_exists('get_field') ? get_field('semester', $subject_id, false) : get_post_meta($subject_id, 'semester', true);
            if (is_numeric($sem)) $semesters[] = (int)$sem;
        }
        // Also accept direct course_group terms on the resource itself.
        if ($post_type !== 'subjects') {
            $direct = wp_get_object_terms($post_id, 'course_group', array('fields'=>'ids'));
            if (!is_wp_error($direct) && $direct) $groups = array_values(array_unique(array_merge($groups, array_map('intval',$direct))));
            $direct_sem = get_post_meta($post_id, 'semester', true);
            if (is_numeric($direct_sem)) $semesters[] = (int)$direct_sem;
        }
        return array('groups'=>array_values(array_unique($groups)), 'semesters'=>array_values(array_unique($semesters)), 'valid'=>!empty($groups) && !empty($semesters));
    }

    private static function user_can_manage_scope($user_id, $post_id, $post_type = '') {
        if (self::is_admin_user($user_id)) return true;
        if (!self::contributor($user_id)) return false;
        if (get_user_meta($user_id, self::META_ACTIVE, true) === '0') return false;
        $allowed_groups = array_map('intval',(array)get_user_meta($user_id,self::META_GROUPS,true));
        $allowed_semesters = array_map('intval',(array)get_user_meta($user_id,self::META_SEMESTERS,true));
        if (!$allowed_groups || !$allowed_semesters) return false;
        $scope = self::post_scope($post_id, $post_type);
        if (!$scope['valid']) return false;
        return (bool) array_intersect($allowed_groups, $scope['groups']) && (bool) array_intersect($allowed_semesters, $scope['semesters']);
    }

    public static function map_post_caps($caps, $cap, $user_id, $args) {
        if (!in_array($cap, array('edit_post','delete_post','publish_post','edit_published_post','delete_published_post','edit_others_posts','delete_others_posts'), true)) return $caps;
        if (empty($args[0])) return $caps;
        $post = get_post((int)$args[0]);
        if (!$post || !in_array($post->post_type, self::CPTS, true) || self::is_admin_user($user_id)) return $caps;
        if (!self::contributor($user_id)) return array('do_not_allow');

        /*
         * WordPress creates an auto-draft before the contributor has selected
         * its course/semester. Permit editing that temporary auto-draft only
         * when the contributor has an active assignment. Scope is enforced
         * when the item is saved and when an existing item is edited.
         */
        if ($cap === 'edit_post' && $post->post_status === 'auto-draft') {
            if (get_user_meta($user_id, self::META_ACTIVE, true) === '0') return array('do_not_allow');
            $groups = (array) get_user_meta($user_id, self::META_GROUPS, true);
            $semesters = (array) get_user_meta($user_id, self::META_SEMESTERS, true);
            $obj = get_post_type_object($post->post_type);
            $create_cap = ($obj && !empty($obj->cap->edit_posts)) ? $obj->cap->edit_posts : 'edit_posts';
            return (!empty($groups) && !empty($semesters) && user_can($user_id, $create_cap))
                ? array($create_cap)
                : array('do_not_allow');
        }

        if (in_array($cap, array('publish_post'), true)) return array('do_not_allow');
        if (in_array($cap, array('edit_others_posts','delete_others_posts'), true)) return array('do_not_allow');

        // Contributors may continue editing their own drafts/pending submissions.
        // The save validators still enforce assigned course/semester scope and
        // published items remain protected below.
        if ((int) $post->post_author === (int) $user_id
            && in_array($post->post_status, array('draft', 'pending', 'auto-draft'), true)
            && in_array($cap, array('edit_post'), true)) {
            $obj = get_post_type_object($post->post_type);
            $edit_cap = ($obj && !empty($obj->cap->edit_posts)) ? $obj->cap->edit_posts : 'edit_posts';
            return array($edit_cap);
        }

        if (!self::user_can_manage_scope($user_id, $post->ID, $post->post_type)) return array('do_not_allow');
        if (in_array($cap, array('delete_post','delete_published_post'), true) && $post->post_status === 'publish') return array('do_not_allow');
        return $caps;
    }

    public static function add_scope_metabox() {
        foreach (self::CPTS as $type) add_meta_box('vsvac_scope', 'VaraStudy Access Scope', array(__CLASS__,'scope_metabox'), $type, 'side', 'high');
    }

    public static function scope_metabox($post) {
        if (self::is_admin_user()) { echo '<p>Administrator access: all groups and semesters.</p>'; return; }
        if (!self::contributor()) { echo '<p>Only assigned contributors can manage this content.</p>'; return; }
        $allowed_groups = array_map('intval',(array)get_user_meta(get_current_user_id(),self::META_GROUPS,true));
        $allowed_semesters = array_map('intval',(array)get_user_meta(get_current_user_id(),self::META_SEMESTERS,true));
        echo '<p><strong>Assigned groups:</strong> ';
        $names=array(); foreach(self::groups() as $t) if(in_array((int)$t->term_id,$allowed_groups,true)) $names[]=$t->name;
        echo esc_html($names ? implode(', ',$names) : 'None assigned').'</p><p><strong>Allowed semesters:</strong> '.esc_html($allowed_semesters ? implode(', ',$allowed_semesters) : 'None assigned').'</p>';
        if ($post->post_type !== 'subjects') echo '<p>Link this resource to a subject in your assigned group and semester. Your submission will be checked before saving.</p>';
    }

    public static function save_scope_metabox($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !in_array($post->post_type, self::CPTS, true)) return;
        if (self::is_admin_user()) return;
        if (!self::contributor()) return;
        if (!self::user_can_manage_scope(get_current_user_id(), $post_id, $post->post_type)) {
            // New posts with incomplete scope remain pending; existing out-of-scope posts cannot be modified.
            if ($update && get_post_meta($post_id, '_vsvac_scope_was_valid', true) === '1') {
                remove_action('save_post', array(__CLASS__, 'save_scope_metabox'), 20);
                wp_update_post(array('ID'=>$post_id, 'post_status'=>'draft'));
                add_action('save_post', array(__CLASS__, 'save_scope_metabox'), 20, 3);
            }
            update_post_meta($post_id, '_vsvac_scope_error', 'Resource is missing an assigned course group/semester or falls outside your assigned scope.');
            return;
        }
        update_post_meta($post_id, '_vsvac_scope_was_valid', '1');
        delete_post_meta($post_id, '_vsvac_scope_error');
    }

    public static function validate_save($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !in_array($post->post_type, self::CPTS, true)) return;
        if (self::is_admin_user()) return;
        if (!self::contributor()) return;
        if (get_user_meta(get_current_user_id(), self::META_ACTIVE, true) === '0') {
            remove_action('save_post', array(__CLASS__, 'validate_save'), 1);
            wp_update_post(array('ID'=>$post_id,'post_status'=>'draft'));
            add_action('save_post', array(__CLASS__, 'validate_save'), 1, 3);
        // Restrict ACF editor choices for contributors; server-side validation runs after ACF saves fields.
            return;
        }
        if ($update && !self::user_can_manage_scope(get_current_user_id(), $post_id, $post->post_type)) {
            // Do not let a contributor change another scope; preserve existing published content.
            if (get_post_meta($post_id, '_vsvac_scope_was_valid', true) === '1') {
                remove_action('save_post', array(__CLASS__, 'validate_save'), 1);
                wp_update_post(array('ID'=>$post_id, 'post_status'=>'draft'));
                add_action('save_post', array(__CLASS__, 'validate_save'), 1, 3);
        // Restrict ACF editor choices for contributors; server-side validation runs after ACF saves fields.
            }
            update_post_meta($post_id, '_vsvac_scope_error', 'This item is outside your assigned group/semester or its scope is incomplete.');
        }
    }

    public static function force_moderation_status($data, $postarr) {
        if (!in_array($data['post_type'], self::CPTS, true) || self::is_admin_user()) return $data;
        if (self::contributor()) {
            if (in_array($data['post_status'], array('publish','future','private'), true)) $data['post_status'] = 'pending';
            // Revisions/autosaves are handled by WordPress; do not interfere with trashing here.
        }
        return $data;
    }

    public static function hide_unapproved_direct_queries($posts, $query) {
        if (is_admin() || $query->is_singular()) return $posts;
        foreach ($posts as $key => $post) {
            if (in_array($post->post_type, self::CPTS, true) && $post->post_status !== 'publish') unset($posts[$key]);
        }
        return array_values($posts);
    }

    public static function hide_admin_bar_items($bar) {
        if (!self::contributor() || self::is_admin_user()) return;
        $bar->remove_node('customize');
        $bar->remove_node('themes');
        $bar->remove_node('updates');
    }
}

VaraStudy_Vault_Access_Control::init();
