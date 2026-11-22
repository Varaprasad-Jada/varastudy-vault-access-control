# VaraStudy Vault Access Control

[![WordPress Plugin](https://img.shields.io/badge/WordPress.org-Plugin%20Directory-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/plugins/varastudy-vault-access-control/)
![Version](https://img.shields.io/badge/Version-1.1.0-green)
![License](https://img.shields.io/badge/License-GPL--3.0--or--later-blue)

![VaraStudy Vault Access Control](https://ps.w.org/varastudy-vault-access-control/assets/banner-772x250.png)

Configurable contributor access controls and administrator moderation for WordPress sites using custom post types and taxonomies.

**Listed on the [WordPress.org Plugin Directory](https://wordpress.org/plugins/varastudy-vault-access-control/).**

## Who is this for?

Education websites and other WordPress sites that allow multiple contributors to submit content while administrators retain control over permissions and publication.

The plugin works with existing WordPress themes, registered custom post types, taxonomies, and post metadata. It is not dependent on the VaraStudy Vault theme.

## How it works

- Administrators configure the subject post type, resource post types, course-group taxonomy, custom-field names, and semester count.
- Contributors are assigned one course group and the semesters they are permitted to manage.
- Contributors can create and update managed content within their assigned scope.
- Contributor submissions are held for administrator review.
- Administrators manage submissions through a centralized **Review Queue**.
- Administrators can approve, edit, view, or request changes to submissions.
- Request Changes returns a submission to Draft with an explanatory note visible to the contributor.
- Administrators can activate or suspend contributor access.

## Screenshots

![Administrator settings dashboard](https://ps.w.org/varastudy-vault-access-control/assets/screenshot-1.png)

![Contributor access scope](https://ps.w.org/varastudy-vault-access-control/assets/screenshot-2.png)

![Review Queue](https://ps.w.org/varastudy-vault-access-control/assets/screenshot-3.png)

## Requirements

- WordPress 6.0 or higher (tested up to 7.1)
- PHP 7.4 or higher
- Existing registered post types and taxonomies for your site's content structure
- Configured post metadata for subject and semester relationships

The plugin does not create your site's subject or resource post types, taxonomy terms, or custom fields.

## Installation

1. Back up your site and test on a staging site first.
2. In WordPress, open **Plugins → Add New Plugin** and search for **VaraStudy Vault Access Control**, or [download it here](https://wordpress.org/plugins/varastudy-vault-access-control/).
3. Click **Install Now**, then **Activate**.
4. Open **VaraStudy Access → Settings** and configure your content structure.
5. Assign the **VaraStudy Contributor** role to users and configure their course-group and semester permissions.
6. Test with a separate contributor account and an administrator account.

## Recent changes

- **1.1.0:** Introduces configurable content settings, a centralized Review Queue, Request Changes workflow, optional contributor upload protection for Apache and LiteSpeed hosts, improved server-side access enforcement, and support for existing WordPress themes and content structures.
- **1.0.6:** Adds course-group assignment handling for contributor taxonomy selections across Subjects, Study Materials, and Question Papers. Administrators retain unrestricted access.
- **1.0.5:** Excludes Draft and Pending Review subjects from contributor subject selectors. Administrators keep unrestricted choices.
- **1.0.4:** Filters semester and subject selectors to a contributor's assigned semesters and course groups. Adds post-save validation, so invalid submissions are kept as drafts and flagged.

## Testing

Tested by the author on a WordPress and ACF installation: assigned-semester filtering, subject-selector filtering, editing own draft and pending content, and administrator access.

These tests do not cover every possible unauthorized request path or all third-party custom post type configurations, so keep a backup and use staging before updating production.

## Contributing

Found a bug or have an idea? Open an [issue](https://github.com/Varaprasad-Jada/varastudy-vault-access-control/issues). Pull requests are welcome.

## License

Distributed under the **GNU General Public License v3.0 or later (GPL-3.0-or-later)**. See [`LICENSE`](LICENSE) for the full text.
