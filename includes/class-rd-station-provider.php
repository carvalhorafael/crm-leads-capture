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

	public function __construct( CRM_Leads_Capture_Settings $settings ) {
		$this->settings = $settings;
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
		$email = $this->clean_email( $payload['email'] ?? '' );
		if ( '' === $email ) {
			return CRM_Leads_Capture_Result::failure( 0, 'RD Station conversion requires an email.' );
		}

		$conversion_identifier = $this->clean_string( $context['conversion_identifier'] ?? '' );
		if ( '' === $conversion_identifier ) {
			$conversion_identifier = $this->settings->rd_station_default_conversion_identifier();
		}
		if ( '' === $conversion_identifier ) {
			$conversion_identifier = $this->clean_string( $payload['material'] ?? 'Material gratuito' );
		}

		$event_payload = array_filter(
			array(
				'conversion_identifier' => $conversion_identifier,
				'email'                 => $email,
				'name'                  => $this->clean_string( $payload['name'] ?? '' ),
				'mobile_phone'          => $this->clean_string( $payload['whatsapp'] ?? '' ),
				'traffic_source'        => $this->clean_string( $payload['utm_source'] ?? '' ),
				'traffic_medium'        => $this->clean_string( $payload['utm_medium'] ?? '' ),
				'traffic_campaign'      => $this->clean_string( $payload['utm_campaign'] ?? '' ),
				'traffic_value'         => $this->clean_string( $payload['utm_term'] ?? '' ),
				'cf_material'           => $this->clean_string( $payload['material'] ?? '' ),
				'cf_source'             => $this->clean_string( $payload['source'] ?? '' ),
			),
			static fn( $value ): bool => '' !== $value
		);

		$tags = $this->tags( $context['tags'] ?? $this->settings->rd_station_default_tags() );
		if ( array() !== $tags ) {
			$event_payload['tags'] = $tags;
		}

		$event = array(
			'event_type'   => 'CONVERSION',
			'event_family' => 'CDP',
			'payload'      => $event_payload,
		);

		return ( new CRM_Leads_Capture_RD_Station_Client( $this->settings->rd_station_api_key() ) )->send_conversion( $event );
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
		$value = $this->clean_string( $value );
		if ( '' === $value ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'trim', explode( ',', $value ) ),
				static fn( string $tag ): bool => '' !== $tag
			)
		);
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
}
