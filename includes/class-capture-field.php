<?php
/**
 * Capture profile field definition and normalization.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Field {
	public const GROUP_LEAD = 'lead';
	public const GROUP_TRACKING = 'tracking';
	public const GROUP_CONSENT = 'consent';
	public const GROUP_CUSTOM = 'custom_fields';

	private const TYPES = array(
		'text',
		'textarea',
		'email',
		'phone',
		'url',
		'integer',
		'number',
		'boolean',
		'date',
		'select',
	);

	private const GROUPS = array(
		self::GROUP_LEAD,
		self::GROUP_TRACKING,
		self::GROUP_CONSENT,
		self::GROUP_CUSTOM,
	);

	private string $name;

	private string $type;

	private string $group;

	private bool $required;

	/**
	 * @var array<int, string>
	 */
	private array $allowed_values;

	/**
	 * @var callable|null
	 */
	private $sanitize_callback;

	/**
	 * @var callable|null
	 */
	private $validate_callback;

	/**
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( string $name, array $config = array() ) {
		$name = self::normalize_key( $name );
		if ( '' === $name ) {
			throw new InvalidArgumentException( 'Capture field name cannot be empty.' );
		}

		$type = self::normalize_key( (string) ( $config['type'] ?? 'text' ) );
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new InvalidArgumentException( 'Unsupported capture field type.' );
		}

		$group = self::normalize_key( (string) ( $config['group'] ?? self::GROUP_CUSTOM ) );
		if ( ! in_array( $group, self::GROUPS, true ) ) {
			throw new InvalidArgumentException( 'Unsupported capture field group.' );
		}

		$sanitize_callback = $config['sanitize_callback'] ?? null;
		if ( null !== $sanitize_callback && ! is_callable( $sanitize_callback ) ) {
			throw new InvalidArgumentException( 'Capture field sanitizer must be callable.' );
		}

		$validate_callback = $config['validate_callback'] ?? null;
		if ( null !== $validate_callback && ! is_callable( $validate_callback ) ) {
			throw new InvalidArgumentException( 'Capture field validator must be callable.' );
		}

		$allowed_values = array();
		foreach ( (array) ( $config['allowed_values'] ?? array() ) as $allowed_value ) {
			if ( is_scalar( $allowed_value ) ) {
				$allowed_values[] = self::clean_text( (string) $allowed_value );
			}
		}

		$this->name              = $name;
		$this->type              = $type;
		$this->group             = $group;
		$this->required          = true === ( $config['required'] ?? false );
		$this->allowed_values    = array_values( array_unique( $allowed_values ) );
		$this->sanitize_callback = $sanitize_callback;
		$this->validate_callback = $validate_callback;
	}

	public function name(): string {
		return $this->name;
	}

	public function type(): string {
		return $this->type;
	}

	public function group(): string {
		return $this->group;
	}

	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * @param mixed $value Submitted field value.
	 */
	public function normalize( $value ): CRM_Leads_Capture_Result {
		if ( is_array( $value ) || is_object( $value ) || is_resource( $value ) ) {
			return $this->failure( 'invalid_type' );
		}

		$normalized = $this->normalize_type( $value );
		if ( ! $normalized->is_successful() ) {
			return $normalized;
		}

		$normalized_value = $normalized->data()['value'] ?? '';
		if ( null !== $this->sanitize_callback ) {
			$normalized_value = call_user_func( $this->sanitize_callback, $normalized_value, $this );
			if ( is_array( $normalized_value ) || is_object( $normalized_value ) || is_resource( $normalized_value ) ) {
				return $this->failure( 'invalid_type' );
			}
		}

		if ( $this->required && $this->is_empty( $normalized_value ) ) {
			return $this->failure( 'required' );
		}

		if ( null !== $this->validate_callback && ! $this->is_empty( $normalized_value ) ) {
			$validation = call_user_func( $this->validate_callback, $normalized_value, $this );
			if ( true !== $validation ) {
				$code = is_string( $validation ) && '' !== self::normalize_key( $validation )
					? self::normalize_key( $validation )
					: 'invalid';

				return $this->failure( $code );
			}
		}

		return CRM_Leads_Capture_Result::success(
			200,
			'Field normalized.',
			array( 'value' => $normalized_value )
		);
	}

	/**
	 * @param mixed $value Submitted field value.
	 */
	private function normalize_type( $value ): CRM_Leads_Capture_Result {
		switch ( $this->type ) {
			case 'email':
				$email = strtolower( self::clean_email( (string) $value ) );
				if ( '' !== $email && ! self::is_email( $email ) ) {
					return $this->failure( 'invalid_email' );
				}
				return $this->success( $email );

			case 'phone':
				$phone  = self::clean_text( (string) $value );
				$prefix = str_starts_with( $phone, '+' ) ? '+' : '';
				$digits = (string) preg_replace( '/\D+/', '', $phone );
				if ( '' !== $digits && ( strlen( $digits ) < 8 || strlen( $digits ) > 15 ) ) {
					return $this->failure( 'invalid_phone' );
				}
				return $this->success( '' === $digits ? '' : $prefix . $digits );

			case 'url':
				$url = self::clean_url( (string) $value );
				if ( '' !== $url && false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return $this->failure( 'invalid_url' );
				}
				return $this->success( $url );

			case 'integer':
				$integer = filter_var( $value, FILTER_VALIDATE_INT );
				if ( '' !== (string) $value && false === $integer ) {
					return $this->failure( 'invalid_integer' );
				}
				return $this->success( '' === (string) $value ? '' : (int) $integer );

			case 'number':
				if ( '' !== (string) $value && ! is_numeric( $value ) ) {
					return $this->failure( 'invalid_number' );
				}
				return $this->success( '' === (string) $value ? '' : (float) $value );

			case 'boolean':
				if ( '' === (string) $value ) {
					return $this->success( false );
				}
				$boolean = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				if ( null === $boolean ) {
					return $this->failure( 'invalid_boolean' );
				}
				return $this->success( $boolean );

			case 'date':
				$date = self::clean_text( (string) $value );
				if ( '' !== $date ) {
					$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
					if ( false === $parsed || $date !== $parsed->format( 'Y-m-d' ) ) {
						return $this->failure( 'invalid_date' );
					}
				}
				return $this->success( $date );

			case 'select':
				$selected = self::clean_text( (string) $value );
				if ( '' !== $selected && ! in_array( $selected, $this->allowed_values, true ) ) {
					return $this->failure( 'invalid_option' );
				}
				return $this->success( $selected );

			case 'textarea':
				return $this->success( self::clean_textarea( (string) $value ) );

			case 'text':
			default:
				return $this->success( self::clean_text( (string) $value ) );
		}
	}

	/**
	 * @param mixed $value Normalized field value.
	 */
	private function success( $value ): CRM_Leads_Capture_Result {
		return CRM_Leads_Capture_Result::success( 200, 'Field normalized.', array( 'value' => $value ) );
	}

	private function failure( string $code ): CRM_Leads_Capture_Result {
		return CRM_Leads_Capture_Result::failure(
			422,
			'Invalid field.',
			array(
				'code'  => $code,
				'field' => $this->name,
			)
		);
	}

	/**
	 * @param mixed $value Normalized field value.
	 */
	private function is_empty( $value ): bool {
		return '' === $value || null === $value || false === $value;
	}

	private static function normalize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $value ) );
	}

	private static function clean_text( string $value ): string {
		return function_exists( 'sanitize_text_field' )
			? sanitize_text_field( $value )
			: trim( strip_tags( (string) preg_replace( '/[\r\n\t ]+/', ' ', $value ) ) );
	}

	private static function clean_textarea( string $value ): string {
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $value );
		}

		$value = strip_tags( $value );
		$value = preg_replace( "/\r\n?|\n/", "\n", $value );

		return trim( (string) $value );
	}

	private static function clean_email( string $value ): string {
		return function_exists( 'sanitize_email' )
			? sanitize_email( $value )
			: (string) filter_var( trim( $value ), FILTER_SANITIZE_EMAIL );
	}

	private static function is_email( string $value ): bool {
		return function_exists( 'is_email' )
			? false !== is_email( $value )
			: false !== filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	private static function clean_url( string $value ): string {
		return function_exists( 'esc_url_raw' )
			? esc_url_raw( trim( $value ) )
			: (string) filter_var( trim( $value ), FILTER_SANITIZE_URL );
	}
}
