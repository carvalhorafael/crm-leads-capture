<?php
/**
 * RD Station CRM provider.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_RD_Station_Provider implements CRM_Leads_Capture_Provider_Interface {
	private CRM_Leads_Capture_Settings $settings;

	/** @var callable|null */
	private $client_factory;

	public function __construct( CRM_Leads_Capture_Settings $settings, ?callable $client_factory = null ) {
		$this->settings       = $settings;
		$this->client_factory = $client_factory;
	}

	public function id(): string {
		return 'rd_station';
	}

	public function label(): string {
		return __( 'RD Station', 'crm-leads-capture' );
	}

	public function settings_fields(): array {
		return array(
			'api_key'                       => array( 'type' => 'secret' ),
			'default_conversion_identifier' => array( 'type' => 'text' ),
			'default_tags'                  => array( 'type' => 'text' ),
		);
	}

	public function material_fields(): array {
		return array(
			'conversion_identifier' => array(
				'label'       => __( 'Identificador de conversão RD Station', 'crm-leads-capture' ),
				'description' => __( 'Opcional. Quando vazio, usa o padrão global ou o título do material.', 'crm-leads-capture' ),
			),
			'tags'                  => array(
				'label'       => __( 'Tags RD Station', 'crm-leads-capture' ),
				'description' => __( 'Lista separada por vírgulas.', 'crm-leads-capture' ),
			),
		);
	}

	public function sanitize_settings( array $input ): array {
		return array(
			'api_key'                       => $this->clean_string( $input['api_key'] ?? '' ),
			'default_conversion_identifier' => $this->clean_string( $input['default_conversion_identifier'] ?? '' ),
			'default_tags'                  => $this->clean_string( $input['default_tags'] ?? '' ),
		);
	}

	public function sanitize_material_meta( array $input ): array {
		return array(
			'conversion_identifier' => $this->clean_string( $input['conversion_identifier'] ?? '' ),
			'tags'                  => $this->clean_string( $input['tags'] ?? '' ),
		);
	}

	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result {
		$is_canonical = isset( $payload['lead'] ) && is_array( $payload['lead'] );
		$input        = $this->payload_input( $payload );
		$email        = $this->clean_email( $input['email'] ?? '' );
		if ( '' === $email ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station conversion requires an email.' );
		}

		$conversion_identifier = $this->clean_string( $context['conversion_identifier'] ?? '' );
		if ( '' === $conversion_identifier ) {
			$conversion_identifier = $this->settings->rd_station_default_conversion_identifier();
		}
		if ( '' === $conversion_identifier && ! $is_canonical ) {
			$conversion_identifier = $this->clean_string( $input['material'] ?? 'Material gratuito' );
		}
		if ( '' === $conversion_identifier ) {
			return CRM_Leads_Capture_Result::failure(
				0,
				'RD Station conversion identifier is not configured.',
				array( 'code' => 'missing_conversion' )
			);
		}

		$event_payload = array_filter(
			array(
				'conversion_identifier' => $conversion_identifier,
				'email'                 => $email,
				'name'                  => $this->clean_string( $input['name'] ?? '' ),
				'mobile_phone'          => $this->clean_string( $input['whatsapp'] ?? '' ),
				'job_title'             => $this->clean_string( $input['job_title'] ?? '' ),
				'company_name'          => $this->clean_string( $input['company_name'] ?? '' ),
				'company_site'          => $this->clean_string( $input['company_site'] ?? '' ),
				'traffic_source'        => $this->clean_string( $input['utm_source'] ?? '' ),
				'traffic_medium'        => $this->clean_string( $input['utm_medium'] ?? '' ),
				'traffic_campaign'      => $this->clean_string( $input['utm_campaign'] ?? '' ),
				'traffic_value'         => $this->clean_string( $input['utm_term'] ?? '' ),
				'cf_material'           => $is_canonical ? '' : $this->clean_string( $input['material'] ?? '' ),
				'cf_source'             => $is_canonical ? '' : $this->clean_string( $input['source'] ?? '' ),
				// Lets the CRM reconstruct the path this person took on the
				// site. Empty values are dropped by the filter below, so a
				// visitor without analytics simply sends one field less.
				'cf_amplitude_device_id' => $this->clean_string( $context['analytics_device_id'] ?? '' ),
			),
			static fn( $value ): bool => '' !== $value
		);

		$mapped = $this->mapped_fields( $payload, $context );
		if ( ! $mapped->is_successful() ) {
			return $mapped;
		}
		$mapped_fields = $mapped->data()['fields'] ?? array();
		if ( array_intersect_key( $event_payload, $mapped_fields ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station field mapping contains duplicate destinations.', array( 'code' => 'invalid_payload' ) );
		}
		$event_payload = array_merge( $event_payload, $mapped_fields );

		$tags_source = array_key_exists( 'tags', $context ) ? $context['tags'] : $this->settings->rd_station_default_tags();
		$tags        = $this->tags( $tags_source );
		if ( array() !== $tags ) {
			$event_payload['tags'] = $tags;
		}

		$event = array(
			'event_type'   => 'CONVERSION',
			'event_family' => 'CDP',
			'payload'      => $event_payload,
		);

		return $this->client()->send_conversion( $event );
	}

	public function error_codes(): array {
		return array(
			'rd_station_bad_request'      => __( 'Requisição recusada pelo RD Station', 'crm-leads-capture' ),
			'rd_station_permission_error' => __( 'Erro de permissão no RD Station', 'crm-leads-capture' ),
			'rd_station_error'            => __( 'Erro genérico do RD Station', 'crm-leads-capture' ),
		);
	}

	/**
	 * @param mixed $value
	 *
	 * @return array<int, string>
	 */
	private function tags( $value ): array {
		$values = is_array( $value ) ? $value : explode( ',', $this->clean_string( $value ) );
		$tags   = array();
		foreach ( $values as $tag ) {
			$tag = $this->clean_string( $tag );
			if ( '' !== $tag ) {
				$tags[] = $tag;
			}
		}

		return array_values( array_unique( $tags ) );
	}

	private function clean_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		return function_exists( 'sanitize_text_field' )
			? sanitize_text_field( (string) $value )
			: trim( strip_tags( (string) $value ) );
	}

	private function clean_email( $value ): string {
		$email = strtolower( $this->clean_string( $value ) );

		return function_exists( 'sanitize_email' ) ? sanitize_email( $email ) : (string) filter_var( $email, FILTER_SANITIZE_EMAIL );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function payload_input( array $payload ): array {
		if ( ! isset( $payload['lead'] ) || ! is_array( $payload['lead'] ) ) {
			return $payload;
		}

		$tracking = isset( $payload['tracking'] ) && is_array( $payload['tracking'] ) ? $payload['tracking'] : array();

		return array_merge( $payload['lead'], $tracking );
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $context
	 */
	private function mapped_fields( array $payload, array $context ): CRM_Leads_Capture_Result {
		if ( ! isset( $payload['lead'] ) || ! is_array( $payload['lead'] ) ) {
			return CRM_Leads_Capture_Result::success( 200, '', array( 'fields' => array() ) );
		}

		$map     = isset( $context['field_map'] ) && is_array( $context['field_map'] ) ? $context['field_map'] : array();
		$fields  = array();
		$targets = array();
		foreach ( array( 'lead', 'tracking', 'custom_fields', 'consent' ) as $group ) {
			$values = isset( $payload[ $group ] ) && is_array( $payload[ $group ] ) ? $payload[ $group ] : array();
			foreach ( $values as $field => $value ) {
				if ( $this->is_empty_value( $value ) || $this->is_standard_field( $group, (string) $field ) ) {
					continue;
				}

				$path       = $group . '.' . $field;
				$identifier = $map[ $path ] ?? $map[ $field ] ?? '';
				if ( ! $this->is_valid_field_identifier( $identifier ) ) {
					return CRM_Leads_Capture_Result::failure(
						0,
						'RD Station field mapping is not configured.',
						array(
							'code'   => 'invalid_payload',
							'fields' => array( $path ),
						)
					);
				}
				if ( isset( $targets[ $identifier ] ) || ! is_scalar( $value ) ) {
					return CRM_Leads_Capture_Result::failure( 0, 'RD Station field mapping is invalid.', array( 'code' => 'invalid_payload' ) );
				}

				$targets[ $identifier ] = true;
				$fields[ $identifier ]  = $this->field_value( $value );
			}
		}

		$trusted = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();
		foreach ( $map as $path => $identifier ) {
			if ( ! is_string( $path ) || ! str_starts_with( $path, 'context.' ) ) {
				continue;
			}
			$key   = substr( $path, strlen( 'context.' ) );
			$value = $trusted[ $key ] ?? '';
			if ( $this->is_empty_value( $value ) ) {
				continue;
			}
			if ( ! $this->is_valid_field_identifier( $identifier ) || isset( $targets[ $identifier ] ) || ! is_scalar( $value ) ) {
				return CRM_Leads_Capture_Result::failure( 0, 'RD Station field mapping is invalid.', array( 'code' => 'invalid_payload' ) );
			}

			$targets[ $identifier ] = true;
			$fields[ $identifier ]  = $this->field_value( $value );
		}

		return CRM_Leads_Capture_Result::success( 200, '', array( 'fields' => $fields ) );
	}

	/**
	 * @param mixed $identifier
	 */
	private function is_valid_field_identifier( $identifier ): bool {
		if ( ! is_string( $identifier ) ) {
			return false;
		}

		$standard = array(
			'name', 'email', 'job_title', 'state', 'city', 'country', 'personal_phone', 'mobile_phone',
			'twitter', 'facebook', 'linkedin', 'website', 'company_name', 'company_site', 'company_address',
			'client_tracking_id', 'traffic_source', 'traffic_medium', 'traffic_campaign', 'traffic_value',
		);

		return in_array( $identifier, $standard, true ) || 1 === preg_match( '/^cf_[a-z0-9_]+$/', $identifier );
	}

	private function is_standard_field( string $group, string $field ): bool {
		$standard = array(
			'lead'     => array( 'email', 'name', 'whatsapp', 'job_title', 'company_name', 'company_site' ),
			'tracking' => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term' ),
		);

		return in_array( $field, $standard[ $group ] ?? array(), true );
	}

	/**
	 * @param mixed $value
	 */
	private function is_empty_value( $value ): bool {
		return '' === $value || null === $value || array() === $value;
	}

	/**
	 * @param mixed $value
	 */
	private function field_value( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return $this->clean_string( $value );
	}

	private function client(): CRM_Leads_Capture_RD_Station_Client {
		if ( null !== $this->client_factory ) {
			$client = call_user_func( $this->client_factory, $this->settings->rd_station_api_key() );
			if ( $client instanceof CRM_Leads_Capture_RD_Station_Client ) {
				return $client;
			}
		}

		return new CRM_Leads_Capture_RD_Station_Client( $this->settings->rd_station_api_key() );
	}
}
