# LJNDI HRMS Workflow

Free, GPL-licensed WordPress OAuth and workforce access integration for **LJNDI HRMS**.

The plugin adds **Continue with LJNDI HRMS** to WordPress, uses OAuth 2.1 Authorization Code with PKCE S256, links approved employees to local WordPress users, maps HRMS roles and departments to WordPress roles, and revalidates sessions when employment access changes.

## Status

`0.1.0` is an initial public preview. Test it on a staging WordPress website before enabling it on a production client website.

## Requirements

- WordPress 6.4 or newer
- PHP 7.4 or newer with OpenSSL
- HTTPS on the WordPress website
- An OAuth application created in **LJNDI HRMS Admin → Settings → Identity & API**
- LJNDI identity service available at `https://auth.lerionjakenwauda.com`

## Installation from GitHub

1. Download the repository as a ZIP.
2. Rename the extracted folder to `ljndi-hrms-workflow` when necessary.
3. Upload it through **WordPress Admin → Plugins → Add Plugin → Upload Plugin**.
4. Activate **LJNDI HRMS Workflow**.
5. Open **Settings → LJNDI HRMS**.

After approval on WordPress.org, administrators will also be able to search for **LJNDI HRMS Workflow** directly inside WordPress and install it from the official plugin directory.

## Connect a WordPress website

The settings page displays the site's exact callback URL:

```text
https://example.com/wp-admin/admin-post.php?action=ljndi_hrms_callback
```

Create a separate confidential OAuth application in HRMS for the WordPress website. Register the exact callback URL and allow these default scopes:

```text
openid profile email employment:read roles:read
```

Paste the generated Client ID and one-time Client Secret into the WordPress plugin settings. The client secret is encrypted before it is stored.

Every WordPress installation must receive its own Client ID, Client Secret, redirect URL, sessions, and revocation controls. Never reuse one secret across client websites.

## Role mapping

The safest default WordPress role is `subscriber`. Higher access must be mapped explicitly:

```text
role:Founder & Visionary Leader=administrator
role:Content Manager=editor
department:Client and Customer Success Department=editor
```

Matching is case-insensitive. HRMS returns active roles in primary-role order. The first exact role or department mapping is used.

## OAuth flow

1. WordPress creates a high-entropy state, nonce, PKCE verifier, and S256 challenge.
2. The browser is redirected to `auth.lerionjakenwauda.com`.
3. The employee signs in and approves the requested scopes on LJNDI Auth.
4. Auth returns a short-lived authorization code to the exact WordPress callback URL.
5. WordPress exchanges the code with the PKCE verifier.
6. The plugin reads approved employee claims and creates or links the local account.
7. WordPress starts its normal authenticated session.
8. The plugin periodically revalidates the HRMS session and refreshes tokens when required.

The employee's HRMS password is never sent to or stored by the WordPress plugin.

## Stored data

The plugin stores:

- OAuth connection settings;
- encrypted client secret;
- linked HRMS employee ID;
- encrypted access and refresh tokens;
- token expiry and last validation time;
- the latest approved employee identity claims.

Uninstalling the plugin removes plugin settings, tokens, linked metadata, cached discovery information, and pending login transactions. It does not delete WordPress user accounts.

## External service disclosure

The plugin communicates with the LJNDI HRMS identity service configured by the site administrator. The default service is `https://auth.lerionjakenwauda.com`.

Documentation and support:

- https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow
- https://lerionjakenwauda.com/privacy-policy
- https://lerionjakenwauda.com/terms

## WordPress.org release

The repository includes a WordPress.org-compatible `readme.txt`. Once the plugin is reviewed and the slug is approved, releases are copied to the WordPress.org SVN repository. GitHub remains the development repository.

## Licence

GPL-2.0-or-later.
