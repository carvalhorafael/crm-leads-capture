<?php
/**
 * Site-level commercial profile fixtures used to verify the generic pipeline.
 *
 * These definitions intentionally live outside production code: installations
 * only receive profiles they create in the WordPress administration.
 *
 * @package CRM_Leads_Capture
 */

class CRM_Leads_Capture_Commercial_Profile_Fixtures {
	public const COO_SLUG = 'coo-as-a-service';
	public const SPEAKER_SLUG = 'speaker-invitation';

	/** @return array<string, array<string, mixed>> */
	public static function configs(): array {
		return array(
			self::COO_SLUG     => self::coo_config(),
			self::SPEAKER_SLUG => self::speaker_config(),
		);
	}

	public static function register( CRM_Leads_Capture_Profile_Registry $registry ): void {
		foreach ( self::configs() as $config ) {
			$fields = array_map(
				static fn( array $field ): CRM_Leads_Capture_Field => new CRM_Leads_Capture_Field( $field['name'], $field ),
				$config['fields']
			);
			$registry->register( new CRM_Leads_Capture_Profile( $config['slug'], $fields, $config ) );
		}
	}

	/** @return array<string, mixed> */
	public static function coo_config(): array {
		return array(
			'name'    => 'COO as a Service',
			'slug'    => self::COO_SLUG,
			'fields'  => array_merge(
				self::contact_fields(),
				array(
					self::field( 'company', 'text', true ),
					self::field( 'role', 'select', true, array( 'founder', 'ceo', 'executive' ) ),
					self::field( 'company_url', 'url' ),
					self::field( 'challenge', 'textarea', true ),
					self::field( 'consent', 'boolean', true, array(), 'consent' ),
				)
			),
			'context' => array( 'source' => 'coo_as_a_service' ),
			'providers' => array(
				'brevo' => array(
					'list_ids'     => array(),
					'attribute_map' => array(
						'custom_fields.company' => 'COMPANY', 'custom_fields.role' => 'ROLE',
						'custom_fields.company_url' => 'COMPANY_URL', 'custom_fields.challenge' => 'OPERATIONAL_CHALLENGE',
						'consent.consent' => 'PRIVACY_CONSENT', 'context.page_url' => 'PAGE_URL',
					),
				),
				'rd_station' => array(
					'conversion_identifier' => 'coo-as-a-service-interest',
					'tags' => array( 'coo-as-a-service' ),
					'field_map' => array(
						'custom_fields.company' => 'company_name', 'custom_fields.role' => 'job_title',
						'custom_fields.company_url' => 'company_site', 'custom_fields.challenge' => 'cf_operational_challenge',
						'consent.consent' => 'cf_privacy_consent', 'context.source' => 'cf_lead_source',
						'context.page_url' => 'cf_page_url', 'tracking.utm_content' => 'cf_utm_content',
						'tracking.utm_name' => 'cf_utm_name',
					),
				),
			),
			'success' => array( 'message' => 'Recebi seu contexto. Vou analisar as informações e entrarei em contato.' ),
		);
	}

	/** @return array<string, mixed> */
	public static function speaker_config(): array {
		return array(
			'name'    => 'Convite para palestras',
			'slug'    => self::SPEAKER_SLUG,
			'fields'  => array_merge(
				self::contact_fields(),
				array(
					self::field( 'organization', 'text', true ), self::field( 'event_name', 'text', true ),
					self::field( 'objective_context', 'textarea', true ), self::field( 'audience_profile', 'textarea', true ),
					self::field( 'audience_size', 'integer', true ), self::field( 'event_date', 'date', true ),
					self::field( 'location', 'text', true ),
					self::field( 'format', 'select', true, array( 'presencial', 'online', 'hibrido' ) ),
					self::field( 'consent', 'boolean', true, array(), 'consent' ),
				)
			),
			'context' => array( 'source' => 'speaker_invitation' ),
			'providers' => array(
				'brevo' => array(
					'list_ids' => array(),
					'attribute_map' => array(
						'custom_fields.organization' => 'ORGANIZATION', 'custom_fields.event_name' => 'EVENT_NAME',
						'custom_fields.objective_context' => 'EVENT_OBJECTIVE', 'custom_fields.audience_profile' => 'AUDIENCE_PROFILE',
						'custom_fields.audience_size' => 'AUDIENCE_SIZE', 'custom_fields.event_date' => 'EVENT_DATE',
						'custom_fields.location' => 'EVENT_LOCATION', 'custom_fields.format' => 'EVENT_FORMAT',
						'consent.consent' => 'PRIVACY_CONSENT', 'context.page_url' => 'PAGE_URL',
					),
				),
				'rd_station' => array(
					'conversion_identifier' => 'speaker-invitation', 'tags' => array( 'speaker', 'event' ),
					'field_map' => array(
						'custom_fields.organization' => 'company_name', 'custom_fields.event_name' => 'cf_event_name',
						'custom_fields.objective_context' => 'cf_event_objective', 'custom_fields.audience_profile' => 'cf_audience_profile',
						'custom_fields.audience_size' => 'cf_audience_size', 'custom_fields.event_date' => 'cf_event_date',
						'custom_fields.location' => 'cf_event_location', 'custom_fields.format' => 'cf_event_format',
						'consent.consent' => 'cf_privacy_consent', 'context.source' => 'cf_lead_source',
						'context.page_url' => 'cf_page_url', 'tracking.utm_content' => 'cf_utm_content',
						'tracking.utm_name' => 'cf_utm_name',
					),
				),
			),
			'success' => array( 'message' => 'Recebi os detalhes do evento. Vou analisar o convite e retornar.' ),
		);
	}

	/** @return array<int, array<string, mixed>> */
	private static function contact_fields(): array {
		$fields = array(
			self::field( 'name', 'text', true, array(), 'lead' ),
			self::field( 'email', 'email', true, array(), 'lead' ),
			self::field( 'whatsapp', 'phone', true, array(), 'lead' ),
		);
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name' ) as $name ) {
			$fields[] = self::field( $name, 'text', false, array(), 'tracking' );
		}
		return $fields;
	}

	/** @param array<int, string> $allowed_values @return array<string, mixed> */
	private static function field( string $name, string $type, bool $required = false, array $allowed_values = array(), string $group = 'custom_fields' ): array {
		$field = array( 'name' => $name, 'type' => $type, 'group' => $group, 'required' => $required );
		if ( array() !== $allowed_values ) {
			$field['allowed_values'] = $allowed_values;
		}
		return $field;
	}
}
