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

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Elementor_Form_Mapper $mapper;

	private CRM_Leads_Capture_Lead_Payload $payload_builder;

	private CRM_Leads_Capture_Logger $logger;

	public function __construct(
		CRM_Leads_Capture_Settings $settings,
		CRM_Leads_Capture_Elementor_Form_Mapper $mapper,
		CRM_Leads_Capture_Lead_Payload $payload_builder,
		?CRM_Leads_Capture_Logger $logger = null,
		?CRM_Leads_Capture_Provider_Registry $providers = null
	) {
		$this->settings        = $settings;
		$this->mapper          = $mapper;
		$this->payload_builder = $payload_builder;
		$this->logger          = $logger ?: new CRM_Leads_Capture_Logger();
		$this->providers       = $providers ?: new CRM_Leads_Capture_Provider_Registry();
		if ( null === $providers ) {
			$this->providers->register( new CRM_Leads_Capture_Brevo_Provider( $this->settings ) );
			$this->providers->register( new CRM_Leads_Capture_RD_Station_Provider( $this->settings ) );
		}
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

		$provider_id = $this->settings->active_provider();
		if ( '' !== $this->settings->provider_configuration_error( $provider_id ) ) {
			$ajax_handler->add_error_message( esc_html__( 'Configuração do CRM incompleta.', 'crm-leads-capture' ) );
			return;
		}

		$provider = $this->providers->get( $provider_id );
		if ( null === $provider ) {
			$ajax_handler->add_error_message( esc_html__( 'Provider de CRM indisponível.', 'crm-leads-capture' ) );
			return;
		}

		$list_id = $this->list_id_for_settings( $settings );
		if ( 'brevo' === $provider_id && 0 >= $list_id ) {
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

		$context = $mapped['context'];
		if ( 'rd_station' === $provider_id ) {
			$context['conversion_identifier'] = $this->settings->rd_station_default_conversion_identifier();
			$context['tags']                  = $this->settings->rd_station_default_tags();
		}

		$result = $provider->send_lead( $mapped['input'], $context );
		if ( $result->is_successful() ) {
			$ajax_handler->add_success_message( esc_html__( 'Contato enviado ao CRM.', 'crm-leads-capture' ) );
			return;
		}

		$this->logger->debug(
			'Elementor CRM provider request failed.',
			array(
				'provider'       => $provider_id,
				'status_code'    => $result->status_code(),
				'payload'        => $this->payload_summary( $mapped['input'] ),
				'provider_error' => $this->provider_error_summary( $result ),
			)
		);

		$ajax_handler->add_error_message( esc_html__( 'Erro ao enviar contato ao CRM. Tente novamente.', 'crm-leads-capture' ) );
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
	private function provider_error_summary( CRM_Leads_Capture_Result $result ): array {
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
