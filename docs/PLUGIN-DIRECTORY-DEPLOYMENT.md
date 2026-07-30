# LJNDI Plugin Directory Deployment

## Target architecture

- WordPress remains installed at `https://lerionjakenwauda.com/`.
- The standalone PHP catalogue is deployed to the WordPress document root at `public_html/plugins/`.
- The catalogue is public at `https://lerionjakenwauda.com/plugins/`.
- The publisher is installed as a normal WordPress plugin and is accessible only to administrators with `manage_options`.
- Public plugin metadata is exposed at `https://lerionjakenwauda.com/plugins/api/v1/plugins/{slug}`.

The standalone application does not bootstrap WordPress and does not have a second login system. The WordPress publisher writes release files and atomic JSON manifests directly into the catalogue directory on the same hosting account.

## Deploy the standalone catalogue

1. Copy the contents of `distribution/plugin-directory/` into `public_html/plugins/`.
2. Rename `config.example.php` to `config.php`.
3. Confirm the `base_url` in `config.php` is `https://lerionjakenwauda.com/plugins`.
4. Ensure PHP can write to:
   - `public_html/plugins/storage/catalog/`
   - `public_html/plugins/storage/releases/`
   - `public_html/plugins/storage/images/`
5. Use `0755` for directories and `0644` for ordinary files. Do not use `0777`.
6. Open `/plugins/` and `/plugins/api/v1/plugins` to confirm routing works.

If the website is deployed below a different URL, update both `RewriteBase` in `.htaccess` and `base_url` in `config.php`.

## Install the WordPress publisher

1. ZIP the `distribution/wordpress-publisher/` folder as `ljndi-plugin-directory-publisher.zip`.
2. Install and activate it in WordPress.
3. Open **Plugin Directory → Connection**.
4. Set the absolute server path to the catalogue directory, for example `/home/CPANEL_USER/public_html/plugins`.
5. Set the public URL to `https://lerionjakenwauda.com/plugins`.
6. Save and confirm the connection status is green.

## Publish a plugin

Open **Plugin Directory → Publish release** and provide the plugin name, slug, version, summary, description, changelog, compatibility values, plugin image, and release ZIP.

A valid ZIP must:

- use the exact plugin slug as its top-level folder;
- contain a PHP file with a WordPress `Plugin Name:` header;
- contain no absolute paths or `../` traversal entries;
- be no larger than 50 MB compressed or 200 MB after expansion.

Publishing creates:

- `storage/catalog/{slug}.json`
- `storage/releases/{slug}/{version}/{slug}.zip`
- `storage/images/{slug}/{hash}.{ext}`

## LJNDI HRMS Workflow updates

The plugin update checker reads:

`https://lerionjakenwauda.com/plugins/api/v1/plugins/ljndi-hrms-workflow`

Publish a version newer than the installed `LJNDI_HRMS_WORKFLOW_VERSION`. WordPress will cache update metadata for up to six hours. The release ZIP must contain the top-level `ljndi-hrms-workflow/` directory so WordPress upgrades the existing plugin instead of creating another folder.

## Security boundaries

- There is no public upload endpoint.
- Only authenticated WordPress administrators can publish.
- Uploads are nonce-protected and validated by MIME, size, path, archive structure, and WordPress plugin header.
- Manifests are written atomically.
- Catalogue JSON storage is blocked from direct web access; consumers use the API route.
- Never place OAuth secrets or API credentials in catalogue manifests.
