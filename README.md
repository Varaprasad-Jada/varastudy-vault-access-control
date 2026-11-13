# VaraStudy Vault Access Control

[![WordPress Plugin](https://img.shields.io/badge/WordPress.org-Plugin%20Directory-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/plugins/varastudy-vault-access-control/)
![Version](https://img.shields.io/badge/Version-1.0.5-green)
![License](https://img.shields.io/badge/License-GPL--3.0-blue)

![VaraStudy Vault Access Control](https://ps.w.org/varastudy-vault-access-control/assets/banner-772x250.png?rev=3774765)

Course, semester and contributor access controls with administrator moderation for the [VaraStudy Vault](https://github.com/Varaprasad-Jada/varastudy-vault) theme.

**Listed on the [WordPress.org Plugin Directory](https://wordpress.org/plugins/varastudy-vault-access-control/).**

## Who is this for?

Sites that run the VaraStudy Vault theme and let several contributors add study materials or question papers. This plugin is built for that setup and is not a general-purpose access plugin.

## How it works

- Public visitors can view published resources across all courses and semesters.
- Contributors can submit and manage content only in their assigned course groups and semesters.
- Contributor submissions go to **Pending Review**, and only administrators can publish.
- Admins assign course groups and semesters under **Users → Edit User**.
- Admins can record the current academic year from the **VaraStudy Access** screen. Year rollover is manual.

## Screenshots

![Administrator settings dashboard](https://ps.w.org/varastudy-vault-access-control/assets/screenshot-1.png?rev=3774765)

![Contributor access scope with assigned course groups and semesters](https://ps.w.org/varastudy-vault-access-control/assets/screenshot-2.png?rev=3774765)

## Requirements

- WordPress 6.0 or higher (tested up to 7.1.4)
- PHP 7.4 or higher
- The [VaraStudy Vault](https://github.com/Varaprasad-Jada/varastudy-vault) theme setup:
  - Custom post types: `subjects`, `study-materials`, `question-papers`
  - Course taxonomy: `course_group`, assigned to Subject posts
  - ACF fields for semester and subject relationships (see the theme repository for the setup files)

## Installation

1. Back up your site and test on a staging site first.
2. In WordPress, open **Plugins → Add New Plugin** and search for **VaraStudy Vault Access Control**, or [download it here](https://wordpress.org/plugins/varastudy-vault-access-control/).
3. Click **Install Now**, then **Activate**.
4. Test with a separate contributor account and an administrator account.

## Recent changes

- **1.0.6:** Adds course-group assignment handling for contributor taxonomy selections across Subjects, Study Materials, and Question Papers. Administrators retain unrestricted access.
- **1.0.5:** Excludes Draft and Pending Review subjects from contributor subject selectors. Administrators keep unrestricted choices.
- **1.0.4:** Filters semester and subject selectors to a contributor's assigned semesters and course groups. Adds post-save validation, so invalid submissions are kept as drafts and flagged.

## Testing

Tested by the author on a live WordPress and ACF installation: assigned-semester filtering, subject-selector filtering, editing own draft and pending content, and administrator access all behaved as expected. These tests don't cover every possible unauthorized request path, so keep a backup and use staging before updating production.

## Contributing

Found a bug or have an idea? Open an [issue](https://github.com/Varaprasad-Jada/varastudy-vault-access-control/issues). Pull requests are welcome.

## License

Distributed under the **GNU General Public License v3.0 (GPL-3.0)**. See [`LICENSE`](LICENSE) for the full text.
