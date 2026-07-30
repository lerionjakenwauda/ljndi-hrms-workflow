# WordPress.org submission guide

This repository is the development source for **LJNDI HRMS Workflow**. WordPress.org uses a separate SVN release repository after the plugin review team approves the requested slug.

## Before submission

1. Create or sign in to a WordPress.org account.
2. Confirm the account email can receive messages from the Plugin Review Team.
3. Create the public documentation page:
   `https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow`
4. Confirm these public pages work:
   - `https://lerionjakenwauda.com/privacy-policy`
   - `https://lerionjakenwauda.com/terms`
5. Install the plugin on a staging WordPress site.
6. Run WordPress Plugin Check and resolve every error.
7. Validate `readme.txt` with the WordPress.org Readme Validator.
8. Test OAuth login, callback, user linking, optional user creation, role mapping, token refresh, logout revocation, and inactive employee access removal.
9. Create a clean ZIP whose top-level folder is `ljndi-hrms-workflow`.

## Submit for review

Open the WordPress.org plugin developer submission page while signed in and upload the clean ZIP. Request the slug:

```text
ljndi-hrms-workflow
```

The plugin is free and licensed GPL-2.0-or-later. The submission description should explain that the plugin connects to an external LJNDI HRMS OAuth identity service and that each WordPress website requires credentials created by an HRMS administrator.

Do not push anything to WordPress.org SVN until the plugin review is approved and you are ready for the listing to become public.

## After approval

WordPress.org creates an SVN repository similar to:

```text
https://plugins.svn.wordpress.org/ljndi-hrms-workflow/
```

It contains:

```text
/assets/
/tags/
/trunk/
```

Copy distributable plugin files directly into `/trunk`; do not nest them inside another plugin folder. Then create the first release tag in SVN:

```text
/tags/0.1.0/
```

The `Stable tag` in `trunk/readme.txt` must match the release tag.

## Listing assets

WordPress.org listing assets belong in the SVN `/assets` directory, not inside the installable plugin ZIP. Prepare:

```text
icon-128x128.png
icon-256x256.png
banner-772x250.png
banner-1544x500.png
screenshot-1.png
screenshot-2.png
```

Suggested screenshots:

1. WordPress settings screen showing the exact callback URL.
2. Continue with LJNDI HRMS on the WordPress login screen.
3. Role and department mapping settings.
4. HRMS Identity & API application registration screen.

## Release discipline

- GitHub remains the development repository.
- WordPress.org SVN receives completed releases only.
- Increment the plugin header version and `Stable tag` for every release.
- Tag the same version in GitHub and WordPress.org SVN.
- Never include real client IDs, client secrets, access tokens, refresh tokens, employee passwords, or client-site credentials in commits or screenshots.
