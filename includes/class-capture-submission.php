<?php
/**
 * Canonical capture submission.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Submission {
	private string $profile_slug;

	/** @var array<string, mixed> */
	private array $lead;

	/** @var array<string, mixed> */
	private array $tracking;

	/** @var array<string, mixed> */
	private array $consent;

	/** @var array<string, mixed> */
	private array $custom_fields;

	/** @var array<string, mixed> */
	private array $context;

	/**
	 * @param array<string, mixed> $lead Canonical lead fields.
	 * @param array<string, mixed> $tracking Tracking fields.
	 * @param array<string, mixed> $consent Consent fields.
	 * @param array<string, mixed> $custom_fields Custom fields.
	 * @param array<string, mixed> $context Trusted capture context.
	 */
	public function __construct(
		string $profile_slug,
		array $lead,
		array $tracking,
		array $consent,
		array $custom_fields,
		array $context
	) {
		$this->profile_slug = $profile_slug;
		$this->lead = $lead;
		$this->tracking = $tracking;
		$this->consent = $consent;
		$this->custom_fields = $custom_fields;
		$this->context = $context;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'profile_slug'  => $this->profile_slug,
			'lead'          => $this->lead,
			'tracking'      => $this->tracking,
			'consent'       => $this->consent,
			'custom_fields' => $this->custom_fields,
			'context'       => $this->context,
		);
	}
}
