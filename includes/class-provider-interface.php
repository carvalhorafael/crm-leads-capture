<?php
/**
 * CRM provider contract.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface CRM_Leads_Capture_Provider_Interface {
	public function id(): string;

	public function label(): string;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function settings_fields(): array;

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function material_fields(): array;

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $input ): array;

	/**
	 * @param array<string, mixed> $input
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_material_meta( array $input ): array;

	/**
	 * @param array<string, mixed> $payload
	 * @param array<string, mixed> $context
	 */
	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result;

	/**
	 * @return array<string, string>
	 */
	public function error_codes(): array;
}
