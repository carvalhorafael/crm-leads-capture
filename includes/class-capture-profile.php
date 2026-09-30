<?php
/**
 * Reusable capture profile contract.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Profile {
	private string $slug;

	/**
	 * @var array<string, CRM_Leads_Capture_Field>
	 */
	private array $fields = array();

	/**
	 * @var array<string, mixed>
	 */
	private array $context;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private array $providers;

	/**
	 * @var array<string, mixed>
	 */
	private array $success;

	private string $nonce_action;

	private string $nonce_field;

	private string $honeypot_field;

	/**
	 * @param array<int, CRM_Leads_Capture_Field> $fields Capture fields.
	 * @param array<string, mixed>                         $config Profile configuration.
	 */
	public function __construct( string $slug, array $fields, array $config = array() ) {
		$slug = self::normalize_key( $slug );
		if ( '' === $slug ) {
			throw new InvalidArgumentException( 'Capture profile slug cannot be empty.' );
		}

		foreach ( $fields as $field ) {
			if ( ! $field instanceof CRM_Leads_Capture_Field ) {
				throw new InvalidArgumentException( 'Capture profile fields must use the capture field contract.' );
			}
			if ( isset( $this->fields[ $field->name() ] ) ) {
				throw new InvalidArgumentException( 'Capture profile field names must be unique.' );
			}
			$this->fields[ $field->name() ] = $field;
		}

		if ( array() === $this->fields ) {
			throw new InvalidArgumentException( 'Capture profile must define at least one field.' );
		}

		$providers = $config['providers'] ?? array();
		if ( ! is_array( $providers ) ) {
			throw new InvalidArgumentException( 'Capture profile provider configuration must be an array.' );
		}

		$this->slug            = $slug;
		$this->context         = is_array( $config['context'] ?? null ) ? $config['context'] : array();
		$this->providers       = $providers;
		$this->success         = is_array( $config['success'] ?? null ) ? $config['success'] : array();
		$this->nonce_action    = self::clean_identifier( $config['nonce_action'] ?? 'crm_leads_capture_' . $slug );
		$this->nonce_field     = self::clean_identifier( $config['nonce_field'] ?? '_wpnonce' );
		$this->honeypot_field  = self::clean_identifier( $config['honeypot_field'] ?? 'crm_leads_capture_website' );
	}

	public function slug(): string {
		return $this->slug;
	}

	/**
	 * @return array<string, CRM_Leads_Capture_Field>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function context(): array {
		return $this->context;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function provider_config( string $provider_id ): array {
		$config = $this->providers[ self::normalize_key( $provider_id ) ] ?? array();

		return is_array( $config ) ? $config : array();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function success_behavior(): array {
		return $this->success;
	}

	public function nonce_action(): string {
		return $this->nonce_action;
	}

	public function nonce_field(): string {
		return $this->nonce_field;
	}

	public function honeypot_field(): string {
		return $this->honeypot_field;
	}

	private static function normalize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $value ) );
	}

	/**
	 * @param mixed $value Identifier value.
	 */
	private static function clean_identifier( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}
}
