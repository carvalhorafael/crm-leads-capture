<?php
/**
 * Provider decorator that exposes the last result to compatibility adapters.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Recording_Provider implements CRM_Leads_Capture_Provider_Interface {
	private CRM_Leads_Capture_Provider_Interface $provider;

	private string $provider_id;

	private ?CRM_Leads_Capture_Result $last_result = null;

	public function __construct( CRM_Leads_Capture_Provider_Interface $provider, string $provider_id = '' ) {
		$this->provider    = $provider;
		$this->provider_id = '' !== $provider_id ? $provider_id : $provider->id();
	}

	public function id(): string {
		return $this->provider_id;
	}

	public function label(): string {
		return $this->provider->label();
	}

	public function settings_fields(): array {
		return $this->provider->settings_fields();
	}

	public function material_fields(): array {
		return $this->provider->material_fields();
	}

	public function sanitize_settings( array $input ): array {
		return $this->provider->sanitize_settings( $input );
	}

	public function sanitize_material_meta( array $input ): array {
		return $this->provider->sanitize_material_meta( $input );
	}

	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result {
		$this->last_result = $this->provider->send_lead( $payload, $context );

		return $this->last_result;
	}

	public function error_codes(): array {
		return $this->provider->error_codes();
	}

	public function last_result(): ?CRM_Leads_Capture_Result {
		return $this->last_result;
	}
}
