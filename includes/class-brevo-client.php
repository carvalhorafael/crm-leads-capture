<?php
/**
 * Brevo HTTP API client.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Brevo_Client {
	private const CONTACTS_ENDPOINT = 'https://api.brevo.com/v3/contacts';

	private string $api_key;

	/**
	 * @var callable|null
	 */
	private $http_client;

	private string $endpoint;

	/**
	 * @param callable|null $http_client Optional transport for tests.
	 */
	public function __construct( string $api_key, ?callable $http_client = null, string $endpoint = self::CONTACTS_ENDPOINT ) {
		$this->api_key     = trim( $api_key );
		$this->http_client = $http_client;
		$this->endpoint    = $endpoint;
	}

	/**
	 * @param array<string, mixed> $lead
	 */
	public function create_or_update_contact( array $lead ): CRM_Leads_Capture_Result {
		if ( '' === $this->api_key ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo API key is not configured.' );
		}

		if ( empty( $lead['email'] ) || ! is_string( $lead['email'] ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo contact payload requires an email.' );
		}

		$response = $this->post( $lead );

		if ( $this->is_wp_error( $response ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo request failed.' );
		}

		$status_code = $this->response_code( $response );
		$body        = $this->response_body( $response );
		$decoded     = $this->decode_json_body( $body );

		if ( in_array( $status_code, array( 200, 201, 204 ), true ) ) {
			return CRM_Leads_Capture_Result::success(
				$status_code,
				'Brevo contact created or updated.',
				array( 'body' => $decoded )
			);
		}

		return CRM_Leads_Capture_Result::failure(
			$status_code,
			'Brevo request returned an error.',
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
	private function post( array $payload ) {
		$args = array(
			'timeout' => 10,
			'headers' => array(
				'api-key'      => $this->api_key,
				'content-type' => 'application/json',
			),
			'body'    => $this->json_encode( $payload ),
		);

		if ( null !== $this->http_client ) {
			return call_user_func( $this->http_client, $this->endpoint, $args );
		}

		if ( ! function_exists( 'wp_remote_post' ) ) {
			return null;
		}

		return wp_remote_post( $this->endpoint, $args );
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function json_encode( array $payload ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			return (string) wp_json_encode( $payload );
		}

		return (string) json_encode( $payload );
	}

	/**
	 * @param mixed $response
	 */
	private function is_wp_error( $response ): bool {
		if ( function_exists( 'is_wp_error' ) ) {
			return is_wp_error( $response );
		}

		return is_object( $response ) && 'WP_Error' === get_class( $response );
	}

	/**
	 * @param mixed $response
	 */
	private function response_code( $response ): int {
		if ( function_exists( 'wp_remote_retrieve_response_code' ) ) {
			return (int) wp_remote_retrieve_response_code( $response );
		}

		return (int) ( $response['response']['code'] ?? 0 );
	}

	/**
	 * @param mixed $response
	 */
	private function response_body( $response ): string {
		if ( function_exists( 'wp_remote_retrieve_body' ) ) {
			return (string) wp_remote_retrieve_body( $response );
		}

		return (string) ( $response['body'] ?? '' );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function decode_json_body( string $body ): ?array {
		if ( '' === $body ) {
			return null;
		}

		$decoded = json_decode( $body, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * @param array<string, mixed>|null $decoded
	 *
	 * @return array<string, mixed>
	 */
	private function error_summary( int $status_code, ?array $decoded ): array {
		$summary = array( 'status_code' => $status_code );

		if ( null === $decoded ) {
			return $summary;
		}

		foreach ( array( 'code', 'message', 'error', 'type' ) as $key ) {
			if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
				$summary[ $key ] = (string) $decoded[ $key ];
			}
		}

		if ( isset( $decoded['details'] ) && is_array( $decoded['details'] ) ) {
			$summary['details'] = $this->scalar_array( $decoded['details'] );
		}

		return $summary;
	}

	/**
	 * @param array<string, mixed> $values
	 *
	 * @return array<string, mixed>
	 */
	private function scalar_array( array $values ): array {
		$scalars = array();

		foreach ( $values as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$scalars[ (string) $key ] = $value;
			}
		}

		return $scalars;
	}
}
