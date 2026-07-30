<?php

if (! defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_Crypto
{
    public static function encrypt($plain_text)
    {
        $plain_text = (string) $plain_text;

        if ($plain_text === '') {
            return '';
        }

        if (! function_exists('openssl_encrypt')) {
            return new WP_Error('ljndi_crypto_unavailable', __('OpenSSL is required to protect OAuth credentials.', 'ljndi-hrms-workflow'));
        }

        try {
            $iv = random_bytes(12);
        } catch (Exception $exception) {
            return new WP_Error('ljndi_crypto_random_failed', __('Unable to create secure encryption material.', 'ljndi-hrms-workflow'));
        }

        $tag = '';
        $cipher_text = openssl_encrypt(
            $plain_text,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'ljndi-hrms-workflow'
        );

        if ($cipher_text === false) {
            return new WP_Error('ljndi_crypto_failed', __('Unable to encrypt the OAuth credential.', 'ljndi-hrms-workflow'));
        }

        return 'enc:v1:' . self::base64url_encode($iv . $tag . $cipher_text);
    }

    public static function decrypt($payload)
    {
        $payload = (string) $payload;

        if ($payload === '') {
            return '';
        }

        if (strpos($payload, 'enc:v1:') !== 0) {
            return $payload;
        }

        if (! function_exists('openssl_decrypt')) {
            return '';
        }

        $decoded = self::base64url_decode(substr($payload, 7));

        if ($decoded === false || strlen($decoded) < 29) {
            return '';
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $cipher_text = substr($decoded, 28);

        $plain_text = openssl_decrypt(
            $cipher_text,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'ljndi-hrms-workflow'
        );

        return is_string($plain_text) ? $plain_text : '';
    }

    private static function key()
    {
        $material = '';

        foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY') as $constant) {
            if (defined($constant)) {
                $material .= constant($constant);
            }
        }

        return hash('sha256', $material . home_url('/'), true);
    }

    private static function base64url_encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64url_decode($value)
    {
        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
