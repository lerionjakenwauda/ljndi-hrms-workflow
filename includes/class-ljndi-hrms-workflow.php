<?php

if (! defined('ABSPATH')) {
    exit;
}

final class LJNDI_HRMS_Workflow
{
    private static $instance;

    public static function instance()
    {
        if (! self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate()
    {
        if (! get_option(LJNDI_HRMS_Settings::OPTION)) {
            add_option(LJNDI_HRMS_Settings::OPTION, LJNDI_HRMS_Settings::defaults());
        }
    }

    private function __construct()
    {
        add_action('admin_init', array('LJNDI_HRMS_Settings', 'register'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('login_enqueue_scripts', array($this, 'login_assets'));
        add_filter('login_message', array($this, 'login_message'));

        add_action('admin_post_ljndi_hrms_login', array('LJNDI_HRMS_Authentication', 'begin_login'));
        add_action('admin_post_nopriv_ljndi_hrms_login', array('LJNDI_HRMS_Authentication', 'begin_login'));
        add_action('admin_post_ljndi_hrms_callback', array('LJNDI_HRMS_Authentication', 'handle_callback'));
        add_action('admin_post_nopriv_ljndi_hrms_callback', array('LJNDI_HRMS_Authentication', 'handle_callback'));

        add_action('init', array('LJNDI_HRMS_Authentication', 'maybe_validate_current_session'), 5);
        add_action('wp_logout', array('LJNDI_HRMS_Authentication', 'revoke_on_logout'), 10, 1);

        add_filter('plugin_action_links_' . plugin_basename(LJNDI_HRMS_WORKFLOW_FILE), array($this, 'action_links'));
        add_filter('plugin_row_meta', array($this, 'row_meta'), 10, 2);
        add_action('admin_notices', array($this, 'configuration_notice'));
    }

    public function admin_menu()
    {
        add_options_page(
            __('LJNDI HRMS Workflow', 'ljndi-hrms-workflow'),
            __('LJNDI HRMS', 'ljndi-hrms-workflow'),
            'manage_options',
            'ljndi-hrms-workflow',
            array('LJNDI_HRMS_Settings', 'render')
        );
    }

    public function admin_assets($hook_suffix)
    {
        if ($hook_suffix !== 'settings_page_ljndi-hrms-workflow') {
            return;
        }

        wp_enqueue_style(
            'ljndi-hrms-workflow-admin',
            LJNDI_HRMS_WORKFLOW_URL . 'assets/css/admin.css',
            array(),
            LJNDI_HRMS_WORKFLOW_VERSION
        );

        wp_add_inline_script(
            'jquery-core',
            "document.addEventListener('click',function(event){var button=event.target.closest('[data-ljndi-copy]');if(!button){return;}navigator.clipboard.writeText(button.getAttribute('data-ljndi-copy')).then(function(){var old=button.textContent;button.textContent='Copied';setTimeout(function(){button.textContent=old;},1600);});});"
        );
    }

    public function login_assets()
    {
        wp_enqueue_style(
            'ljndi-hrms-workflow-login',
            LJNDI_HRMS_WORKFLOW_URL . 'assets/css/login.css',
            array(),
            LJNDI_HRMS_WORKFLOW_VERSION
        );
    }

    public function login_message($message)
    {
        // This public query value is only a fixed notice code created by this plugin.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $notice_code = isset($_GET['ljndi_hrms_notice']) ? sanitize_key(wp_unslash($_GET['ljndi_hrms_notice'])) : '';
        $notices = array(
            'session-ended' => __('Your LJNDI HRMS session ended. Sign in again to continue.', 'ljndi-hrms-workflow'),
        );

        if (isset($notices[$notice_code])) {
            $message .= '<div id="login_error">' . esc_html($notices[$notice_code]) . '</div>';
        }

        return LJNDI_HRMS_Authentication::login_message($message);
    }

    public function action_links($links)
    {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('options-general.php?page=ljndi-hrms-workflow')) . '">' . esc_html__('Settings', 'ljndi-hrms-workflow') . '</a>'
        );

        return $links;
    }

    public function row_meta($links, $file)
    {
        if ($file !== plugin_basename(LJNDI_HRMS_WORKFLOW_FILE)) {
            return $links;
        }

        $links[] = '<a href="https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow" target="_blank" rel="noopener noreferrer">' . esc_html__('Documentation', 'ljndi-hrms-workflow') . '</a>';
        $links[] = '<a href="https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow" target="_blank" rel="noopener noreferrer">' . esc_html__('Support', 'ljndi-hrms-workflow') . '</a>';

        return $links;
    }

    public function configuration_notice()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = LJNDI_HRMS_Settings::get();
        if (! empty($settings['client_id']) && ! empty($settings['client_secret'])) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'settings_page_ljndi-hrms-workflow') {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        printf(
            wp_kses(
                /* translators: %s: URL of the LJNDI HRMS Workflow settings page. */
                __('LJNDI HRMS Workflow is active but not connected. <a href="%s">Add this website’s OAuth credentials</a>.', 'ljndi-hrms-workflow'),
                array('a' => array('href' => array()))
            ),
            esc_url(admin_url('options-general.php?page=ljndi-hrms-workflow'))
        );
        echo '</p></div>';
    }
}
