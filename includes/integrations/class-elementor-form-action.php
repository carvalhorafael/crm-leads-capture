<?php
/**
 * Elementor Pro form action adapter.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Elementor_Form_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {
	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Elementor_Form_Mapper $mapper;

	private CRM_Leads_Capture_Lead_Payload $payload_builder;

	private CRM_Leads_Capture_Logger $logger;

	public function __construct(
		CRM_Leads_Capture_Settings $settings,
		CRM_Leads_Capture_Elementor_Form_Mapper $mapper,
		CRM_Leads_Capture_Lead_Payload $payload_builder,
		?CRM_Leads_Capture_Logger $logger = null
	) {
		$this->settings        = $settings;
		$this->mapper          = $mapper;
		$this->payload_builder = $payload_builder;
		$this->logger          = $logger ?: new CRM_Leads_Capture_Logger();
	}

	public function get_name(): string {
		return 'brevo';
	}

	public function get_label(): string {
		return esc_html__( 'Brevo CRM', 'crm-leads-capture' );
	}

	/**
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section( $widget ): void {
		$widget->start_controls_section(
			'section_brevo',
			array(
				'label'     => esc_html__( 'Brevo CRM', 'crm-leads-capture' ),
				'condition' => array(
					'submit_actions' => $this->get_name(),
				),
			)
		);

		foreach ( $this->mapper->controls() as $control_id => $control ) {
			$widget->add_control(
				$control_id,
				array(
					'label'       => esc_html( (string) $control['label'] ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => $control['placeholder'] ?? '',
					'default'     => $control['default'] ?? '',
					'description' => $control['description'] ?? '',
				)
			);
		}

		$widget->end_controls_section();
	}

	/**
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ): void {
		$settings = $record->get( 'form_settings' );
		$settings = is_array( $settings ) ? $settings : array();

		$api_key = $this->api_key_for_settings( $settings );
		$list_id = $this->list_id_for_settings( $settings );

		if ( '' === $api_key || 0 >= $list_id ) {
			$ajax_handler->add_error_message( esc_html__( 'Configuração Brevo incompleta.', 'crm-leads-capture' ) );
			return;
		}

		$raw_fields = $record->get( 'fields' );
		$raw_fields = is_array( $raw_fields ) ? $raw_fields : array();
		$fields     = $this->mapper->normalize_fields( $raw_fields );
		$fields     = $this->mapper->inject_posted_utm_fields( $fields, $this->unslash_array( $_POST ) );

		$mapped = $this->mapper->map_to_payload_input(
			array_merge( $settings, array( 'brevo_list_id' => $list_id ) ),
			$fields
		);

		$payload_result = $this->payload_builder->build_contact( $mapped['input'], $mapped['context'] );
		if ( ! $payload_result->is_successful() ) {
			$ajax_handler->add_error_message( esc_html__( 'Email inválido.', 'crm-leads-capture' ) );
			return;
		}

		$payload = $payload_result->data()['payload'] ?? null;
		if ( ! is_array( $payload ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Payload Brevo inválido.', 'crm-leads-capture' ) );
			return;
		}

		$result = ( new CRM_Leads_Capture_Brevo_Client( $api_key ) )->create_or_update_contact( $payload );
		if ( $result->is_successful() ) {
			$ajax_handler->add_success_message( esc_html__( 'Contato adicionado ao Brevo.', 'crm-leads-capture' ) );
			return;
		}

		$this->logger->debug(
			'Elementor Brevo request failed.',
			array(
				'status_code' => $result->status_code(),
				'payload'     => $this->payload_summary( $payload ),
				'brevo_error' => $this->brevo_error_summary( $result ),
			)
		);

		$ajax_handler->add_error_message( esc_html__( 'Erro ao adicionar contato ao Brevo. Tente novamente.', 'crm-leads-capture' ) );
	}

	/**
	 * @param array<string, mixed> $element
	 *
	 * @return array<string, mixed>
	 */
	public function on_export( $element ): array {
		foreach ( array_keys( $this->mapper->controls() ) as $field ) {
			unset( $element[ $field ] );
		}

		return $element;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function api_key_for_settings( array $settings ): string {
		$global_api_key = $this->settings->api_key();
		if ( '' !== $global_api_key ) {
			return $global_api_key;
		}

		return isset( $settings['brevo_api_key'] ) && is_string( $settings['brevo_api_key'] )
			? trim( $settings['brevo_api_key'] )
			: '';
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function list_id_for_settings( array $settings ): int {
		$list_id = isset( $settings['brevo_list_id'] ) ? max( 0, (int) $settings['brevo_list_id'] ) : 0;

		return 0 < $list_id ? $list_id : $this->settings->default_list_id();
	}

	/**
	 * @param array<string, mixed> $payload
	 *
	 * @return array<string, mixed>
	 */
	private function payload_summary( array $payload ): array {
		$attributes = isset( $payload['attributes'] ) && is_array( $payload['attributes'] )
			? array_keys( $payload['attributes'] )
			: array();

		return array(
			'has_email'      => isset( $payload['email'] ) && '' !== $payload['email'],
			'attribute_keys' => array_values( array_map( 'strval', $attributes ) ),
			'list_ids'       => isset( $payload['listIds'] ) && is_array( $payload['listIds'] )
				? array_values( array_map( 'intval', $payload['listIds'] ) )
				: array(),
			'update_enabled' => isset( $payload['updateEnabled'] ) ? (bool) $payload['updateEnabled'] : null,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function brevo_error_summary( CRM_Leads_Capture_Result $result ): array {
		$data = $result->data();

		if ( isset( $data['error_summary'] ) && is_array( $data['error_summary'] ) ) {
			return $data['error_summary'];
		}

		return array( 'status_code' => $result->status_code() );
	}

	/**
	 * @param array<string, mixed> $value
	 *
	 * @return array<string, mixed>
	 */
	private function unslash_array( array $value ): array {
		return array_map(
			function ( $item ) {
				if ( is_array( $item ) ) {
					return $this->unslash_array( $item );
				}

				return is_string( $item ) ? wp_unslash( $item ) : $item;
			},
			$value
		);
	}
}
