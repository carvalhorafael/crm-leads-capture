<?php
/**
 * Registry and resolver for reusable capture profiles.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Profile_Registry {
	/**
	 * @var array<string, CRM_Leads_Capture_Profile>
	 */
	private array $profiles = array();

	public function register( CRM_Leads_Capture_Profile $profile ): void {
		$this->profiles[ $profile->slug() ] = $profile;
	}

	public function resolve( string $slug ): ?CRM_Leads_Capture_Profile {
		$slug = $this->normalize_key( $slug );

		return $this->profiles[ $slug ] ?? null;
	}

	/**
	 * @return array<string, CRM_Leads_Capture_Profile>
	 */
	public function all(): array {
		return $this->profiles;
	}

	private function normalize_key( string $value ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( (string) preg_replace( '/[^a-z0-9_\-]/i', '', $value ) );
	}
}
