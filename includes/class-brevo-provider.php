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

	public function __construct( CRM_Leads_Capture_Settings $settings, ?CRM_Leads_Capture_Lead_Payload $payload_builder = null ) {
		$this->settings        = $settings;
		$this->payload_builder = $payload_builder ?: new CRM_Leads_Capture_Lead_Payload();
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
		$list_id = max( 0, (int) ( $context['list_id'] ?? $this->settings->brevo_default_list_id() ) );
		if ( 0 >= $list_id ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo list is not configured.', array( 'code' => 'missing_list' ) );
		}

		$contact = $this->payload_builder->build_contact(
			$payload,
			array(
				'source'   => $payload['source'] ?? '',
				'material' => $payload['material'] ?? '',
				'list_id'  => $list_id,
			)
		);

		if ( ! $contact->is_successful() ) {
			return $contact;
		}

		$lead = $contact->data()['payload'] ?? null;
		if ( ! is_array( $lead ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Brevo payload is invalid.' );
		}

		return ( new CRM_Leads_Capture_Brevo_Client( $this->settings->brevo_api_key() ) )->create_or_update_contact( $lead );
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
}
