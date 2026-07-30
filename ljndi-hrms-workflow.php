<?php
/**
 * Plugin Name: LJNDI HRMS Workflow
 * Plugin URI: https://lerionjakenwauda.com/plugins/ljndi-hrms-workflow
 * Description: Free OAuth 2.1 PKCE sign-in and workforce access integration for LJNDI HRMS.
 * Version: 0.1.1
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Lerion Jake Nwauda Digital Innovations Ltd
 * Author URI: https://lerionjakenwauda.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ljndi-hrms-workflow
 */

if (! defined('ABSPATH')) {
    exit;
}

define('LJNDI_HRMS_WORKFLOW_VERSION', '0.1.1');
define('LJNDI_HRMS_WORKFLOW_FILE', __FILE__);
define('LJNDI_HRMS_WORKFLOW_DIR', plugin_dir_path(__FILE__));
define('LJNDI_HRMS_WORKFLOW_URL', plugin_dir_url(__FILE__));

require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-crypto.php';
require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-oauth-client.php';
require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-settings.php';
require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-authentication.php';
require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-avatar.php';
require_once LJNDI_HRMS_WORKFLOW_DIR . 'includes/class-ljndi-hrms-workflow.php';

register_activation_hook(__FILE__, array('LJNDI_HRMS_Workflow', 'activate'));

add_action('plugins_loaded', static function () {
    LJNDI_HRMS_Workflow::instance();
    LJNDI_HRMS_Avatar::boot();
});
