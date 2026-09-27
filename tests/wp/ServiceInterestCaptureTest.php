<?php
/**
 * Service interest capture integration tests.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Service_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $last_payload = null;

	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $last_context = null;

	public function id(): string {
		return 'brevo';
	}

	public function label(): string {
		return 'Brevo';
	}

	public function settings_fields(): array {
		return array();
	}

	public function material_fields(): array {
		return array();
	}

	public function sanitize_settings( array $input ): array {
		return $input;
	}

	public function sanitize_material_meta( array $input ): array {
		return $input;
	}

	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result {
		$this->last_payload = $payload;
		$this->last_context = $context;

		return CRM_Leads_Capture_Result::success( 201, 'Created.' );
	}

	public function error_codes(): array {
		return array();
	}
}

class ServiceInterestCaptureTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Service_Test_Provider $provider;

	private CRM_Leads_Capture_Service_Interest_Capture $capture;

	public function set_up(): void {
		parent::set_up();

		$this->provider = new CRM_Leads_Capture_Service_Test_Provider();
		$this->capture  = new CRM_Leads_Capture_Service_Interest_Capture(
			crm_leads_capture()->settings(),
			crm_leads_capture()->providers(),
			fn(): CRM_Leads_Capture_Service_Test_Provider => $this->provider
		);
		$this->capture->register_post_type();

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array(
					'brevo' => array(
						'enabled'         => true,
						'default_list_id' => 321,
					),
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( CRM_Leads_Capture_Settings::OPTION_SETTINGS );
		parent::tear_down();
	}

	public function test_plugin_registers_service_interest_hooks(): void {
		$this->assertSame(
			10,
			has_action(
				'admin_post_nopriv_' . CRM_Leads_Capture_Service_Interest_Capture::ACTION,
				array( crm_leads_capture()->service_interest_capture(), 'handle_request' )
			)
		);
		$this->assertTrue( post_type_exists( CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );
	}

	public function test_processes_and_stores_valid_service_interest(): void {
		$result = $this->capture->process_submission( $this->valid_request() );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'coo_as_a_service', $this->provider->last_payload['source'] );
		$this->assertSame( 'COO as a Service', $this->provider->last_payload['material'] );
		$this->assertSame( 321, $this->provider->last_context['list_id'] );

		$lead_id = $result->data()['lead_id'];
		$this->assertGreaterThan( 0, $lead_id );
		$this->assertSame( 'Empresa Teste', get_post_meta( $lead_id, '_crm_service_interest_company', true ) );
		$this->assertSame( 'Aprovo decisões demais.', get_post_meta( $lead_id, '_crm_service_interest_challenge', true ) );
		$this->assertSame( 'sent', get_post_meta( $lead_id, '_crm_service_interest_status', true ) );
		$this->assertNotSame( '', get_post_meta( $lead_id, '_crm_service_interest_consent_at', true ) );
	}

	public function test_rejects_missing_consent_without_storing_or_sending(): void {
		$result = $this->capture->process_submission( $this->valid_request( array( 'consent' => '' ) ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_lead', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
		$this->assertSame( 0, wp_count_posts( CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE )->private );
	}

	public function test_rest_success_returns_message_without_redirect(): void {
		$request = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Service_Interest_Capture::REST_NAMESPACE . CRM_Leads_Capture_Service_Interest_Capture::REST_ROUTE );
		foreach ( $this->valid_request() as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertArrayNotHasKey( 'redirect_url', $data );
		$this->assertStringContainsString( 'analisar pessoalmente', $data['message'] );
	}

	public function test_rest_provider_configuration_error_uses_service_specific_copy(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array(
					'brevo' => array(
						'enabled'         => true,
						'default_list_id' => 0,
					),
				),
			)
		);

		$request = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Service_Interest_Capture::REST_NAMESPACE . CRM_Leads_Capture_Service_Interest_Capture::REST_ROUTE );
		foreach ( $this->valid_request() as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertSame( 'missing_list', $data['code'] );
		$this->assertStringNotContainsString( 'material', $data['message'] );
		$this->assertStringContainsString( 'enviar seus dados', $data['message'] );
	}

	public function test_current_message_supports_successful_server_fallback(): void {
		$_GET['crm_leads_capture'] = 'success';
		$_GET['capture_type']      = 'service_interest';

		$message = $this->capture->current_message();
		$markup  = $this->capture->render_message_shortcode();

		$this->assertSame( 'success', $message['tone'] );
		$this->assertStringContainsString( 'data-feedback-tone="success"', $markup );
		$this->assertStringContainsString( 'analisar pessoalmente', $markup );

		unset( $_GET['crm_leads_capture'], $_GET['capture_type'] );
	}

	/**
	 * @param array<string, mixed> $overrides Request overrides.
	 * @return array<string, mixed>
	 */
	private function valid_request( array $overrides = array() ): array {
		return array_merge(
			array(
				CRM_Leads_Capture_Service_Interest_Capture::NONCE_FIELD => wp_create_nonce( CRM_Leads_Capture_Service_Interest_Capture::NONCE_ACTION ),
				CRM_Leads_Capture_Service_Interest_Capture::HONEYPOT_FIELD => '',
				'name'         => 'Rafael Carvalho',
				'email'        => 'rafael@example.com',
				'company'      => 'Empresa Teste',
				'role'         => 'founder',
				'company_url'  => 'https://example.com',
				'whatsapp'     => '+55 21 99999-9999',
				'challenge'    => 'Aprovo decisões demais.',
				'consent'      => '1',
				'utm_source'   => 'linkedin',
				'utm_campaign' => 'coo-service',
			),
			$overrides
		);
	}
}
