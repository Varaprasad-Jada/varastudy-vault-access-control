VaraStudy Vault Access Control
==============================

Version: 1.0.3

Purpose
-------
- Public visitors can view published resources across courses and semesters.
- Contributors can submit and manage content within their assigned course groups and semesters, subject to permissions.
- Contributor submissions are sent to Pending Review; administrators control publication.
- Administrators assign course groups, semesters, and contributor active status through Users -> Edit User.
- Administrators can record the current academic year through VaraStudy Access. Academic-year rollover is manual.

Existing-site requirements and assumptions
------------------------------------------
- Course taxonomy slug: course_group
- Custom post types: subjects, study-materials, question-papers
- Subject semester ACF field/meta key: semester
- Resource-to-subject relationship ACF field/meta key: subject
- Resources may also have course_group terms assigned directly.

Installation
------------
1. Back up your WordPress site and database.
2. Upload the installable plugin ZIP through Plugins -> Add New Plugin -> Upload Plugin.
3. Activate VaraStudy Vault Access Control.
4. Create contributor accounts using the VaraStudy Contributor role.
5. Edit each contributor profile and configure permitted course groups, semesters, and active status.
6. Test with a separate contributor account before using real accounts.

Moderation and permissions
--------------------------
Contributors cannot publish submissions. Attempts to publish are changed to Pending Review. Administrators can review pending entries through the relevant custom post type lists.

Development status
------------------
Version 1.0.3 includes changes to contributor editing permissions for their own draft and pending submissions. Approved/published content remains restricted according to the plugin's permissions.

Test on a staging site or backup before production use. Verify access restrictions, submission moderation, course and semester assignments, and compatibility with your site's custom post types and ACF fields.

The plugin assumes that course_group terms are associated with Subject posts and that the subject ACF field references a Subject. Academic-year rollover does not automatically move content.
