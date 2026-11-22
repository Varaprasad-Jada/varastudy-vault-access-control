=== VaraStudy Vault Access Control ===
Tags: access-control, contributors, editorial-workflow, content-moderation, education
Requires at least: 6.0
Tested up to: 7.1.5
Contributors: varaprasadjada
Stable tag: 1.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Manage contributor access by course group and semester, review submissions, and approve or request changes. Works with your existing WordPress theme.

== Description ==

VaraStudy Vault Access Control helps education websites manage contributor permissions and review submitted content without requiring a particular WordPress theme.

Choose the subject post type, resource post types, course-group taxonomy, custom-field names, and semester count in the plugin settings. Contributors can then be assigned a course group and the semesters they are allowed to manage. Their submissions are held for administrator review.

**Use your existing theme.** VaraStudy Vault Access Control manages permissions and editorial workflow in the WordPress dashboard. It does not provide or require a frontend theme.

= Features =

* **Configurable content structure:** Select the subject post type, resource post types, course-group taxonomy, subject field name, semester field name, and number of semesters (1–12).
* **Contributor assignments:** Assign each contributor one course-group term and the semesters they may manage. Administrators can activate or suspend contributor access.
* **Server-side scope enforcement:** Enforce course-group and semester restrictions when contributors create or update managed content, including supported REST API post-type routes.
* **Editor restrictions:** Preselect the contributor's assigned course group and restrict the course-group selector in the editor.
* **Central Review Queue:** Review contributor submissions with filters for content type, course group, semester, subject, and contributor.
* **Editorial actions:** Approve and publish submissions, edit or view them, or request changes.
* **Contributor feedback:** Request Changes returns a submission to Draft with an explanatory note. The note is shown to the contributor in the editor; an email notification can also be sent when the site's email delivery is working.
* **Suspended-account protection:** Reject supported REST submissions from suspended contributors and restrict their access to managed content.
* **Optional upload protection:** On Apache and LiteSpeed hosts, optionally store non-image files uploaded by contributors in a protected directory until a managed post using the file is published. This option is off by default. Images remain in the normal WordPress uploads directory.
* **Upgrade compatibility:** Existing installations with the previous contributor role retain the plugin's legacy VaraStudy configuration when the settings are first initialized. New installations start unconfigured until an administrator completes setup.

The plugin can work with registered custom post types and taxonomies from your site's existing setup. It does not register a college-specific theme or impose a frontend design.

== Installation ==

1. Upload the `varastudy-vault-access-control` folder to the `/wp-content/plugins/` directory, or install the ZIP through **Plugins > Add New > Upload Plugin**.
2. Activate **VaraStudy Vault Access Control** through the **Plugins** menu.
3. Open **VaraStudy Access > Settings**.
4. Choose the post type that represents a subject or course unit.
5. Select the resource post types contributors will submit.
6. Choose the taxonomy used to represent course groups.
7. Enter the custom-field names used to link a resource to its subject and to store a semester number.
8. Set the number of semesters (from 1 to 12). If selected resource types store their own semester value, choose them under the corresponding setting.
9. Save the settings.
10. Create or edit a WordPress user and assign the **VaraStudy Contributor** role. In that user's profile, an administrator can assign one course group, permitted semesters, and active or suspended status.

Your site must already have the relevant post types, taxonomy, and custom fields registered. The plugin configures its access-control workflow around those existing structures; it does not create them for you.

== Frequently Asked Questions ==

= Does this plugin require the VaraStudy Vault theme? =

No. The plugin is designed to work with an existing WordPress theme. It manages contributor access and editorial review in the WordPress dashboard; it does not control your site's theme or frontend design.

= Does the plugin create subject and resource post types or course-group terms? =

No. Your site must already register the post types and taxonomy and create the required course-group terms. Select the relevant registered content types and taxonomy in **VaraStudy Access > Settings**.

= Does it require Advanced Custom Fields (ACF)? =

The plugin reads the configured subject and semester values from post metadata. It can be used with metadata created by your site's own setup. When using ACF, enter the ACF field names in the settings; the subject field should be a Post Object field that stores the linked subject post.

= What happens to a contributor's submission? =

Contributors can submit content for the configured post types, but they cannot publish it. Administrators review submissions in **VaraStudy Access > Review Queue** and can approve, edit, view, or request changes.

= What happens when an administrator requests changes? =

The submission is returned to Draft and the administrator's note is saved. The contributor can see the note in the editor. The plugin also attempts to send an email notification; successful delivery depends on the site's WordPress email configuration.

= Can a contributor access another course group's content by changing a request or using the REST API? =

The plugin applies server-side scope checks in addition to editor restrictions, and registers REST handling for the configured post types. Site owners should still test the plugin with their own custom post-type capabilities, REST integrations, and other plugins before enabling contributor accounts on a production site.

= What does optional upload protection protect? =

When enabled, non-image files uploaded by contributors are placed in a protected uploads subdirectory. Access is checked when a protected file is requested. A protected file can be served publicly when a configured managed post that references it is published; otherwise, access is limited to an administrator or the file's uploader. Images remain in the normal uploads directory and are not covered by this feature.

= Which servers support upload protection? =

The feature is intended for Apache and LiteSpeed hosts that honor the directory's `.htaccess` access rules. It is disabled by default. Confirm the behaviour with your hosting provider and test direct file URLs before relying on it. Other server configurations may require equivalent server-level deny rules and are not claimed as supported by this feature.

= What happens on a new installation? =

A new installation starts unconfigured. The plugin displays a setup notice and does not apply its content restrictions until an administrator chooses the required subject post type and course-group taxonomy.

= Will an existing VaraStudy Vault installation lose its previous configuration when upgraded? =

When the plugin first initializes its settings, it detects an existing VaraStudy Contributor role and seeds the previous VaraStudy content configuration for that installation. Review the settings after upgrading.

== Changelog ==

= 1.1.0 =
* New: Settings page (VaraStudy Access > Settings). Choose your subject post type, resource post types, course group taxonomy, subject and semester field names, and number of semesters. The plugin now works on any site and with any theme.
* New: Review Queue. One admin screen for all contributor submissions, with filters (type, course group, semester, subject, contributor), status tabs with counts (Pending, Needs changes, Approved), and Approve, Request changes, Edit and View actions.
* New: Request changes sends the post back to draft with a note. The contributor sees the note in the editor and can also get an email.
* New: Optional protection for contributor uploads until the post is approved (Apache and LiteSpeed hosts). Off by default.
* New: Links and resources card on the plugin pages, a Settings link on the Plugins page, and a setup notice until the plugin is configured.
* Changed: Removed the hardcoded post types, taxonomy and ACF field keys. Fields are now found by name.
* Changed: New installs start unconfigured and do nothing until set up. Existing sites keep their current setup automatically.
* Improved: Each contributor is locked to exactly one course group, enforced on the server and in the editor.
* Improved: REST requests from suspended contributors are rejected.
* Improved: The assigned course group is preselected in the editor.

= 1.0.6 =
* Previous public release.

== Upgrade Notice ==

= 1.1.0 =
This release adds configurable site settings and a centralized contributor Review Queue. Existing installations should review their migrated settings after upgrading. New installations must complete setup before the plugin applies content restrictions.
