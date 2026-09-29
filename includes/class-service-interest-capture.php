<?php
/**
 * Service interest lead capture handler.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Service_Interest_Capture {
	public const ACTION = 'crm_leads_capture_service_interest';
	public const NONCE_ACTION = 'crm_leads_capture_service_interest';
	public const NONCE_FIELD = '_wpnonce';
	public const REST_NONCE_FIELD = 'crm_leads_capture_nonce';
	public const HONEYPOT_FIELD = 'crm_leads_capture_website';
	public const REST_NAMESPACE = 'crm-leads-capture/v1';
	public const REST_ROUTE = '/service-interest';
	public const REST_NONCE_ROUTE = '/service-interest/nonce';
	public const SHORTCODE_MESSAGE = 'crm_leads_capture_service_interest_message';
	public const POST_TYPE = 'crm_service_interest';

	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Logger $logger;

	/**
	 * @var callable|null
	 */
	private $provider_factory;

	/**
	 * @param callable|null $provider_factory Optional provider factory for tests.
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'register_details_meta_box' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'filter_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_shortcode( self::SHORTCODE_MESSAGE, array( $this, 'render_message_shortcode' ) );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Interesses em serviços', 'crm-leads-capture' ),
					'singular_name' => __( 'Interesse em serviço', 'crm-leads-capture' ),
					'menu_name'     => __( 'Interesses em serviços', 'crm-leads-capture' ),
					'edit_item'     => __( 'Ver interesse em serviço', 'crm-leads-capture' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'options-general.php',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => array( 'title' ),
				'capabilities'        => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'delete_posts'       => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'do_not_allow',
				),
				'map_meta_cap'        => false,
			)
		);
	}

	public function register_rest_routes(): void {
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
		$response = rest_ensure_response( array( 'nonce' => wp_create_nonce( self::NONCE_ACTION ) ) );
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
		}

		$result = $this->process_submission( $params );
		$data   = $result->data();

		if ( $result->is_successful() ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => $this->settings->service_success_message(),
				)
			);
		}

		$code = isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : 'provider_error';

		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $this->error_message( $code ),
			),
			'invalid_nonce' === $code ? 403 : 400
		);
	}

	public function handle_request(): void {
		$request = $this->unslash_array( $_POST );
		$result  = $this->process_submission( $request );
		$data    = $result->data();
		$status  = $result->is_successful() ? 'success' : 'error';
		$code    = isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';

		wp_safe_redirect( $this->fallback_redirect_url( $request, $status, $code ), 303 );
		exit;
	}

	/**
	 * @param array<string, mixed> $request Submitted fields.
	 */
	public function process_submission( array $request ): CRM_Leads_Capture_Result {
		if ( ! $this->is_valid_nonce( $request[ self::NONCE_FIELD ] ?? '' ) ) {
			return $this->failure( 'invalid_nonce' );
		}

		if ( '' !== $this->clean_string( $request[ self::HONEYPOT_FIELD ] ?? '' ) ) {
			return $this->failure( 'spam' );
		}

		$lead = $this->lead_from_request( $request );
		if ( ! $this->is_valid_lead( $lead ) ) {
			return $this->failure( 'invalid_lead' );
		}

		$provider_id = $this->settings->active_provider();
		$provider    = $this->provider( $provider_id );
		$lead_id     = $this->store_interest( $lead, $provider_id );
		$context     = $this->provider_context( $provider_id, $request );

		if ( 'brevo' === $provider_id && empty( $context['list_id'] ) ) {
			$this->update_interest_status( $lead_id, 'failed', 'missing_list' );
			return $this->failure( 'missing_list', $lead_id );
		}

		$payload = array(
			'name'         => $lead['name'],
			'email'        => $lead['email'],
			'whatsapp'     => $lead['whatsapp'],
			'source'       => 'coo_as_a_service',
			'material'     => 'COO as a Service',
			'utm_source'   => $lead['utm_source'],
			'utm_medium'   => $lead['utm_medium'],
			'utm_campaign' => $lead['utm_campaign'],
			'utm_term'     => $lead['utm_term'],
			'utm_content'  => $lead['utm_content'],
		);

		$provider_result = $provider->send_lead( $payload, $context );
		if ( ! $provider_result->is_successful() ) {
			$code = $this->provider_failure_code( $provider_id, $provider_result );
			$this->update_interest_status( $lead_id, 'failed', $code );
			$this->logger->debug(
				'Service interest CRM provider request failed.',
				array(
					'lead_id'     => $lead_id,
					'provider'    => $provider_id,
					'status_code' => $provider_result->status_code(),
				)
			);

			return $this->failure( $code, $lead_id );
		}

		$this->update_interest_status( $lead_id, 'sent', '' );

		return CRM_Leads_Capture_Result::success(
			200,
			'Service interest captured.',
			array(
				'lead_id'  => $lead_id,
				'provider' => $provider_id,
			)
		);
	}

	public function enqueue_frontend_assets(): void {
		$style_path  = CRM_LEADS_CAPTURE_DIR . 'assets/css/free-material-capture.css';
		$script_path = CRM_LEADS_CAPTURE_DIR . 'assets/js/service-interest-capture.js';

		wp_enqueue_style(
			'crm-leads-capture-feedback',
			plugins_url( 'assets/css/free-material-capture.css', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $style_path )
		);
		wp_enqueue_script(
			'crm-leads-capture-service-interest',
			plugins_url( 'assets/js/service-interest-capture.js', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $script_path ),
			true
		);
		wp_localize_script(
			'crm-leads-capture-service-interest',
			'CRMLeadsCaptureServiceInterest',
			array(
				'restUrl'             => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
				'nonceUrl'            => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_NONCE_ROUTE ) ),
				'genericMessage'      => $this->error_message( 'provider_error' ),
				'invalidNonceMessage' => $this->error_message( 'invalid_nonce' ),
				'successMessage'      => $this->settings->service_success_message(),
				'successLabel'        => __( 'Recebido', 'crm-leads-capture' ),
				'errorLabel'          => __( 'Erro', 'crm-leads-capture' ),
			)
		);
	}

	public function current_message(): array {
		$request = $this->unslash_array( $_GET );
		if ( 'service_interest' !== $this->clean_string( $request['capture_type'] ?? '' ) ) {
			return array();
		}

		$status = $this->clean_string( $request['crm_leads_capture'] ?? '' );
		if ( 'success' === $status ) {
			return array(
				'tone'    => 'success',
				'label'   => __( 'Recebido', 'crm-leads-capture' ),
				'message' => $this->settings->service_success_message(),
			);
		}

		if ( 'error' !== $status ) {
			return array();
		}

		$code = $this->clean_string( $request['crm_error'] ?? '' );
		if ( ! in_array( $code, CRM_Leads_Capture_Settings::ERROR_MESSAGE_CODES, true ) ) {
			$code = 'provider_error';
		}

		return array(
			'tone'    => 'danger',
			'label'   => __( 'Erro', 'crm-leads-capture' ),
			'message' => $this->error_message( $code ),
		);
	}

	public function render_message_shortcode(): string {
		return $this->message_markup( $this->current_message() );
	}

	public function render_message(): void {
		// The markup is escaped while it is assembled by message_markup().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->message_markup( $this->current_message() );
	}

	public function register_details_meta_box(): void {
		add_meta_box(
			'crm_service_interest_details',
			__( 'Detalhes do interesse', 'crm-leads-capture' ),
			array( $this, 'render_details_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Service interest post.
	 */
	public function render_details_meta_box( $post ): void {
		$fields = array(
			'email'       => __( 'E-mail', 'crm-leads-capture' ),
			'whatsapp'    => __( 'WhatsApp', 'crm-leads-capture' ),
			'company'     => __( 'Empresa', 'crm-leads-capture' ),
			'role'        => __( 'Papel', 'crm-leads-capture' ),
			'company_url' => __( 'Site ou LinkedIn', 'crm-leads-capture' ),
			'challenge'   => __( 'Dependência operacional relatada', 'crm-leads-capture' ),
			'page_url'    => __( 'Página de origem', 'crm-leads-capture' ),
			'provider'    => __( 'Provider', 'crm-leads-capture' ),
			'status'      => __( 'Status de envio', 'crm-leads-capture' ),
			'error_code'  => __( 'Código de erro', 'crm-leads-capture' ),
		);
		?>
		<table class="widefat striped">
			<tbody>
				<?php foreach ( $fields as $key => $label ) : ?>
					<?php $value = (string) get_post_meta( $post->ID, '_crm_service_interest_' . $key, true ); ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><?php echo 'challenge' === $key ? nl2br( esc_html( $value ) ) : esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param array<string, string> $columns Admin columns.
	 * @return array<string, string>
	 */
	public function filter_admin_columns( array $columns ): array {
		return array(
			'cb'      => $columns['cb'] ?? '',
			'title'   => __( 'Contato', 'crm-leads-capture' ),
			'company' => __( 'Empresa', 'crm-leads-capture' ),
			'email'   => __( 'E-mail', 'crm-leads-capture' ),
			'status'  => __( 'Status', 'crm-leads-capture' ),
			'date'    => $columns['date'] ?? __( 'Data', 'crm-leads-capture' ),
		);
	}

	public function render_admin_column( string $column, int $post_id ): void {
		$allowed = array( 'company', 'email', 'status' );
		if ( in_array( $column, $allowed, true ) ) {
			echo esc_html( (string) get_post_meta( $post_id, '_crm_service_interest_' . $column, true ) );
		}
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
	 * @param array<string, mixed> $request Submitted fields.
	 * @return array<string, string>
	 */
	private function lead_from_request( array $request ): array {
		$lead = array(
			'name'        => $this->clean_string( $request['name'] ?? '' ),
			'email'       => sanitize_email( $this->clean_string( $request['email'] ?? '' ) ),
			'whatsapp'    => $this->clean_string( $request['whatsapp'] ?? '' ),
			'company'     => $this->clean_string( $request['company'] ?? '' ),
			'role'        => $this->clean_string( $request['role'] ?? '' ),
			'company_url' => esc_url_raw( $this->clean_string( $request['company_url'] ?? '' ) ),
			'challenge'   => sanitize_textarea_field( $this->scalar_string( $request['challenge'] ?? '' ) ),
			'consent'     => $this->clean_string( $request['consent'] ?? '' ),
			'page_url'    => esc_url_raw( $this->clean_string( $request['page_url'] ?? '' ) ),
		);

		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $utm_field ) {
			$lead[ $utm_field ] = $this->clean_string( $request[ $utm_field ] ?? '' );
		}

		return $lead;
	}

	/**
	 * @param array<string, string> $lead Normalized lead.
	 */
	private function is_valid_lead( array $lead ): bool {
		return '' !== $lead['name']
			&& false !== is_email( $lead['email'] )
			&& '' !== $lead['company']
			&& in_array( $lead['role'], array( 'founder', 'ceo', 'executive' ), true )
			&& '' !== $lead['challenge']
			&& '1' === $lead['consent'];
	}

	/**
	 * @param array<string, string> $lead Normalized lead.
	 */
	private function store_interest( array $lead, string $provider_id ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => sprintf( '%s — %s', $lead['name'], $lead['company'] ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->logger->debug( 'Could not store service interest.', array( 'error' => $post_id->get_error_code() ) );
			return 0;
		}

		$stored = array_merge(
			$lead,
			array(
				'provider'   => $provider_id,
				'status'     => 'pending',
				'error_code' => '',
			)
		);

		foreach ( $stored as $key => $value ) {
			if ( 'consent' !== $key ) {
				update_post_meta( $post_id, '_crm_service_interest_' . $key, $value );
			}
		}

		update_post_meta( $post_id, '_crm_service_interest_consent_at', gmdate( 'c' ) );

		return (int) $post_id;
	}

	private function update_interest_status( int $post_id, string $status, string $error_code ): void {
		if ( 0 >= $post_id ) {
			return;
		}

		update_post_meta( $post_id, '_crm_service_interest_status', $status );
		update_post_meta( $post_id, '_crm_service_interest_error_code', $error_code );
	}

	/**
	 * @param array<string, mixed> $request Submitted fields.
	 * @return array<string, mixed>
	 */
	private function provider_context( string $provider_id, array $request ): array {
		if ( 'rd_station' === $provider_id ) {
			return array(
				'conversion_identifier' => 'COO as a Service - Interesse',
				'tags'                  => $this->settings->rd_station_default_tags(),
				'analytics_device_id'   => $this->clean_analytics_device_id( $request['analytics_device_id'] ?? '' ),
			);
		}

		return array( 'list_id' => $this->settings->brevo_default_list_id() );
	}

	private function provider_failure_code( string $provider_id, CRM_Leads_Capture_Result $result ): string {
		$data = $result->data();
		$code = isset( $data['error_summary']['code'] ) && is_string( $data['error_summary']['code'] ) ? $data['error_summary']['code'] : '';

		if ( 'rd_station' === $provider_id ) {
			if ( in_array( $result->status_code(), array( 401, 403 ), true ) ) {
				return 'rd_station_permission_error';
			}

			return 400 === $result->status_code() ? 'rd_station_bad_request' : 'rd_station_error';
		}

		$codes = array(
			'invalid_parameter'   => 'brevo_invalid_parameter',
			'missing_parameter'   => 'brevo_missing_parameter',
			'duplicate_parameter' => 'brevo_duplicate_parameter',
			'document_not_found' => 'brevo_document_not_found',
			'unauthorized'         => 'brevo_permission_error',
			'permission_denied'    => 'brevo_permission_error',
		);

		return $codes[ $code ] ?? ( 400 === $result->status_code() ? 'brevo_bad_request' : 'brevo_error' );
	}

	private function failure( string $code, int $lead_id = 0 ): CRM_Leads_Capture_Result {
		return CRM_Leads_Capture_Result::failure(
			0,
			'Service interest capture failed.',
			array(
				'code'    => $code,
				'lead_id' => $lead_id,
				'message' => $this->error_message( $code ),
			)
		);
	}

	private function error_message( string $code ): string {
		if ( 'invalid_nonce' === $code ) {
			return __( 'Sua sessão expirou. Atualize a página e tente novamente.', 'crm-leads-capture' );
		}

		if ( 'invalid_lead' === $code ) {
			return __( 'Revise os campos obrigatórios e tente novamente.', 'crm-leads-capture' );
		}

		if ( 'spam' === $code ) {
			return __( 'Não foi possível validar o envio. Atualize a página e tente novamente.', 'crm-leads-capture' );
		}

		return __( 'Não foi possível enviar seus dados agora. Tente novamente mais tarde.', 'crm-leads-capture' );
	}

	/**
	 * @param array<string, mixed> $request Submitted fields.
	 */
	private function fallback_redirect_url( array $request, string $status, string $code ): string {
		$url = esc_url_raw( $this->clean_string( $request['page_url'] ?? '' ) );
		if ( '' === $url ) {
			$referer = wp_get_referer();
			$url     = is_string( $referer ) ? $referer : home_url( '/' );
		}

		$args = array(
			'crm_leads_capture' => $status,
			'capture_type'      => 'service_interest',
		);
		if ( '' !== $code ) {
			$args['crm_error'] = $code;
		}

		return add_query_arg( $args, $url ) . '#conversar';
	}

	/**
	 * @param array<string, string> $message Message data.
	 */
	private function message_markup( array $message ): string {
		$tone   = $message['tone'] ?? 'danger';
		$label  = $message['label'] ?? '';
		$text   = $message['message'] ?? '';
		$hidden = '' === $text ? ' hidden="hidden"' : '';

		return '<div class="crm-leads-capture-message" data-crm-leads-capture-message data-feedback-tone="' . esc_attr( $tone ) . '" role="status" aria-live="polite" tabindex="-1"' . $hidden . '><span class="crm-leads-capture-message__badge">' . esc_html( $label ) . '</span><p class="crm-leads-capture-message__text">' . esc_html( $text ) . '</p></div>';
	}

	private function is_valid_nonce( $nonce ): bool {
		$nonce = $this->clean_string( $nonce );

		return '' !== $nonce && false !== wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	private function clean_analytics_device_id( $value ): string {
		$value = $this->scalar_string( $value );

		return substr( (string) preg_replace( '/[^A-Za-z0-9._:-]/', '', $value ), 0, 128 );
	}

	private function asset_version( string $path ): string {
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return CRM_LEADS_CAPTURE_VERSION . ( false !== $modified ? '-' . (string) $modified : '' );
	}

	private function clean_string( $value ): string {
		return sanitize_text_field( $this->scalar_string( $value ) );
	}

	private function scalar_string( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * @param array<string, mixed> $value Raw request.
	 * @return array<string, mixed>
	 */
	private function unslash_array( array $value ): array {
		return function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
	}
}
