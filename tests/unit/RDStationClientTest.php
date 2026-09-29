<?php
/**
 * RD Station client validation tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_RD_Station_Client;

class RDStationClientTest extends TestCase {
	public function test_rejects_invalid_identifier_before_http_call(): void {
		$calls  = 0;
		$client = new CRM_Leads_Capture_RD_Station_Client(
			'token',
			static function () use ( &$calls ): array {
				++$calls;
				return array();
			}
		);
		$result = $client->send_conversion(
			array(
				'event_type'   => 'CONVERSION',
				'event_family' => 'CDP',
				'payload'      => array(
					'conversion_identifier' => 'test',
					'email'                 => 'lead@example.com',
					'custom_field'          => 'not-allowed',
				),
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_payload', $result->data()['code'] );
		$this->assertSame( 0, $calls );
	}

	public function test_accepts_documented_custom_identifier_and_tags(): void {
		$client = new CRM_Leads_Capture_RD_Station_Client(
			'token',
			static fn(): array => array( 'response' => array( 'code' => 200 ), 'body' => '{"event_uuid":"123"}' )
		);
		$result = $client->send_conversion(
			array(
				'event_type'   => 'CONVERSION',
				'event_family' => 'CDP',
				'payload'      => array(
					'conversion_identifier' => 'test',
					'email'                 => 'lead@example.com',
					'cf_speaking_topic'     => 'Operações',
					'tags'                  => array( 'speaker' ),
				),
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 200, $result->status_code() );
	}
}
