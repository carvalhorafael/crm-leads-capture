<?php
/**
 * Free material capture integration tests.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $last_payload = null;

	/**
	 * @var array<string, mixed>|null
	 */
	public ?array $last_context = null;

	private CRM_Leads_Capture_Result $result;

	public function __construct( ?CRM_Leads_Capture_Result $result = null ) {
		$this->result = $result ?: CRM_Leads_Capture_Result::success( 201, 'Created.' );
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

class FreeMaterialCaptureTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Test_Provider $provider;

	private CRM_Leads_Capture_Free_Material_Capture $capture;

	public function set_up(): void {
		parent::set_up();

		$this->provider = new CRM_Leads_Capture_Test_Provider();
		$this->capture = new CRM_Leads_Capture_Free_Material_Capture(
			crm_leads_capture()->settings(),
			crm_leads_capture()->providers(),
			fn(): CRM_Leads_Capture_Test_Provider => $this->provider
		);
	}

	public function test_registers_admin_post_hooks(): void {
		$this->assertSame(
			10,
			has_action(
				'admin_post_nopriv_' . CRM_Leads_Capture_Free_Material_Capture::ACTION,
				array( crm_leads_capture()->free_material_capture(), 'handle_request' )
			)
		);

		$this->assertSame(
			10,
			has_action(
				'admin_post_' . CRM_Leads_Capture_Free_Material_Capture::ACTION,
				array( crm_leads_capture()->free_material_capture(), 'handle_request' )
			)
		);

		$this->assertSame( 10, has_action( 'admin_post_nopriv_' . CRM_Leads_Capture_Free_Material_Capture::LEGACY_ACTION, array( crm_leads_capture()->free_material_capture(), 'handle_request' ) ) );
		$this->assertSame( 10, has_action( 'admin_post_' . CRM_Leads_Capture_Free_Material_Capture::LEGACY_ACTION, array( crm_leads_capture()->free_material_capture(), 'handle_request' ) ) );
	}

	public function test_builds_transient_generic_profile_from_existing_material_metadata(): void {
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID      => '456',
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => 'https://example.com/current-download',
			)
		);

		$profile = $this->capture->profile_for_material( $material_id );

		$this->assertSame( 'free-material-' . $material_id, $profile->slug() );
		$this->assertSame( 456, $profile->provider_config( 'brevo' )['list_id'] );
		$this->assertSame( 'free_material', $profile->context()['source'] );
		$this->assertSame( 'Material Teste', $profile->context()['material'] );
		$this->assertSame( 'https://example.com/current-download', $profile->success_behavior()['redirect_url'] );
		$this->assertSame( CRM_Leads_Capture_Free_Material_Capture::NONCE_ACTION, $profile->nonce_action() );
	}

	public function test_processes_valid_free_material_submission(): void {
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID      => '123',
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => 'https://example.com/download',
			)
		);

		$result = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array(
					'name'         => 'Rafael Carvalho',
					'email'        => 'RAFAEL@example.com',
					'whatsapp'     => '+55 (11) 99999-9999',
					'utm_source'   => 'linkedin',
					'utm_campaign' => 'material',
				)
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'https://example.com/download', $result->data()['redirect_url'] );
		$this->assertTrue( $result->data()['allow_external_redirect'] );
		$this->assertSame( 'rafael@example.com', $this->provider->last_payload['lead']['email'] );
		$this->assertSame( 123, $this->provider->last_context['list_id'] );
		$this->assertSame( 'free_material', $this->provider->last_payload['context']['source'] );
		$this->assertSame( 'Material Teste', $this->provider->last_payload['context']['material'] );
		$this->assertSame( 'linkedin', $this->provider->last_payload['tracking']['utm_source'] );
		$this->assertSame( 'material', $this->provider->last_payload['tracking']['utm_campaign'] );
	}

	public function test_rejects_invalid_nonce_without_calling_brevo(): void {
		$material_id = $this->create_material();

		$result = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array( CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD => 'invalid' )
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_nonce', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_rejects_honeypot_without_calling_brevo(): void {
		$material_id = $this->create_material();

		$result = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array( CRM_Leads_Capture_Free_Material_Capture::HONEYPOT_FIELD => 'filled' )
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'spam', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_accepts_legacy_nonce_and_rejects_legacy_honeypot(): void {
		$material_id = $this->create_material();
		$result      = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array( CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD => wp_create_nonce( CRM_Leads_Capture_Free_Material_Capture::LEGACY_NONCE_ACTION ) )
			)
		);
		$this->assertTrue( $result->is_successful() );

		$this->provider->last_payload = null;
		$result = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array( CRM_Leads_Capture_Free_Material_Capture::LEGACY_HONEYPOT_FIELD => 'bot' )
			)
		);
		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'spam', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_rejects_negative_material_id_without_calling_brevo(): void {
		$result = $this->capture->process_submission( $this->valid_request( -123 ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_material', $result->data()['code'] );
		$this->assertSame( 0, $result->data()['material_id'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_rejects_invalid_email_without_calling_brevo(): void {
		$material_id = $this->create_material();
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'error_messages' => array(
					'invalid_lead' => 'Revise o e-mail informado.',
				),
			)
		);

		$result = $this->capture->process_submission(
			$this->valid_request(
				$material_id,
				array( 'email' => 'invalid-email' )
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_lead', $result->data()['code'] );
		$this->assertSame( 'Revise o e-mail informado.', $result->data()['message'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_current_error_message_uses_query_string_and_configured_copy(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'error_messages' => array(
					'brevo_permission_error' => 'Não foi possível concluir agora.',
				),
			)
		);

		$_GET['crm_leads_capture'] = 'error';
		$_GET['brevo_error']         = 'brevo_permission_error';

		$this->assertSame( 'Não foi possível concluir agora.', $this->capture->current_error_message() );
		$markup = do_shortcode( '[crm_leads_capture_error]' );
		$this->assertStringContainsString( 'crm-leads-capture-message__text', $markup );
		$this->assertStringContainsString( 'data-feedback-tone="danger"', $markup );
		$this->assertStringContainsString( 'crm-leads-capture-message__badge', $markup );
		$this->assertStringContainsString( 'Não foi possível concluir agora.', $markup );

		// The host theme owns the identity: no borrowed design system classes.
		$this->assertStringNotContainsString( 'es-panel', $markup );
		$this->assertStringNotContainsString( 'es-badge', $markup );
		$this->assertStringNotContainsString( 'es-operational-feedback', $markup );

		unset( $_GET['crm_leads_capture'], $_GET['brevo_error'] );
	}

	public function test_rest_request_returns_public_error_message_without_redirect(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'error_messages' => array(
					'invalid_lead' => 'Revise os campos marcados.',
				),
			)
		);
		$material_id = $this->create_material();
		$request     = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Free_Material_Capture::REST_NAMESPACE . CRM_Leads_Capture_Free_Material_Capture::REST_ROUTE );

		foreach ( $this->valid_request( $material_id, array( 'email' => 'invalid-email' ) ) as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $data['success'] );
		$this->assertSame( 'invalid_lead', $data['code'] );
		$this->assertSame( 'Revise os campos marcados.', $data['message'] );
		$this->assertArrayNotHasKey( 'redirect_url', $data );
	}

	public function test_rest_nonce_request_returns_capture_nonce(): void {
		$response = $this->capture->handle_rest_nonce_request();
		$data     = $response->get_data();

		$this->assertIsString( $data['nonce'] );
		$this->assertNotSame( '', $data['nonce'] );
		$this->assertNotFalse( wp_verify_nonce( $data['nonce'], CRM_Leads_Capture_Free_Material_Capture::NONCE_ACTION ) );
	}

	public function test_rest_request_returns_redirect_url_on_success(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'success_message' => 'Tudo certo. Redirecionando para o material.',
			)
		);
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => 'https://example.com/download',
			)
		);
		$request = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Free_Material_Capture::REST_NAMESPACE . CRM_Leads_Capture_Free_Material_Capture::REST_ROUTE );

		foreach ( $this->valid_request( $material_id ) as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'https://example.com/download', $data['redirect_url'] );
		$this->assertSame( 'Tudo certo. Redirecionando para o material.', $data['message'] );
	}

	public function test_rest_request_accepts_rest_specific_capture_nonce_field(): void {
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => 'https://example.com/download',
			)
		);
		$request_data = $this->valid_request( $material_id );
		$request      = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Free_Material_Capture::REST_NAMESPACE . CRM_Leads_Capture_Free_Material_Capture::REST_ROUTE );

		$request_data[ CRM_Leads_Capture_Free_Material_Capture::REST_NONCE_FIELD ] = $request_data[ CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD ];
		unset( $request_data[ CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD ] );

		foreach ( $request_data as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertSame( 'https://example.com/download', $data['redirect_url'] );
	}

	public function test_rest_request_accepts_legacy_rest_nonce_field(): void {
		$material_id = $this->create_material();
		$request_data = $this->valid_request( $material_id );
		$request      = new WP_REST_Request( 'POST', '/' . CRM_Leads_Capture_Free_Material_Capture::REST_NAMESPACE . CRM_Leads_Capture_Free_Material_Capture::REST_ROUTE );
		$request_data[ CRM_Leads_Capture_Free_Material_Capture::LEGACY_REST_NONCE_FIELD ] = wp_create_nonce( CRM_Leads_Capture_Free_Material_Capture::LEGACY_NONCE_ACTION );
		unset( $request_data[ CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD ] );
		foreach ( $request_data as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = $this->capture->handle_rest_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_material_fallback_order_preserves_current_and_legacy_metadata(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'providers'            => array( 'brevo' => array( 'default_list_id' => 999 ) ),
				'default_delivery_url' => 'https://example.com/global',
			)
		);
		$legacy_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID                  => '',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_LIST_ID           => '321',
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL             => '',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_DELIVERY_URL_BREVO => 'https://example.com/brevo-legacy',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_DELIVERY_URL       => 'https://example.com/theme-legacy',
			)
		);
		$result = $this->capture->process_submission( $this->valid_request( $legacy_id ) );
		$this->assertSame( 321, $this->provider->last_context['list_id'] );
		$this->assertSame( 'https://example.com/brevo-legacy', $result->data()['redirect_url'] );

		$current_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID                  => '123',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_LIST_ID           => '321',
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL             => 'https://example.com/current',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_DELIVERY_URL_BREVO => 'https://example.com/brevo-legacy',
			)
		);
		$result = $this->capture->process_submission( $this->valid_request( $current_id ) );
		$this->assertSame( 123, $this->provider->last_context['list_id'] );
		$this->assertSame( 'https://example.com/current', $result->data()['redirect_url'] );
	}

	public function test_submission_does_not_migrate_or_delete_material_metadata(): void {
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_LIST_ID     => '321',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_DELIVERY_URL => 'https://example.com/legacy',
			)
		);
		$before = get_post_meta( $material_id );

		$this->capture->process_submission( $this->valid_request( $material_id ) );

		$this->assertSame( $before, get_post_meta( $material_id ) );
	}

	public function test_uses_legacy_delivery_url_fallback(): void {
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL        => '',
				CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID             => '456',
				CRM_Leads_Capture_Free_Material_Capture::META_LEGACY_DELIVERY_URL => 'https://example.com/legacy-download',
			)
		);

		$result = $this->capture->process_submission( $this->valid_request( $material_id ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'https://example.com/legacy-download', $result->data()['redirect_url'] );
		$this->assertSame( 456, $this->provider->last_context['list_id'] );
	}

	public function test_uses_global_delivery_url_when_material_has_no_delivery_url(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'default_delivery_url' => 'https://example.com/default-download',
			)
		);
		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => '',
			)
		);

		$result = $this->capture->process_submission( $this->valid_request( $material_id ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'https://example.com/default-download', $result->data()['redirect_url'] );
	}

	public function test_material_meta_box_uses_neutral_labels_when_provider_comes_from_global_setting(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'rd_station',
			)
		);
		$material_id = $this->create_material();
		$post        = get_post( $material_id );

		ob_start();
		$this->capture->render_material_meta_box( $post );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Provider global', $output );
		$this->assertStringContainsString( 'RD Station', $output );
		$this->assertStringNotContainsString( 'crm_leads_capture_material_provider', $output );
		$this->assertStringContainsString( 'Identificador de conversão', $output );
		$this->assertStringContainsString( 'Tags', $output );
		$this->assertStringContainsString( 'Se ficar em branco, será usada a URL de entrega configurada no plugin.', $output );
		$this->assertStringContainsString( 'Nome do evento de conversão que aparecerá na RD Station para este lead.', $output );
		$this->assertStringContainsString( 'Tags adicionadas ao lead para segmentação e automações.', $output );
		$this->assertStringNotContainsString( 'Conversão RD Station</strong>', $output );
		$this->assertStringNotContainsString( 'Tags RD Station</strong>', $output );
	}

	public function test_legacy_provider_override_is_preserved_but_ignored(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'rd_station' )
		);
		$material_id = $this->create_material(
			array( CRM_Leads_Capture_Free_Material_Capture::META_PROVIDER => 'brevo' )
		);

		$result = $this->capture->process_submission(
			$this->valid_request( $material_id, array( 'analytics_device_id' => 'device-123' ) )
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'rd_station', $result->data()['provider'] );
		$this->assertSame( 'device-123', $this->provider->last_context['analytics_device_id'] );
		$this->assertSame( 'brevo', get_post_meta( $material_id, CRM_Leads_Capture_Free_Material_Capture::META_PROVIDER, true ) );

		$administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator_id );
		$_POST = array(
			'crm_leads_capture_material_meta_nonce' => wp_create_nonce( 'crm_leads_capture_material_meta' ),
			'crm_leads_capture_material_provider'   => 'rd_station',
		);

		$this->capture->save_material_meta_box( $material_id );

		$this->assertSame( 'brevo', get_post_meta( $material_id, CRM_Leads_Capture_Free_Material_Capture::META_PROVIDER, true ) );
		$_POST = array();
		wp_set_current_user( 0 );
	}

	public function test_admin_notice_reports_legacy_provider_overrides_without_deleting_them(): void {
		$material_id = $this->create_material(
			array( CRM_Leads_Capture_Free_Material_Capture::META_PROVIDER => 'rd_station' )
		);
		$administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $administrator_id );

		ob_start();
		$this->capture->render_legacy_provider_override_notice();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, $this->capture->legacy_provider_override_count() );
		$this->assertStringContainsString( 'configuração legada de provider', $output );
		$this->assertStringContainsString( 'preservada', $output );
		$this->assertStringContainsString( 'Revisar provider global', $output );
		$this->assertSame( 'rd_station', get_post_meta( $material_id, CRM_Leads_Capture_Free_Material_Capture::META_PROVIDER, true ) );
		wp_set_current_user( 0 );
	}

	public function test_returns_controlled_errors_for_unavailable_global_provider_configuration(): void {
		$material_id = $this->create_material();
		$capture = new CRM_Leads_Capture_Free_Material_Capture(
			crm_leads_capture()->settings(),
			crm_leads_capture()->providers()
		);

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array( 'brevo' => array( 'enabled' => false ) ),
			)
		);
		$result = $capture->process_submission( $this->valid_request( $material_id ) );
		$this->assertSame( 'provider_disabled', $result->data()['code'] );

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'rd_station',
				'providers'       => array( 'rd_station' => array( 'enabled' => true, 'api_key' => '' ) ),
			)
		);
		$result = $capture->process_submission( $this->valid_request( $material_id ) );
		$this->assertSame( 'provider_not_configured', $result->data()['code'] );
	}

	public function test_returns_controlled_error_when_brevo_fails(): void {
		$this->provider = new CRM_Leads_Capture_Test_Provider(
			CRM_Leads_Capture_Result::failure(
				400,
				'Brevo request returned an error.',
				array(
					'body'          => array( 'api-key' => 'secret' ),
					'error_summary' => array(
						'status_code' => 400,
						'code'        => 'invalid_parameter',
						'message'     => 'Attribute SOURCE does not exist.',
					),
				)
			)
		);
		$this->capture = new CRM_Leads_Capture_Free_Material_Capture(
			crm_leads_capture()->settings(),
			crm_leads_capture()->providers(),
			fn(): CRM_Leads_Capture_Test_Provider => $this->provider
		);
		$material_id = $this->create_material();

		$result = $this->capture->process_submission( $this->valid_request( $material_id ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'brevo_invalid_parameter', $result->data()['code'] );
		$this->assertArrayNotHasKey( 'allow_external_redirect', $result->data() );
		$this->assertStringContainsString( 'crm_leads_capture=error', $result->data()['redirect_url'] );
		$this->assertStringContainsString( 'crm_error=brevo_invalid_parameter', $result->data()['redirect_url'] );
		$this->assertStringNotContainsString( 'secret', $result->message() );
		$this->assertStringNotContainsString( 'secret', $result->data()['redirect_url'] );
	}

	/**
	 * @param array<string, string> $meta
	 */
	/**
	 * The anonymous analytics identifier the host site's analytics assigned to
	 * this browser has to reach the CRM, so the CRM can reconstruct the path
	 * this person took on the site.
	 */
	public function test_analytics_device_id_is_forwarded_to_the_crm(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'rd_station' )
		);
		$material_id = $this->create_material();

		$this->capture->process_submission(
			$this->valid_request( $material_id, array( 'analytics_device_id' => 'abc-123_XY.Z:0' ) )
		);

		$this->assertSame( 'abc-123_XY.Z:0', $this->provider->last_context['analytics_device_id'] ?? null );
	}

	public function test_rd_station_destination_uses_material_then_global_then_title_fallbacks(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'rd_station',
				'providers'       => array(
					'rd_station' => array(
						'default_conversion_identifier' => 'global-conversion',
						'default_tags'                  => 'global, material',
					),
				),
			)
		);
		$global_id = $this->create_material();
		$this->capture->process_submission( $this->valid_request( $global_id ) );
		$this->assertSame( 'global-conversion', $this->provider->last_context['conversion_identifier'] );
		$this->assertSame( 'global, material', $this->provider->last_context['tags'] );

		$material_id = $this->create_material(
			array(
				CRM_Leads_Capture_Free_Material_Capture::META_RD_STATION_CONVERSION_IDENTIFIER => 'material-conversion',
				CRM_Leads_Capture_Free_Material_Capture::META_RD_STATION_TAGS => 'specific',
			)
		);
		$this->capture->process_submission( $this->valid_request( $material_id ) );
		$this->assertSame( 'material-conversion', $this->provider->last_context['conversion_identifier'] );
		$this->assertSame( 'specific', $this->provider->last_context['tags'] );

		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'rd_station' )
		);
		$title_id = $this->create_material();
		$this->capture->process_submission( $this->valid_request( $title_id ) );
		$this->assertSame( 'Material Teste', $this->provider->last_context['conversion_identifier'] );
	}

	/**
	 * It is forwarded untouched otherwise, so it is treated as an opaque string
	 * with a restricted charset and a capped length, never interpreted.
	 */
	public function test_analytics_device_id_is_sanitised(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'rd_station' )
		);
		$material_id = $this->create_material();

		$this->capture->process_submission(
			$this->valid_request( $material_id, array( 'analytics_device_id' => '<script>a</script>b' ) )
		);

		$this->assertSame( 'scriptascriptb', $this->provider->last_context['analytics_device_id'] ?? null );

		$this->capture->process_submission(
			$this->valid_request( $material_id, array( 'analytics_device_id' => str_repeat( 'a', 200 ) ) )
		);

		$this->assertSame( 128, strlen( (string) ( $this->provider->last_context['analytics_device_id'] ?? '' ) ) );
	}

	/**
	 * A visitor whose browser has no analytics must still become a lead.
	 */
	public function test_a_submission_without_the_identifier_still_succeeds(): void {
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array( 'active_provider' => 'rd_station' )
		);
		$material_id = $this->create_material();

		$result = $this->capture->process_submission( $this->valid_request( $material_id ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( '', $this->provider->last_context['analytics_device_id'] ?? null );
	}

	/**
	 * The other half of the chain: the provider is where the vendor specific
	 * field name lives, so this asserts the body the CRM actually receives.
	 */
	public function test_the_rd_station_payload_carries_the_identifier(): void {
		update_option(
			'crm_leads_capture_settings',
			array( 'providers' => array( 'rd_station' => array( 'api_key' => 'test-key' ) ) )
		);

		$bodies = array();
		$spy    = static function ( $preempt, $parsed_args ) use ( &$bodies ) {
			$bodies[] = json_decode( (string) ( $parsed_args['body'] ?? '' ), true );

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{}',
			);
		};

		add_filter( 'pre_http_request', $spy, 10, 2 );

		$provider = new CRM_Leads_Capture_RD_Station_Provider( crm_leads_capture()->settings() );
		$lead     = array(
			'email'    => 'lead@example.com',
			'name'     => 'Lead Teste',
			'material' => 'Material Teste',
		);

		$provider->send_lead( $lead, array( 'analytics_device_id' => 'abc-123' ) );

		// Empty values are dropped, so a visitor without analytics sends one
		// field less rather than an empty one.
		$provider->send_lead( $lead, array() );

		remove_filter( 'pre_http_request', $spy, 10 );

		$this->assertSame( 'abc-123', $bodies[0]['payload']['cf_amplitude_device_id'] ?? null );
		$this->assertArrayNotHasKey( 'cf_amplitude_device_id', $bodies[1]['payload'] ?? array() );
	}

	private function create_material( array $meta = array() ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Material Teste',
				'post_status' => 'publish',
			)
		);

		$defaults = array(
			CRM_Leads_Capture_Free_Material_Capture::META_LIST_ID      => '123',
			CRM_Leads_Capture_Free_Material_Capture::META_DELIVERY_URL => 'https://example.com/download',
		);

		foreach ( array_merge( $defaults, $meta ) as $key => $value ) {
			if ( '' !== $value ) {
				update_post_meta( $post_id, $key, $value );
			} else {
				delete_post_meta( $post_id, $key );
			}
		}

		return $post_id;
	}

	/**
	 * @param array<string, mixed> $overrides
	 *
	 * @return array<string, mixed>
	 */
	private function valid_request( int $material_id, array $overrides = array() ): array {
		return array_merge(
			array(
				CRM_Leads_Capture_Free_Material_Capture::NONCE_FIELD    => wp_create_nonce( CRM_Leads_Capture_Free_Material_Capture::NONCE_ACTION ),
				CRM_Leads_Capture_Free_Material_Capture::HONEYPOT_FIELD => '',
				'material_id' => (string) $material_id,
				'name'        => 'Lead Teste',
				'email'       => 'lead@example.com',
				'whatsapp'    => '+55 11 99999-9999',
			),
			$overrides
		);
	}
}
