<?php
/**
 * Free material lead capture handler.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Free_Material_Capture {
	public const ACTION = 'crm_leads_capture_free_material';
	public const LEGACY_ACTION = 'brevo_leads_capture_free_material';
	public const NONCE_ACTION = 'crm_leads_capture_free_material';
	public const LEGACY_NONCE_ACTION = 'brevo_leads_capture_free_material';
	public const NONCE_FIELD = '_wpnonce';
	public const REST_NONCE_FIELD = 'crm_leads_capture_nonce';
	public const LEGACY_REST_NONCE_FIELD = 'brevo_leads_capture_nonce';
	public const HONEYPOT_FIELD = 'crm_leads_capture_website';
	public const LEGACY_HONEYPOT_FIELD = 'brevo_leads_capture_website';
	public const REST_NAMESPACE = 'crm-leads-capture/v1';
	public const REST_ROUTE = '/free-material';
	public const REST_NONCE_ROUTE = '/free-material/nonce';
	public const SHORTCODE_ERROR_MESSAGE = 'crm_leads_capture_error';

	public const META_LIST_ID = '_crm_leads_capture_list_id';
	public const META_DELIVERY_URL = '_crm_leads_capture_delivery_url';
	public const META_PROVIDER = '_crm_leads_capture_provider';
	public const META_RD_STATION_CONVERSION_IDENTIFIER = '_crm_leads_capture_rd_station_conversion_identifier';
	public const META_RD_STATION_TAGS = '_crm_leads_capture_rd_station_tags';
	public const META_LEGACY_LIST_ID = '_brevo_leads_capture_list_id';
	public const META_LEGACY_DELIVERY_URL_BREVO = '_brevo_leads_capture_delivery_url';
	public const META_LEGACY_DELIVERY_URL = '_executive_signal_material_capture_url';

	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Logger $logger;

	/**
	 * @var callable|null
	 */
	private $provider_factory;

	/**
	 * @param callable|null $provider_factory Optional factory for tests.
	 */
	public function __construct(
		CRM_Leads_Capture_Settings $settings,
		CRM_Leads_Capture_Provider_Registry $providers,
		?callable $provider_factory = null,
		?CRM_Leads_Capture_Logger $logger = null
	) {
		$this->settings         = $settings;
		$this->providers        = $providers;
		$this->provider_factory = $provider_factory;
		$this->logger           = $logger ?: new CRM_Leads_Capture_Logger();
	}

	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_nopriv_' . self::LEGACY_ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::LEGACY_ACTION, array( $this, 'handle_request' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_material_meta_box' ) );
		add_action( 'save_post_material_gratuito', array( $this, 'save_material_meta_box' ) );
		add_action( 'admin_notices', array( $this, 'render_free_materials_notice' ) );
		add_shortcode( self::SHORTCODE_ERROR_MESSAGE, array( $this, 'render_error_message_shortcode' ) );
	}

	public function render_free_materials_notice(): void {
		if ( post_type_exists( 'material_gratuito' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'CRM Leads Capture está ativo, mas o CPT material_gratuito não foi encontrado. Ative o plugin free-materials para configurar capturas por material.', 'crm-leads-capture' ) . '</p></div>';
	}

	public function register_material_meta_box(): void {
		if ( ! post_type_exists( 'material_gratuito' ) ) {
			return;
		}

		add_meta_box(
			'crm_leads_capture_material_settings',
			__( 'CRM Leads Capture', 'crm-leads-capture' ),
			array( $this, 'render_material_meta_box' ),
			'material_gratuito',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post object.
	 */
	public function render_material_meta_box( $post ): void {
		wp_nonce_field( 'crm_leads_capture_material_meta', 'crm_leads_capture_material_meta_nonce' );

		$provider_override_id = $this->material_provider_override_id( (int) $post->ID );
		$provider_id          = '' !== $provider_override_id ? $provider_override_id : $this->settings->active_provider();
		$uses_global_provider = '' === $provider_override_id;
		?>
		<p>
			<label for="crm_leads_capture_material_provider"><strong><?php echo esc_html__( 'Provider', 'crm-leads-capture' ); ?></strong></label>
			<select id="crm_leads_capture_material_provider" name="crm_leads_capture_material_provider" class="widefat">
				<option value="" <?php selected( '', $provider_override_id ); ?>><?php echo esc_html__( 'Usar configuração global', 'crm-leads-capture' ); ?></option>
				<option value="brevo" <?php selected( 'brevo', $provider_override_id ); ?>><?php echo esc_html__( 'Brevo', 'crm-leads-capture' ); ?></option>
				<option value="rd_station" <?php selected( 'rd_station', $provider_override_id ); ?>><?php echo esc_html__( 'RD Station', 'crm-leads-capture' ); ?></option>
			</select>
			<?php if ( $uses_global_provider ) : ?>
				<span class="description">
					<?php
					printf(
						/* translators: %s: active provider label. */
						esc_html__( 'Provider efetivo: %s.', 'crm-leads-capture' ),
						esc_html( $this->provider_label( $provider_id ) )
					);
					?>
				</span>
			<?php endif; ?>
		</p>
		<p>
			<label for="crm_leads_capture_delivery_url"><strong><?php echo esc_html__( 'URL de entrega', 'crm-leads-capture' ); ?></strong></label>
			<input type="url" id="crm_leads_capture_delivery_url" name="crm_leads_capture_delivery_url" value="<?php echo esc_attr( $this->material_delivery_url_override( (int) $post->ID ) ); ?>" class="widefat" />
			<span class="description"><?php echo esc_html__( 'Se ficar em branco, será usada a URL de entrega configurada no plugin.', 'crm-leads-capture' ); ?></span>
		</p>
		<?php if ( 'rd_station' === $provider_id ) : ?>
			<p>
				<label for="crm_leads_capture_rd_station_conversion_identifier"><strong><?php echo esc_html( $uses_global_provider ? __( 'Identificador de conversão', 'crm-leads-capture' ) : __( 'Conversão RD Station', 'crm-leads-capture' ) ); ?></strong></label>
				<input type="text" id="crm_leads_capture_rd_station_conversion_identifier" name="crm_leads_capture_rd_station_conversion_identifier" value="<?php echo esc_attr( (string) get_post_meta( (int) $post->ID, self::META_RD_STATION_CONVERSION_IDENTIFIER, true ) ); ?>" class="widefat" />
				<span class="description"><?php echo esc_html__( 'Nome do evento de conversão que aparecerá na RD Station para este lead.', 'crm-leads-capture' ); ?></span>
			</p>
			<p>
				<label for="crm_leads_capture_rd_station_tags"><strong><?php echo esc_html( $uses_global_provider ? __( 'Tags', 'crm-leads-capture' ) : __( 'Tags RD Station', 'crm-leads-capture' ) ); ?></strong></label>
				<input type="text" id="crm_leads_capture_rd_station_tags" name="crm_leads_capture_rd_station_tags" value="<?php echo esc_attr( (string) get_post_meta( (int) $post->ID, self::META_RD_STATION_TAGS, true ) ); ?>" class="widefat" />
				<span class="description"><?php echo esc_html__( 'Tags adicionadas ao lead para segmentação e automações. Separe múltiplas tags por vírgulas.', 'crm-leads-capture' ); ?></span>
			</p>
		<?php else : ?>
			<p>
				<label for="crm_leads_capture_list_id"><strong><?php echo esc_html( $uses_global_provider ? __( 'Lista', 'crm-leads-capture' ) : __( 'Lista Brevo', 'crm-leads-capture' ) ); ?></strong></label>
				<input type="number" min="0" step="1" id="crm_leads_capture_list_id" name="crm_leads_capture_list_id" value="<?php echo esc_attr( (string) $this->material_list_id( (int) $post->ID ) ); ?>" class="widefat" />
			</p>
		<?php endif; ?>
		<?php
	}

	public function save_material_meta_box( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST['crm_leads_capture_material_meta_nonce'] ) ? $this->clean_string( wp_unslash( $_POST['crm_leads_capture_material_meta_nonce'] ) ) : '';
		if ( '' === $nonce || false === wp_verify_nonce( $nonce, 'crm_leads_capture_material_meta' ) ) {
			return;
		}

		$request  = $this->unslash_array( $_POST );
		$provider = $this->clean_string( $request['crm_leads_capture_material_provider'] ?? '' );
		if ( in_array( $provider, array( 'brevo', 'rd_station' ), true ) ) {
			update_post_meta( $post_id, self::META_PROVIDER, $provider );
		} else {
			delete_post_meta( $post_id, self::META_PROVIDER );
		}

		$this->update_or_delete_meta( $post_id, self::META_DELIVERY_URL, $this->clean_url( $request['crm_leads_capture_delivery_url'] ?? '' ) );
		$list_id = $this->absint( $request['crm_leads_capture_list_id'] ?? 0 );
		0 < $list_id ? update_post_meta( $post_id, self::META_LIST_ID, $list_id ) : delete_post_meta( $post_id, self::META_LIST_ID );
		$this->update_or_delete_meta( $post_id, self::META_RD_STATION_CONVERSION_IDENTIFIER, $this->clean_string( $request['crm_leads_capture_rd_station_conversion_identifier'] ?? '' ) );
		$this->update_or_delete_meta( $post_id, self::META_RD_STATION_TAGS, $this->clean_string( $request['crm_leads_capture_rd_station_tags'] ?? '' ) );
	}

	public function handle_request(): void {
		$request = $this->unslash_array( $_POST );
		$result  = $this->process_submission( $request );
		$data    = $result->data();

		$redirect_url = isset( $data['redirect_url'] ) && is_string( $data['redirect_url'] )
			? $data['redirect_url']
			: $this->fallback_redirect_url( 0, 'error' );

		$this->safe_redirect( $redirect_url, true === ( $data['allow_external_redirect'] ?? false ) );
		exit;
	}

	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_rest_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_NONCE_ROUTE,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_rest_nonce_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle_rest_nonce_request(): WP_REST_Response {
		$response = rest_ensure_response(
			array(
				'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			)
		);

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	/**
	 * @param WP_REST_Request $request REST request.
	 */
	public function handle_rest_request( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		if ( isset( $params[ self::REST_NONCE_FIELD ] ) ) {
			$params[ self::NONCE_FIELD ] = $params[ self::REST_NONCE_FIELD ];
		} elseif ( isset( $params[ self::LEGACY_REST_NONCE_FIELD ] ) ) {
			$params[ self::NONCE_FIELD ] = $params[ self::LEGACY_REST_NONCE_FIELD ];
		}

		$result = $this->process_submission( $params );
		$data   = $result->data();

		if ( $result->is_successful() ) {
			return rest_ensure_response(
				array(
					'success'      => true,
					'redirect_url' => $data['redirect_url'] ?? '',
					'message'      => $this->settings->success_message(),
				)
			);
		}

		$code = isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : 'provider_error';

		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $this->public_error_message( $code ),
			),
			$this->rest_error_status( $code )
		);
	}

	public function enqueue_frontend_assets(): void {
		$style_path  = CRM_LEADS_CAPTURE_DIR . 'assets/css/free-material-capture.css';
		$script_path = CRM_LEADS_CAPTURE_DIR . 'assets/js/free-material-capture.js';

		wp_enqueue_style(
			'crm-leads-capture-free-material',
			plugins_url( 'assets/css/free-material-capture.css', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $style_path )
		);

		wp_enqueue_script(
			'crm-leads-capture-free-material',
			plugins_url( 'assets/js/free-material-capture.js', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $script_path ),
			true
		);

		wp_localize_script(
			'crm-leads-capture-free-material',
			'CRMLeadsCaptureFreeMaterial',
			array(
				'restUrl'             => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
				'nonceUrl'            => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_NONCE_ROUTE ) ),
				'genericMessage'      => $this->settings->error_message( 'provider_error' ),
				'invalidNonceMessage' => $this->settings->error_message( 'invalid_nonce' ),
				'successMessage'      => $this->settings->success_message(),
				'successLabel'        => __( 'Sucesso', 'crm-leads-capture' ),
				'errorLabel'          => __( 'Erro', 'crm-leads-capture' ),
				'redirectLinkLabel'   => __( 'Acessar o material agora', 'crm-leads-capture' ),
				'redirectDelayMs'     => 5000,
			)
		);
	}

	/**
	 * @param array<string, mixed> $request
	 */
	public function process_submission( array $request ): CRM_Leads_Capture_Result {
		$material_id = $this->absint( $request['material_id'] ?? 0 );

		if ( ! $this->is_valid_nonce( $request[ self::NONCE_FIELD ] ?? '' ) ) {
			return $this->failure( 'invalid_nonce', $material_id );
		}

		if ( '' !== $this->clean_string( $request[ self::HONEYPOT_FIELD ] ?? '' ) ) {
			return $this->failure( 'spam', $material_id );
		}

		if ( 0 >= $material_id || ! $this->post_exists( $material_id ) ) {
			return $this->failure( 'invalid_material', 0 );
		}

		$delivery_url = $this->material_delivery_url( $material_id );
		if ( '' === $delivery_url ) {
			return $this->failure( 'missing_delivery', $material_id );
		}

		$payload = $this->lead_input_from_request( $request );
		$payload['source']   = 'free_material';
		$payload['material'] = $this->material_label( $material_id );

		if ( ! $this->is_valid_email( $payload['email'] ?? '' ) ) {
			return $this->failure( 'invalid_lead', $material_id );
		}

		$provider_id = $this->material_provider_id( $material_id );
		$provider    = $this->provider( $provider_id );
		$context     = $this->provider_context( $material_id, $provider_id );

		if ( 'brevo' === $provider_id && empty( $context['list_id'] ) ) {
			return $this->failure( 'missing_list', $material_id );
		}

		$provider_result = $provider->send_lead( $payload, $context );
		if ( ! $provider_result->is_successful() ) {
		$this->logger->debug(
				'Free material CRM provider request failed.',
				array(
					'material_id'     => $material_id,
					'provider'        => $provider_id,
					'status_code'     => $provider_result->status_code(),
					'payload_summary' => $this->payload_summary( $payload ),
					'error_summary'   => $this->provider_error_summary( $provider_result ),
				)
			);

			return $this->failure( $this->provider_failure_code( $provider_id, $provider_result ), $material_id );
		}

		return CRM_Leads_Capture_Result::success(
			200,
			'Free material lead captured.',
			array(
				'redirect_url'             => $delivery_url,
				'material_id'              => $material_id,
				'provider'                 => $provider_id,
				'allow_external_redirect' => true,
			)
		);
	}

	public static function nonce_action(): string {
		return self::NONCE_ACTION;
	}

	public static function nonce_field(): string {
		return self::NONCE_FIELD;
	}

	public static function honeypot_field(): string {
		return self::HONEYPOT_FIELD;
	}

	public function current_error_message(): string {
		$request = $this->unslash_array( $_GET );

		$has_new_error    = 'error' === $this->clean_string( $request['crm_leads_capture'] ?? '' );
		$has_legacy_error = 'error' === $this->clean_string( $request['brevo_leads_capture'] ?? '' );
		if ( ! $has_new_error && ! $has_legacy_error ) {
			return '';
		}

		$code = $this->clean_string( $request['crm_error'] ?? ( $request['brevo_error'] ?? '' ) );
		if ( ! in_array( $code, CRM_Leads_Capture_Settings::ERROR_MESSAGE_CODES, true ) ) {
			$code = 'provider_error';
		}

		return $this->public_error_message( $code );
	}

	/**
	 * @param mixed $atts Shortcode attributes.
	 */
	public function render_error_message_shortcode( $atts = array() ): string {
		$message = $this->current_error_message();

		return $this->error_message_markup( $message );
	}

	public function render_error_message(): void {
		echo $this->error_message_markup( $this->current_error_message() );
	}

	private function provider( string $provider_id ): CRM_Leads_Capture_Provider_Interface {
		if ( null !== $this->provider_factory ) {
			$provider = call_user_func( $this->provider_factory, $provider_id );
			if ( $provider instanceof CRM_Leads_Capture_Provider_Interface ) {
				return $provider;
			}
		}

		return $this->providers->active( $provider_id );
	}

	/**
	 * @param array<string, mixed> $request
	 *
	 * @return array<string, mixed>
	 */
	private function lead_input_from_request( array $request ): array {
		$input = array(
			'name'     => $request['name'] ?? '',
			'email'    => $request['email'] ?? '',
			'whatsapp' => $request['whatsapp'] ?? '',
		);

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $utm_field ) {
			$input[ $utm_field ] = $request[ $utm_field ] ?? '';
		}

		return $input;
	}

	private function material_list_id( int $material_id ): int {
		$list_id = $this->absint( get_post_meta( $material_id, self::META_LIST_ID, true ) );
		if ( 0 >= $list_id ) {
			$list_id = $this->absint( get_post_meta( $material_id, self::META_LEGACY_LIST_ID, true ) );
		}

		return 0 < $list_id ? $list_id : $this->settings->brevo_default_list_id();
	}

	private function material_delivery_url( int $material_id ): string {
		$url = $this->material_delivery_url_override( $material_id );

		if ( '' !== $url ) {
			return $url;
		}

		return $this->settings->default_delivery_url();
	}

	private function material_delivery_url_override( int $material_id ): string {
		$url = $this->clean_url( get_post_meta( $material_id, self::META_DELIVERY_URL, true ) );

		if ( '' !== $url ) {
			return $url;
		}

		$url = $this->clean_url( get_post_meta( $material_id, self::META_LEGACY_DELIVERY_URL_BREVO, true ) );
		if ( '' !== $url ) {
			return $url;
		}

		return $this->clean_url( get_post_meta( $material_id, self::META_LEGACY_DELIVERY_URL, true ) );
	}

	private function material_provider_id( int $material_id ): string {
		$provider = $this->material_provider_override_id( $material_id );

		return in_array( $provider, array( 'brevo', 'rd_station' ), true ) ? $provider : $this->settings->active_provider();
	}

	private function material_provider_override_id( int $material_id ): string {
		$provider = $this->clean_string( get_post_meta( $material_id, self::META_PROVIDER, true ) );

		return in_array( $provider, array( 'brevo', 'rd_station' ), true ) ? $provider : '';
	}

	private function provider_label( string $provider_id ): string {
		$provider = $this->providers->get( $provider_id );

		return null !== $provider ? $provider->label() : $provider_id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function provider_context( int $material_id, string $provider_id ): array {
		if ( 'rd_station' === $provider_id ) {
			return array(
				'conversion_identifier' => $this->clean_string( get_post_meta( $material_id, self::META_RD_STATION_CONVERSION_IDENTIFIER, true ) ),
				'tags'                  => $this->clean_string( get_post_meta( $material_id, self::META_RD_STATION_TAGS, true ) ),
			);
		}

		return array( 'list_id' => $this->material_list_id( $material_id ) );
	}

	private function material_label( int $material_id ): string {
		$title = get_the_title( $material_id );

		return is_string( $title ) ? $this->clean_string( $title ) : '';
	}

	private function post_exists( int $post_id ): bool {
		return null !== get_post( $post_id );
	}

	/**
	 * @param mixed $nonce
	 */
	private function is_valid_nonce( $nonce ): bool {
		$nonce = $this->clean_string( $nonce );

		return '' !== $nonce && ( false !== wp_verify_nonce( $nonce, self::NONCE_ACTION ) || false !== wp_verify_nonce( $nonce, self::LEGACY_NONCE_ACTION ) );
	}

	private function failure( string $code, int $material_id ): CRM_Leads_Capture_Result {
		return CRM_Leads_Capture_Result::failure(
			0,
			'Free material lead capture failed.',
			array(
				'code'         => $code,
				'redirect_url' => $this->fallback_redirect_url( $material_id, $code ),
				'material_id'  => $material_id,
				'message'      => $this->public_error_message( $code ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 *
	 * @return array<string, mixed>
	 */
	private function payload_summary( array $payload ): array {
		return array(
			'has_email'      => isset( $payload['email'] ) && '' !== $payload['email'],
			'has_name'       => isset( $payload['name'] ) && '' !== $payload['name'],
			'has_whatsapp'   => isset( $payload['whatsapp'] ) && '' !== $payload['whatsapp'],
			'utm_keys'       => array_values(
				array_filter(
					array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ),
					static fn( string $key ): bool => isset( $payload[ $key ] ) && '' !== $payload[ $key ]
				)
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function provider_error_summary( CRM_Leads_Capture_Result $result ): array {
		$data = $result->data();

		if ( isset( $data['error_summary'] ) && is_array( $data['error_summary'] ) ) {
			return $data['error_summary'];
		}

		return array( 'status_code' => $result->status_code() );
	}

	private function provider_failure_code( string $provider_id, CRM_Leads_Capture_Result $result ): string {
		$summary = $this->provider_error_summary( $result );
		$code    = isset( $summary['code'] ) && is_string( $summary['code'] ) ? $summary['code'] : '';

		if ( 'rd_station' === $provider_id ) {
			if ( in_array( $result->status_code(), array( 401, 403 ), true ) ) {
				return 'rd_station_permission_error';
			}

			return 400 === $result->status_code() ? 'rd_station_bad_request' : 'rd_station_error';
		}

		switch ( $code ) {
			case 'invalid_parameter':
				return 'brevo_invalid_parameter';
			case 'missing_parameter':
				return 'brevo_missing_parameter';
			case 'duplicate_parameter':
				return 'brevo_duplicate_parameter';
			case 'document_not_found':
				return 'brevo_document_not_found';
			case 'unauthorized':
			case 'permission_denied':
				return 'brevo_permission_error';
		}

		if ( 400 === $result->status_code() ) {
			return 'brevo_bad_request';
		}

		return 'brevo_error';
	}

	private function fallback_redirect_url( int $material_id, string $code ): string {
		$url = 0 < $material_id ? get_permalink( $material_id ) : '';

		if ( ! is_string( $url ) || '' === $url ) {
			$url = wp_get_referer();
		}

		if ( ! is_string( $url ) || '' === $url ) {
			$url = home_url( '/' );
		}

		return add_query_arg(
			array(
				'crm_leads_capture' => 'error',
				'crm_error'          => $code,
			),
			$url
		);
	}

	private function safe_redirect( string $redirect_url, bool $allow_external_redirect ): void {
		if ( $allow_external_redirect ) {
			$host = $this->redirect_host( $redirect_url );

			if ( '' !== $host ) {
				add_filter(
					'allowed_redirect_hosts',
					static function ( array $hosts, string $requested_host ) use ( $host ): array {
						if ( strtolower( $requested_host ) === strtolower( $host ) && ! in_array( $host, $hosts, true ) ) {
							$hosts[] = $host;
						}

						return $hosts;
					},
					10,
					2
				);
			}
		}

		wp_safe_redirect( $redirect_url, 303 );
	}

	private function public_error_message( string $code ): string {
		return $this->settings->error_message( $code );
	}

	private function asset_version( string $path ): string {
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return CRM_LEADS_CAPTURE_VERSION . ( false !== $modified ? '-' . (string) $modified : '' );
	}

	private function rest_error_status( string $code ): int {
		if ( 'invalid_nonce' === $code ) {
			return 403;
		}

		if ( in_array( $code, array( 'missing_list', 'missing_delivery' ), true ) ) {
			return 500;
		}

		return 400;
	}

	private function error_message_markup( string $message ): string {
		$attributes = array(
			'class'                          => 'crm-leads-capture-message es-panel es-operational-feedback',
			'data-crm-leads-capture-message' => '',
			'data-tone'                      => 'muted',
			'data-padding'                   => 'md',
			'data-feedback-tone'             => 'danger',
			'role'                           => 'alert',
			'aria-live'                      => 'polite',
		);

		if ( '' === $message ) {
			$attributes['hidden'] = 'hidden';
		}

		$attribute_html = '';
		foreach ( $attributes as $name => $value ) {
			$attribute_html .= '' === $value
				? ' ' . esc_attr( $name )
				: ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return '<div' . $attribute_html . '><span class="es-badge" data-tone="danger">' . esc_html__( 'Erro', 'crm-leads-capture' ) . '</span><p class="es-operational-feedback__message">' . esc_html( $message ) . '</p></div>';
	}

	private function redirect_host( string $redirect_url ): string {
		$host = wp_parse_url( $redirect_url, PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}

	/**
	 * @param mixed $value
	 */
	private function clean_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * @param mixed $value
	 */
	private function clean_url( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return esc_url_raw( trim( (string) $value ) );
	}

	/**
	 * @param mixed $value
	 */
	private function absint( $value ): int {
		return max( 0, (int) $value );
	}

	private function update_or_delete_meta( int $post_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	private function is_valid_email( $value ): bool {
		$email = is_array( $value ) || is_object( $value ) ? '' : strtolower( $this->clean_string( $value ) );
		$email = function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : (string) filter_var( $email, FILTER_SANITIZE_EMAIL );

		return '' !== $email && ( function_exists( 'is_email' ) ? false !== is_email( $email ) : false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) );
	}

	/**
	 * @param array<string, mixed> $value
	 *
	 * @return array<string, mixed>
	 */
	private function unslash_array( array $value ): array {
		return array_map(
			function ( $item ) {
				if ( is_array( $item ) ) {
					return $this->unslash_array( $item );
				}

				return is_string( $item ) ? wp_unslash( $item ) : $item;
			},
			$value
		);
	}
}
