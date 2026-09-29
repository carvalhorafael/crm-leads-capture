<?php
/**
 * Commercial capture profile integration tests.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Service_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
	/** @var array<string, mixed>|null */
	public ?array $last_payload = null;

	/** @var array<string, mixed>|null */
	public ?array $last_context = null;

	public bool $should_succeed = true;

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

		return $this->should_succeed
			? CRM_Leads_Capture_Result::success( 201, 'Created.' )
			: CRM_Leads_Capture_Result::failure( 503, 'Remote details must not be exposed.' );
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
			fn(): CRM_Leads_Capture_Service_Test_Provider => $this->provider,
			crm_leads_capture()->logger(),
			crm_leads_capture()->capture_profiles()
		);
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array(
					'brevo' => array( 'enabled' => true, 'default_list_id' => 321 ),
				),
			)
		);
	}

	public function tear_down(): void {
		delete_option( CRM_Leads_Capture_Settings::OPTION_SETTINGS );
		parent::tear_down();
	}

	public function test_plugin_keeps_legacy_transport_without_registering_post_type(): void {
		$adapter = crm_leads_capture()->service_interest_capture();
		$this->assertSame( 10, has_action( 'admin_post_nopriv_' . CRM_Leads_Capture_Service_Interest_Capture::ACTION, array( $adapter, 'handle_request' ) ) );
		$this->assertFalse( post_type_exists( CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );
		$this->assertFalse( has_action( 'init', array( $adapter, 'register_post_type' ) ) );
	}

	public function test_builtin_commercial_profiles_are_available_with_independent_messages(): void {
		$coo     = crm_leads_capture()->capture_profiles()->resolve( CRM_Leads_Capture_Profile_Defaults::COO_SLUG );
		$speaker = crm_leads_capture()->capture_profiles()->resolve( CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG );

		$this->assertNotNull( $coo );
		$this->assertNotNull( $speaker );
		$this->assertArrayHasKey( 'challenge', $coo->fields() );
		$this->assertArrayHasKey( 'event_date', $speaker->fields() );
		$this->assertNotSame( $coo->success_behavior()['message'], $speaker->success_behavior()['message'] );
	}

	public function test_legacy_coo_form_uses_canonical_pipeline_without_local_persistence(): void {
		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );
		$result = $this->capture->process_submission( $this->valid_coo_request(), 'https://example.com/coo' );
		$after  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( $before, $after );
		$this->assertSame( CRM_Leads_Capture_Profile_Defaults::COO_SLUG, $this->provider->last_payload['profile_slug'] );
		$this->assertSame( 'Rafael Carvalho', $this->provider->last_payload['lead']['name'] );
		$this->assertSame( '+5521999999999', $this->provider->last_payload['lead']['whatsapp'] );
		$this->assertSame( 'Empresa Teste', $this->provider->last_payload['custom_fields']['company'] );
		$this->assertSame( 'Aprovo decisões demais.', $this->provider->last_payload['custom_fields']['challenge'] );
		$this->assertTrue( $this->provider->last_payload['consent']['consent'] );
		$this->assertSame( 'https://example.com/coo', $this->provider->last_payload['context']['page_url'] );
		$this->assertSame( 'linkedin', $this->provider->last_payload['tracking']['utm_source'] );
		$this->assertSame( 'COMPANY', $this->provider->last_context['attribute_map']['custom_fields.company'] );
		$this->assertArrayNotHasKey( 'lead_id', $result->data() );
	}

	public function test_rejects_invalid_coo_fields_without_sending_or_storing(): void {
		$result = $this->capture->process_submission( $this->valid_coo_request( array( 'consent' => '' ) ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_lead', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
		$this->assertFalse( post_type_exists( CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );
	}

	public function test_provider_failure_returns_controlled_message_and_keeps_rest_on_page(): void {
		$this->provider->should_succeed = false;
		$request = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Service_Interest_Capture::REST_NAMESPACE . CRM_Leads_Capture_Service_Interest_Capture::REST_ROUTE );
		foreach ( $this->valid_coo_request() as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertSame( 'provider_error', $data['code'] );
		$this->assertStringNotContainsString( 'Remote details', $data['message'] );
		$this->assertArrayNotHasKey( 'redirect_url', $data );
	}

	public function test_speaker_profile_sends_every_required_field_without_storage(): void {
		$profiles = new CRM_Leads_Capture_Profile_Registry();
		$profiles->register( ( new CRM_Leads_Capture_Profile_Defaults( crm_leads_capture()->settings() ) )->speaker_profile() );
		$providers = new CRM_Leads_Capture_Provider_Registry();
		$providers->register( $this->provider );
		$processor = new CRM_Leads_Capture_Processor(
			$profiles,
			$providers,
			static fn(): string => 'brevo',
			static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action )
		);

		$result = $processor->process(
			CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG,
			$this->valid_speaker_request(),
			array( 'page_url' => 'https://example.com/palestras' )
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Summit 2027', $this->provider->last_payload['custom_fields']['event_name'] );
		$this->assertSame( 500, $this->provider->last_payload['custom_fields']['audience_size'] );
		$this->assertSame( '2027-05-20', $this->provider->last_payload['custom_fields']['event_date'] );
		$this->assertSame( 'hibrido', $this->provider->last_payload['custom_fields']['format'] );
		$this->assertSame( 'https://example.com/palestras', $this->provider->last_payload['context']['page_url'] );
		$this->assertFalse( post_type_exists( CRM_Leads_Capture_Service_Interest_Capture::POST_TYPE ) );
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

	/** @param array<string, mixed> $overrides @return array<string, mixed> */
	private function valid_coo_request( array $overrides = array() ): array {
		return array_merge(
			array(
				CRM_Leads_Capture_Service_Interest_Capture::NONCE_FIELD => wp_create_nonce( CRM_Leads_Capture_Service_Interest_Capture::NONCE_ACTION ),
				CRM_Leads_Capture_Service_Interest_Capture::HONEYPOT_FIELD => '',
				'name' => 'Rafael Carvalho', 'email' => 'rafael@example.com', 'whatsapp' => '+55 21 99999-9999',
				'company' => 'Empresa Teste', 'role' => 'founder', 'company_url' => 'https://example.com',
				'challenge' => 'Aprovo decisões demais.', 'consent' => '1',
				'utm_source' => 'linkedin', 'utm_campaign' => 'coo-service',
			),
			$overrides
		);
	}

	/** @return array<string, mixed> */
	private function valid_speaker_request(): array {
		return array(
			'_wpnonce' => wp_create_nonce( 'crm_leads_capture_' . CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG ),
			'name' => 'Rafael Carvalho', 'email' => 'rafael@example.com', 'whatsapp' => '+55 21 99999-9999',
			'organization' => 'Empresa Teste', 'event_name' => 'Summit 2027',
			'objective_context' => 'Debater operações.', 'audience_profile' => 'Executivos de operações.',
			'audience_size' => '500', 'event_date' => '2027-05-20', 'location' => 'São Paulo',
			'format' => 'hibrido', 'consent' => '1', 'utm_source' => 'indicacao',
		);
	}
}
