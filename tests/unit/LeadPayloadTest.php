<?php
/**
 * Lead payload unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Lead_Payload;

class LeadPayloadTest extends TestCase {
	public function test_builds_brevo_contact_payload(): void {
		$builder = new CRM_Leads_Capture_Lead_Payload();

		$result = $builder->build_contact(
			array(
				'name'         => 'Rafael Carvalho',
				'email'        => ' RAFAEL@example.com ',
				'whatsapp'     => '+55 (11) 99999-9999',
				'utm_source'   => 'newsletter',
				'utm_campaign' => 'lead magnet',
			),
			array(
				'source'   => 'free_material',
				'material' => 'Guia Executivo',
				'list_id'  => '123',
			)
		);

		$this->assertTrue( $result->is_successful() );

		$payload = $result->data()['payload'];

		$this->assertSame( 'rafael@example.com', $payload['email'] );
		$this->assertSame( true, $payload['updateEnabled'] );
		$this->assertSame( array( 123 ), $payload['listIds'] );
		$this->assertSame( 'Rafael', $payload['attributes']['FIRSTNAME'] );
		$this->assertSame( 'Carvalho', $payload['attributes']['LASTNAME'] );
		$this->assertSame( '+5511999999999', $payload['attributes']['WHATSAPP'] );
		$this->assertSame( 'free_material', $payload['attributes']['SOURCE'] );
		$this->assertSame( 'Guia Executivo', $payload['attributes']['MATERIAL'] );
		$this->assertSame( 'newsletter', $payload['attributes']['UTM_SOURCE'] );
		$this->assertSame( 'lead magnet', $payload['attributes']['UTM_CAMPAIGN'] );
	}

	public function test_rejects_invalid_email(): void {
		$builder = new CRM_Leads_Capture_Lead_Payload();

		$result = $builder->build_contact(
			array(
				'name'  => 'Rafael',
				'email' => 'not-an-email',
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'Invalid email.', $result->message() );
	}

	public function test_normalizes_brazilian_whatsapp_without_country_code(): void {
		$builder = new CRM_Leads_Capture_Lead_Payload();

		$this->assertSame( '+5511999999999', $builder->normalize_whatsapp( '11999999999' ) );
		$this->assertSame( '+5511999999999', $builder->normalize_whatsapp( '55 11 99999-9999' ) );
		$this->assertSame( '+5511999999999', $builder->normalize_whatsapp( '+55 (11) 99999-9999' ) );
		$this->assertSame( '123456789', $builder->normalize_whatsapp( '123456789' ) );
	}

	public function test_ignores_empty_and_invalid_list_ids(): void {
		$builder = new CRM_Leads_Capture_Lead_Payload();

		$result = $builder->build_contact(
			array(
				'email'    => 'lead@example.com',
				'list_ids' => array( '0', '456', '-1', '456' ),
			)
		);

		$payload = $result->data()['payload'];

		$this->assertSame( array( 456 ), $payload['listIds'] );
	}

	public function test_preserves_supported_custom_attribute_types_and_omits_empty_values(): void {
		$builder = new CRM_Leads_Capture_Lead_Payload();
		$result  = $builder->build_contact(
			array( 'email' => 'lead@example.com' ),
			array(
				'attributes' => array(
					'COMPANY'    => 'Acme',
					'TEAM_SIZE'  => 12,
					'SCORE'      => 9.5,
					'CONSENT'    => false,
					'INTERESTS'  => array( 'COO', 'Palestras' ),
					'EMPTY_TEXT' => '',
					'EMPTY_LIST' => array(),
				),
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'Acme', $result->data()['payload']['attributes']['COMPANY'] );
		$this->assertSame( 12, $result->data()['payload']['attributes']['TEAM_SIZE'] );
		$this->assertSame( 9.5, $result->data()['payload']['attributes']['SCORE'] );
		$this->assertFalse( $result->data()['payload']['attributes']['CONSENT'] );
		$this->assertSame( array( 'COO', 'Palestras' ), $result->data()['payload']['attributes']['INTERESTS'] );
		$this->assertArrayNotHasKey( 'EMPTY_TEXT', $result->data()['payload']['attributes'] );
		$this->assertArrayNotHasKey( 'EMPTY_LIST', $result->data()['payload']['attributes'] );
	}

	/**
	 * @dataProvider invalid_attributes_provider
	 *
	 * @param mixed $value Attribute value.
	 */
	public function test_rejects_invalid_custom_attribute_before_http_payload( string $key, $value ): void {
		$result = ( new CRM_Leads_Capture_Lead_Payload() )->build_contact(
			array( 'email' => 'lead@example.com' ),
			array( 'attributes' => array( $key => $value ) )
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_payload', $result->data()['code'] );
	}

	/**
	 * @return array<string, array{string, mixed}>
	 */
	public function invalid_attributes_provider(): array {
		return array(
			'lowercase name' => array( 'company', 'Acme' ),
			'invalid name'   => array( 'COMPANY-NAME', 'Acme' ),
			'object value'   => array( 'COMPANY', (object) array( 'name' => 'Acme' ) ),
			'mixed list'     => array( 'INTERESTS', array( 'COO', 2 ) ),
		);
	}
}
