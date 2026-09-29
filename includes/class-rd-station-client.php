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
		if ( ! $this->is_valid_event( $event ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station conversion payload is invalid.', array( 'code' => 'invalid_payload' ) );
		}

		$url      = $this->endpoint_url();
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

	private function endpoint_url(): string {
		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( 'api_key', $this->api_key, $this->endpoint );
		}

		$separator = str_contains( $this->endpoint, '?' ) ? '&' : '?';

		return $this->endpoint . $separator . 'api_key=' . rawurlencode( $this->api_key );
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
			$error_types = array();
			$paths       = array();
			foreach ( $decoded['errors'] as $error ) {
				if ( ! is_array( $error ) ) {
					continue;
				}
				if ( isset( $error['error_type'] ) && is_scalar( $error['error_type'] ) ) {
					$error_types[] = (string) $error['error_type'];
				}
				if ( isset( $error['path'] ) && is_scalar( $error['path'] ) ) {
					$paths[] = (string) $error['path'];
				}
			}
			$summary['error_types'] = array_values( array_unique( $error_types ) );
			$summary['paths']       = array_values( array_unique( $paths ) );
		}

		return $summary;
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private function is_valid_event( array $event ): bool {
		if ( 'CONVERSION' !== ( $event['event_type'] ?? '' ) || 'CDP' !== ( $event['event_family'] ?? '' ) || ! is_array( $event['payload'] ?? null ) ) {
			return false;
		}

		$payload = $event['payload'];
		if ( ! is_string( $payload['conversion_identifier'] ?? null ) || '' === trim( $payload['conversion_identifier'] ) ) {
			return false;
		}
		if ( ! is_string( $payload['email'] ?? null ) || false === filter_var( $payload['email'], FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		$standard = array(
			'conversion_identifier', 'name', 'email', 'job_title', 'state', 'city', 'country', 'personal_phone',
			'mobile_phone', 'twitter', 'facebook', 'linkedin', 'website', 'company_name', 'company_site',
			'company_address', 'client_tracking_id', 'traffic_source', 'traffic_medium', 'traffic_campaign',
			'traffic_value', 'tags', 'available_for_mailing', 'legal_bases',
		);
		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) || ( ! in_array( $key, $standard, true ) && 1 !== preg_match( '/^cf_[a-z0-9_]+$/', $key ) ) ) {
				return false;
			}
			if ( 'tags' === $key && ( ! is_array( $value ) || array_filter( $value, 'is_string' ) !== $value ) ) {
				return false;
			}
			if ( 'legal_bases' === $key && ! is_array( $value ) ) {
				return false;
			}
			if ( 'available_for_mailing' === $key && ! is_bool( $value ) ) {
				return false;
			}
			if ( ! in_array( $key, array( 'tags', 'legal_bases', 'available_for_mailing' ), true ) && ! is_scalar( $value ) ) {
				return false;
			}
		}

		return true;
	}
}
