<?php

if (! defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_Authentication
{
    const META_EMPLOYEE_ID   = '_ljndi_hrms_employee_id';
    const META_ACCESS_TOKEN  = '_ljndi_hrms_access_token';
    const META_REFRESH_TOKEN = '_ljndi_hrms_refresh_token';
    const META_EXPIRES_AT    = '_ljndi_hrms_expires_at';
    const META_VALIDATED_AT  = '_ljndi_hrms_validated_at';
    const META_CLAIMS        = '_ljndi_hrms_claims';

    public static function begin_login()
    {
        check_admin_referer('ljndi_hrms_login');

        $settings = LJNDI_HRMS_Settings::get();

        if (empty($settings['client_id'])) {
            self::fail(__('This website has not finished configuring LJNDI HRMS sign-in.', 'ljndi-hrms-workflow'));
        }

        try {
            $state = self::random_urlsafe(32);
            $verifier = self::random_urlsafe(64);
            $nonce = self::random_urlsafe(24);
        } catch (Exception $exception) {
            self::fail(__('Unable to start a secure sign-in request.', 'ljndi-hrms-workflow'));
        }

        $requested_redirect = isset($_REQUEST['redirect_to'])
            ? esc_url_raw(wp_unslash($_REQUEST['redirect_to']))
            : admin_url();
        $redirect_to = wp_validate_redirect($requested_redirect, admin_url());

        set_transient(
            self::transaction_key($state),
            array(
                'verifier'    => $verifier,
                'nonce'       => $nonce,
                'redirect_to' => $redirect_to,
                'created_at'  => time(),
            ),
            10 * MINUTE_IN_SECONDS
        );

        $client = new LJNDI_HRMS_OAuth_Client($settings);
        $challenge = self::base64url(hash('sha256', $verifier, true));
        $authorization_url = $client->authorization_url($state, $challenge, $nonce);

        if (is_wp_error($authorization_url)) {
            self::fail($authorization_url->get_error_message());
        }

        // The authorization URL is obtained from validated OAuth discovery metadata.
        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        wp_redirect($authorization_url, 302, 'LJNDI HRMS Workflow');
        exit;
    }

    public static function handle_callback()
    {
        // OAuth callbacks cannot carry a WordPress nonce. The high-entropy state value
        // and the one-time transient transaction provide request correlation and CSRF protection.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $oauth_error = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
        $error_description = isset($_GET['error_description'])
            ? sanitize_text_field(wp_unslash($_GET['error_description']))
            : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $transaction = $state ? get_transient(self::transaction_key($state)) : false;

        if (! is_array($transaction) || empty($transaction['verifier'])) {
            self::fail(__('The sign-in request expired or could not be verified. Please start again.', 'ljndi-hrms-workflow'));
        }

        delete_transient(self::transaction_key($state));

        if ($oauth_error !== '') {
            $description = $error_description !== ''
                ? $error_description
                : __('Access was not approved.', 'ljndi-hrms-workflow');
            self::fail($description);
        }

        if ($code === '') {
            self::fail(__('The identity service did not return an authorization code.', 'ljndi-hrms-workflow'));
        }

        $settings = LJNDI_HRMS_Settings::get();
        $client = new LJNDI_HRMS_OAuth_Client($settings);
        $tokens = $client->exchange_code($code, $transaction['verifier']);

        if (is_wp_error($tokens)) {
            self::fail($tokens->get_error_message());
        }

        $claims = ! empty($tokens['employee']) && is_array($tokens['employee'])
            ? $tokens['employee']
            : $client->userinfo(isset($tokens['access_token']) ? $tokens['access_token'] : '');

        if (is_wp_error($claims)) {
            self::fail($claims->get_error_message());
        }

        $user_id = self::provision_user($claims, $settings);
        if (is_wp_error($user_id)) {
            self::fail($user_id->get_error_message());
        }

        self::store_session($user_id, $tokens, $claims);

        $user = get_userdata($user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());
        // Invoke WordPress core's standard post-login hook after programmatic authentication.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action('wp_login', $user->user_login, $user);

        $redirect_to = ! empty($transaction['redirect_to']) ? $transaction['redirect_to'] : admin_url();
        wp_safe_redirect($redirect_to);
        exit;
    }

    public static function maybe_validate_current_session()
    {
        if (! is_user_logged_in() || wp_doing_cron() || wp_doing_ajax()) {
            return;
        }

        $user_id = get_current_user_id();
        $employee_id = (string) get_user_meta($user_id, self::META_EMPLOYEE_ID, true);

        if ($employee_id === '') {
            return;
        }

        $settings = LJNDI_HRMS_Settings::get();
        $validated_at = (int) get_user_meta($user_id, self::META_VALIDATED_AT, true);
        $interval = max(5, absint($settings['revalidate_minutes'])) * MINUTE_IN_SECONDS;

        if ($validated_at && (time() - $validated_at) < $interval) {
            return;
        }

        $access_token = LJNDI_HRMS_Crypto::decrypt(get_user_meta($user_id, self::META_ACCESS_TOKEN, true));
        $refresh_token = LJNDI_HRMS_Crypto::decrypt(get_user_meta($user_id, self::META_REFRESH_TOKEN, true));

        if ($access_token === '' || $refresh_token === '') {
            self::end_local_session($user_id, __('Your LJNDI HRMS session is no longer available.', 'ljndi-hrms-workflow'));
        }

        $client = new LJNDI_HRMS_OAuth_Client($settings);
        $claims = $client->userinfo($access_token);

        if (is_wp_error($claims)) {
            $tokens = $client->refresh($refresh_token);

            if (is_wp_error($tokens)) {
                $grace_period = max(HOUR_IN_SECONDS, $interval * 2);
                if (! $validated_at || (time() - $validated_at) >= $grace_period) {
                    self::end_local_session($user_id, __('Your LJNDI HRMS access was revoked, expired, or could not be renewed.', 'ljndi-hrms-workflow'));
                }
                return;
            }

            $claims = ! empty($tokens['employee']) && is_array($tokens['employee'])
                ? $tokens['employee']
                : $client->userinfo(isset($tokens['access_token']) ? $tokens['access_token'] : '');

            if (is_wp_error($claims)) {
                return;
            }

            self::store_session($user_id, $tokens, $claims);
        }

        $claim_employee_id = isset($claims['employee_id']) ? (string) $claims['employee_id'] : '';
        $claim_status = isset($claims['status']) ? strtolower((string) $claims['status']) : '';

        if ($claim_employee_id === '' || ! hash_equals($employee_id, $claim_employee_id) || $claim_status !== 'active') {
            self::end_local_session($user_id, __('Your LJNDI HRMS employment access is inactive.', 'ljndi-hrms-workflow'));
        }

        self::sync_user_profile($user_id, $claims, $settings);
        update_user_meta($user_id, self::META_VALIDATED_AT, time());
        update_user_meta($user_id, self::META_CLAIMS, wp_json_encode($claims));
    }

    public static function revoke_on_logout($user_id)
    {
        $user_id = absint($user_id);
        if (! $user_id) {
            return;
        }

        $refresh_token = LJNDI_HRMS_Crypto::decrypt(get_user_meta($user_id, self::META_REFRESH_TOKEN, true));

        if ($refresh_token !== '') {
            $client = new LJNDI_HRMS_OAuth_Client(LJNDI_HRMS_Settings::get());
            $client->revoke($refresh_token);
        }

        self::clear_session_meta($user_id);
    }

    public static function login_message($message)
    {
        $settings = LJNDI_HRMS_Settings::get();

        if (empty($settings['show_login_button']) || empty($settings['client_id'])) {
            return $message;
        }

        // This value only controls the local destination after a successful OAuth flow.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requested_redirect = isset($_REQUEST['redirect_to'])
            ? esc_url_raw(wp_unslash($_REQUEST['redirect_to']))
            : admin_url();
        $redirect_to = wp_validate_redirect($requested_redirect, admin_url());
        $url = add_query_arg(
            array(
                'action'      => 'ljndi_hrms_login',
                'redirect_to' => $redirect_to,
                '_wpnonce'    => wp_create_nonce('ljndi_hrms_login'),
            ),
            admin_url('admin-post.php')
        );

        $button = '<div class="ljndi-hrms-login"><a class="button button-primary button-large" href="' . esc_url($url) . '">'
            . esc_html__('Continue with LJNDI HRMS', 'ljndi-hrms-workflow')
            . '</a><span>' . esc_html__('Use your approved LJNDI work account.', 'ljndi-hrms-workflow') . '</span></div>';

        return $message . $button;
    }

    private static function provision_user(array $claims, array $settings)
    {
        $employee_id = isset($claims['employee_id']) ? sanitize_text_field($claims['employee_id']) : '';
        $email = isset($claims['email']) ? sanitize_email($claims['email']) : '';
        $name = isset($claims['name']) ? sanitize_text_field($claims['name']) : $employee_id;
        $status = isset($claims['status']) ? strtolower((string) $claims['status']) : '';

        if ($employee_id === '' || ! is_email($email) || $status !== 'active') {
            return new WP_Error('ljndi_invalid_employee', __('The HRMS account is missing an active employee identity or valid email address.', 'ljndi-hrms-workflow'));
        }

        $user = get_user_by('email', $email);

        if (! $user) {
            if (empty($settings['auto_create_users'])) {
                return new WP_Error('ljndi_user_missing', __('No matching WordPress account exists and automatic account creation is disabled.', 'ljndi-hrms-workflow'));
            }

            $user_id = wp_insert_user(
                array(
                    'user_login'   => self::unique_username($employee_id, $email),
                    'user_pass'    => wp_generate_password(48, true, true),
                    'user_email'   => $email,
                    'display_name' => $name,
                    'role'         => $settings['default_role'],
                )
            );

            if (is_wp_error($user_id)) {
                return $user_id;
            }

            $user = get_userdata($user_id);
        }

        $linked_employee_id = (string) get_user_meta($user->ID, self::META_EMPLOYEE_ID, true);
        if ($linked_employee_id !== '' && ! hash_equals($linked_employee_id, $employee_id)) {
            return new WP_Error('ljndi_identity_conflict', __('This WordPress account is already linked to another HRMS employee.', 'ljndi-hrms-workflow'));
        }

        update_user_meta($user->ID, self::META_EMPLOYEE_ID, $employee_id);
        self::sync_user_profile($user->ID, $claims, $settings);

        return $user->ID;
    }

    private static function sync_user_profile($user_id, array $claims, array $settings)
    {
        $update = array('ID' => $user_id);

        if (! empty($claims['name'])) {
            $update['display_name'] = sanitize_text_field($claims['name']);
        }

        if (! empty($claims['email']) && is_email($claims['email'])) {
            $update['user_email'] = sanitize_email($claims['email']);
        }

        if (count($update) > 1) {
            wp_update_user($update);
        }

        if (! empty($settings['sync_roles'])) {
            $mapped_role = self::mapped_role($claims, $settings);
            $user = new WP_User($user_id);

            if ($mapped_role && get_role($mapped_role) && ! is_super_admin($user_id)) {
                $user->set_role($mapped_role);
            }
        }
    }

    private static function mapped_role(array $claims, array $settings)
    {
        $map = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $settings['role_mappings']);

        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }
            list($source, $target) = array_map('trim', explode('=', $line, 2));
            $map[strtolower($source)] = sanitize_key($target);
        }

        if (! empty($claims['roles']) && is_array($claims['roles'])) {
            foreach ($claims['roles'] as $role) {
                if (! is_array($role)) {
                    continue;
                }

                $title = isset($role['title']) ? trim((string) $role['title']) : '';
                $title_key = 'role:' . strtolower($title);
                if ($title !== '' && isset($map[$title_key]) && get_role($map[$title_key])) {
                    return $map[$title_key];
                }

                $department_name = '';
                if (! empty($role['department']) && is_array($role['department']) && ! empty($role['department']['name'])) {
                    $department_name = trim((string) $role['department']['name']);
                }

                $department_key = 'department:' . strtolower($department_name);
                if ($department_name !== '' && isset($map[$department_key]) && get_role($map[$department_key])) {
                    return $map[$department_key];
                }
            }
        }

        return get_role($settings['default_role']) ? $settings['default_role'] : 'subscriber';
    }

    private static function store_session($user_id, array $tokens, array $claims)
    {
        $access_token = isset($tokens['access_token']) ? LJNDI_HRMS_Crypto::encrypt($tokens['access_token']) : '';
        $refresh_token = isset($tokens['refresh_token']) ? LJNDI_HRMS_Crypto::encrypt($tokens['refresh_token']) : '';

        if (is_wp_error($access_token) || is_wp_error($refresh_token) || $access_token === '' || $refresh_token === '') {
            self::fail(__('WordPress could not protect the OAuth session tokens.', 'ljndi-hrms-workflow'));
        }

        update_user_meta($user_id, self::META_ACCESS_TOKEN, $access_token);
        update_user_meta($user_id, self::META_REFRESH_TOKEN, $refresh_token);
        update_user_meta($user_id, self::META_EXPIRES_AT, time() + absint(isset($tokens['expires_in']) ? $tokens['expires_in'] : 3600));
        update_user_meta($user_id, self::META_VALIDATED_AT, time());
        update_user_meta($user_id, self::META_CLAIMS, wp_json_encode($claims));
    }

    private static function clear_session_meta($user_id)
    {
        foreach (array(self::META_ACCESS_TOKEN, self::META_REFRESH_TOKEN, self::META_EXPIRES_AT, self::META_VALIDATED_AT, self::META_CLAIMS) as $key) {
            delete_user_meta($user_id, $key);
        }
    }

    private static function end_local_session($user_id, $message)
    {
        self::clear_session_meta($user_id);
        wp_logout();

        if (! wp_doing_ajax() && ! wp_doing_cron() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            wp_safe_redirect(add_query_arg('ljndi_hrms_notice', 'session-ended', wp_login_url()));
            exit;
        }
    }

    private static function unique_username($employee_id, $email)
    {
        $base = sanitize_user(strtolower($employee_id), true);
        if ($base === '') {
            $base = sanitize_user(strstr($email, '@', true), true);
        }
        if ($base === '') {
            $base = 'ljndi-staff';
        }

        $username = $base;
        $suffix = 2;
        while (username_exists($username)) {
            $username = $base . '-' . $suffix;
            $suffix++;
        }

        return $username;
    }

    private static function transaction_key($state)
    {
        return 'ljndi_hrms_txn_' . hash('sha256', $state);
    }

    private static function random_urlsafe($bytes)
    {
        return self::base64url(random_bytes($bytes));
    }

    private static function base64url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function fail($message)
    {
        wp_die(
            '<p>' . esc_html($message) . '</p><p><a href="' . esc_url(wp_login_url()) . '">' . esc_html__('Return to WordPress sign in', 'ljndi-hrms-workflow') . '</a></p>',
            esc_html__('LJNDI HRMS sign-in error', 'ljndi-hrms-workflow'),
            array('response' => 400)
        );
    }
}
