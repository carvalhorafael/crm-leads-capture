<?php
/**
 * Built-in capture profiles owned by the plugin.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Profile_Defaults {
	public const COO_SLUG = 'coo-as-a-service';
	public const SPEAKER_SLUG = 'speaker-invitation';

	private CRM_Leads_Capture_Settings $settings;

	public function __construct( CRM_Leads_Capture_Settings $settings ) {
		$this->settings = $settings;
	}

	public function register( CRM_Leads_Capture_Profile_Registry $registry ): void {
		$registry->register( $this->coo_profile() );
		$registry->register( $this->speaker_profile() );
	}

	public function coo_profile(): CRM_Leads_Capture_Profile {
		return new CRM_Leads_Capture_Profile(
			self::COO_SLUG,
			array_merge(
				$this->contact_fields(),
				array(
					$this->field( 'company', 'text', true ),
					$this->field( 'role', 'select', true, array( 'founder', 'ceo', 'executive' ) ),
					$this->field( 'company_url', 'url' ),
					$this->field( 'challenge', 'textarea', true ),
					$this->consent_field(),
				)
			),
			array(
				'context' => array(
					'source'   => 'coo_as_a_service',
					'material' => 'COO as a Service',
				),
				'providers' => array(
					'brevo' => array(
						'list_ids'     => array(),
						'attribute_map' => array(
							'custom_fields.company'     => 'COMPANY',
							'custom_fields.role'        => 'ROLE',
							'custom_fields.company_url' => 'COMPANY_URL',
							'custom_fields.challenge'   => 'OPERATIONAL_CHALLENGE',
							'consent.consent'           => 'PRIVACY_CONSENT',
							'context.page_url'          => 'PAGE_URL',
						),
					),
					'rd_station' => array(
						'conversion_identifier' => 'coo-as-a-service-interest',
						'tags'                  => array( 'coo-as-a-service' ),
						'field_map'             => array(
							'custom_fields.company'     => 'company_name',
							'custom_fields.role'        => 'job_title',
							'custom_fields.company_url' => 'company_site',
							'custom_fields.challenge'   => 'cf_operational_challenge',
							'consent.consent'           => 'cf_privacy_consent',
							'context.source'            => 'cf_lead_source',
							'context.page_url'          => 'cf_page_url',
							'tracking.utm_content'      => 'cf_utm_content',
							'tracking.utm_name'         => 'cf_utm_name',
						),
					),
				),
				'success' => array( 'message' => $this->settings->service_success_message() ),
				'nonce_action'   => 'crm_leads_capture_service_interest',
				'nonce_field'    => '_wpnonce',
				'honeypot_field' => 'crm_leads_capture_website',
			)
		);
	}

	public function speaker_profile(): CRM_Leads_Capture_Profile {
		return new CRM_Leads_Capture_Profile(
			self::SPEAKER_SLUG,
			array_merge(
				$this->contact_fields(),
				array(
					$this->field( 'organization', 'text', true ),
					$this->field( 'event_name', 'text', true ),
					$this->field( 'objective_context', 'textarea', true ),
					$this->field( 'audience_profile', 'textarea', true ),
					$this->field( 'audience_size', 'integer', true ),
					$this->field( 'event_date', 'date', true ),
					$this->field( 'location', 'text', true ),
					$this->field( 'format', 'select', true, array( 'presencial', 'online', 'hibrido' ) ),
					$this->consent_field(),
				)
			),
			array(
				'context' => array(
					'source'   => 'speaker_invitation',
					'material' => 'Convite para palestras',
				),
				'providers' => array(
					'brevo' => array(
						'list_ids'     => array(),
						'attribute_map' => array(
							'custom_fields.organization'      => 'ORGANIZATION',
							'custom_fields.event_name'        => 'EVENT_NAME',
							'custom_fields.objective_context' => 'EVENT_OBJECTIVE',
							'custom_fields.audience_profile'  => 'AUDIENCE_PROFILE',
							'custom_fields.audience_size'     => 'AUDIENCE_SIZE',
							'custom_fields.event_date'        => 'EVENT_DATE',
							'custom_fields.location'          => 'EVENT_LOCATION',
							'custom_fields.format'            => 'EVENT_FORMAT',
							'consent.consent'                 => 'PRIVACY_CONSENT',
							'context.page_url'                => 'PAGE_URL',
						),
					),
					'rd_station' => array(
						'conversion_identifier' => 'speaker-invitation',
						'tags'                  => array( 'speaker', 'event' ),
						'field_map'             => array(
							'custom_fields.organization'      => 'company_name',
							'custom_fields.event_name'        => 'cf_event_name',
							'custom_fields.objective_context' => 'cf_event_objective',
							'custom_fields.audience_profile'  => 'cf_audience_profile',
							'custom_fields.audience_size'     => 'cf_audience_size',
							'custom_fields.event_date'        => 'cf_event_date',
							'custom_fields.location'          => 'cf_event_location',
							'custom_fields.format'            => 'cf_event_format',
							'consent.consent'                 => 'cf_privacy_consent',
							'context.source'                  => 'cf_lead_source',
							'context.page_url'                => 'cf_page_url',
							'tracking.utm_content'            => 'cf_utm_content',
							'tracking.utm_name'               => 'cf_utm_name',
						),
					),
				),
				'success' => array(
					'message' => __( 'Recebi os detalhes do evento. Vou analisar o convite e retornarei sobre a disponibilidade.', 'crm-leads-capture' ),
				),
			)
		);
	}

	/** @return array<int, CRM_Leads_Capture_Field> */
	private function contact_fields(): array {
		return array_merge(
			array(
				new CRM_Leads_Capture_Field( 'name', array( 'required' => true, 'group' => CRM_Leads_Capture_Field::GROUP_LEAD ) ),
				new CRM_Leads_Capture_Field( 'email', array( 'type' => 'email', 'required' => true, 'group' => CRM_Leads_Capture_Field::GROUP_LEAD ) ),
				new CRM_Leads_Capture_Field( 'whatsapp', array( 'type' => 'phone', 'required' => true, 'group' => CRM_Leads_Capture_Field::GROUP_LEAD ) ),
			),
			array_map(
				static fn( string $name ): CRM_Leads_Capture_Field => new CRM_Leads_Capture_Field( $name, array( 'group' => CRM_Leads_Capture_Field::GROUP_TRACKING ) ),
				array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name' )
			)
		);
	}

	/** @param array<int, string> $allowed_values */
	private function field( string $name, string $type, bool $required = false, array $allowed_values = array() ): CRM_Leads_Capture_Field {
		return new CRM_Leads_Capture_Field(
			$name,
			array(
				'type'           => $type,
				'group'          => CRM_Leads_Capture_Field::GROUP_CUSTOM,
				'required'       => $required,
				'allowed_values' => $allowed_values,
			)
		);
	}

	private function consent_field(): CRM_Leads_Capture_Field {
		return new CRM_Leads_Capture_Field(
			'consent',
			array(
				'type'     => 'boolean',
				'group'    => CRM_Leads_Capture_Field::GROUP_CONSENT,
				'required' => true,
			)
		);
	}
}
