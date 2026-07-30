<?php

if (!defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_Updater
{
    const ENDPOINT = 'https://lerionjakenwauda.com/plugins/api/v1/plugins/ljndi-hrms-workflow';
    const CACHE_KEY = 'ljndi_hrms_workflow_update_metadata';

    public static function boot()
    {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_update'));
        add_filter('plugins_api', array(__CLASS__, 'plugin_information'), 20, 3);
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_cache'), 10, 2);
    }

    public static function check_for_update($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $metadata = self::metadata();
        if (is_wp_error($metadata) || empty($metadata['version']) || empty($metadata['download_url'])) {
            return $transient;
        }

        $pluginBasename = plugin_basename(LJNDI_HRMS_WORKFLOW_FILE);
        $installed = isset($transient->checked[$pluginBasename]) ? (string) $transient->checked[$pluginBasename] : LJNDI_HRMS_WORKFLOW_VERSION;
        if (version_compare((string) $metadata['version'], $installed, '<=')) {
            return $transient;
        }

        $transient->response[$pluginBasename] = (object) array(
            'id'           => (string) ($metadata['homepage'] ?? self::ENDPOINT),
            'slug'         => 'ljndi-hrms-workflow',
            'plugin'       => $pluginBasename,
            'new_version'  => (string) $metadata['version'],
            'url'          => (string) ($metadata['homepage'] ?? 'https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow'),
            'package'      => (string) $metadata['download_url'],
            'icons'        => !empty($metadata['icon_url']) ? array('2x' => $metadata['icon_url'], '1x' => $metadata['icon_url']) : array(),
            'tested'       => (string) ($metadata['tested'] ?? ''),
            'requires_php' => (string) ($metadata['requires_php'] ?? ''),
        );

        return $transient;
    }

    public static function plugin_information($result, $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'ljndi-hrms-workflow') {
            return $result;
        }

        $metadata = self::metadata();
        if (is_wp_error($metadata)) {
            return $result;
        }

        return (object) array(
            'name'          => (string) ($metadata['name'] ?? 'LJNDI HRMS Workflow'),
            'slug'          => 'ljndi-hrms-workflow',
            'version'       => (string) ($metadata['version'] ?? LJNDI_HRMS_WORKFLOW_VERSION),
            'author'        => '<a href="' . esc_url((string) ($metadata['author_url'] ?? 'https://lerionjakenwauda.com')) . '">' . esc_html((string) ($metadata['author'] ?? 'Lerion Jake Nwauda Digital Innovations Ltd')) . '</a>',
            'homepage'      => (string) ($metadata['homepage'] ?? 'https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow'),
            'requires'      => (string) ($metadata['requires'] ?? ''),
            'tested'        => (string) ($metadata['tested'] ?? ''),
            'requires_php'  => (string) ($metadata['requires_php'] ?? ''),
            'download_link' => (string) ($metadata['download_url'] ?? ''),
            'sections'      => is_array($metadata['sections'] ?? null) ? $metadata['sections'] : array(),
            'icons'         => !empty($metadata['icon_url']) ? array('2x' => $metadata['icon_url'], '1x' => $metadata['icon_url']) : array(),
            'last_updated'  => (string) ($metadata['updated_at'] ?? ''),
        );
    }

    public static function clear_cache($upgrader, $options)
    {
        if (($options['action'] ?? '') !== 'update' || ($options['type'] ?? '') !== 'plugin') {
            return;
        }

        $plugins = isset($options['plugins']) && is_array($options['plugins']) ? $options['plugins'] : array();
        if (in_array(plugin_basename(LJNDI_HRMS_WORKFLOW_FILE), $plugins, true)) {
            delete_site_transient(self::CACHE_KEY);
        }
    }

    private static function metadata()
    {
        $cached = get_site_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::ENDPOINT, array(
            'timeout'     => 12,
            'redirection' => 2,
            'headers'     => array('Accept' => 'application/json'),
            'user-agent'  => 'LJNDI-HRMS-Workflow/' . LJNDI_HRMS_WORKFLOW_VERSION . '; ' . home_url('/'),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || !is_array($body) || empty($body['slug']) || $body['slug'] !== 'ljndi-hrms-workflow') {
            return new WP_Error('ljndi_update_metadata_invalid', __('The LJNDI plugin update service returned invalid metadata.', 'ljndi-hrms-workflow'));
        }

        set_site_transient(self::CACHE_KEY, $body, 6 * HOUR_IN_SECONDS);
        return $body;
    }
}
