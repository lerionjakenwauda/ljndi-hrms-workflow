<?php

if (! defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_OAuth_Client
{
    private $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function authorization_url($state, $code_challenge, $nonce)
    {
        $endpoints = $this->endpoints();

        if (is_wp_error($endpoints)) {
            return $endpoints;
        }

        return add_query_arg(
            array(
                'response_type'         => 'code',
                'client_id'             => $this->settings['client_id'],
                'redirect_uri'          => LJNDI_HRMS_Settings::callback_url(),
                'scope'                 => $this->settings['scopes'],
                'state'                 => $state,
                'nonce'                 => $nonce,
                'code_challenge'        => $code_challenge,
                'code_challenge_method' => 'S256',
            ),
            $endpoints['authorization_endpoint']
        );
    }

    public function exchange_code($code, $code_verifier)
    {
        $endpoints = $this->endpoints();

        if (is_wp_error($endpoints)) {
            return $endpoints;
        }

        return $this->post_token(
            $endpoints['token_endpoint'],
            array(
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => LJNDI_HRMS_Settings::callback_url(),
                'code_verifier' => $code_verifier,
            )
        );
    }

    public function refresh($refresh_token)
    {
        $endpoints = $this->endpoints();

        if (is_wp_error($endpoints)) {
            return $endpoints;
        }

        return $this->post_token(
            $endpoints['token_endpoint'],
            array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            )
        );
    }

    public function userinfo($access_token)
    {
        $endpoints = $this->endpoints();

        if (is_wp_error($endpoints)) {
            return $endpoints;
        }

        $response = wp_remote_get(
            $endpoints['userinfo_endpoint'],
            array(
                'timeout'     => 15,
                'redirection' => 0,
                'headers'     => array(
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'user-agent'  => $this->user_agent(),
            )
        );

        return $this->decode_response($response, __('The identity service could not validate this employee session.', 'ljndi-hrms-workflow'));
    }

    public function revoke($token)
    {
        if (! $token) {
            return true;
        }

        $endpoints = $this->endpoints();

        if (is_wp_error($endpoints)) {
            return $endpoints;
        }

        $response = wp_remote_post(
            $endpoints['revocation_endpoint'],
            array(
                'timeout'     => 15,
                'redirection' => 0,
                'headers'     => array('Accept' => 'application/json'),
                'body'        => array('token' => $token),
                'user-agent'  => $this->user_agent(),
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return wp_remote_retrieve_response_code($response) < 400;
    }

    public function endpoints($force = false)
    {
        $issuer = untrailingslashit($this->settings['issuer']);
        $cache_key = 'ljndi_hrms_discovery_' . md5($issuer);

        if (! $force) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get(
            $issuer . '/.well-known/oauth-authorization-server',
            array(
                'timeout'     => 15,
                'redirection' => 0,
                'headers'     => array('Accept' => 'application/json'),
                'user-agent'  => $this->user_agent(),
            )
        );

        $body = $this->decode_response($response, __('Unable to discover the LJNDI HRMS OAuth endpoints.', 'ljndi-hrms-workflow'));

        if (is_wp_error($body)) {
            return $body;
        }

        $required = array('authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'revocation_endpoint');
        foreach ($required as $key) {
            if (empty($body[$key]) || strpos($body[$key], $issuer . '/') !== 0) {
                return new WP_Error('ljndi_invalid_discovery', __('The identity service returned invalid OAuth endpoint metadata.', 'ljndi-hrms-workflow'));
            }
        }

        set_transient($cache_key, $body, HOUR_IN_SECONDS);

        return $body;
    }

    private function post_token($endpoint, array $body)
    {
        $body['client_id'] = $this->settings['client_id'];
        $client_secret = LJNDI_HRMS_Crypto::decrypt($this->settings['client_secret']);

        if ($client_secret !== '') {
            $body['client_secret'] = $client_secret;
        }

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout'     => 20,
                'redirection' => 0,
                'headers'     => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'        => $body,
                'user-agent'  => $this->user_agent(),
            )
        );

        return $this->decode_response($response, __('The identity service could not complete the OAuth token request.', 'ljndi-hrms-workflow'));
    }

    private function decode_response($response, $fallback_message)
    {
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (! is_array($decoded)) {
            return new WP_Error('ljndi_invalid_json', $fallback_message);
        }

        if ($status < 200 || $status >= 300) {
            $message = ! empty($decoded['error_description']) ? $decoded['error_description'] : $fallback_message;
            return new WP_Error(! empty($decoded['error']) ? sanitize_key($decoded['error']) : 'ljndi_oauth_error', sanitize_text_field($message));
        }

        return $decoded;
    }

    private function user_agent()
    {
        return 'LJNDI-HRMS-Workflow/' . LJNDI_HRMS_WORKFLOW_VERSION . '; ' . home_url('/');
    }
}
