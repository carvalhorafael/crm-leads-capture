<?php
/**
 * Commercial capture profile integration tests.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Commercial_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
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

class CommercialCaptureProfilesTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Commercial_Test_Provider $provider;
	private CRM_Leads_Capture_Frontend $frontend;
	private CRM_Leads_Capture_Profile_Registry $profiles;

	public function set_up(): void {
		parent::set_up();

		$this->profiles = new CRM_Leads_Capture_Profile_Registry();
		( new CRM_Leads_Capture_Profile_Defaults( crm_leads_capture()->settings() ) )->register( $this->profiles );
		$this->provider = new CRM_Leads_Capture_Commercial_Test_Provider();
		$providers      = new CRM_Leads_Capture_Provider_Registry();
		$providers->register( $this->provider );
		$processor = new CRM_Leads_Capture_Processor(
			$this->profiles,
			$providers,
			static fn(): string => 'brevo',
			static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action )
		);
		$this->frontend = new CRM_Leads_Capture_Frontend( $this->profiles, $processor, crm_leads_capture()->settings() );
	}

	public function test_builtin_commercial_profiles_use_only_generic_contract(): void {
		$coo     = $this->profiles->resolve( CRM_Leads_Capture_Profile_Defaults::COO_SLUG );
		$speaker = $this->profiles->resolve( CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG );

		$this->assertNotNull( $coo );
		$this->assertNotNull( $speaker );
		$this->assertSame( 'crm_leads_capture_coo-as-a-service', $coo->nonce_action() );
		$this->assertArrayHasKey( 'challenge', $coo->fields() );
		$this->assertArrayHasKey( 'event_date', $speaker->fields() );
		$this->assertNotSame( $coo->success_behavior()['message'], $speaker->success_behavior()['message'] );

		$fields = $this->frontend->form_fields( CRM_Leads_Capture_Profile_Defaults::COO_SLUG );
		$this->assertStringContainsString( 'name="action" value="crm_leads_capture_submit"', $fields );
		$this->assertStringContainsString( 'name="crm_leads_capture_profile" value="coo-as-a-service"', $fields );
	}

	public function test_coo_profile_uses_generic_pipeline_without_local_persistence(): void {
		global $wpdb;
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'crm_service_interest' ) );
		$result = $this->frontend->process_submission( CRM_Leads_Capture_Profile_Defaults::COO_SLUG, $this->valid_coo_request(), 'https://example.com/coo' );
		$after  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'crm_service_interest' ) );

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

	public function test_invalid_coo_fields_are_rejected_without_sending_or_storing(): void {
		$result = $this->frontend->process_submission(
			CRM_Leads_Capture_Profile_Defaults::COO_SLUG,
			$this->valid_coo_request( array( 'consent' => '' ) )
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_fields', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
		$this->assertFalse( post_type_exists( 'crm_service_interest' ) );
	}

	public function test_provider_failure_returns_controlled_generic_rest_response(): void {
		$this->provider->should_succeed = false;
		$request = new WP_REST_Request( 'POST', '/crm-leads-capture/v1/capture/coo-as-a-service' );
		$request->set_url_params( array( 'profile' => CRM_Leads_Capture_Profile_Defaults::COO_SLUG ) );
		foreach ( $this->valid_coo_request() as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$request->set_param( CRM_Leads_Capture_Frontend::REST_NONCE_FIELD, $this->valid_coo_request()['_wpnonce'] );

		$response = $this->frontend->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
		$this->assertSame( 'provider_error', $data['code'] );
		$this->assertStringNotContainsString( 'Remote details', $data['message'] );
		$this->assertArrayNotHasKey( 'redirect_url', $data );
	}

	public function test_speaker_profile_sends_every_required_field_without_storage(): void {
		$result = $this->frontend->process_submission(
			CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG,
			$this->valid_speaker_request(),
			'https://example.com/palestras'
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Summit 2027', $this->provider->last_payload['custom_fields']['event_name'] );
		$this->assertSame( 500, $this->provider->last_payload['custom_fields']['audience_size'] );
		$this->assertSame( '2027-05-20', $this->provider->last_payload['custom_fields']['event_date'] );
		$this->assertSame( 'hibrido', $this->provider->last_payload['custom_fields']['format'] );
		$this->assertSame( 'https://example.com/palestras', $this->provider->last_payload['context']['page_url'] );
		$this->assertFalse( post_type_exists( 'crm_service_interest' ) );
	}

	/** @param array<string, mixed> $overrides @return array<string, mixed> */
	private function valid_coo_request( array $overrides = array() ): array {
		return array_merge(
			array(
				'_wpnonce' => wp_create_nonce( 'crm_leads_capture_' . CRM_Leads_Capture_Profile_Defaults::COO_SLUG ),
				'crm_leads_capture_website' => '',
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
