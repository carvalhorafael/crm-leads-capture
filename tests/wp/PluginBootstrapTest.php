<?php
/**
 * WordPress bootstrap smoke tests.
 *
 * @package CRM_Leads_Capture
 */

class PluginBootstrapTest extends WP_UnitTestCase {
	public function test_plugin_main_file_exists(): void {
		$plugin_file = dirname( __DIR__, 2 ) . '/crm-leads-capture.php';

		$this->assertFileExists( $plugin_file );
	}

	public function test_plugin_bootstrap_defines_expected_constants(): void {
		$this->assertTrue( defined( 'CRM_LEADS_CAPTURE_VERSION' ) );
		$this->assertTrue( defined( 'CRM_LEADS_CAPTURE_FILE' ) );
		$this->assertTrue( defined( 'CRM_LEADS_CAPTURE_DIR' ) );
		$this->assertTrue( function_exists( 'crm_leads_capture' ) );
		$this->assertInstanceOf( CRM_Leads_Capture_Logger::class, crm_leads_capture()->logger() );
		$this->assertInstanceOf( CRM_Leads_Capture_Profile_Registry::class, crm_leads_capture()->capture_profiles() );
		$this->assertInstanceOf( CRM_Leads_Capture_Processor::class, crm_leads_capture()->capture_processor() );
		$this->assertInstanceOf( CRM_Leads_Capture_Frontend::class, crm_leads_capture()->frontend() );
		$this->assertInstanceOf( CRM_Leads_Capture_Profile_Repository::class, crm_leads_capture()->profile_repository() );
		$this->assertTrue( function_exists( 'crm_leads_capture_form_fields' ) );
		$this->assertTrue( function_exists( 'crm_leads_capture_render_message' ) );
	}

	public function test_plugin_registers_textdomain_loader(): void {
		$this->assertSame( 10, has_action( 'init', array( crm_leads_capture(), 'load_textdomain' ) ) );
	}

	public function test_plugin_registers_elementor_action_callback(): void {
		$this->assertSame(
			10,
			has_action( 'elementor_pro/forms/actions/register', array( crm_leads_capture(), 'register_elementor_form_action' ) )
		);
	}

	public function test_plugin_exposes_service_interest_capture(): void {
		$this->assertInstanceOf( CRM_Leads_Capture_Service_Interest_Capture::class, crm_leads_capture()->service_interest_capture() );
		$this->assertTrue( function_exists( 'crm_leads_capture_render_service_interest_message' ) );
		$this->assertTrue( function_exists( 'crm_leads_capture_service_interest_nonce_field' ) );
	}
}
