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
		delete_option( CRM_Leads_Capture_Settings::LEGACY_OPTION_SETTINGS );
		delete_option( CRM_Leads_Capture_Settings::LEGACY_OPTION_DEFAULT_LIST_ID );
	}

	public function test_plugin_registers_settings_admin_hooks(): void {
		$this->assertSame( 10, has_action( 'admin_menu', array( crm_leads_capture()->settings(), 'register_page' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( crm_leads_capture()->settings(), 'register_settings' ) ) );
	}

	public function test_general_settings_expose_only_material_compatibility(): void {
		global $wp_settings_fields;
		$this->settings->register_settings();
		$fields = $wp_settings_fields['crm-leads-capture-general']['crm_leads_capture_modules_section'];

		$this->assertArrayHasKey( 'crm_leads_capture_free_material_compatibility', $fields );
		$this->assertArrayNotHasKey( 'crm_leads_capture_commercial_profiles', $fields );
		$this->assertCount( 1, $fields );
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

	public function test_active_provider_defaults_to_brevo_when_not_explicitly_configured(): void {
		$this->assertSame( 'brevo', $this->settings->active_provider() );
		$this->assertTrue( $this->settings->free_material_compatibility_enabled() );
	}

	public function test_optional_modules_can_be_disabled_without_changing_provider_configuration(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'rd_station',
				'providers'       => array( 'rd_station' => array( 'api_key' => 'rd-key', 'enabled' => true ) ),
			)
		);

		$sanitized = $this->settings->sanitize_options(
			array(
				'active_provider' => 'rd_station',
				'modules'         => array(),
			)
		);

		$this->assertFalse( $sanitized['modules']['free_material_compatibility'] );
		$this->assertSame( 'rd-key', $sanitized['providers']['rd_station']['api_key'] );

		update_option( CRM_Leads_Capture_Settings::OPTION_SETTINGS, $sanitized );
		$this->assertFalse( $this->settings->free_material_compatibility_enabled() );
	}

	public function test_saving_another_tab_preserves_optional_module_state(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'modules' => array(
					'free_material_compatibility' => false,
				),
			)
		);

		$sanitized = $this->settings->sanitize_options( array( 'success_message' => 'Tudo certo.' ) );

		$this->assertFalse( $sanitized['modules']['free_material_compatibility'] );
	}

	public function test_upgrade_keeps_legacy_brevo_options_readable_without_migration(): void {
		update_option(
			CRM_Leads_Capture_Settings::LEGACY_OPTION_SETTINGS,
			array(
				'api_key'         => 'legacy-api-key',
				'default_list_id' => '654',
			)
		);

		$this->assertSame( 'legacy-api-key', $this->settings->brevo_api_key() );
		$this->assertSame( 654, $this->settings->brevo_default_list_id() );
		$this->assertSame(
			array( 'api_key' => 'legacy-api-key', 'default_list_id' => '654' ),
			get_option( CRM_Leads_Capture_Settings::LEGACY_OPTION_SETTINGS )
		);
	}

	public function test_validates_active_provider_availability_and_credentials(): void {
		$this->assertSame( 'provider_not_configured', $this->settings->provider_configuration_error() );

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array(
					'brevo' => array( 'enabled' => false, 'api_key' => 'brevo-key' ),
				),
			)
		);
		$this->assertSame( 'provider_disabled', $this->settings->provider_configuration_error() );

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'rd_station',
				'providers'       => array(
					'rd_station' => array( 'enabled' => true, 'api_key' => 'rd-key' ),
				),
			)
		);
		$this->assertSame( '', $this->settings->provider_configuration_error() );
		$this->assertTrue( $this->settings->provider_configured( 'rd_station' ) );
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
				'default_delivery_url' => ' https://example.com/obrigado ',
				'providers'       => array(
					'brevo'      => array(
						'enabled'         => '1',
						'api_key'         => ' new-api-key ',
						'default_list_id' => '321abc',
					),
					'rd_station' => array(
						'enabled'                       => '1',
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
		$this->assertTrue( $sanitized['providers']['brevo']['enabled'] );
		$this->assertTrue( $sanitized['providers']['rd_station']['enabled'] );
		$this->assertSame( 'https://example.com/obrigado', $sanitized['default_delivery_url'] );
	}

	public function test_sanitize_options_preserves_other_provider_settings_when_saving_one_tab(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'default_delivery_url' => 'https://example.com/default',
				'providers'       => array(
					'brevo'      => array(
						'enabled'         => true,
						'api_key'         => 'existing-brevo-key',
						'default_list_id' => 123,
					),
					'rd_station' => array(
						'enabled'                       => true,
						'api_key'                       => 'existing-rd-key',
						'default_conversion_identifier' => 'Material antigo',
						'default_tags'                  => 'old',
					),
				),
			)
		);

		$sanitized = $this->settings->sanitize_options(
			array(
				'active_provider' => 'rd_station',
			)
		);

		$this->assertSame( 'rd_station', $sanitized['active_provider'] );
		$this->assertSame( 'https://example.com/default', $sanitized['default_delivery_url'] );
		$this->assertTrue( $sanitized['providers']['brevo']['enabled'] );
		$this->assertSame( 'existing-brevo-key', $sanitized['providers']['brevo']['api_key'] );
		$this->assertSame( 123, $sanitized['providers']['brevo']['default_list_id'] );
		$this->assertTrue( $sanitized['providers']['rd_station']['enabled'] );
		$this->assertSame( 'existing-rd-key', $sanitized['providers']['rd_station']['api_key'] );
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

	public function test_default_delivery_url_uses_configured_value(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'default_delivery_url' => 'https://example.com/default-download',
			)
		);

		$this->assertSame( 'https://example.com/default-download', $this->settings->default_delivery_url() );
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

	public function test_global_provider_copy_does_not_offer_content_overrides(): void {
		ob_start();
		$this->settings->render_provider_section();
		$this->settings->render_active_provider_field();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'único provider', $output );
		$this->assertStringContainsString( 'usam sempre esta configuração global', $output );
		$this->assertStringNotContainsString( 'sobrescrever', $output );
	}

	public function test_render_tabs_marks_current_tab_active(): void {
		$_GET['tab'] = 'messages';

		ob_start();
		$this->settings->render_tabs();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'nav-tab-wrapper', $output );
		$this->assertStringContainsString( 'General', $output );
		$this->assertStringContainsString( 'Messages', $output );
		$this->assertStringContainsString( 'RD Station', $output );
		$this->assertStringContainsString( 'Brevo', $output );
		$this->assertStringContainsString( 'tab=messages', $output );
		$this->assertStringContainsString( 'nav-tab-active', $output );

		unset( $_GET['tab'] );
	}
}
