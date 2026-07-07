<?php
/**
 * Elementor form field mapper.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Elementor_Form_Mapper {
	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function controls(): array {
		return array(
			'brevo_api_key' => array(
				'label'       => __( 'API Key', 'crm-leads-capture' ),
				'placeholder' => 'xkeysib-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
				'description' => __( 'Opcional quando a API key global estiver configurada no plugin.', 'crm-leads-capture' ),
			),
			'brevo_list_id' => array(
				'label'       => __( 'List ID', 'crm-leads-capture' ),
				'placeholder' => '2',
				'description' => __( 'Opcional quando a lista padrão global estiver configurada no plugin.', 'crm-leads-capture' ),
			),
			'brevo_email_field' => array(
				'label'       => __( 'Email Field ID', 'crm-leads-capture' ),
				'placeholder' => 'email',
				'default'     => 'email',
			),
			'brevo_name_field' => array(
				'label'       => __( 'Name Field ID', 'crm-leads-capture' ),
				'placeholder' => 'name',
				'default'     => 'name',
			),
			'brevo_last_name_field' => array(
				'label'       => __( 'Last Name Field ID', 'crm-leads-capture' ),
				'placeholder' => 'last_name',
				'default'     => 'last_name',
			),
			'brevo_whatsapp_field' => array(
				'label'       => __( 'WhatsApp Field ID', 'crm-leads-capture' ),
				'placeholder' => 'whatsapp',
				'default'     => 'whatsapp',
			),
		) + $this->custom_attribute_controls() + $this->utm_controls();
	}

	/**
	 * @return array<string, string>
	 */
	public function custom_attribute_mappings(): array {
		return array(
			'brevo_who_is_field'             => 'WHO_IS',
			'brevo_already_sell_field'       => 'ALREADY_SELL',
			'brevo_what_sells_field'         => 'WHAT_SELLS',
			'brevo_current_situation_field'  => 'CURRENT_SITUATION',
			'brevo_is_seeking_help_field'    => 'IS_SEEKING_HELP',
			'brevo_faturamento_digital_field' => 'FATURAMENTO_DIGITAL',
			'brevo_biggest_challenge_field'  => 'BIGGEST_CHALLENGE',
		);
	}

	/**
	 * @return array<string, string>
	 */
	public function utm_mappings(): array {
		return array(
			'brevo_utm_source_field'   => 'utm_source',
			'brevo_utm_medium_field'   => 'utm_medium',
			'brevo_utm_campaign_field' => 'utm_campaign',
			'brevo_utm_content_field'  => 'utm_content',
			'brevo_utm_name_field'     => 'utm_name',
			'brevo_utm_term_field'     => 'utm_term',
		);
	}

	/**
	 * @param array<string, mixed> $raw_fields
	 *
	 * @return array<string, mixed>
	 */
	public function normalize_fields( array $raw_fields ): array {
		$fields = array();

		foreach ( $raw_fields as $id => $field ) {
			if ( is_array( $field ) && array_key_exists( 'value', $field ) ) {
				$fields[ (string) $id ] = $field['value'];
			}
		}

		return $fields;
	}

	/**
	 * @param array<string, mixed> $fields
	 * @param array<string, mixed> $posted
	 *
	 * @return array<string, mixed>
	 */
	public function inject_posted_utm_fields( array $fields, array $posted ): array {
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_name', 'utm_term' ) as $utm_field ) {
			if ( isset( $posted[ $utm_field ] ) && ! isset( $fields[ $utm_field ] ) ) {
				$fields[ $utm_field ] = $this->clean_string( $posted[ $utm_field ] );
			}
		}

		return $fields;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @param array<string, mixed> $fields
	 *
	 * @return array{input: array<string, mixed>, context: array<string, mixed>}
	 */
	public function map_to_payload_input( array $settings, array $fields ): array {
		$input = array(
			'email'    => $this->field_value( $fields, $settings['brevo_email_field'] ?? 'email' ),
			'name'     => $this->field_value( $fields, $settings['brevo_name_field'] ?? 'name' ),
			'whatsapp' => $this->normalize_elementor_whatsapp( $this->field_value( $fields, $settings['brevo_whatsapp_field'] ?? 'whatsapp' ) ),
			'source'   => 'elementor',
		);

		$last_name = $this->field_value( $fields, $settings['brevo_last_name_field'] ?? '' );
		if ( '' !== $last_name ) {
			$input['name'] = trim( $input['name'] . ' ' . $last_name );
		}

		foreach ( $this->utm_mappings() as $setting_key => $input_key ) {
			$input[ $input_key ] = $this->field_value( $fields, $settings[ $setting_key ] ?? $input_key );
		}

		$attributes = array();
		foreach ( $this->custom_attribute_mappings() as $setting_key => $attribute_key ) {
			$value = $this->field_value( $fields, $settings[ $setting_key ] ?? '' );
			if ( '' !== $value ) {
				$attributes[ $attribute_key ] = $value;
			}
		}

		return array(
			'input'   => $input,
			'context' => array(
				'source'     => 'elementor',
				'list_id'    => $settings['brevo_list_id'] ?? 0,
				'attributes' => $attributes,
			),
		);
	}

	/**
	 * @param array<string, mixed> $fields
	 * @param mixed                $field_id
	 */
	private function field_value( array $fields, $field_id ): string {
		$field_id = $this->clean_string( $field_id );

		if ( '' === $field_id || ! array_key_exists( $field_id, $fields ) ) {
			return '';
		}

		return $this->clean_string( $fields[ $field_id ] );
	}

	private function normalize_elementor_whatsapp( string $value ): string {
		if ( '' === $value || str_starts_with( $value, '+' ) ) {
			return $value;
		}

		return '+55' . $value;
	}

	/**
	 * @param mixed $value
	 */
	private function clean_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( strip_tags( $value ) );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function custom_attribute_controls(): array {
		$controls = array();

		foreach ( $this->custom_attribute_mappings() as $setting_key => $attribute_key ) {
			$label = ucwords( strtolower( str_replace( '_', ' ', $attribute_key ) ) ) . ' Field ID';
			$controls[ $setting_key ] = array(
				'label'       => $label,
				'placeholder' => strtolower( $attribute_key ),
				'default'     => strtolower( $attribute_key ),
			);
		}

		return $controls;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function utm_controls(): array {
		$controls = array();

		foreach ( $this->utm_mappings() as $setting_key => $input_key ) {
			$controls[ $setting_key ] = array(
				'label'       => strtoupper( str_replace( '_', ' ', $input_key ) ) . ' Field ID',
				'placeholder' => $input_key,
				'default'     => $input_key,
			);
		}

		return $controls;
	}
}
