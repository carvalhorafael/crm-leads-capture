<?php
/**
 * RD Station Marketing API client.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_RD_Station_Client {
	private const CONVERSIONS_ENDPOINT = 'https://api.rd.services/platform/conversions';

	private string $api_key;

	/** @var callable|null */
	private $http_client;

	private string $endpoint;

	public function __construct( string $api_key, ?callable $http_client = null, string $endpoint = self::CONVERSIONS_ENDPOINT ) {
		$this->api_key     = trim( $api_key );
		$this->http_client = $http_client;
		$this->endpoint    = $endpoint;
	}

	/**
	 * @param array<string, mixed> $event
	 */
	public function send_conversion( array $event ): CRM_Leads_Capture_Result {
		if ( '' === $this->api_key ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station API key is not configured.' );
		}

		$url      = add_query_arg( 'api_key', rawurlencode( $this->api_key ), $this->endpoint );
		$response = $this->post( $url, $event );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $response ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station request failed.' );
		}

		$status_code = function_exists( 'wp_remote_retrieve_response_code' ) ? (int) wp_remote_retrieve_response_code( $response ) : (int) ( $response['response']['code'] ?? 0 );
		$body        = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : (string) ( $response['body'] ?? '' );
		$decoded     = '' !== $body ? json_decode( $body, true ) : null;
		$decoded     = is_array( $decoded ) ? $decoded : null;

		if ( in_array( $status_code, array( 200, 201, 202, 204 ), true ) ) {
			return CRM_Leads_Capture_Result::success( $status_code, 'RD Station conversion sent.', array( 'body' => $decoded ) );
		}

		return CRM_Leads_Capture_Result::failure(
			$status_code,
			'RD Station request returned an error.',
			array(
				'body'          => $decoded,
				'error_summary' => $this->error_summary( $status_code, $decoded ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 *
	 * @return mixed
	 */
	private function post( string $url, array $payload ) {
		$args = array(
			'timeout' => 10,
			'headers' => array( 'content-type' => 'application/json' ),
			'body'    => function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload ),
		);

		if ( null !== $this->http_client ) {
			return call_user_func( $this->http_client, $url, $args );
		}

		return function_exists( 'wp_remote_post' ) ? wp_remote_post( $url, $args ) : null;
	}

	/**
	 * @param array<string, mixed>|null $decoded
	 *
	 * @return array<string, mixed>
	 */
	private function error_summary( int $status_code, ?array $decoded ): array {
		$summary = array( 'status_code' => $status_code );
		if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
			$summary['errors_count'] = count( $decoded['errors'] );
		}
		foreach ( array( 'error', 'message', 'error_type', 'error_message' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
				$summary[ $key ] = (string) $decoded[ $key ];
			}
		}

		return $summary;
	}
}
