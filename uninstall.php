<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ljndi_hrms_workflow_settings');

global $wpdb;

$meta_keys = array(
    '_ljndi_hrms_employee_id',
    '_ljndi_hrms_access_token',
    '_ljndi_hrms_refresh_token',
    '_ljndi_hrms_expires_at',
    '_ljndi_hrms_validated_at',
    '_ljndi_hrms_claims',
);

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->usermeta, array('meta_key' => $meta_key), array('%s'));
}

$like = $wpdb->esc_like('_transient_ljndi_hrms_') . '%';
$timeout_like = $wpdb->esc_like('_transient_timeout_ljndi_hrms_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $timeout_like));
