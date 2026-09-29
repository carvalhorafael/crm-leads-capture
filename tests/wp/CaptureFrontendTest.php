<?php
/**
 * Generic profile frontend integration tests.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Frontend_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
	/** @var array<string, mixed>|null */
	public ?array $last_payload = null;

	/** @var array<string, mixed>|null */
	public ?array $last_context = null;

	public CRM_Leads_Capture_Result $result;

	public function __construct() {
		$this->result = CRM_Leads_Capture_Result::success( 201, 'Created.' );
	}

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

		return $this->result;
	}

	public function error_codes(): array {
		return array();
	}
}

class CaptureFrontendTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Profile $profile;

	private CRM_Leads_Capture_Frontend_Test_Provider $provider;

	private CRM_Leads_Capture_Frontend $frontend;

	private CRM_Leads_Capture_Processor $processor;

	public function set_up(): void {
		parent::set_up();

		$profiles = new CRM_Leads_Capture_Profile_Registry();
		$this->profile = new CRM_Leads_Capture_Profile(
			'generic-test',
			array(
				new CRM_Leads_Capture_Field( 'name', array( 'group' => 'lead', 'required' => true ) ),
				new CRM_Leads_Capture_Field( 'email', array( 'type' => 'email', 'group' => 'lead', 'required' => true ) ),
				new CRM_Leads_Capture_Field( 'utm_source', array( 'group' => 'tracking' ) ),
			),
			array(
				'providers' => array(
					'brevo' => array( 'list_id' => 123 ),
				),
				'success' => array(
					'message'      => 'Cadastro concluído.',
					'redirect_url' => 'https://delivery.example/material',
				),
			)
		);
		$profiles->register( $this->profile );

		$this->provider = new CRM_Leads_Capture_Frontend_Test_Provider();
		$providers      = new CRM_Leads_Capture_Provider_Registry();
		$providers->register( $this->provider );
		$this->processor = new CRM_Leads_Capture_Processor(
			$profiles,
			$providers,
			static fn(): string => 'brevo',
			static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action )
		);

		$this->frontend = new CRM_Leads_Capture_Frontend( $profiles, $this->processor, new CRM_Leads_Capture_Settings() );
	}

	public function tear_down(): void {
		unset( $_GET['crm_leads_capture'], $_GET['capture_profile'], $_GET['crm_error'] );
		parent::tear_down();
	}

	public function test_plugin_registers_generic_frontend_hooks(): void {
		$this->assertSame( 10, has_action( 'admin_post_nopriv_' . CRM_Leads_Capture_Frontend::ACTION, array( crm_leads_capture()->frontend(), 'handle_admin_post' ) ) );
		$this->assertSame( 10, has_action( 'admin_post_' . CRM_Leads_Capture_Frontend::ACTION, array( crm_leads_capture()->frontend(), 'handle_admin_post' ) ) );
		$this->assertSame( 10, has_action( 'rest_api_init', array( crm_leads_capture()->frontend(), 'register_rest_routes' ) ) );
	}

	public function test_nonce_endpoint_issues_profile_nonce_without_cache(): void {
		$response = $this->frontend->handle_rest_nonce_request( $this->request( 'generic-test' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertNotFalse( wp_verify_nonce( $data['nonce'], $this->profile->nonce_action() ) );
		$this->assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	public function test_nonce_endpoint_rejects_unknown_profile(): void {
		$response = $this->frontend->handle_rest_nonce_request( $this->request( 'missing' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'profile_not_found', $response->get_data()['code'] );
	}

	public function test_rest_submission_uses_server_profile_and_ignores_browser_destinations(): void {
		$request = $this->request(
			'generic-test',
			array(
				CRM_Leads_Capture_Frontend::REST_NONCE_FIELD => wp_create_nonce( $this->profile->nonce_action() ),
				'profile'       => 'missing',
				'name'          => 'Rafael Carvalho',
				'email'         => 'RAFAEL@EXAMPLE.COM',
				'utm_source'    => 'linkedin',
				'provider'      => 'rd_station',
				'list_id'       => '999',
				'redirect_url'  => 'https://attacker.example',
			)
		);

		$response = $this->frontend->handle_rest_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'https://delivery.example/material', $response->get_data()['redirect_url'] );
		$this->assertSame( 'rafael@example.com', $this->provider->last_payload['lead']['email'] );
		$this->assertSame( 'linkedin', $this->provider->last_payload['tracking']['utm_source'] );
		$this->assertSame( 123, $this->provider->last_context['list_id'] );
		$this->assertArrayNotHasKey( 'provider', $this->provider->last_payload['custom_fields'] );
		$this->assertArrayNotHasKey( 'list_id', $this->provider->last_payload['custom_fields'] );
	}

	/**
	 * @dataProvider invalid_request_provider
	 *
	 * @param array<string, mixed> $input Request input.
	 */
	public function test_rest_submission_returns_controlled_errors( string $profile, array $input, int $status, string $code ): void {
		$response = $this->frontend->handle_rest_request( $this->request( $profile, $input ) );

		$this->assertSame( $status, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
		$this->assertSame( $code, $response->get_data()['code'] );
	}

	/**
	 * @return array<string, array{string, array<string, mixed>, int, string}>
	 */
	public function invalid_request_provider(): array {
		return array(
			'missing profile' => array( 'missing', array(), 404, 'profile_not_found' ),
			'invalid nonce'   => array( 'generic-test', array( CRM_Leads_Capture_Frontend::REST_NONCE_FIELD => 'invalid' ), 403, 'invalid_nonce' ),
			'honeypot'        => array(
				'generic-test',
				array(
					CRM_Leads_Capture_Frontend::REST_NONCE_FIELD => wp_create_nonce( 'crm_leads_capture_generic-test' ),
					'crm_leads_capture_website' => 'filled',
				),
				400,
				'spam',
			),
			'invalid fields'  => array(
				'generic-test',
				array(
					CRM_Leads_Capture_Frontend::REST_NONCE_FIELD => wp_create_nonce( 'crm_leads_capture_generic-test' ),
					'name'  => 'Rafael',
					'email' => 'not-an-email',
				),
				422,
				'invalid_fields',
			),
		);
	}

	public function test_rest_submission_hides_provider_failure(): void {
		$this->provider->result = CRM_Leads_Capture_Result::failure( 500, 'Sensitive provider response.' );
		$response = $this->frontend->handle_rest_request(
			$this->request(
				'generic-test',
				array(
					CRM_Leads_Capture_Frontend::REST_NONCE_FIELD => wp_create_nonce( $this->profile->nonce_action() ),
					'name'  => 'Rafael',
					'email' => 'rafael@example.com',
				)
			)
		);

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'provider_error', $response->get_data()['code'] );
		$this->assertStringNotContainsString( 'Sensitive', $response->get_data()['message'] );
	}

	public function test_helpers_render_native_fallback_and_accessible_message(): void {
		$fields = $this->frontend->form_fields( 'generic-test' );

		$this->assertStringContainsString( 'name="action" value="crm_leads_capture_submit"', $fields );
		$this->assertStringContainsString( 'name="crm_leads_capture_profile" value="generic-test"', $fields );
		$this->assertStringContainsString( 'name="_wpnonce"', $fields );
		$this->assertStringContainsString( 'name="crm_leads_capture_website"', $fields );
		$this->assertStringNotContainsString( 'name="provider"', $fields );
		$this->assertStringNotContainsString( 'name="list_id"', $fields );

		$_GET['crm_leads_capture'] = 'success';
		$_GET['capture_profile']   = 'generic-test';
		$markup                    = $this->frontend->message_markup( 'generic-test' );

		$this->assertStringContainsString( 'role="status"', $markup );
		$this->assertStringContainsString( 'aria-live="polite"', $markup );
		$this->assertStringContainsString( 'data-feedback-tone="success"', $markup );
		$this->assertStringContainsString( 'Cadastro concluído.', $markup );
	}

	public function test_progressive_enhancement_is_profile_based_and_does_not_send_cookies(): void {
		$script = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/capture.js' );

		$this->assertStringContainsString( 'data-crm-leads-capture', $script );
		$this->assertSame( 2, substr_count( $script, "credentials: 'omit'" ) );
		$this->assertStringNotContainsString( 'Brevo', $script );
		$this->assertStringNotContainsString( 'RD Station', $script );
		$this->assertStringNotContainsString( 'list_id', $script );
		$this->assertStringNotContainsString( 'conversion_identifier', $script );
	}

	public function test_page_override_is_resolved_from_association_instead_of_browser_destination(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'brevo' )
		);
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$repository = new CRM_Leads_Capture_Profile_Repository( new CRM_Leads_Capture_Settings() );
		update_post_meta( $page_id, CRM_Leads_Capture_Profile_Repository::PAGE_PROFILE_META, 'generic-test' );
		update_post_meta( $page_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDE_ENABLED_META, '1' );
		update_post_meta(
			$page_id,
			CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META,
			array( 'brevo' => array( 'list_ids' => array( 456 ) ) )
		);
		$frontend = new CRM_Leads_Capture_Frontend(
			$this->profile_registry(),
			$this->processor,
			new CRM_Leads_Capture_Settings(),
			$repository
		);

		$result = $frontend->process_submission(
			'generic-test',
			array(
				'_wpnonce' => wp_create_nonce( $this->profile->nonce_action() ),
				CRM_Leads_Capture_Frontend::PAGE_FIELD => $page_id,
				'name'  => 'Rafael',
				'email' => 'rafael@example.com',
				'list_id' => 999,
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( array( 456 ), $this->provider->last_context['list_ids'] );
		$this->assertSame( $page_id, $this->provider->last_payload['context']['page_id'] );
	}

	private function profile_registry(): CRM_Leads_Capture_Profile_Registry {
		$registry = new CRM_Leads_Capture_Profile_Registry();
		$registry->register( $this->profile );

		return $registry;
	}

	/**
	 * @param array<string, mixed> $params Request parameters.
	 */
	private function request( string $profile, array $params = array() ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/crm-leads-capture/v1/capture/' . $profile );
		$request->set_url_params( array( 'profile' => $profile ) );
		$request->set_body_params( $params );

		return $request;
	}
}
