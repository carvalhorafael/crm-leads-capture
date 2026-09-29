<?php
/**
 * Compatibility adapter for the former service-interest capture contract.
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
	private ?CRM_Leads_Capture_Profile_Registry $profiles;
	private ?CRM_Leads_Capture_Processor $processor;

	/** @var callable|null */
	private $provider_factory;

	/**
	 * @param callable|null $provider_factory Optional provider factory for tests.
	 */
	public function __construct(
		CRM_Leads_Capture_Settings $settings,
		CRM_Leads_Capture_Provider_Registry $providers,
		?callable $provider_factory = null,
		?CRM_Leads_Capture_Logger $logger = null,
		?CRM_Leads_Capture_Profile_Registry $profiles = null,
		?CRM_Leads_Capture_Processor $processor = null
	) {
		$this->settings         = $settings;
		$this->providers        = $providers;
		$this->provider_factory = $provider_factory;
		$this->logger           = $logger ?: new CRM_Leads_Capture_Logger();
		$this->profiles         = $profiles;
		$this->processor        = $processor;
	}

	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_request' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_shortcode( self::SHORTCODE_MESSAGE, array( $this, 'render_message_shortcode' ) );
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

	public function handle_rest_request( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_params();
		if ( isset( $params[ self::REST_NONCE_FIELD ] ) ) {
			$params[ self::NONCE_FIELD ] = $params[ self::REST_NONCE_FIELD ];
		}

		$referer = $request->get_header( 'referer' );
		$result  = $this->process_submission( $params, is_string( $referer ) ? $referer : '' );
		$data    = $result->data();
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
		$referer = wp_get_referer();
		$result  = $this->process_submission( $request, is_string( $referer ) ? $referer : '' );
		$data    = $result->data();
		$status  = $result->is_successful() ? 'success' : 'error';
		$code    = isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';

		wp_safe_redirect( $this->fallback_redirect_url( $request, $status, $code ), 303 );
		exit;
	}

	/**
	 * Routes the legacy form through the profile processor without local storage.
	 *
	 * @param array<string, mixed> $request Submitted fields.
	 */
	public function process_submission( array $request, string $referer = '' ): CRM_Leads_Capture_Result {
		$processor = $this->processor;
		if ( null !== $this->provider_factory || null === $processor ) {
			$processor = $this->compatibility_processor();
		}

		$page_url = esc_url_raw( '' !== $referer ? $referer : $this->clean_string( $request['page_url'] ?? '' ) );
		$context  = array_filter(
			array(
				'page_url' => '' !== $page_url ? $page_url : null,
				'provider_overrides' => '' !== $this->clean_analytics_device_id( $request['analytics_device_id'] ?? '' )
					? array(
						'rd_station' => array( 'analytics_device_id' => $this->clean_analytics_device_id( $request['analytics_device_id'] ?? '' ) ),
					)
					: null,
			),
			static fn( $value ): bool => null !== $value
		);

		$result = $processor->process( CRM_Leads_Capture_Profile_Defaults::COO_SLUG, $request, $context );
		if ( ! $result->is_successful() && 'invalid_fields' === ( $result->data()['code'] ?? '' ) ) {
			return $this->failure( 'invalid_lead' );
		}

		return $result;
	}

	public function enqueue_frontend_assets(): void {
		$style_path  = CRM_LEADS_CAPTURE_DIR . 'assets/css/free-material-capture.css';
		$script_path = CRM_LEADS_CAPTURE_DIR . 'assets/js/service-interest-capture.js';
		wp_enqueue_style( 'crm-leads-capture-feedback', plugins_url( 'assets/css/free-material-capture.css', CRM_LEADS_CAPTURE_FILE ), array(), $this->asset_version( $style_path ) );
		wp_enqueue_script( 'crm-leads-capture-service-interest', plugins_url( 'assets/js/service-interest-capture.js', CRM_LEADS_CAPTURE_FILE ), array(), $this->asset_version( $script_path ), true );
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
			return array( 'tone' => 'success', 'label' => __( 'Recebido', 'crm-leads-capture' ), 'message' => $this->settings->service_success_message() );
		}
		if ( 'error' !== $status ) {
			return array();
		}

		return array( 'tone' => 'danger', 'label' => __( 'Erro', 'crm-leads-capture' ), 'message' => $this->error_message( $this->clean_string( $request['crm_error'] ?? '' ) ) );
	}

	public function render_message_shortcode(): string {
		return $this->message_markup( $this->current_message() );
	}

	public function render_message(): void {
		// The markup is escaped while it is assembled by message_markup().
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->message_markup( $this->current_message() );
	}

	private function compatibility_processor(): CRM_Leads_Capture_Processor {
		$profile = null !== $this->profiles ? $this->profiles->resolve( CRM_Leads_Capture_Profile_Defaults::COO_SLUG ) : null;
		if ( null === $profile ) {
			$profile = ( new CRM_Leads_Capture_Profile_Defaults( $this->settings ) )->coo_profile();
		}
		$profiles = new CRM_Leads_Capture_Profile_Registry();
		$profiles->register( $profile );

		$provider_id = $this->settings->active_provider();
		$provider    = $this->providers->active( $provider_id );
		if ( null !== $this->provider_factory ) {
			$candidate = call_user_func( $this->provider_factory, $provider_id );
			if ( $candidate instanceof CRM_Leads_Capture_Provider_Interface ) {
				$provider = $candidate;
			}
		}
		$providers = new CRM_Leads_Capture_Provider_Registry();
		$providers->register( new CRM_Leads_Capture_Recording_Provider( $provider, $provider_id ) );

		return new CRM_Leads_Capture_Processor(
			$profiles,
			$providers,
			static fn(): string => $provider_id,
			static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action ),
			$this->logger,
			null === $this->provider_factory ? fn( string $id ): string => $this->settings->provider_configuration_error( $id ) : null
		);
	}

	private function failure( string $code ): CRM_Leads_Capture_Result {
		return CRM_Leads_Capture_Result::failure( 422, 'Service interest capture failed.', array( 'code' => $code, 'message' => $this->error_message( $code ) ) );
	}

	private function error_message( string $code ): string {
		if ( 'invalid_nonce' === $code ) {
			return __( 'Sua sessão expirou. Atualize a página e tente novamente.', 'crm-leads-capture' );
		}
		if ( 'invalid_lead' === $code || 'invalid_fields' === $code ) {
			return __( 'Revise os campos obrigatórios e tente novamente.', 'crm-leads-capture' );
		}
		if ( 'spam' === $code ) {
			return __( 'Não foi possível validar o envio. Atualize a página e tente novamente.', 'crm-leads-capture' );
		}

		return __( 'Não foi possível enviar seus dados agora. Tente novamente mais tarde.', 'crm-leads-capture' );
	}

	/** @param array<string, mixed> $request */
	private function fallback_redirect_url( array $request, string $status, string $code ): string {
		$url = esc_url_raw( $this->clean_string( $request['page_url'] ?? '' ) );
		if ( '' === $url ) {
			$referer = wp_get_referer();
			$url     = is_string( $referer ) ? $referer : home_url( '/' );
		}
		$args = array( 'crm_leads_capture' => $status, 'capture_type' => 'service_interest' );
		if ( '' !== $code ) {
			$args['crm_error'] = $code;
		}

		return add_query_arg( $args, $url ) . '#conversar';
	}

	/** @param array<string, string> $message */
	private function message_markup( array $message ): string {
		$tone   = $message['tone'] ?? 'danger';
		$label  = $message['label'] ?? '';
		$text   = $message['message'] ?? '';
		$hidden = '' === $text ? ' hidden="hidden"' : '';

		return '<div class="crm-leads-capture-message" data-crm-leads-capture-message data-feedback-tone="' . esc_attr( $tone ) . '" role="status" aria-live="polite" tabindex="-1"' . $hidden . '><span class="crm-leads-capture-message__badge">' . esc_html( $label ) . '</span><p class="crm-leads-capture-message__text">' . esc_html( $text ) . '</p></div>';
	}

	private function clean_analytics_device_id( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		return substr( (string) preg_replace( '/[^A-Za-z0-9._:-]/', '', $value ), 0, 128 );
	}

	private function asset_version( string $path ): string {
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return CRM_LEADS_CAPTURE_VERSION . ( false !== $modified ? '-' . (string) $modified : '' );
	}

	private function clean_string( $value ): string {
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/** @param array<string, mixed> $value @return array<string, mixed> */
	private function unslash_array( array $value ): array {
		return wp_unslash( $value );
	}
}
