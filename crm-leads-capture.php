<?php
/**
 * Plugin Name: CRM Leads Capture
 * Description: Centraliza capturas de leads WordPress e envio para CRMs.
 * Version: 0.3.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Rafael Carvalho
 * Plugin URI: https://github.com/carvalhorafael/crm-leads-capture
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/carvalhorafael/crm-leads-capture
 * Text Domain: crm-leads-capture
 * Domain Path: /languages
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRM_LEADS_CAPTURE_VERSION', '0.3.0' );
define( 'CRM_LEADS_CAPTURE_FILE', __FILE__ );
define( 'CRM_LEADS_CAPTURE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CRM_LEADS_CAPTURE_BASENAME', plugin_basename( __FILE__ ) );

require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-result.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-logger.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-settings.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-lead-payload.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-provider-interface.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-provider-registry.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-brevo-client.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-brevo-provider.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-rd-station-client.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-rd-station-provider.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-github-updater.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-free-material-capture.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/integrations/class-elementor-form-mapper.php';
require_once CRM_LEADS_CAPTURE_DIR . 'includes/class-plugin.php';

/**
 * Returns the plugin singleton.
 */
function crm_leads_capture(): CRM_Leads_Capture_Plugin {
	return CRM_Leads_Capture_Plugin::instance();
}

/**
 * Returns the current public error message for the free material form.
 */
function crm_leads_capture_get_free_material_error_message(): string {
	return crm_leads_capture()->free_material_capture()->current_error_message();
}

/**
 * Renders the current public error message for the free material form.
 */
function crm_leads_capture_render_free_material_error_message(): void {
	crm_leads_capture()->free_material_capture()->render_error_message();
}

if ( ! function_exists( 'brevo_leads_capture' ) ) {
	/**
	 * Legacy plugin singleton accessor kept for temporary migration compatibility.
	 */
	function brevo_leads_capture(): CRM_Leads_Capture_Plugin {
		return crm_leads_capture();
	}
}

crm_leads_capture()->boot();
