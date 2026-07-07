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
		$this->assertSame( 'RAFAEL@example.com', $this->provider->last_payload['email'] );
		$this->assertSame( 123, $this->provider->last_context['list_id'] );
		$this->assertSame( 'free_material', $this->provider->last_payload['source'] );
		$this->assertSame( 'Material Teste', $this->provider->last_payload['material'] );
		$this->assertSame( 'linkedin', $this->provider->last_payload['utm_source'] );
		$this->assertSame( 'material', $this->provider->last_payload['utm_campaign'] );
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
		$this->assertStringContainsString( 'es-operational-feedback', $markup );
		$this->assertStringContainsString( 'data-feedback-tone="danger"', $markup );
		$this->assertStringContainsString( 'es-badge', $markup );
		$this->assertStringContainsString( 'Não foi possível concluir agora.', $markup );

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

		$this->assertStringContainsString( 'Provider efetivo: RD Station.', $output );
		$this->assertStringContainsString( 'Identificador de conversão', $output );
		$this->assertStringContainsString( 'Tags', $output );
		$this->assertStringContainsString( 'Se ficar em branco, será usada a URL de entrega configurada no plugin.', $output );
		$this->assertStringContainsString( 'Nome do evento de conversão que aparecerá na RD Station para este lead.', $output );
		$this->assertStringContainsString( 'Tags adicionadas ao lead para segmentação e automações.', $output );
		$this->assertStringNotContainsString( 'Conversão RD Station</strong>', $output );
		$this->assertStringNotContainsString( 'Tags RD Station</strong>', $output );
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
