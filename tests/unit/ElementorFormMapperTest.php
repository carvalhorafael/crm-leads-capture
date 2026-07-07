<?php
/**
 * Elementor form mapper unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Elementor_Form_Mapper;
use CRM_Leads_Capture_Lead_Payload;

class ElementorFormMapperTest extends TestCase {
	public function test_maps_elementor_fields_to_payload_input_and_context(): void {
		$mapper = new CRM_Leads_Capture_Elementor_Form_Mapper();

		$fields = $mapper->normalize_fields(
			array(
				'email'             => array( 'value' => 'RAFAEL@example.com' ),
				'name'              => array( 'value' => 'Rafael' ),
				'last_name'         => array( 'value' => 'Carvalho' ),
				'whatsapp'          => array( 'value' => '11999999999' ),
				'who_is'            => array( 'value' => 'Founder' ),
				'biggest_challenge' => array( 'value' => 'Growth' ),
				'utm_source'        => array( 'value' => 'linkedin' ),
			)
		);

		$mapped = $mapper->map_to_payload_input(
			array(
				'brevo_list_id'                 => '123',
				'brevo_email_field'             => 'email',
				'brevo_name_field'              => 'name',
				'brevo_last_name_field'         => 'last_name',
				'brevo_whatsapp_field'          => 'whatsapp',
				'brevo_who_is_field'            => 'who_is',
				'brevo_biggest_challenge_field' => 'biggest_challenge',
				'brevo_utm_source_field'        => 'utm_source',
			),
			$fields
		);

		$result  = ( new CRM_Leads_Capture_Lead_Payload() )->build_contact( $mapped['input'], $mapped['context'] );
		$payload = $result->data()['payload'];

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'rafael@example.com', $payload['email'] );
		$this->assertSame( array( 123 ), $payload['listIds'] );
		$this->assertSame( 'Rafael', $payload['attributes']['FIRSTNAME'] );
		$this->assertSame( 'Carvalho', $payload['attributes']['LASTNAME'] );
		$this->assertSame( '+5511999999999', $payload['attributes']['WHATSAPP'] );
		$this->assertSame( 'elementor', $payload['attributes']['SOURCE'] );
		$this->assertSame( 'Founder', $payload['attributes']['WHO_IS'] );
		$this->assertSame( 'Growth', $payload['attributes']['BIGGEST_CHALLENGE'] );
		$this->assertSame( 'linkedin', $payload['attributes']['UTM_SOURCE'] );
	}

	public function test_injects_posted_utm_fields_when_elementor_does_not_include_them(): void {
		$mapper = new CRM_Leads_Capture_Elementor_Form_Mapper();

		$fields = $mapper->inject_posted_utm_fields(
			array(),
			array(
				'utm_source' => 'google',
				'utm_term'   => '<strong>crm</strong>',
			)
		);

		$this->assertSame( 'google', $fields['utm_source'] );
		$this->assertSame( 'crm', $fields['utm_term'] );
	}
}
