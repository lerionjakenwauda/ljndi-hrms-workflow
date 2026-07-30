<?php
/**
 * Plugin Name: LJNDI Plugin Directory Publisher
 * Plugin URI: https://lerionjakenwauda.com/plugins
 * Description: Publishes LJNDI plugin metadata, images, ZIP releases, and update manifests to the standalone public plugin directory.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Lerion Jake Nwauda Digital Innovations Ltd
 * Author URI: https://lerionjakenwauda.com
 * License: GPL-2.0-or-later
 * Text Domain: ljndi-plugin-publisher
 */

if (!defined('ABSPATH')) {
    exit;
}

final class LJNDI_Plugin_Directory_Publisher
{
    const VERSION = '0.1.0';
    const OPTION = 'ljndi_plugin_directory_publisher';
    const PAGE = 'ljndi-plugin-directory';
    const CAPABILITY = 'manage_options';

    public static function boot()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_post_ljndi_publish_plugin', array(__CLASS__, 'handle_publish'));
        add_action('admin_post_ljndi_plugin_status', array(__CLASS__, 'handle_status'));
        add_action('admin_notices', array(__CLASS__, 'admin_notice'));
    }

    public static function activate()
    {
        self::ensure_directories(self::settings());
    }

    public static function defaults()
    {
        return array(
            'root_path'  => trailingslashit(ABSPATH) . 'plugins',
            'public_url' => home_url('/plugins'),
        );
    }

    public static function settings()
    {
        return wp_parse_args((array) get_option(self::OPTION, array()), self::defaults());
    }

    public static function register_settings()
    {
        register_setting(
            'ljndi_plugin_directory',
            self::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
                'default'           => self::defaults(),
            )
        );
    }

    public static function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : array();
        $root = isset($input['root_path']) ? wp_normalize_path(trim((string) $input['root_path'])) : self::defaults()['root_path'];
        $url = isset($input['public_url']) ? untrailingslashit(esc_url_raw(trim((string) $input['public_url']))) : self::defaults()['public_url'];

        if ($root === '' || strpos($root, '..') !== false) {
            add_settings_error(self::OPTION, 'invalid_root', __('Enter a valid absolute plugin-directory path.', 'ljndi-plugin-publisher'));
            $root = self::settings()['root_path'];
        }

        if (strpos($url, 'https://') !== 0 && !self::is_local_url($url)) {
            add_settings_error(self::OPTION, 'invalid_url', __('The public directory URL must use HTTPS.', 'ljndi-plugin-publisher'));
            $url = self::settings()['public_url'];
        }

        $clean = array(
            'root_path'  => untrailingslashit($root),
            'public_url' => $url,
        );

        $result = self::ensure_directories($clean);
        if (is_wp_error($result)) {
            add_settings_error(self::OPTION, $result->get_error_code(), $result->get_error_message());
        }

        return $clean;
    }

    public static function admin_menu()
    {
        add_menu_page(
            __('LJNDI Plugin Directory', 'ljndi-plugin-publisher'),
            __('Plugin Directory', 'ljndi-plugin-publisher'),
            self::CAPABILITY,
            self::PAGE,
            array(__CLASS__, 'render_page'),
            'dashicons-admin-plugins',
            58
        );
    }

    public static function render_page()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to manage plugin releases.', 'ljndi-plugin-publisher'));
        }

        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'plugins';
        $settings = self::settings();
        $editSlug = isset($_GET['edit']) ? sanitize_title(wp_unslash($_GET['edit'])) : '';
        $manifest = $editSlug !== '' ? self::load_manifest($editSlug, $settings) : null;
        ?>
        <div class="wrap ljndi-publisher-wrap">
            <h1><?php esc_html_e('LJNDI Plugin Directory', 'ljndi-plugin-publisher'); ?></h1>
            <p class="description"><?php esc_html_e('Publish public WordPress plugins without logging into a second system. Releases written here appear automatically on the standalone /plugins website and its update API.', 'ljndi-plugin-publisher'); ?></p>

            <nav class="nav-tab-wrapper">
                <a class="nav-tab <?php echo $tab === 'plugins' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::page_url(array('tab' => 'plugins'))); ?>"><?php esc_html_e('Plugins', 'ljndi-plugin-publisher'); ?></a>
                <a class="nav-tab <?php echo $tab === 'publish' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::page_url(array('tab' => 'publish'))); ?>"><?php echo esc_html($manifest ? __('Edit plugin', 'ljndi-plugin-publisher') : __('Publish release', 'ljndi-plugin-publisher')); ?></a>
                <a class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(self::page_url(array('tab' => 'settings'))); ?>"><?php esc_html_e('Connection', 'ljndi-plugin-publisher'); ?></a>
            </nav>

            <?php
            if ($tab === 'publish') {
                self::render_publish_form($manifest, $settings);
            } elseif ($tab === 'settings') {
                self::render_settings($settings);
            } else {
                self::render_plugins($settings);
            }
            ?>
        </div>
        <style>
            .ljndi-publisher-wrap{max-width:1180px}.ljndi-publisher-grid{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:24px;margin-top:24px}.ljndi-publisher-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px}.ljndi-publisher-card h2{margin-top:0}.ljndi-publisher-card label strong{display:block;margin-bottom:7px}.ljndi-publisher-card input[type=text],.ljndi-publisher-card input[type=url],.ljndi-publisher-card input[type=file],.ljndi-publisher-card textarea{width:100%}.ljndi-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.ljndi-field-wide{grid-column:1/-1}.ljndi-release-table td{vertical-align:middle}.ljndi-plugin-cell{display:flex;gap:12px;align-items:center}.ljndi-plugin-cell img,.ljndi-placeholder{width:46px;height:46px;border-radius:10px;object-fit:cover}.ljndi-placeholder{display:grid;place-items:center;background:#e7f5ef;color:#075f46;font-weight:800}.ljndi-status{display:inline-flex;padding:4px 9px;border-radius:999px;background:#e7f5ef;color:#075f46;font-size:11px;font-weight:800;text-transform:uppercase}.ljndi-status.draft{background:#f1f1f1;color:#50575e}.ljndi-path-code{display:block;word-break:break-all;padding:12px;background:#f6f7f7;border-radius:8px}.ljndi-check{padding:16px;border-radius:10px;background:#f6f7f7}.ljndi-check.good{background:#e7f5ef;color:#075f46}.ljndi-check.bad{background:#fcf0f1;color:#8a2424}@media(max-width:900px){.ljndi-publisher-grid,.ljndi-fields{grid-template-columns:1fr}.ljndi-field-wide{grid-column:auto}}
        </style>
        <?php
    }

    private static function render_plugins(array $settings)
    {
        $plugins = self::load_catalog($settings);
        ?>
        <div class="ljndi-publisher-card" style="margin-top:24px">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:20px">
                <div><h2><?php esc_html_e('Published plugin catalogue', 'ljndi-plugin-publisher'); ?></h2><p class="description"><?php echo esc_html(sprintf(_n('%d plugin manifest found.', '%d plugin manifests found.', count($plugins), 'ljndi-plugin-publisher'), count($plugins))); ?></p></div>
                <a class="button button-primary" href="<?php echo esc_url(self::page_url(array('tab' => 'publish'))); ?>"><?php esc_html_e('Publish plugin', 'ljndi-plugin-publisher'); ?></a>
            </div>
            <?php if (empty($plugins)) : ?>
                <p><?php esc_html_e('There are no plugin manifests yet.', 'ljndi-plugin-publisher'); ?></p>
            <?php else : ?>
                <table class="widefat striped ljndi-release-table">
                    <thead><tr><th><?php esc_html_e('Plugin', 'ljndi-plugin-publisher'); ?></th><th><?php esc_html_e('Version', 'ljndi-plugin-publisher'); ?></th><th><?php esc_html_e('Status', 'ljndi-plugin-publisher'); ?></th><th><?php esc_html_e('Updated', 'ljndi-plugin-publisher'); ?></th><th><?php esc_html_e('Actions', 'ljndi-plugin-publisher'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($plugins as $plugin) :
                        $slug = (string) $plugin['slug'];
                        $status = (string) ($plugin['status'] ?? 'published');
                        $toggle = $status === 'published' ? 'draft' : 'published';
                        $toggleUrl = wp_nonce_url(admin_url('admin-post.php?action=ljndi_plugin_status&slug=' . rawurlencode($slug) . '&status=' . $toggle), 'ljndi_plugin_status_' . $slug);
                        ?>
                        <tr>
                            <td><div class="ljndi-plugin-cell"><?php if (!empty($plugin['icon_url'])) : ?><img src="<?php echo esc_url($plugin['icon_url']); ?>" alt=""><?php else : ?><span class="ljndi-placeholder"><?php echo esc_html(strtoupper(substr((string) $plugin['name'], 0, 1))); ?></span><?php endif; ?><div><strong><?php echo esc_html($plugin['name']); ?></strong><br><code><?php echo esc_html($slug); ?></code></div></div></td>
                            <td><?php echo esc_html($plugin['version']); ?></td>
                            <td><span class="ljndi-status <?php echo $status === 'published' ? '' : 'draft'; ?>"><?php echo esc_html($status); ?></span></td>
                            <td><?php echo esc_html(!empty($plugin['updated_at']) ? wp_date('M j, Y g:i a', strtotime((string) $plugin['updated_at'])) : '—'); ?></td>
                            <td><a href="<?php echo esc_url(self::page_url(array('tab' => 'publish', 'edit' => $slug))); ?>"><?php esc_html_e('Edit', 'ljndi-plugin-publisher'); ?></a> · <a href="<?php echo esc_url($settings['public_url'] . '/' . rawurlencode($slug)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View', 'ljndi-plugin-publisher'); ?></a> · <a href="<?php echo esc_url($toggleUrl); ?>"><?php echo $toggle === 'draft' ? esc_html__('Unpublish', 'ljndi-plugin-publisher') : esc_html__('Publish', 'ljndi-plugin-publisher'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_publish_form($manifest, array $settings)
    {
        $manifest = is_array($manifest) ? $manifest : array();
        $sections = is_array($manifest['sections'] ?? null) ? $manifest['sections'] : array();
        $isEdit = !empty($manifest['slug']);
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <input type="hidden" name="action" value="ljndi_publish_plugin">
            <?php wp_nonce_field('ljndi_publish_plugin'); ?>
            <div class="ljndi-publisher-grid">
                <div class="ljndi-publisher-card">
                    <h2><?php echo $isEdit ? esc_html__('Edit plugin release', 'ljndi-plugin-publisher') : esc_html__('Publish a public plugin', 'ljndi-plugin-publisher'); ?></h2>
                    <div class="ljndi-fields">
                        <p><label><strong><?php esc_html_e('Plugin name', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="name" required value="<?php echo esc_attr($manifest['name'] ?? ''); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Plugin slug', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="slug" required pattern="[a-z0-9-]+" value="<?php echo esc_attr($manifest['slug'] ?? ''); ?>" <?php readonly($isEdit); ?>></label></p>
                        <p><label><strong><?php esc_html_e('Version', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="version" required placeholder="1.0.0" value="<?php echo esc_attr($manifest['version'] ?? ''); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Homepage', 'ljndi-plugin-publisher'); ?></strong><input type="url" name="homepage" value="<?php echo esc_attr($manifest['homepage'] ?? ''); ?>"></label></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('Short summary', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="summary" maxlength="220" required value="<?php echo esc_attr($manifest['summary'] ?? ''); ?>"></label></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('About the plugin', 'ljndi-plugin-publisher'); ?></strong><textarea name="description" rows="8" required><?php echo esc_textarea($manifest['description'] ?? ''); ?></textarea></label></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('Installation instructions', 'ljndi-plugin-publisher'); ?></strong><textarea name="installation" rows="5"><?php echo esc_textarea($sections['installation'] ?? 'Upload the ZIP in WordPress under Plugins → Add New → Upload Plugin, activate it, then configure its settings.'); ?></textarea></label></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('Changelog', 'ljndi-plugin-publisher'); ?></strong><textarea name="changelog" rows="7" required><?php echo esc_textarea($sections['changelog'] ?? ''); ?></textarea></label></p>
                        <p><label><strong><?php esc_html_e('Requires WordPress', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="requires" placeholder="6.4" value="<?php echo esc_attr($manifest['requires'] ?? '6.4'); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Tested up to', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="tested" placeholder="6.8" value="<?php echo esc_attr($manifest['tested'] ?? get_bloginfo('version')); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Requires PHP', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="requires_php" placeholder="7.4" value="<?php echo esc_attr($manifest['requires_php'] ?? '7.4'); ?>"></label></p>
                        <p><label><strong><?php esc_html_e('Author', 'ljndi-plugin-publisher'); ?></strong><input type="text" name="author" value="<?php echo esc_attr($manifest['author'] ?? 'Lerion Jake Nwauda Digital Innovations Ltd'); ?>"></label></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('Plugin ZIP', 'ljndi-plugin-publisher'); ?></strong><input type="file" name="package" accept=".zip,application/zip" <?php echo $isEdit ? '' : 'required'; ?>></label><span class="description"><?php esc_html_e('The ZIP must contain a WordPress plugin header and use the plugin slug as its top-level folder. Leave blank when editing only the text or image.', 'ljndi-plugin-publisher'); ?></span></p>
                        <p class="ljndi-field-wide"><label><strong><?php esc_html_e('Plugin image', 'ljndi-plugin-publisher'); ?></strong><input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label><span class="description"><?php esc_html_e('PNG, JPG, or WebP. A square image works best.', 'ljndi-plugin-publisher'); ?></span></p>
                        <p class="ljndi-field-wide"><label><input type="checkbox" name="published" value="1" <?php checked(($manifest['status'] ?? 'published') === 'published'); ?>> <strong style="display:inline"><?php esc_html_e('Publish publicly immediately', 'ljndi-plugin-publisher'); ?></strong></label></p>
                    </div>
                    <?php submit_button($isEdit ? __('Save plugin', 'ljndi-plugin-publisher') : __('Publish release', 'ljndi-plugin-publisher')); ?>
                </div>
                <aside class="ljndi-publisher-card">
                    <h2><?php esc_html_e('Publishing target', 'ljndi-plugin-publisher'); ?></h2>
                    <p><strong><?php esc_html_e('Website', 'ljndi-plugin-publisher'); ?></strong><code class="ljndi-path-code"><?php echo esc_html($settings['public_url']); ?></code></p>
                    <p><strong><?php esc_html_e('Server path', 'ljndi-plugin-publisher'); ?></strong><code class="ljndi-path-code"><?php echo esc_html($settings['root_path']); ?></code></p>
                    <p><?php esc_html_e('Saving this form writes an atomic JSON manifest and copies the release assets into the standalone PHP directory. No separate account is involved.', 'ljndi-plugin-publisher'); ?></p>
                    <?php if ($isEdit && !empty($manifest['download_url'])) : ?><p><a class="button" href="<?php echo esc_url($manifest['download_url']); ?>"><?php esc_html_e('Download current ZIP', 'ljndi-plugin-publisher'); ?></a></p><?php endif; ?>
                </aside>
            </div>
        </form>
        <?php
    }

    private static function render_settings(array $settings)
    {
        $check = self::connection_check($settings);
        ?>
        <div class="ljndi-publisher-grid">
            <div class="ljndi-publisher-card">
                <h2><?php esc_html_e('Standalone directory connection', 'ljndi-plugin-publisher'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('ljndi_plugin_directory'); ?>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><label for="ljndi-root-path"><?php esc_html_e('Absolute server path', 'ljndi-plugin-publisher'); ?></label></th><td><input id="ljndi-root-path" class="large-text code" type="text" name="<?php echo esc_attr(self::OPTION); ?>[root_path]" value="<?php echo esc_attr($settings['root_path']); ?>"><p class="description"><?php esc_html_e('Example: /home/account/public_html/plugins', 'ljndi-plugin-publisher'); ?></p></td></tr>
                        <tr><th scope="row"><label for="ljndi-public-url"><?php esc_html_e('Public URL', 'ljndi-plugin-publisher'); ?></label></th><td><input id="ljndi-public-url" class="regular-text code" type="url" name="<?php echo esc_attr(self::OPTION); ?>[public_url]" value="<?php echo esc_attr($settings['public_url']); ?>"></td></tr>
                    </table>
                    <?php submit_button(__('Save connection', 'ljndi-plugin-publisher')); ?>
                </form>
            </div>
            <aside class="ljndi-publisher-card">
                <h2><?php esc_html_e('Connection status', 'ljndi-plugin-publisher'); ?></h2>
                <div class="ljndi-check <?php echo is_wp_error($check) ? 'bad' : 'good'; ?>"><?php echo is_wp_error($check) ? esc_html($check->get_error_message()) : esc_html__('The catalogue storage exists and WordPress can write to it.', 'ljndi-plugin-publisher'); ?></div>
                <p><?php esc_html_e('This plugin does not create another login. Access is inherited from WordPress administrators with the manage_options capability.', 'ljndi-plugin-publisher'); ?></p>
            </aside>
        </div>
        <?php
    }

    public static function handle_publish()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to publish plugin releases.', 'ljndi-plugin-publisher'));
        }
        check_admin_referer('ljndi_publish_plugin');

        $settings = self::settings();
        $ready = self::ensure_directories($settings);
        if (is_wp_error($ready)) {
            self::redirect_with_notice('error', $ready->get_error_message(), array('tab' => 'publish'));
        }

        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $version = self::sanitize_version(wp_unslash($_POST['version'] ?? ''));
        $existing = $slug !== '' ? self::load_manifest($slug, $settings) : null;

        if ($slug === '' || $name === '' || $version === '') {
            self::redirect_with_notice('error', __('Plugin name, slug, and a valid numeric version are required.', 'ljndi-plugin-publisher'), array('tab' => 'publish', 'edit' => $slug));
        }

        $package = null;
        if (!empty($_FILES['package']['name'])) {
            $package = self::store_package($_FILES['package'], $slug, $version, $settings);
            if (is_wp_error($package)) {
                self::redirect_with_notice('error', $package->get_error_message(), array('tab' => 'publish', 'edit' => $slug));
            }
        } elseif (!$existing) {
            self::redirect_with_notice('error', __('A plugin ZIP is required for a new public plugin.', 'ljndi-plugin-publisher'), array('tab' => 'publish'));
        }

        $imageUrl = (string) ($existing['icon_url'] ?? '');
        if (!empty($_FILES['image']['name'])) {
            $image = self::store_image($_FILES['image'], $slug, $settings);
            if (is_wp_error($image)) {
                self::redirect_with_notice('error', $image->get_error_message(), array('tab' => 'publish', 'edit' => $slug));
            }
            $imageUrl = $image['url'];
        }

        $now = gmdate('c');
        $manifest = array(
            'schema_version' => 1,
            'status'         => !empty($_POST['published']) ? 'published' : 'draft',
            'slug'           => $slug,
            'name'           => $name,
            'version'        => $version,
            'summary'        => sanitize_text_field(wp_unslash($_POST['summary'] ?? '')),
            'description'    => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'author'         => sanitize_text_field(wp_unslash($_POST['author'] ?? 'Lerion Jake Nwauda Digital Innovations Ltd')),
            'author_url'     => 'https://lerionjakenwauda.com',
            'homepage'       => esc_url_raw(wp_unslash($_POST['homepage'] ?? ($settings['public_url'] . '/' . $slug))),
            'requires'       => self::sanitize_version(wp_unslash($_POST['requires'] ?? '')),
            'tested'         => self::sanitize_version(wp_unslash($_POST['tested'] ?? '')),
            'requires_php'   => self::sanitize_version(wp_unslash($_POST['requires_php'] ?? '')),
            'download_url'   => $package ? $package['url'] : (string) ($existing['download_url'] ?? ''),
            'package_sha256' => $package ? $package['sha256'] : (string) ($existing['package_sha256'] ?? ''),
            'package_size'   => $package ? $package['size'] : (int) ($existing['package_size'] ?? 0),
            'icon_url'       => $imageUrl,
            'sections'       => array(
                'description'  => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
                'installation' => sanitize_textarea_field(wp_unslash($_POST['installation'] ?? '')),
                'changelog'    => sanitize_textarea_field(wp_unslash($_POST['changelog'] ?? '')),
            ),
            'published_at'   => (string) ($existing['published_at'] ?? $now),
            'updated_at'     => $now,
        );

        $saved = self::write_manifest($manifest, $settings);
        if (is_wp_error($saved)) {
            self::redirect_with_notice('error', $saved->get_error_message(), array('tab' => 'publish', 'edit' => $slug));
        }

        self::redirect_with_notice('success', __('Plugin release published successfully.', 'ljndi-plugin-publisher'), array('tab' => 'plugins'));
    }

    public static function handle_status()
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You are not allowed to change plugin status.', 'ljndi-plugin-publisher'));
        }

        $slug = sanitize_title(wp_unslash($_GET['slug'] ?? ''));
        check_admin_referer('ljndi_plugin_status_' . $slug);
        $status = sanitize_key(wp_unslash($_GET['status'] ?? 'draft'));
        $status = $status === 'published' ? 'published' : 'draft';
        $settings = self::settings();
        $manifest = self::load_manifest($slug, $settings, true);

        if (!$manifest) {
            self::redirect_with_notice('error', __('Plugin manifest not found.', 'ljndi-plugin-publisher'), array('tab' => 'plugins'));
        }

        $manifest['status'] = $status;
        $manifest['updated_at'] = gmdate('c');
        $saved = self::write_manifest($manifest, $settings);
        if (is_wp_error($saved)) {
            self::redirect_with_notice('error', $saved->get_error_message(), array('tab' => 'plugins'));
        }

        self::redirect_with_notice('success', $status === 'published' ? __('Plugin published.', 'ljndi-plugin-publisher') : __('Plugin unpublished.', 'ljndi-plugin-publisher'), array('tab' => 'plugins'));
    }

    public static function admin_notice()
    {
        if (empty($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== self::PAGE || empty($_GET['ljndi_notice'])) {
            return;
        }

        $type = isset($_GET['ljndi_notice_type']) && sanitize_key(wp_unslash($_GET['ljndi_notice_type'])) === 'error' ? 'error' : 'success';
        $message = sanitize_text_field(wp_unslash($_GET['ljndi_notice']));
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function ensure_directories(array $settings)
    {
        $root = untrailingslashit(wp_normalize_path((string) $settings['root_path']));
        $directories = array(
            $root,
            $root . '/storage',
            $root . '/storage/catalog',
            $root . '/storage/releases',
            $root . '/storage/images',
        );

        foreach ($directories as $directory) {
            if (!is_dir($directory) && !wp_mkdir_p($directory)) {
                return new WP_Error('directory_create_failed', sprintf(__('WordPress could not create %s.', 'ljndi-plugin-publisher'), $directory));
            }
            if (!is_writable($directory)) {
                return new WP_Error('directory_not_writable', sprintf(__('The directory is not writable: %s', 'ljndi-plugin-publisher'), $directory));
            }
        }

        return true;
    }

    private static function connection_check(array $settings)
    {
        $ready = self::ensure_directories($settings);
        if (is_wp_error($ready)) {
            return $ready;
        }

        $test = untrailingslashit($settings['root_path']) . '/storage/catalog/.ljndi-write-test-' . wp_generate_password(8, false, false);
        if (@file_put_contents($test, 'ok', LOCK_EX) === false) {
            return new WP_Error('write_test_failed', __('WordPress cannot write inside the catalogue directory.', 'ljndi-plugin-publisher'));
        }
        @unlink($test);
        return true;
    }

    private static function store_package(array $file, $slug, $version, array $settings)
    {
        $validation = self::validate_upload($file, array('application/zip', 'application/x-zip-compressed', 'application/x-zip', 'application/octet-stream'), 50 * MB_IN_BYTES, 'zip');
        if (is_wp_error($validation)) {
            return $validation;
        }
        if (!class_exists('ZipArchive')) {
            return new WP_Error('ziparchive_missing', __('The server must enable the PHP ZipArchive extension before publishing plugin ZIP files.', 'ljndi-plugin-publisher'));
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            return new WP_Error('invalid_zip', __('The uploaded package is not a readable ZIP archive.', 'ljndi-plugin-publisher'));
        }

        $hasHeader = false;
        $totalSize = 0;
        if ($zip->numFiles > 5000) {
            $zip->close();
            return new WP_Error('zip_too_many_files', __('The plugin ZIP contains too many files.', 'ljndi-plugin-publisher'));
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = isset($stat['name']) ? str_replace('\\', '/', (string) $stat['name']) : '';
            $totalSize += (int) ($stat['size'] ?? 0);
            if ($name === '' || strpos($name, '../') !== false || strpos($name, '/') === 0 || preg_match('/^[A-Za-z]:\//', $name)) {
                $zip->close();
                return new WP_Error('unsafe_zip_path', __('The ZIP contains an unsafe file path.', 'ljndi-plugin-publisher'));
            }
            if ($totalSize > 200 * MB_IN_BYTES) {
                $zip->close();
                return new WP_Error('zip_expands_too_large', __('The uncompressed plugin is larger than the permitted limit.', 'ljndi-plugin-publisher'));
            }
            if (substr($name, -4) === '.php' && strpos($name, $slug . '/') === 0) {
                $contents = $zip->getFromIndex($index, 65536);
                if (is_string($contents) && preg_match('/^[ \t\/*#@]*Plugin Name\s*:/mi', $contents)) {
                    $hasHeader = true;
                }
            }
        }
        $zip->close();

        if (!$hasHeader) {
            return new WP_Error('plugin_header_missing', sprintf(__('The ZIP must contain a PHP file with a WordPress Plugin Name header inside the top-level %s folder.', 'ljndi-plugin-publisher'), $slug));
        }

        $relative = 'storage/releases/' . $slug . '/' . $version . '/' . $slug . '.zip';
        $destination = untrailingslashit($settings['root_path']) . '/' . $relative;
        if (!wp_mkdir_p(dirname($destination)) || !@move_uploaded_file($file['tmp_name'], $destination)) {
            return new WP_Error('package_move_failed', __('WordPress could not move the ZIP into the release directory.', 'ljndi-plugin-publisher'));
        }
        @chmod($destination, 0644);

        return array(
            'url'    => untrailingslashit($settings['public_url']) . '/' . $relative,
            'sha256' => hash_file('sha256', $destination),
            'size'   => filesize($destination),
        );
    }

    private static function store_image(array $file, $slug, array $settings)
    {
        $validation = self::validate_upload($file, array('image/png', 'image/jpeg', 'image/webp'), 5 * MB_IN_BYTES, null);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $extensions = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp');
        $extension = $extensions[$validation['mime']];
        $relative = 'storage/images/' . $slug . '/' . substr(hash_file('sha256', $file['tmp_name']), 0, 16) . '.' . $extension;
        $destination = untrailingslashit($settings['root_path']) . '/' . $relative;
        if (!wp_mkdir_p(dirname($destination)) || !@move_uploaded_file($file['tmp_name'], $destination)) {
            return new WP_Error('image_move_failed', __('WordPress could not move the plugin image into the directory.', 'ljndi-plugin-publisher'));
        }
        @chmod($destination, 0644);

        return array('url' => untrailingslashit($settings['public_url']) . '/' . $relative);
    }

    private static function validate_upload(array $file, array $allowedMimes, $maxBytes, $requiredExtension)
    {
        if (!isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_failed', __('The file upload did not complete successfully.', 'ljndi-plugin-publisher'));
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', __('The uploaded file could not be verified.', 'ljndi-plugin-publisher'));
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            return new WP_Error('upload_size', __('The uploaded file exceeds the permitted size.', 'ljndi-plugin-publisher'));
        }

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : (string) ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!in_array($mime, $allowedMimes, true)) {
            return new WP_Error('upload_type', __('The uploaded file type is not allowed.', 'ljndi-plugin-publisher'));
        }
        if ($requiredExtension !== null && strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== $requiredExtension) {
            return new WP_Error('upload_extension', __('The file extension is invalid.', 'ljndi-plugin-publisher'));
        }

        return array('mime' => $mime);
    }

    private static function write_manifest(array $manifest, array $settings)
    {
        $path = untrailingslashit($settings['root_path']) . '/storage/catalog/' . $manifest['slug'] . '.json';
        $json = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) {
            return new WP_Error('manifest_encode_failed', __('The plugin manifest could not be encoded.', 'ljndi-plugin-publisher'));
        }

        $temporary = $path . '.tmp-' . wp_generate_password(8, false, false);
        if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false || !@rename($temporary, $path)) {
            @unlink($temporary);
            return new WP_Error('manifest_write_failed', __('The plugin manifest could not be written atomically.', 'ljndi-plugin-publisher'));
        }
        @chmod($path, 0644);
        return true;
    }

    private static function load_catalog(array $settings)
    {
        $files = glob(untrailingslashit($settings['root_path']) . '/storage/catalog/*.json') ?: array();
        $catalog = array();
        foreach ($files as $file) {
            $decoded = json_decode((string) @file_get_contents($file), true);
            if (is_array($decoded) && !empty($decoded['slug'])) {
                $catalog[] = $decoded;
            }
        }
        usort($catalog, static function ($left, $right) {
            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        });
        return $catalog;
    }

    private static function load_manifest($slug, array $settings, $includeDraft = true)
    {
        $path = untrailingslashit($settings['root_path']) . '/storage/catalog/' . sanitize_title($slug) . '.json';
        if (!is_readable($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || (!$includeDraft && ($decoded['status'] ?? 'published') !== 'published')) {
            return null;
        }
        return $decoded;
    }

    private static function sanitize_version($version)
    {
        $version = trim((string) $version);
        return preg_match('/^\d+(?:\.\d+){0,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
    }

    private static function redirect_with_notice($type, $message, array $args)
    {
        $args['ljndi_notice_type'] = $type;
        $args['ljndi_notice'] = $message;
        wp_safe_redirect(self::page_url($args));
        exit;
    }

    private static function page_url(array $args = array())
    {
        return add_query_arg(array_merge(array('page' => self::PAGE), $args), admin_url('admin.php'));
    }

    private static function is_local_url($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return in_array($host, array('localhost', '127.0.0.1', '::1'), true);
    }
}

register_activation_hook(__FILE__, array('LJNDI_Plugin_Directory_Publisher', 'activate'));
LJNDI_Plugin_Directory_Publisher::boot();
