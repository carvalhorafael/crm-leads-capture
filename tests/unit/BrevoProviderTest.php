<?php
/**
 * Brevo provider profile mapping tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Brevo_Client;
use CRM_Leads_Capture_Brevo_Provider;
use CRM_Leads_Capture_Result;
use CRM_Leads_Capture_Settings;

class BrevoProviderTestSettings extends CRM_Leads_Capture_Settings {
	public function brevo_api_key(): string {
		return 'test-api-key';
	}

	public function brevo_default_list_id(): int {
		return 99;
	}
}

class BrevoProviderTest extends TestCase {
	/** @var array<string, mixed>|null */
	private ?array $request_payload;

	private int $http_calls;

	private int $response_status;

	protected function set_up(): void {
		parent::set_up();
		$this->request_payload = null;
		$this->http_calls      = 0;
		$this->response_status = 201;
	}

	public function test_maps_canonical_submission_to_multiple_lists_and_custom_attributes(): void {
		$payload             = $this->canonical_payload();
		$payload['list_ids'] = array( 999 );
		$result              = $this->provider()->send_lead(
			$payload,
			array(
				'list_ids' => array( 12, '13', 12 ),
				'attribute_map' => array(
					'custom_fields.company'   => 'COMPANY',
					'custom_fields.team_size' => 'TEAM_SIZE',
					'consent.consent'         => 'PRIVACY_CONSENT',
				),
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( array( 12, 13 ), $this->request_payload['listIds'] );
		$this->assertTrue( $this->request_payload['updateEnabled'] );
		$this->assertSame( 'Rafael', $this->request_payload['attributes']['FIRSTNAME'] );
		$this->assertSame( 'Carvalho', $this->request_payload['attributes']['LASTNAME'] );
		$this->assertSame( '+5511999999999', $this->request_payload['attributes']['WHATSAPP'] );
		$this->assertSame( 'coo_as_a_service', $this->request_payload['attributes']['SOURCE'] );
		$this->assertSame( 'linkedin', $this->request_payload['attributes']['UTM_SOURCE'] );
		$this->assertSame( 'Acme', $this->request_payload['attributes']['COMPANY'] );
		$this->assertSame( 12, $this->request_payload['attributes']['TEAM_SIZE'] );
		$this->assertTrue( $this->request_payload['attributes']['PRIVACY_CONSENT'] );
	}

	public function test_maps_speaker_profile_fields_to_configured_attributes(): void {
		$payload = array(
			'profile_slug' => 'speaker',
			'lead'          => array(
				'name'      => 'Rafael Carvalho',
				'email'     => 'rafael@example.com',
				'job_title' => 'Diretor',
			),
			'tracking'      => array( 'referrer_kind' => 'partner' ),
			'consent'       => array(),
			'custom_fields' => array(
				'event_name'     => 'Summit 2027',
				'audience_size'  => 500,
				'speaking_topic' => 'Operações',
			),
			'context'       => array( 'source' => 'speaker_invitation' ),
		);

		$result = $this->provider()->send_lead(
			$payload,
			array(
				'list_id'       => 40,
				'attribute_map' => array(
					'lead.job_title'                   => 'JOB_TITLE',
					'tracking.referrer_kind'           => 'REFERRER_KIND',
					'custom_fields.event_name'     => 'EVENT_NAME',
					'custom_fields.audience_size'  => 'AUDIENCE_SIZE',
					'custom_fields.speaking_topic' => 'SPEAKING_TOPIC',
				),
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Summit 2027', $this->request_payload['attributes']['EVENT_NAME'] );
		$this->assertSame( 500, $this->request_payload['attributes']['AUDIENCE_SIZE'] );
		$this->assertSame( 'Operações', $this->request_payload['attributes']['SPEAKING_TOPIC'] );
		$this->assertSame( 'Diretor', $this->request_payload['attributes']['JOB_TITLE'] );
		$this->assertSame( 'partner', $this->request_payload['attributes']['REFERRER_KIND'] );
	}

	public function test_uses_default_list_for_legacy_payload_and_preserves_attributes(): void {
		$result = $this->provider()->send_lead(
			array(
				'name'  => 'Rafael Carvalho',
				'email' => 'rafael@example.com',
			),
			array( 'attributes' => array( 'WHO_IS' => 'Founder' ) )
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( array( 99 ), $this->request_payload['listIds'] );
		$this->assertSame( 'Founder', $this->request_payload['attributes']['WHO_IS'] );
	}

	public function test_omits_empty_custom_attributes_without_requiring_mapping(): void {
		$payload                            = $this->canonical_payload();
		$payload['custom_fields']['company'] = '';
		$payload['custom_fields']['team_size'] = '';
		$payload['consent']['consent']      = '';

		$result = $this->provider()->send_lead( $payload, array( 'list_id' => 12 ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertArrayNotHasKey( 'COMPANY', $this->request_payload['attributes'] );
		$this->assertArrayNotHasKey( 'TEAM_SIZE', $this->request_payload['attributes'] );
	}

	public function test_rejects_missing_or_invalid_attribute_mapping_before_http_call(): void {
		$missing = $this->provider()->send_lead(
			$this->canonical_payload(),
			array( 'list_id' => 12 )
		);
		$invalid = $this->provider()->send_lead(
			$this->canonical_payload(),
			array(
				'list_id'       => 12,
				'attribute_map' => array( 'custom_fields.company' => 'company-name' ),
			)
		);

		$this->assertSame( 'invalid_payload', $missing->data()['code'] );
		$this->assertSame( array( 'custom_fields.company' ), $missing->data()['fields'] );
		$this->assertSame( 'invalid_payload', $invalid->data()['code'] );
		$this->assertSame( 0, $this->http_calls );
	}

	public function test_rejects_duplicate_attribute_destinations_before_http_call(): void {
		$result = $this->provider()->send_lead(
			$this->canonical_payload(),
			array(
				'list_id'       => 12,
				'attribute_map' => array(
					'custom_fields.company'   => 'COMPANY',
					'custom_fields.team_size' => 'COMPANY',
					'consent.consent'         => 'PRIVACY_CONSENT',
				),
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_payload', $result->data()['code'] );
		$this->assertSame( 0, $this->http_calls );
	}

	public function test_rejects_missing_list_before_http_call(): void {
		$settings = new class() extends BrevoProviderTestSettings {
			public function brevo_default_list_id(): int {
				return 0;
			}
		};
		$provider = new CRM_Leads_Capture_Brevo_Provider(
			$settings,
			null,
			function (): CRM_Leads_Capture_Brevo_Client {
				++$this->http_calls;
				return new CRM_Leads_Capture_Brevo_Client( 'test-api-key' );
			}
		);

		$result = $provider->send_lead( array( 'email' => 'lead@example.com' ), array() );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'missing_list', $result->data()['code'] );
		$this->assertSame( 0, $this->http_calls );
	}

	public function test_propagates_api_failure_without_personal_data_in_result_metadata(): void {
		$this->response_status = 400;
		$result = $this->provider()->send_lead(
			array( 'email' => 'rafael@example.com' ),
			array( 'list_ids' => array( 12, 13 ) )
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 400, $result->status_code() );
		$this->assertStringNotContainsString( 'rafael@example.com', serialize( $result->data() ) );
	}

	private function provider(): CRM_Leads_Capture_Brevo_Provider {
		return new CRM_Leads_Capture_Brevo_Provider(
			new BrevoProviderTestSettings(),
			null,
			function ( string $api_key ): CRM_Leads_Capture_Brevo_Client {
				return new CRM_Leads_Capture_Brevo_Client(
					$api_key,
					function ( string $url, array $args ): array {
						unset( $url );
						++$this->http_calls;
						$this->request_payload = json_decode( $args['body'], true );

						return array(
							'response' => array( 'code' => $this->response_status ),
							'body'     => 400 === $this->response_status
								? '{"code":"invalid_parameter","message":"Invalid attribute"}'
								: '{"id":42}',
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
				'company'   => 'Acme',
				'team_size' => 12,
			),
			'context' => array( 'source' => 'coo_as_a_service' ),
		);
	}
}
