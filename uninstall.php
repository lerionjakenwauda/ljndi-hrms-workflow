<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('ljndi_hrms_workflow_settings');

$ljndi_hrms_meta_keys = array(
    '_ljndi_hrms_employee_id',
    '_ljndi_hrms_access_token',
    '_ljndi_hrms_refresh_token',
    '_ljndi_hrms_expires_at',
    '_ljndi_hrms_validated_at',
    '_ljndi_hrms_claims',
    '_ljndi_hrms_avatar_url',
);

foreach ($ljndi_hrms_meta_keys as $ljndi_hrms_meta_key) {
    delete_metadata('user', 0, $ljndi_hrms_meta_key, '', true);
}

global $wpdb;

$ljndi_hrms_like = $wpdb->esc_like('_transient_ljndi_hrms_') . '%';
$ljndi_hrms_timeout_like = $wpdb->esc_like('_transient_timeout_ljndi_hrms_') . '%';

// A wildcard delete is necessary to remove short-lived OAuth transactions whose random state is part of the option name.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $ljndi_hrms_like,
        $ljndi_hrms_timeout_like
    )
);
