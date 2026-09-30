<?php
/**
 * Persistent capture profile and page association repository.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Profile_Repository {
	public const OPTION_PROFILES = 'crm_leads_capture_profiles';
	public const PAGE_PROFILE_META = '_crm_leads_capture_profile';
	public const PAGE_OVERRIDE_ENABLED_META = '_crm_leads_capture_override_enabled';
	public const PAGE_OVERRIDES_META = '_crm_leads_capture_provider_overrides';

	private CRM_Leads_Capture_Settings $settings;

	public function __construct( CRM_Leads_Capture_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_page_meta' ) );
	}

	public function register_page_meta(): void {
		register_post_meta(
			'page',
			self::PAGE_PROFILE_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'sanitize_key',
				'auth_callback'     => static fn( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
				'show_in_rest'      => array(
					'schema' => array(
						'type' => 'string',
					),
				),
			)
		);
	}

	public function register_profiles( CRM_Leads_Capture_Profile_Registry $registry ): void {
		foreach ( $this->all() as $config ) {
			$profile = $this->profile_from_config( $config );
			if ( null !== $profile ) {
				$registry->register( $profile );
			}
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$profiles = get_option( self::OPTION_PROFILES, array() );

		return is_array( $profiles ) ? $profiles : array();
	}

	/**
	 * Settings API sanitizer that merges one edited profile without deleting
	 * inactive provider configuration.
	 *
	 * @param mixed $input Submitted option value.
	 * @return array<string, array<string, mixed>>
	 */
	public function sanitize_option( $input ): array {
		$stored = $this->all();
		$input  = is_array( $input ) ? $input : array();
		$slug   = $this->clean_key( $input['original_slug'] ?? $input['slug'] ?? '' );

		if ( true === (bool) ( $input['delete'] ?? false ) ) {
			unset( $stored[ $slug ] );
			return $stored;
		}

		$config = $this->sanitize_profile( $input, $stored[ $slug ] ?? array() );
		if ( null === $config ) {
			add_settings_error( self::OPTION_PROFILES, 'invalid_profile', __( 'O perfil precisa de nome, slug e pelo menos um campo válido.', 'crm-leads-capture' ) );
			return $stored;
		}

		$new_slug = $config['slug'];
		if ( '' !== $slug && $slug !== $new_slug ) {
			unset( $stored[ $slug ] );
		}
		$stored[ $new_slug ] = $config;

		return $stored;
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<string, mixed> $existing
	 * @return array<string, mixed>|null
	 */
	public function sanitize_profile( array $input, array $existing = array() ): ?array {
		$name = $this->clean_text( $input['name'] ?? '' );
		$slug = $this->clean_key( $input['slug'] ?? '' );
		if ( '' === $name || '' === $slug ) {
			return null;
		}

		$fields = array();
		$seen   = array();
		foreach ( (array) ( $input['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field_name = $this->clean_key( $field['name'] ?? '' );
			$type       = $this->clean_key( $field['type'] ?? 'text' );
			$group      = $this->clean_key( $field['group'] ?? 'custom_fields' );
			if ( '' === $field_name || isset( $seen[ $field_name ] ) || ! in_array( $type, $this->field_types(), true ) || ! in_array( $group, $this->field_groups(), true ) ) {
				continue;
			}
			$seen[ $field_name ] = true;
			$allowed_values = array();
			if ( 'select' === $type ) {
				$submitted_values = is_array( $field['allowed_values'] ?? null ) ? $field['allowed_values'] : explode( ',', (string) ( $field['allowed_values'] ?? '' ) );
				foreach ( $submitted_values as $value ) {
					$value = $this->clean_key( $value );
					if ( '' !== $value ) {
						$allowed_values[] = $value;
					}
				}
				$allowed_values = array_values( array_unique( $allowed_values ) );
				if ( array() === $allowed_values ) {
					add_settings_error(
						self::OPTION_PROFILES,
						'invalid_select_values',
						__( 'Campos do tipo select precisam informar pelo menos um valor permitido.', 'crm-leads-capture' )
					);
					continue;
				}
			}
			$fields[] = array_filter(
				array(
					'name'           => $field_name,
					'type'           => $type,
					'group'          => $group,
					'required'       => ! empty( $field['required'] ),
					'allowed_values' => $allowed_values,
				),
				static fn( $value, string $key ): bool => 'allowed_values' !== $key || array() !== $value,
				ARRAY_FILTER_USE_BOTH
			);
		}
		if ( array() === $fields ) {
			return null;
		}

		$providers = isset( $existing['providers'] ) && is_array( $existing['providers'] ) ? $existing['providers'] : array();
		$active    = $this->settings->active_provider();
		$submitted = isset( $input['providers'][ $active ] ) && is_array( $input['providers'][ $active ] ) ? $input['providers'][ $active ] : array();
		$providers[ $active ] = $this->sanitize_provider_config( $active, $submitted );

		return array(
			'name'      => $name,
			'slug'      => $slug,
			'fields'    => $fields,
			'context'   => array( 'source' => $this->clean_key( $input['source'] ?? $slug ) ),
			'providers' => $providers,
			'success'   => array_filter(
				array(
					'message'      => $this->clean_textarea( $input['success_message'] ?? '' ),
					'redirect_url' => $this->clean_url( $input['redirect_url'] ?? '' ),
				),
				static fn( string $value ): bool => '' !== $value
			),
		);
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function sanitize_provider_config( string $provider, array $input ): array {
		if ( 'brevo' === $provider ) {
			return array(
				'list_ids'      => $this->positive_ids( $input['list_ids'] ?? array() ),
				'attribute_map' => $this->mapping( $input['attribute_map'] ?? '' ),
			);
		}

		return array(
			'conversion_identifier' => $this->clean_text( $input['conversion_identifier'] ?? '' ),
			'tags'                  => $this->tags( $input['tags'] ?? array() ),
			'field_map'             => $this->mapping( $input['field_map'] ?? '' ),
		);
	}

	public function page_profile_slug( int $page_id ): string {
		return 0 < $page_id ? $this->clean_key( get_post_meta( $page_id, self::PAGE_PROFILE_META, true ) ) : '';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function page_provider_overrides( int $page_id, string $provider ): array {
		if ( 0 >= $page_id || '1' !== (string) get_post_meta( $page_id, self::PAGE_OVERRIDE_ENABLED_META, true ) ) {
			return array();
		}

		$overrides = get_post_meta( $page_id, self::PAGE_OVERRIDES_META, true );
		$config    = is_array( $overrides ) && isset( $overrides[ $provider ] ) && is_array( $overrides[ $provider ] ) ? $overrides[ $provider ] : array();

		return array_filter(
			$this->sanitize_provider_config( $provider, $config ),
			static fn( $value ): bool => '' !== $value && array() !== $value
		);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	private function profile_from_config( array $config ): ?CRM_Leads_Capture_Profile {
		try {
			$fields = array();
			foreach ( (array) ( $config['fields'] ?? array() ) as $field ) {
				if ( is_array( $field ) ) {
					$fields[] = new CRM_Leads_Capture_Field( (string) ( $field['name'] ?? '' ), $field );
				}
			}
			if ( array() === $fields ) {
				return null;
			}

			return new CRM_Leads_Capture_Profile( (string) ( $config['slug'] ?? '' ), $fields, $config );
		} catch ( InvalidArgumentException $exception ) {
			return null;
		}
	}

	/** @return array<int, string> */
	public function field_types(): array {
		return array( 'text', 'textarea', 'email', 'phone', 'url', 'integer', 'number', 'boolean', 'date', 'select' );
	}

	/** @return array<int, string> */
	public function field_groups(): array {
		return array( 'lead', 'tracking', 'consent', 'custom_fields' );
	}

	/** @param mixed $value @return array<int, int> */
	private function positive_ids( $value ): array {
		$values = is_array( $value ) ? $value : explode( ',', (string) $value );
		$ids    = array_filter( array_map( 'intval', $values ), static fn( int $id ): bool => 0 < $id );

		return array_values( array_unique( $ids ) );
	}

	/** @param mixed $value @return array<int, string> */
	private function tags( $value ): array {
		$values = is_array( $value ) ? $value : explode( ',', (string) $value );
		$tags   = array_filter( array_map( array( $this, 'clean_text' ), $values ) );

		return array_values( array_unique( $tags ) );
	}

	/** @param mixed $value @return array<string, string> */
	private function mapping( $value ): array {
		if ( is_array( $value ) ) {
			$map = array();
			foreach ( $value as $source => $target ) {
				if ( is_scalar( $source ) && is_scalar( $target ) && '' !== trim( (string) $source ) && '' !== trim( (string) $target ) ) {
					$map[ trim( (string) $source ) ] = trim( (string) $target );
				}
			}
			return $map;
		}

		$map = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) ?: array() as $line ) {
			$parts = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
				$map[ $parts[0] ] = $parts[1];
			}
		}

		return $map;
	}

	private function clean_key( $value ): string {
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	private function clean_text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private function clean_textarea( $value ): string {
		return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	}

	private function clean_url( $value ): string {
		return is_scalar( $value ) ? esc_url_raw( (string) $value ) : '';
	}
}
