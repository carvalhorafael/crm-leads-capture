<?php
/**
 * Lead payload normalization for Brevo contacts.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Lead_Payload {
	private const UTM_FIELDS = array(
		'utm_source'   => 'UTM_SOURCE',
		'utm_medium'   => 'UTM_MEDIUM',
		'utm_campaign' => 'UTM_CAMPAIGN',
		'utm_term'     => 'UTM_TERM',
		'utm_content'  => 'UTM_CONTENT',
		'utm_name'     => 'UTM_NAME',
	);

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $context
	 */
	public function build_contact( array $input, array $context = array() ): CRM_Leads_Capture_Result {
		$email = $this->normalize_email( $input['email'] ?? '' );

		if ( ! $this->is_valid_email( $email ) ) {
			return CRM_Leads_Capture_Result::failure( 0, 'Invalid email.' );
		}

		$name_parts = $this->split_name( $this->clean_string( $input['name'] ?? '' ) );
		$attributes = array(
			'FIRSTNAME' => $name_parts['first_name'],
			'LASTNAME'  => $name_parts['last_name'],
			'WHATSAPP'  => $this->normalize_whatsapp( $input['whatsapp'] ?? '' ),
			'SOURCE'    => $this->clean_string( $context['source'] ?? $input['source'] ?? '' ),
			'MATERIAL'  => $this->clean_string( $context['material'] ?? $input['material'] ?? '' ),
		);

		foreach ( self::UTM_FIELDS as $input_key => $attribute_key ) {
			$attributes[ $attribute_key ] = $this->clean_string( $input[ $input_key ] ?? '' );
		}

		if ( isset( $context['attributes'] ) && is_array( $context['attributes'] ) ) {
			foreach ( $context['attributes'] as $attribute_key => $attribute_value ) {
				$attribute_key = $this->normalize_attribute_key( $attribute_key );
				if ( '' !== $attribute_key ) {
					$attributes[ $attribute_key ] = $this->clean_string( $attribute_value );
				}
			}
		}

		$attributes = array_filter(
			$attributes,
			static fn( $value ): bool => '' !== $value
		);

		$payload = array(
			'email'         => $email,
			'attributes'    => $attributes,
			'updateEnabled' => true,
		);

		$list_ids = $this->normalize_list_ids( $context['list_ids'] ?? $context['list_id'] ?? $input['list_ids'] ?? $input['list_id'] ?? array() );
		if ( array() !== $list_ids ) {
			$payload['listIds'] = $list_ids;
		}

		return CRM_Leads_Capture_Result::success( 0, 'Payload built.', array( 'payload' => $payload ) );
	}

	/**
	 * @param mixed $value
	 */
	public function clean_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		$value = strip_tags( $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );

		return trim( (string) $value );
	}

	/**
	 * @param mixed $value
	 */
	public function normalize_email( $value ): string {
		$email = strtolower( $this->clean_string( $value ) );

		if ( function_exists( 'sanitize_email' ) ) {
			return sanitize_email( $email );
		}

		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}

	public function is_valid_email( string $email ): bool {
		if ( function_exists( 'is_email' ) ) {
			return false !== is_email( $email );
		}

		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * @return array{first_name: string, last_name: string}
	 */
	public function split_name( string $name ): array {
		if ( '' === $name ) {
			return array(
				'first_name' => '',
				'last_name'  => '',
			);
		}

		$parts = preg_split( '/\s+/', $name );
		if ( false === $parts || array() === $parts ) {
			return array(
				'first_name' => $name,
				'last_name'  => '',
			);
		}

		$first_name = array_shift( $parts );

		return array(
			'first_name' => (string) $first_name,
			'last_name'  => implode( ' ', $parts ),
		);
	}

	/**
	 * @param mixed $value
	 */
	public function normalize_whatsapp( $value ): string {
		$value = $this->clean_string( $value );
		if ( '' === $value ) {
			return '';
		}

		$prefix = str_starts_with( $value, '+' ) ? '+' : '';
		$digits = preg_replace( '/\D+/', '', $value );
		$digits = (string) $digits;

		if ( '' === $digits ) {
			return '';
		}

		if ( '+' === $prefix ) {
			return '+' . $digits;
		}

		if ( str_starts_with( $digits, '55' ) && in_array( strlen( $digits ), array( 12, 13 ), true ) ) {
			return '+' . $digits;
		}

		if ( in_array( strlen( $digits ), array( 10, 11 ), true ) ) {
			return '+55' . $digits;
		}

		return $digits;
	}

	/**
	 * @param mixed $value
	 *
	 * @return array<int, int>
	 */
	private function normalize_list_ids( $value ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$list_ids = array();

		foreach ( $values as $list_id ) {
			$list_id = (int) $list_id;
			if ( 0 < $list_id ) {
				$list_ids[] = $list_id;
			}
		}

		return array_values( array_unique( $list_ids ) );
	}

	/**
	 * @param mixed $key
	 */
	private function normalize_attribute_key( $key ): string {
		if ( is_array( $key ) || is_object( $key ) ) {
			return '';
		}

		$key = strtoupper( (string) $key );
		$key = preg_replace( '/[^A-Z0-9_]/', '', $key );

		return trim( (string) $key, '_' );
	}
}
