<?php
/**
 * Generic frontend contract for profile-based captures.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Frontend {
	public const ACTION = 'crm_leads_capture_submit';
	public const PROFILE_FIELD = 'crm_leads_capture_profile';
	public const REST_NONCE_FIELD = 'crm_leads_capture_nonce';
	public const REST_NAMESPACE = 'crm-leads-capture/v1';
	public const REST_ROUTE = '/capture/(?P<profile>[a-z0-9_-]+)';
	public const REST_NONCE_ROUTE = '/capture/(?P<profile>[a-z0-9_-]+)/nonce';

	private CRM_Leads_Capture_Profile_Registry $profiles;

	private CRM_Leads_Capture_Processor $processor;

	private CRM_Leads_Capture_Settings $settings;

	public function __construct(
		CRM_Leads_Capture_Profile_Registry $profiles,
		CRM_Leads_Capture_Processor $processor,
		CRM_Leads_Capture_Settings $settings
	) {
		$this->profiles  = $profiles;
		$this->processor = $processor;
		$this->settings  = $settings;
	}

	public function register_hooks(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_admin_post' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_admin_post' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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

	public function handle_rest_nonce_request( WP_REST_Request $request ): WP_REST_Response {
		$profile = $this->profiles->resolve( $this->request_profile_slug( $request ) );
		if ( null === $profile ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => 'profile_not_found',
					'message' => $this->public_error_message( 'profile_not_found' ),
				),
				404
			);
		}

		$response = rest_ensure_response(
			array(
				'success' => true,
				'nonce'   => wp_create_nonce( $profile->nonce_action() ),
			)
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}

	public function handle_rest_request( WP_REST_Request $request ): WP_REST_Response {
		$profile_slug = $this->request_profile_slug( $request );
		$input        = $request->get_params();
		$profile      = $this->profiles->resolve( $profile_slug );
		if ( null !== $profile && isset( $input[ self::REST_NONCE_FIELD ] ) ) {
			$input[ $profile->nonce_field() ] = $input[ self::REST_NONCE_FIELD ];
		}

		$referer = $request->get_header( 'referer' );
		$result  = $this->process_submission( $profile_slug, $input, is_string( $referer ) ? $referer : '' );

		return $this->rest_response( $profile_slug, $result );
	}

	/**
	 * Processes a generic submission without coupling transports to providers.
	 *
	 * @param array<string, mixed> $input Untrusted request fields.
	 */
	public function process_submission( string $profile_slug, array $input, string $referer = '' ): CRM_Leads_Capture_Result {
		return $this->processor->process( $profile_slug, $input, $this->trusted_context( $referer ) );
	}

	public function handle_admin_post(): void {
		$input        = $this->unslash_array( $_POST );
		$profile_slug = $this->clean_key( $input[ self::PROFILE_FIELD ] ?? '' );
		$referer      = wp_get_referer();
		$referer      = is_string( $referer ) ? $referer : '';
		$result       = $this->process_submission( $profile_slug, $input, $referer );
		$redirect_url = $this->fallback_redirect_url( $profile_slug, $result, $referer );

		$this->safe_redirect( $redirect_url, $this->is_server_configured_redirect( $profile_slug, $redirect_url, $result ) );
		exit;
	}

	/**
	 * Returns the server-rendered hidden fields required by a generic form.
	 */
	public function form_fields( string $profile_slug ): string {
		$profile = $this->profiles->resolve( $profile_slug );
		if ( null === $profile ) {
			return '';
		}

		return '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::PROFILE_FIELD ) . '" value="' . esc_attr( $profile->slug() ) . '">'
			. wp_nonce_field( $profile->nonce_action(), $profile->nonce_field(), true, false )
			. '<input type="text" name="' . esc_attr( $profile->honeypot_field() ) . '" value="" tabindex="-1" autocomplete="off" aria-hidden="true" class="crm-leads-capture-honeypot">';
	}

	public function message_markup( string $profile_slug = '' ): string {
		$query           = $this->unslash_array( $_GET );
		$request_profile = $this->clean_key( $query['capture_profile'] ?? '' );
		$status          = $this->clean_key( $query['crm_leads_capture'] ?? '' );
		$code            = $this->clean_key( $query['crm_error'] ?? '' );
		$matches_profile = '' === $profile_slug || '' === $request_profile || $this->clean_key( $profile_slug ) === $request_profile;
		$message         = '';
		$tone            = 'danger';
		$label           = __( 'Erro', 'crm-leads-capture' );

		if ( $matches_profile && 'success' === $status ) {
			$profile = $this->profiles->resolve( $request_profile );
			$success = null !== $profile ? $profile->success_behavior() : array();
			$message = $this->clean_text( $success['message'] ?? $this->settings->success_message() );
			$tone    = 'success';
			$label   = __( 'Sucesso', 'crm-leads-capture' );
		} elseif ( $matches_profile && 'error' === $status ) {
			$message = $this->public_error_message( $code );
		}

		$hidden = '' === $message ? ' hidden="hidden"' : '';

		return '<div class="crm-leads-capture-message" data-crm-leads-capture-message data-feedback-tone="' . esc_attr( $tone ) . '" role="status" aria-live="polite" tabindex="-1"' . $hidden . '><span class="crm-leads-capture-message__badge">' . esc_html( $label ) . '</span><p class="crm-leads-capture-message__text">' . esc_html( $message ) . '</p></div>';
	}

	public function enqueue_assets(): void {
		$style_path  = CRM_LEADS_CAPTURE_DIR . 'assets/css/free-material-capture.css';
		$script_path = CRM_LEADS_CAPTURE_DIR . 'assets/js/capture.js';

		wp_enqueue_style(
			'crm-leads-capture-feedback',
			plugins_url( 'assets/css/free-material-capture.css', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $style_path )
		);
		wp_enqueue_script(
			'crm-leads-capture',
			plugins_url( 'assets/js/capture.js', CRM_LEADS_CAPTURE_FILE ),
			array(),
			$this->asset_version( $script_path ),
			true
		);
		wp_localize_script(
			'crm-leads-capture',
			'CRMLeadsCapture',
			array(
				'restBase'            => esc_url_raw( rest_url( self::REST_NAMESPACE . '/capture/' ) ),
				'nonceField'          => self::REST_NONCE_FIELD,
				'genericMessage'      => $this->settings->error_message( 'provider_error' ),
				'invalidNonceMessage' => $this->settings->error_message( 'invalid_nonce' ),
				'successMessage'      => $this->settings->success_message(),
				'successLabel'        => __( 'Sucesso', 'crm-leads-capture' ),
				'errorLabel'          => __( 'Erro', 'crm-leads-capture' ),
			)
		);
	}

	private function rest_response( string $profile_slug, CRM_Leads_Capture_Result $result ): WP_REST_Response {
		if ( ! $result->is_successful() ) {
			$code = $this->clean_key( $result->data()['code'] ?? 'provider_error' );

			return new WP_REST_Response(
				array(
					'success' => false,
					'code'    => $code,
					'message' => $this->public_error_message( $code ),
				),
				0 < $result->status_code() ? $result->status_code() : 400
			);
		}

		$profile  = $this->profiles->resolve( $profile_slug );
		$behavior = null !== $profile ? $profile->success_behavior() : array();
		$data     = array(
			'success' => true,
			'profile' => $profile_slug,
			'message' => $this->clean_text( $behavior['message'] ?? $this->settings->success_message() ),
		);
		$redirect_url = $this->clean_url( $behavior['redirect_url'] ?? '' );
		if ( '' !== $redirect_url ) {
			$data['redirect_url'] = $redirect_url;
		}

		return new WP_REST_Response( $data, 200 );
	}

	private function fallback_redirect_url( string $profile_slug, CRM_Leads_Capture_Result $result, string $referer ): string {
		if ( $result->is_successful() ) {
			$redirect_url = $this->success_redirect_url( $profile_slug );
			if ( '' !== $redirect_url ) {
				return $redirect_url;
			}
		}

		if ( '' === $referer ) {
			$referer = home_url( '/' );
		}

		return add_query_arg(
			array(
				'crm_leads_capture' => $result->is_successful() ? 'success' : 'error',
				'capture_profile'   => $profile_slug,
				'crm_error'         => $result->is_successful() ? false : $this->clean_key( $result->data()['code'] ?? 'provider_error' ),
			),
			$referer
		);
	}

	private function success_redirect_url( string $profile_slug ): string {
		$profile  = $this->profiles->resolve( $profile_slug );
		$behavior = null !== $profile ? $profile->success_behavior() : array();

		return $this->clean_url( $behavior['redirect_url'] ?? '' );
	}

	private function is_server_configured_redirect( string $profile_slug, string $redirect_url, CRM_Leads_Capture_Result $result ): bool {
		$configured_url = $result->is_successful() ? $this->success_redirect_url( $profile_slug ) : '';

		return '' !== $configured_url && $configured_url === $redirect_url;
	}

	private function public_error_message( string $code ): string {
		$mapped_code = array(
			'invalid_fields'       => 'invalid_lead',
			'profile_not_found'    => 'provider_error',
			'provider_unavailable' => 'provider_error',
		)[ $code ] ?? $code;

		return $this->settings->error_message( $mapped_code );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function trusted_context( string $referer ): array {
		$referer = $this->clean_url( $referer );
		$page_id = '' !== $referer ? url_to_postid( $referer ) : 0;

		return array_filter(
			array(
				'page_id'  => 0 < $page_id ? $page_id : null,
				'page_url' => '' !== $referer ? $referer : null,
			),
			static fn( $value ): bool => null !== $value
		);
	}

	private function request_profile_slug( WP_REST_Request $request ): string {
		$route = $request->get_url_params();

		return $this->clean_key( $route['profile'] ?? '' );
	}

	private function safe_redirect( string $redirect_url, bool $server_configured ): void {
		if ( $server_configured ) {
			$host = wp_parse_url( $redirect_url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
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

	private function clean_key( $value ): string {
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	private function clean_text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private function clean_url( $value ): string {
		return is_scalar( $value ) ? esc_url_raw( (string) $value ) : '';
	}

	/**
	 * @param array<string, mixed> $value Raw request.
	 * @return array<string, mixed>
	 */
	private function unslash_array( array $value ): array {
		return wp_unslash( $value );
	}

	private function asset_version( string $path ): string {
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		return CRM_LEADS_CAPTURE_VERSION . ( false !== $modified ? '-' . (string) $modified : '' );
	}
}
