<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Makes the approved HRMS profile photo available through WordPress core avatars.
 *
 * The image remains hosted by LJNDI HRMS. WordPress stores only the validated HTTPS
 * URL and uses the normal get_avatar() pipeline, so themes and plugins receive the
 * same avatar they would receive from providers such as Google or Gravatar.
 */
final class LJNDI_HRMS_Avatar
{
    const META_EMPLOYEE_ID = '_ljndi_hrms_employee_id';
    const META_CLAIMS      = '_ljndi_hrms_claims';
    const META_AVATAR_URL  = '_ljndi_hrms_avatar_url';

    public static function boot()
    {
        add_action('wp_login', array(__CLASS__, 'sync_on_login'), 20, 2);
        add_filter('pre_get_avatar_data', array(__CLASS__, 'filter_avatar_data'), 10, 2);
    }

    /**
     * Persist the current approved picture claim immediately after OAuth login.
     *
     * @param string  $user_login WordPress username.
     * @param WP_User $user       Authenticated WordPress user.
     */
    public static function sync_on_login($user_login, $user)
    {
        unset($user_login);

        if ($user instanceof WP_User) {
            self::sync_from_claims($user->ID);
        }
    }

    /**
     * Replace the normal Gravatar URL for users linked to LJNDI HRMS.
     *
     * @param array $args        Processed avatar arguments.
     * @param mixed $id_or_email User ID, email, WP_User, WP_Post, or WP_Comment.
     * @return array
     */
    public static function filter_avatar_data($args, $id_or_email)
    {
        $user_id = self::resolve_user_id($id_or_email);

        if (! $user_id || get_user_meta($user_id, self::META_EMPLOYEE_ID, true) === '') {
            return $args;
        }

        $avatar_url = self::validated_avatar_url(
            get_user_meta($user_id, self::META_AVATAR_URL, true)
        );

        if ($avatar_url === '') {
            $avatar_url = self::sync_from_claims($user_id);
        }

        if ($avatar_url !== '') {
            $args['url'] = $avatar_url;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    /**
     * Read the latest OAuth claims and store the approved profile-photo URL.
     *
     * @param int $user_id WordPress user ID.
     * @return string Validated avatar URL or an empty string.
     */
    private static function sync_from_claims($user_id)
    {
        $raw_claims = get_user_meta($user_id, self::META_CLAIMS, true);
        $claims = is_array($raw_claims) ? $raw_claims : json_decode((string) $raw_claims, true);
        $picture = is_array($claims) && isset($claims['picture']) ? $claims['picture'] : '';
        $avatar_url = self::validated_avatar_url($picture);

        if ($avatar_url === '') {
            delete_user_meta($user_id, self::META_AVATAR_URL);
            return '';
        }

        update_user_meta($user_id, self::META_AVATAR_URL, $avatar_url);
        return $avatar_url;
    }

    /**
     * Only use a valid HTTPS image URL received from the identity service.
     *
     * @param mixed $value Potential URL.
     * @return string
     */
    private static function validated_avatar_url($value)
    {
        $url = esc_url_raw(trim((string) $value), array('https'));

        if ($url === '' || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || ! wp_http_validate_url($url)) {
            return '';
        }

        return $url;
    }

    /**
     * Resolve the user represented by WordPress avatar APIs.
     *
     * @param mixed $id_or_email WordPress avatar identity input.
     * @return int
     */
    private static function resolve_user_id($id_or_email)
    {
        if ($id_or_email instanceof WP_User) {
            return (int) $id_or_email->ID;
        }

        if ($id_or_email instanceof WP_Post) {
            return (int) $id_or_email->post_author;
        }

        if ($id_or_email instanceof WP_Comment) {
            if (! empty($id_or_email->user_id)) {
                return (int) $id_or_email->user_id;
            }

            $id_or_email = $id_or_email->comment_author_email;
        }

        if (is_numeric($id_or_email)) {
            return absint($id_or_email);
        }

        if (is_object($id_or_email) && ! empty($id_or_email->user_id)) {
            return absint($id_or_email->user_id);
        }

        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? (int) $user->ID : 0;
        }

        return 0;
    }
}
