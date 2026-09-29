<?php
/**
 * Brevo CRM provider.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Brevo_Provider implements CRM_Leads_Capture_Provider_Interface {
	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Lead_Payload $payload_builder;

	/** @var callable|null */
	private $client_factory;

	public function __construct(
		CRM_Leads_Capture_Settings $settings,
		?CRM_Leads_Capture_Lead_Payload $payload_builder = null,
		?callable $client_factory = null
	) {
		$this->settings        = $settings;
		$this->payload_builder = $payload_builder ?: new CRM_Leads_Capture_Lead_Payload();
		$this->client_factory  = $client_factory;
	}

	public function id(): string {
		return 'brevo';
	}

	public function label(): string {
		return __( 'Brevo', 'crm-leads-capture' );
	}

	public function settings_fields(): array {
		return array(
			'api_key'         => array( 'type' => 'secret' ),
			'default_list_id' => array( 'type' => 'integer' ),
		);
	}

	public function material_fields(): array {
		return array(
			'list_id' => array(
				'label'       => __( 'Lista Brevo', 'crm-leads-capture' ),
				'description' => __( 'Opcional. Quando vazio, usa a lista padrão global.', 'crm-leads-capture' ),
			),
		);
	}

	public function sanitize_settings( array $input ): array {
		return array(
			'api_key'         => $this->clean_string( $input['api_key'] ?? '' ),
			'default_list_id' => max( 0, (int) ( $input['default_list_id'] ?? 0 ) ),
		);
	}

	public function sanitize_material_meta( array $input ): array {
		return array(
			'list_id' => max( 0, (int) ( $input['list_id'] ?? 0 ) ),
		);
	}

	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result {
		$list_ids = $this->list_ids( $context );
		if ( array() === $list_ids ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo list is not configured.', array( 'code' => 'missing_list' ) );
		}

		$mapped = $this->mapped_attributes( $payload, $context );
		if ( ! $mapped->is_successful() ) {
			return $mapped;
		}

		$input = $this->payload_input( $payload );

		$contact = $this->payload_builder->build_contact(
			$input,
			array(
				'source'     => $input['source'] ?? '',
				'material'   => $input['material'] ?? '',
				'list_ids'   => $list_ids,
				'attributes' => $mapped->data()['attributes'] ?? array(),
			)
		);

		if ( ! $contact->is_successful() ) {
			return $contact;
		}

		$lead = $contact->data()['payload'] ?? null;
		if ( ! is_array( $lead ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo payload is invalid.' );
		}

		return $this->client()->create_or_update_contact( $lead );
	}

	public function error_codes(): array {
		return array(
			'brevo_invalid_parameter'  => __( 'Parâmetro inválido na Brevo', 'crm-leads-capture' ),
			'brevo_missing_parameter'  => __( 'Parâmetro ausente na Brevo', 'crm-leads-capture' ),
			'brevo_duplicate_parameter' => __( 'Parâmetro duplicado na Brevo', 'crm-leads-capture' ),
			'brevo_document_not_found' => __( 'Registro não encontrado na Brevo', 'crm-leads-capture' ),
			'brevo_permission_error'   => __( 'Erro de permissão na Brevo', 'crm-leads-capture' ),
			'brevo_bad_request'        => __( 'Requisição recusada pela Brevo', 'crm-leads-capture' ),
			'brevo_error'              => __( 'Erro genérico da Brevo', 'crm-leads-capture' ),
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

	/**
	 * @param array<string, mixed> $context
	 * @return array<int, int>
	 */
	private function list_ids( array $context ): array {
		$value = $context['list_ids'] ?? $context['list_id'] ?? array();
		$ids   = is_array( $value ) ? $value : array( $value );
		$ids   = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', $ids ),
					static fn( int $id ): bool => 0 < $id
				)
			)
		);

		if ( array() === $ids ) {
			$default_id = $this->settings->brevo_default_list_id();
			if ( 0 < $default_id ) {
				$ids[] = $default_id;
			}
		}

		return $ids;
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
		$trusted  = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();

		return array_merge(
			$payload['lead'],
			$tracking,
			array(
				'source'   => $trusted['source'] ?? '',
				'material' => $trusted['material'] ?? '',
			)
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $context
	 */
	private function mapped_attributes( array $payload, array $context ): CRM_Leads_Capture_Result {
		$attributes = isset( $context['attributes'] ) && is_array( $context['attributes'] ) ? $context['attributes'] : array();
		if ( ! isset( $payload['lead'] ) || ! is_array( $payload['lead'] ) ) {
			return CRM_Leads_Capture_Result::success( 200, '', array( 'attributes' => $attributes ) );
		}

		$map            = isset( $context['attribute_map'] ) && is_array( $context['attribute_map'] ) ? $context['attribute_map'] : array();
		$mapped_targets = array();
		foreach ( array( 'lead', 'tracking', 'custom_fields', 'consent' ) as $group ) {
			$values = isset( $payload[ $group ] ) && is_array( $payload[ $group ] ) ? $payload[ $group ] : array();
			foreach ( $values as $field => $value ) {
				if ( $this->is_empty_value( $value ) || $this->is_standard_field( $group, (string) $field ) ) {
					continue;
				}

				$path      = $group . '.' . $field;
				$attribute = $map[ $path ] ?? $map[ $field ] ?? '';
				if ( ! is_string( $attribute ) || 1 !== preg_match( '/^[A-Z][A-Z0-9_]*$/', $attribute ) ) {
					return CRM_Leads_Capture_Result::failure(
						0,
						'Brevo attribute mapping is not configured.',
						array(
							'code'   => 'invalid_payload',
							'fields' => array( $path ),
						)
					);
				}
				if ( isset( $mapped_targets[ $attribute ] ) ) {
					return CRM_Leads_Capture_Result::failure(
						0,
						'Brevo attribute mapping contains duplicate destinations.',
						array( 'code' => 'invalid_payload' )
					);
				}

				$mapped_targets[ $attribute ] = true;
				$attributes[ $attribute ]     = $value;
			}
		}

		$trusted = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();
		foreach ( $map as $path => $attribute ) {
			if ( ! is_string( $path ) || ! str_starts_with( $path, 'context.' ) ) {
				continue;
			}

			$key   = substr( $path, strlen( 'context.' ) );
			$value = $trusted[ $key ] ?? '';
			if ( $this->is_empty_value( $value ) ) {
				continue;
			}
			if ( ! is_string( $attribute ) || 1 !== preg_match( '/^[A-Z][A-Z0-9_]*$/', $attribute ) || isset( $mapped_targets[ $attribute ] ) || ! is_scalar( $value ) ) {
				return CRM_Leads_Capture_Result::failure( 0, 'Brevo context attribute mapping is invalid.', array( 'code' => 'invalid_payload' ) );
			}

			$mapped_targets[ $attribute ] = true;
			$attributes[ $attribute ]     = $value;
		}

		return CRM_Leads_Capture_Result::success( 200, '', array( 'attributes' => $attributes ) );
	}

	/**
	 * @param mixed $value
	 */
	private function is_empty_value( $value ): bool {
		return '' === $value || null === $value || array() === $value;
	}

	private function is_standard_field( string $group, string $field ): bool {
		$standard = array(
			'lead'     => array( 'email', 'name', 'whatsapp' ),
			'tracking' => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name' ),
		);

		return in_array( $field, $standard[ $group ] ?? array(), true );
	}

	private function client(): CRM_Leads_Capture_Brevo_Client {
		if ( null !== $this->client_factory ) {
			$client = call_user_func( $this->client_factory, $this->settings->brevo_api_key() );
			if ( $client instanceof CRM_Leads_Capture_Brevo_Client ) {
				return $client;
			}
		}

		return new CRM_Leads_Capture_Brevo_Client( $this->settings->brevo_api_key() );
	}
}
