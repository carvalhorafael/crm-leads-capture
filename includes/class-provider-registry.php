<?php
/**
 * CRM provider registry.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Provider_Registry {
	/**
	 * @var array<string, CRM_Leads_Capture_Provider_Interface>
	 */
	private array $providers = array();

	public function register( CRM_Leads_Capture_Provider_Interface $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	public function get( string $id ): ?CRM_Leads_Capture_Provider_Interface {
		return $this->providers[ $id ] ?? null;
	}

	public function active( string $id ): CRM_Leads_Capture_Provider_Interface {
		return $this->providers[ $id ] ?? $this->providers['brevo'];
	}

	/**
	 * @return array<string, CRM_Leads_Capture_Provider_Interface>
	 */
	public function all(): array {
		return $this->providers;
	}
}
