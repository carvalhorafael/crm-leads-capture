<?php
/**
 * RD Station provider unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_RD_Station_Client;
use CRM_Leads_Capture_RD_Station_Provider;
use CRM_Leads_Capture_Settings;

class RDStationProviderTestSettings extends CRM_Leads_Capture_Settings {
	public string $conversion_identifier = '';

	public string $default_tags = '';

	public function rd_station_api_key(): string {
		return 'test-token';
	}

	public function rd_station_default_conversion_identifier(): string {
		return $this->conversion_identifier;
	}

	public function rd_station_default_tags(): string {
		return $this->default_tags;
	}
}

class RDStationProviderTest extends TestCase {
	/** @var array<string, mixed>|null */
	private ?array $request_event;

	private int $http_calls;

	private int $response_status;

	protected function set_up(): void {
		parent::set_up();
		$this->request_event   = null;
		$this->http_calls      = 0;
		$this->response_status = 200;
	}

	public function test_exposes_expected_structural_contract(): void {
		$provider = new CRM_Leads_Capture_RD_Station_Provider( new RDStationProviderTestSettings() );

		$this->assertSame( 'rd_station', $provider->id() );
		$this->assertArrayHasKey( 'api_key', $provider->settings_fields() );
		$this->assertArrayHasKey( 'default_conversion_identifier', $provider->settings_fields() );
		$this->assertArrayHasKey( 'conversion_identifier', $provider->material_fields() );
		$this->assertArrayHasKey( 'rd_station_error', $provider->error_codes() );
	}

	public function test_maps_coo_fields_tags_analytics_and_custom_api_identifiers(): void {
		$result = $this->provider()->send_lead(
			$this->canonical_payload(),
			array(
				'conversion_identifier' => 'coo-interest',
				'tags'                  => array( 'coo', ' inbound ', 'coo', '' ),
				'analytics_device_id'   => 'device-123',
				'field_map'             => array(
					'custom_fields.company'           => 'company_name',
					'custom_fields.company_site'      => 'company_site',
					'custom_fields.job_title'         => 'job_title',
					'custom_fields.biggest_challenge' => 'cf_biggest_challenge',
					'consent.consent'                 => 'cf_privacy_consent',
					'context.source'                   => 'cf_lead_source',
				),
			)
		);

		$event_payload = $this->request_event['payload'];
		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'coo-interest', $event_payload['conversion_identifier'] );
		$this->assertSame( 'rafael@example.com', $event_payload['email'] );
		$this->assertSame( '+5511999999999', $event_payload['mobile_phone'] );
		$this->assertSame( 'Acme', $event_payload['company_name'] );
		$this->assertSame( 'https://acme.example', $event_payload['company_site'] );
		$this->assertSame( 'CEO', $event_payload['job_title'] );
		$this->assertSame( 'Crescimento', $event_payload['cf_biggest_challenge'] );
		$this->assertSame( 'true', $event_payload['cf_privacy_consent'] );
		$this->assertSame( 'coo_as_a_service', $event_payload['cf_lead_source'] );
		$this->assertSame( 'device-123', $event_payload['cf_amplitude_device_id'] );
		$this->assertSame( 'linkedin', $event_payload['traffic_source'] );
		$this->assertSame( array( 'coo', 'inbound' ), $event_payload['tags'] );
	}

	public function test_maps_speaker_fields_to_custom_identifiers(): void {
		$payload                  = $this->canonical_payload();
		$payload['profile_slug']  = 'speaker';
		$payload['custom_fields'] = array(
			'event_name'     => 'Summit 2027',
			'audience_size'  => 500,
			'speaking_topic' => 'Operações',
		);
		$payload['consent'] = array();

		$result = $this->provider()->send_lead(
			$payload,
			array(
				'conversion_identifier' => 'speaker-invitation',
				'tags'                  => 'speaker, events, speaker',
				'field_map'             => array(
					'custom_fields.event_name'     => 'cf_event_name',
					'custom_fields.audience_size'  => 'cf_audience_size',
					'custom_fields.speaking_topic' => 'cf_speaking_topic',
					'context.source'                => 'cf_lead_source',
				),
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Summit 2027', $this->request_event['payload']['cf_event_name'] );
		$this->assertSame( '500', $this->request_event['payload']['cf_audience_size'] );
		$this->assertSame( 'Operações', $this->request_event['payload']['cf_speaking_topic'] );
		$this->assertSame( array( 'speaker', 'events' ), $this->request_event['payload']['tags'] );
	}

	public function test_omits_empty_tags_instead_of_sending_an_empty_tag_list(): void {
		$payload                  = $this->canonical_payload();
		$payload['custom_fields'] = array();
		$payload['consent']       = array();

		$result = $this->provider()->send_lead(
			$payload,
			array( 'conversion_identifier' => 'empty-tags', 'tags' => array( '', ' ' ) )
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertArrayNotHasKey( 'tags', $this->request_event['payload'] );
	}

	public function test_rejects_empty_conversion_for_canonical_profile_before_http_call(): void {
		$result = $this->provider()->send_lead( $this->canonical_payload(), array() );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'missing_conversion', $result->data()['code'] );
		$this->assertSame( 0, $this->http_calls );
	}

	public function test_rejects_missing_and_invalid_field_identifiers_before_http_call(): void {
		$missing = $this->provider()->send_lead(
			$this->canonical_payload(),
			array( 'conversion_identifier' => 'coo' )
		);
		$invalid = $this->provider()->send_lead(
			$this->canonical_payload(),
			array(
				'conversion_identifier' => 'coo',
				'field_map'             => array( 'custom_fields.company' => 'custom_company' ),
			)
		);

		$this->assertSame( 'invalid_payload', $missing->data()['code'] );
		$this->assertSame( array( 'custom_fields.company' ), $missing->data()['fields'] );
		$this->assertSame( 'invalid_payload', $invalid->data()['code'] );
		$this->assertSame( 0, $this->http_calls );
	}

	public function test_preserves_legacy_material_conversion_fallback(): void {
		$result = $this->provider()->send_lead(
			array(
				'email'    => 'lead@example.com',
				'name'     => 'Lead',
				'material' => 'Guia de Operações',
				'source'   => 'free_material',
			),
			array()
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Guia de Operações', $this->request_event['payload']['conversion_identifier'] );
		$this->assertSame( 'Guia de Operações', $this->request_event['payload']['cf_material'] );
		$this->assertSame( 'free_material', $this->request_event['payload']['cf_source'] );
	}

	public function test_propagates_http_failure_without_personal_values_in_summary(): void {
		$this->response_status = 400;
		$payload                  = $this->canonical_payload();
		$payload['custom_fields'] = array();
		$payload['consent']       = array();

		$result = $this->provider()->send_lead( $payload, array( 'conversion_identifier' => 'coo' ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 400, $result->status_code() );
		$this->assertSame( array( 'INVALID_FIELD' ), $result->data()['error_summary']['error_types'] );
		$this->assertSame( array( '$.payload.cf_private' ), $result->data()['error_summary']['paths'] );
		$this->assertStringNotContainsString( 'rafael@example.com', serialize( $result->data()['error_summary'] ) );
	}

	private function provider(): CRM_Leads_Capture_RD_Station_Provider {
		return new CRM_Leads_Capture_RD_Station_Provider(
			new RDStationProviderTestSettings(),
			function ( string $token ): CRM_Leads_Capture_RD_Station_Client {
				return new CRM_Leads_Capture_RD_Station_Client(
					$token,
					function ( string $url, array $args ): array {
						++$this->http_calls;
						$this->request_event = json_decode( $args['body'], true );
						$this->assertStringContainsString( 'api_key=test-token', $url );

						return array(
							'response' => array( 'code' => $this->response_status ),
							'body'     => 400 === $this->response_status
								? '{"errors":[{"error_type":"INVALID_FIELD","error_message":"rafael@example.com","path":"$.payload.cf_private"}]}'
								: '{"event_uuid":"event-123"}',
						);
					}
				);
			}
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function canonical_payload(): array {
		return array(
			'profile_slug' => 'coo',
			'lead' => array(
				'name'     => 'Rafael Carvalho',
				'email'    => 'rafael@example.com',
				'whatsapp' => '+5511999999999',
			),
			'tracking' => array( 'utm_source' => 'linkedin' ),
			'consent' => array( 'consent' => true ),
			'custom_fields' => array(
				'company'           => 'Acme',
				'company_site'      => 'https://acme.example',
				'job_title'         => 'CEO',
				'biggest_challenge' => 'Crescimento',
			),
			'context' => array( 'source' => 'coo_as_a_service' ),
		);
	}
}
