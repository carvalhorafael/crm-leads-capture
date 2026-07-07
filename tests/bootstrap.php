<?php
/**
 * Bootstrap file for WordPress integration tests.
 *
 * @package CRM_Leads_Capture
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found. Run `composer install:wp-tests` first." . PHP_EOL;
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin before WordPress finishes bootstrapping.
 */
function crm_leads_capture_manually_load_plugin(): void {
	$plugin_file = dirname( __DIR__ ) . '/crm-leads-capture.php';

	if ( file_exists( $plugin_file ) ) {
		require_once $plugin_file;
	}
}

tests_add_filter( 'muplugins_loaded', 'crm_leads_capture_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
