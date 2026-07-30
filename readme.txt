=== LJNDI HRMS Workflow ===
Contributors: lerionjakenwauda
Tags: oauth, sso, hrms, staff, security
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free OAuth 2.1 PKCE sign-in and workforce access integration for LJNDI HRMS.

== Description ==

LJNDI HRMS Workflow adds **Continue with LJNDI HRMS** to the WordPress login screen. Approved team members authenticate on the LJNDI identity service and return to WordPress without sharing their HRMS password with the WordPress website.

The plugin can:

* Use OAuth 2.1 Authorization Code with mandatory PKCE S256.
* Link an HRMS employee to an existing WordPress user by verified email.
* Create a local WordPress user after approved sign-in when enabled.
* Use the approved HRMS profile photo as the linked user's WordPress avatar.
* Map exact HRMS roles or departments to WordPress roles.
* Revalidate linked staff sessions and remove access when HRMS credentials are revoked or the employee becomes inactive.
* Encrypt the OAuth client secret, access token, and refresh token before storing them in WordPress.
* Show the exact callback URL required when registering the WordPress website in HRMS Admin.

Administrator access is never granted automatically. It must be mapped explicitly in the plugin settings.

Documentation and support: https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow

= External service =

This plugin connects to the LJNDI HRMS identity service configured by the website administrator. The default service is:

* https://auth.lerionjakenwauda.com

During sign-in, the browser is redirected to the identity service. The plugin sends the OAuth client ID, exact WordPress callback URL, requested scopes, a random state value, a random nonce, and a PKCE S256 code challenge.

After the employee approves access, the plugin exchanges the short-lived authorization code for an access token and refresh token. It then requests approved employee claims such as employee ID, name, work email, profile photo URL, active/inactive status, employment dates, roles, and departments. The employee's HRMS password is entered only on the identity service and is never sent to or stored by this WordPress plugin.

The profile photo remains hosted by LJNDI HRMS. WordPress stores only the validated HTTPS image URL and uses it through the normal WordPress avatar system.

The service is operated by Lerion Jake Nwauda Digital Innovations Ltd.

* Service information: https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow
* Privacy policy: https://lerionjakenwauda.com/privacy-policy/
* Terms of service: https://lerionjakenwauda.com/terms-of-service/

The website administrator must intentionally configure OAuth credentials before any data is sent to the external service.

== Installation ==

1. Install and activate **LJNDI HRMS Workflow**.
2. Open **Settings > LJNDI HRMS**.
3. Copy the callback URL shown by the plugin.
4. In LJNDI HRMS Admin, open **Settings > Identity & API**.
5. Create a separate confidential OAuth application for this WordPress website.
6. Register the exact callback URL and allow the requested scopes.
7. Copy the generated Client ID and one-time Client Secret into the WordPress plugin settings.
8. Choose whether WordPress may create approved staff accounts.
9. Configure explicit HRMS role or department mappings.
10. Save the settings and test **Continue with LJNDI HRMS** in a private browser window.

Each WordPress website must have its own OAuth application and credentials. Do not reuse one client secret across multiple websites.

== Frequently Asked Questions ==

= Does this plugin store HRMS passwords? =

No. Employees enter their HRMS credentials only on the configured LJNDI identity service.

= Does it copy the HRMS profile photo into WordPress? =

The plugin stores the approved HTTPS profile-photo URL and uses it as the linked user's WordPress avatar across the admin bar, user lists, comments, and themes that use the normal WordPress avatar API. The image itself remains hosted by LJNDI HRMS.

= Does an HRMS job title automatically make someone a WordPress administrator? =

No. The default local role is Subscriber. Administrator access must be mapped explicitly by a WordPress administrator.

= What callback URL should I register? =

The plugin displays the exact callback URL under **Settings > LJNDI HRMS**. It normally looks like:

`https://example.com/wp-admin/admin-post.php?action=ljndi_hrms_callback`

Register the exact displayed URL, including HTTPS and the full path.

= What happens when an employee becomes inactive? =

The HRMS identity service stops accepting the employee's token. During session revalidation, the plugin clears the stored OAuth session and signs the linked user out of WordPress.

= Can I disable automatic WordPress account creation? =

Yes. When disabled, only existing WordPress users whose email matches the approved HRMS work email can sign in.

= Does it support WordPress multisite? =

The first public version can run on a site within a multisite installation. It never changes a network super administrator's role. Network-wide provisioning controls are planned for a later release.

== Privacy ==

The plugin stores the linked HRMS employee ID, encrypted OAuth access and refresh tokens, token expiry, last validation time, the latest approved identity claims, and the validated HRMS profile-photo URL in WordPress user metadata. OAuth client settings are stored in the WordPress options table, with secrets encrypted using keys derived from the WordPress authentication salts.

Uninstalling the plugin removes its settings, linked HRMS metadata, encrypted tokens, synced avatar URL, cached OAuth discovery data, and pending sign-in transactions. It does not delete WordPress user accounts.

== Changelog ==

= 0.1.1 =

* Added HRMS profile-photo synchronisation through the standard WordPress avatar system.
* Added lazy avatar refresh for users linked before this release.
* Updated external-service and privacy disclosures for profile images.

= 0.1.0 =

* Initial public preview.
* Added OAuth 2.1 Authorization Code with PKCE S256.
* Added encrypted credential and token storage.
* Added WordPress login button and callback handling.
* Added approved staff account linking and optional provisioning.
* Added exact role and department mappings.
* Added periodic session revalidation and token refresh.
* Added WordPress.org external-service and privacy disclosures.

== Upgrade Notice ==

= 0.1.1 =

Adds HRMS profile photos as WordPress avatars and updates privacy disclosures.
