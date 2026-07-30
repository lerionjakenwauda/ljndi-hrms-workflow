<?php

if (! defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_Settings
{
    const OPTION = 'ljndi_hrms_workflow_settings';

    public static function defaults()
    {
        return array(
            'issuer'             => 'https://auth.lerionjakenwauda.com',
            'client_id'          => '',
            'client_secret'      => '',
            'scopes'             => 'openid profile email employment:read roles:read',
            'show_login_button'  => 1,
            'auto_create_users'  => 1,
            'sync_roles'         => 1,
            'default_role'       => 'subscriber',
            'role_mappings'      => '',
            'revalidate_minutes' => 15,
        );
    }

    public static function get()
    {
        return wp_parse_args((array) get_option(self::OPTION, array()), self::defaults());
    }

    public static function callback_url()
    {
        return admin_url('admin-post.php?action=ljndi_hrms_callback');
    }

    public static function register()
    {
        register_setting(
            'ljndi_hrms_workflow',
            self::OPTION,
            array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize'),
                'default'           => self::defaults(),
            )
        );
    }

    public static function sanitize($input)
    {
        $current = self::get();
        $input = is_array($input) ? $input : array();

        $issuer = isset($input['issuer']) ? untrailingslashit(esc_url_raw(trim($input['issuer']))) : $current['issuer'];
        if (strpos($issuer, 'https://') !== 0 && ! self::is_local_url($issuer)) {
            add_settings_error(self::OPTION, 'issuer_https', __('The OAuth issuer must use HTTPS.', 'ljndi-hrms-workflow'));
            $issuer = $current['issuer'];
        }

        $secret = isset($input['client_secret']) ? trim((string) $input['client_secret']) : '';
        if ($secret === '') {
            $secret = $current['client_secret'];
        } else {
            $encrypted = LJNDI_HRMS_Crypto::encrypt($secret);
            if (is_wp_error($encrypted)) {
                add_settings_error(self::OPTION, $encrypted->get_error_code(), $encrypted->get_error_message());
                $secret = $current['client_secret'];
            } else {
                $secret = $encrypted;
            }
        }

        $roles = wp_roles()->roles;
        $default_role = isset($input['default_role']) ? sanitize_key($input['default_role']) : 'subscriber';
        if (! isset($roles[$default_role])) {
            $default_role = 'subscriber';
        }

        $minutes = isset($input['revalidate_minutes']) ? absint($input['revalidate_minutes']) : 15;
        $minutes = max(5, min(1440, $minutes));

        return array(
            'issuer'             => $issuer,
            'client_id'          => isset($input['client_id']) ? sanitize_text_field($input['client_id']) : '',
            'client_secret'      => $secret,
            'scopes'             => self::sanitize_scopes(isset($input['scopes']) ? $input['scopes'] : ''),
            'show_login_button'  => empty($input['show_login_button']) ? 0 : 1,
            'auto_create_users'  => empty($input['auto_create_users']) ? 0 : 1,
            'sync_roles'         => empty($input['sync_roles']) ? 0 : 1,
            'default_role'       => $default_role,
            'role_mappings'      => self::sanitize_mappings(isset($input['role_mappings']) ? $input['role_mappings'] : ''),
            'revalidate_minutes' => $minutes,
        );
    }

    public static function render()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = self::get();
        $roles = wp_roles()->roles;
        ?>
        <div class="wrap ljndi-hrms-settings">
            <h1><?php esc_html_e('LJNDI HRMS Workflow', 'ljndi-hrms-workflow'); ?></h1>
            <p class="description"><?php esc_html_e('Connect this WordPress website to Continue with LJNDI HRMS using OAuth 2.1 Authorization Code with PKCE.', 'ljndi-hrms-workflow'); ?></p>

            <?php settings_errors(self::OPTION); ?>

            <div class="ljndi-hrms-card ljndi-hrms-callback-card">
                <h2><?php esc_html_e('Register this website in HRMS', 'ljndi-hrms-workflow'); ?></h2>
                <p><?php esc_html_e('Create a confidential OAuth application in HRMS Admin → Identity & API, then register this exact callback URL:', 'ljndi-hrms-workflow'); ?></p>
                <code><?php echo esc_html(self::callback_url()); ?></code>
                <button type="button" class="button" data-ljndi-copy="<?php echo esc_attr(self::callback_url()); ?>"><?php esc_html_e('Copy callback URL', 'ljndi-hrms-workflow'); ?></button>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('ljndi_hrms_workflow'); ?>

                <div class="ljndi-hrms-card">
                    <h2><?php esc_html_e('OAuth connection', 'ljndi-hrms-workflow'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ljndi-issuer"><?php esc_html_e('Identity service', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><input id="ljndi-issuer" class="regular-text" type="url" name="<?php echo esc_attr(self::OPTION); ?>[issuer]" value="<?php echo esc_attr($settings['issuer']); ?>" required><p class="description"><?php esc_html_e('Normally https://auth.lerionjakenwauda.com', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ljndi-client-id"><?php esc_html_e('Client ID', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><input id="ljndi-client-id" class="regular-text code" type="text" name="<?php echo esc_attr(self::OPTION); ?>[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>" autocomplete="off" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ljndi-client-secret"><?php esc_html_e('Client secret', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><input id="ljndi-client-secret" class="regular-text code" type="password" name="<?php echo esc_attr(self::OPTION); ?>[client_secret]" value="" autocomplete="new-password" placeholder="<?php echo $settings['client_secret'] ? esc_attr__('Stored securely — leave blank to keep it', 'ljndi-hrms-workflow') : ''; ?>"><p class="description"><?php esc_html_e('Shown once by HRMS. The plugin encrypts it before saving it in WordPress.', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ljndi-scopes"><?php esc_html_e('Requested scopes', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><input id="ljndi-scopes" class="large-text code" type="text" name="<?php echo esc_attr(self::OPTION); ?>[scopes]" value="<?php echo esc_attr($settings['scopes']); ?>"><p class="description"><?php esc_html_e('These scopes must also be allowed for the application in HRMS Admin.', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                    </table>
                </div>

                <div class="ljndi-hrms-card">
                    <h2><?php esc_html_e('WordPress access', 'ljndi-hrms-workflow'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th scope="row"><?php esc_html_e('Login screen', 'ljndi-hrms-workflow'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[show_login_button]" value="1" <?php checked($settings['show_login_button']); ?>> <?php esc_html_e('Show “Continue with LJNDI HRMS” on wp-login.php', 'ljndi-hrms-workflow'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Account provisioning', 'ljndi-hrms-workflow'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[auto_create_users]" value="1" <?php checked($settings['auto_create_users']); ?>> <?php esc_html_e('Create a WordPress user after an approved HRMS sign-in when no matching email exists', 'ljndi-hrms-workflow'); ?></label></td></tr>
                        <tr><th scope="row"><?php esc_html_e('Role synchronization', 'ljndi-hrms-workflow'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[sync_roles]" value="1" <?php checked($settings['sync_roles']); ?>> <?php esc_html_e('Synchronize the local WordPress role from the mappings below', 'ljndi-hrms-workflow'); ?></label></td></tr>
                        <tr>
                            <th scope="row"><label for="ljndi-default-role"><?php esc_html_e('Default WordPress role', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><select id="ljndi-default-role" name="<?php echo esc_attr(self::OPTION); ?>[default_role]">
                                <?php foreach ($roles as $role_key => $role) : ?><option value="<?php echo esc_attr($role_key); ?>" <?php selected($settings['default_role'], $role_key); ?>><?php echo esc_html(translate_user_role($role['name'])); ?></option><?php endforeach; ?>
                            </select><p class="description"><?php esc_html_e('Subscriber is the safest fallback. Administrator access must be mapped explicitly.', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ljndi-role-mappings"><?php esc_html_e('HRMS role mappings', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><textarea id="ljndi-role-mappings" class="large-text code" rows="9" name="<?php echo esc_attr(self::OPTION); ?>[role_mappings]" placeholder="role:Founder &amp; Visionary Leader=administrator&#10;role:Content Manager=editor&#10;department:Client and Customer Success Department=editor"><?php echo esc_textarea($settings['role_mappings']); ?></textarea><p class="description"><?php esc_html_e('One rule per line. Use role:Name=wordpress_role or department:Name=wordpress_role. Matching is case-insensitive.', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ljndi-revalidate"><?php esc_html_e('Session revalidation', 'ljndi-hrms-workflow'); ?></label></th>
                            <td><input id="ljndi-revalidate" type="number" min="5" max="1440" name="<?php echo esc_attr(self::OPTION); ?>[revalidate_minutes]" value="<?php echo esc_attr($settings['revalidate_minutes']); ?>"> <?php esc_html_e('minutes', 'ljndi-hrms-workflow'); ?><p class="description"><?php esc_html_e('Linked staff sessions are rechecked so inactive or revoked HRMS accounts lose access.', 'ljndi-hrms-workflow'); ?></p></td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save connection', 'ljndi-hrms-workflow')); ?>
            </form>

            <p><a href="https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Plugin documentation and support', 'ljndi-hrms-workflow'); ?></a></p>
        </div>
        <?php
    }

    private static function sanitize_scopes($value)
    {
        $scopes = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $clean = array();

        foreach ((array) $scopes as $scope) {
            $scope = preg_replace('/[^A-Za-z0-9:_\-.]/', '', (string) $scope);
            if ($scope !== '') {
                $clean[] = $scope;
            }
        }

        return implode(' ', array_values(array_unique($clean)));
    }

    private static function sanitize_mappings($value)
    {
        $roles = wp_roles()->roles;
        $clean = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $value);

        foreach ($lines as $line) {
            $line = trim(wp_strip_all_tags($line));
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            list($source, $target) = array_map('trim', explode('=', $line, 2));
            $target = sanitize_key($target);
            if (! isset($roles[$target]) || ! preg_match('/^(role|department):.+$/i', $source)) {
                continue;
            }
            $clean[] = $source . '=' . $target;
        }

        return implode("\n", array_unique($clean));
    }

    private static function is_local_url($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return in_array($host, array('localhost', '127.0.0.1', '::1'), true);
    }
}
