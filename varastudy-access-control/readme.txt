VaraStudy Vault Access Control

Purpose
- Public visitors can view published resources across all courses and semesters.
- Contributors can submit/manage only content in their assigned course groups and semesters.
- Contributor submissions are sent to Pending Review; only administrators can publish.
- Admins assign groups and semesters on Users -> Edit User.
- Admins can record the current academic year from VaraStudy Access; rollover is deliberately manual to avoid accidentally changing access.

Important existing-site assumptions
- Course taxonomy slug: course_group
- Custom post types: subjects, study-materials, question-papers
- Subject semester stored in ACF field/meta key: semester
- Resource-to-subject relationship stored in ACF field/meta key: subject
- Resources may also have course_group terms directly assigned.

Installation
1. Back up the WordPress site and database first.
2. Upload the ZIP via Plugins -> Add New -> Upload Plugin.
3. Activate VaraStudy Vault Access Control.
4. Go to Users -> Add New, create contributor accounts with role VaraStudy Contributor.
5. Edit each contributor profile and select allowed course groups, semesters, and active status.
6. Test with a separate contributor account before using real accounts.

Moderation
Contributors cannot publish. Their attempted Publish action is changed to Pending Review. Admins can review pending entries from each custom post type list.

Caution
This is version 1.0.1 and should be tested on a backup/staging site before production. The plugin assumes course_group taxonomy terms are attached to Subject posts and the subject ACF field points to a Subject. It intentionally does not move content during academic-year rollover.

Version 1.0.1 fixes contributor dashboard access and synchronizes the edit capabilities required by registered custom post types without granting publishing or edit-others permissions. Test with a separate account before production use.
