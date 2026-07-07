<?php
/**
 * Settings integration tests.
 *
 * @package CRM_Leads_Capture
 */

class SettingsTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Settings $settings;

	public function set_up(): void {
		parent::set_up();

		$this->settings = new CRM_Leads_Capture_Settings();
		delete_option( CRM_Leads_Capture_Settings::OPTION_SETTINGS );
		delete_option( CRM_Leads_Capture_Settings::OPTION_DEFAULT_LIST_ID );
	}

	public function test_plugin_registers_settings_admin_hooks(): void {
		$this->assertSame( 10, has_action( 'admin_menu', array( crm_leads_capture()->settings(), 'register_page' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( crm_leads_capture()->settings(), 'register_settings' ) ) );
	}

	public function test_reads_api_key_and_default_list_id_from_grouped_option(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'providers' => array(
					'brevo' => array(
						'api_key'         => 'stored-api-key',
						'default_list_id' => '789',
					),
				),
			)
		);

		$this->assertSame( 'stored-api-key', $this->settings->api_key() );
		$this->assertSame( 789, $this->settings->default_list_id() );
		$this->assertTrue( $this->settings->has_api_key() );
	}

	public function test_default_list_id_falls_back_to_legacy_option(): void {
		update_option( CRM_Leads_Capture_Settings::OPTION_DEFAULT_LIST_ID, '456' );

		$this->assertSame( 456, $this->settings->default_list_id() );
	}

	public function test_sanitize_options_preserves_existing_api_key_when_empty(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'providers' => array(
					'brevo' => array(
						'api_key'         => 'existing-api-key',
						'default_list_id' => 123,
					),
				),
			)
		);

		$sanitized = $this->settings->sanitize_options(
			array(
				'providers' => array(
					'brevo' => array(
						'api_key'         => '',
						'default_list_id' => '-999',
					),
				),
			)
		);

		$this->assertSame( 'existing-api-key', $sanitized['providers']['brevo']['api_key'] );
		$this->assertSame( 0, $sanitized['providers']['brevo']['default_list_id'] );
	}

	public function test_sanitize_options_accepts_new_api_key_and_absints_list_id(): void {
		$sanitized = $this->settings->sanitize_options(
			array(
				'active_provider' => 'rd_station',
				'providers'       => array(
					'brevo'      => array(
						'api_key'         => ' new-api-key ',
						'default_list_id' => '321abc',
					),
					'rd_station' => array(
						'api_key'                       => ' rd-key ',
						'default_conversion_identifier' => ' Material Baixado ',
						'default_tags'                  => ' material, crm ',
					),
				),
			)
		);

		$this->assertSame( 'rd_station', $sanitized['active_provider'] );
		$this->assertSame( 'new-api-key', $sanitized['providers']['brevo']['api_key'] );
		$this->assertSame( 321, $sanitized['providers']['brevo']['default_list_id'] );
		$this->assertSame( 'rd-key', $sanitized['providers']['rd_station']['api_key'] );
		$this->assertSame( 'Material Baixado', $sanitized['providers']['rd_station']['default_conversion_identifier'] );
		$this->assertSame( 'material, crm', $sanitized['providers']['rd_station']['default_tags'] );
	}

	public function test_sanitize_options_accepts_custom_error_messages(): void {
		$sanitized = $this->settings->sanitize_options(
			array(
				'api_key'         => '',
				'default_list_id' => '0',
				'success_message' => '<strong>Você será redirecionado.</strong>',
				'error_messages'  => array(
					'invalid_lead' => " Revise o e-mail informado.\nTente novamente. ",
					'brevo_error'  => '<strong>Tente novamente mais tarde.</strong>',
					'unknown_code'  => 'Ignored.',
				),
			)
		);

		$this->assertSame( "Revise o e-mail informado.\nTente novamente.", $sanitized['error_messages']['invalid_lead'] );
		$this->assertSame( 'Tente novamente mais tarde.', $sanitized['error_messages']['brevo_error'] );
		$this->assertSame( 'Você será redirecionado.', $sanitized['success_message'] );
		$this->assertArrayNotHasKey( 'unknown_code', $sanitized['error_messages'] );
	}

	public function test_error_message_uses_custom_text_and_falls_back_to_default(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'error_messages' => array(
					'invalid_lead' => 'Revise o e-mail informado.',
				),
			)
		);

		$this->assertSame( 'Revise o e-mail informado.', $this->settings->error_message( 'invalid_lead' ) );
		$this->assertSame( $this->settings->error_message( 'brevo_error' ), $this->settings->error_message( 'unknown_code' ) );
	}

	public function test_success_message_uses_custom_text_and_falls_back_to_default(): void {
		$this->assertStringContainsString( 'Você será redirecionado', $this->settings->success_message() );

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'success_message' => 'Tudo certo. Redirecionando para o material.',
			)
		);

		$this->assertSame( 'Tudo certo. Redirecionando para o material.', $this->settings->success_message() );
	}

	public function test_status_panel_does_not_render_api_key_value(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'providers' => array(
					'brevo' => array(
						'api_key'         => 'secret-api-key',
						'default_list_id' => 123,
					),
				),
			)
		);

		ob_start();
		$this->settings->render_status_panel();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Status da configuração', $output );
		$this->assertStringContainsString( 'Configurada', $output );
		$this->assertStringNotContainsString( 'secret-api-key', $output );
	}
}
