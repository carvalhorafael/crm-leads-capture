<?php
/**
 * Brevo client unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Brevo_Client;

class BrevoClientTest extends TestCase {
	public function test_posts_contact_payload_to_brevo(): void {
		$captured_url  = null;
		$captured_args = null;

		$client = new CRM_Leads_Capture_Brevo_Client(
			'test-api-key',
			static function ( string $url, array $args ) use ( &$captured_url, &$captured_args ): array {
				$captured_url  = $url;
				$captured_args = $args;

				return array(
					'response' => array( 'code' => 201 ),
					'body'     => '{"id":42}',
				);
			}
		);

		$result = $client->create_or_update_contact(
			array(
				'email'         => 'lead@example.com',
				'attributes'    => array( 'FIRSTNAME' => 'Lead' ),
				'listIds'       => array( 123 ),
				'updateEnabled' => true,
			)
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 201, $result->status_code() );
		$this->assertSame( 'https://api.brevo.com/v3/contacts', $captured_url );
		$this->assertSame( 'test-api-key', $captured_args['headers']['api-key'] );
		$this->assertSame( 'application/json', $captured_args['headers']['content-type'] );
		$this->assertStringContainsString( '"updateEnabled":true', $captured_args['body'] );
	}

	public function test_accepts_no_content_success(): void {
		$client = new CRM_Leads_Capture_Brevo_Client(
			'test-api-key',
			static fn(): array => array(
				'response' => array( 'code' => 204 ),
				'body'     => '',
			)
		);

		$result = $client->create_or_update_contact( array( 'email' => 'lead@example.com' ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 204, $result->status_code() );
	}

	public function test_accepts_ok_success(): void {
		$client = new CRM_Leads_Capture_Brevo_Client(
			'test-api-key',
			static fn(): array => array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"id":42}',
			)
		);

		$result = $client->create_or_update_contact( array( 'email' => 'lead@example.com' ) );

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 200, $result->status_code() );
	}

	public function test_rejects_invalid_attributes_and_lists_before_http_call(): void {
		$calls  = 0;
		$client = new CRM_Leads_Capture_Brevo_Client(
			'test-api-key',
			static function () use ( &$calls ): array {
				++$calls;
				return array( 'response' => array( 'code' => 201 ), 'body' => '' );
			}
		);

		$attribute_result = $client->create_or_update_contact(
			array(
				'email'      => 'lead@example.com',
				'attributes' => array( 'invalid-name' => 'value' ),
			)
		);
		$list_result = $client->create_or_update_contact(
			array(
				'email'   => 'lead@example.com',
				'listIds' => array( '12' ),
			)
		);

		$this->assertSame( 'invalid_payload', $attribute_result->data()['code'] );
		$this->assertSame( 'invalid_payload', $list_result->data()['code'] );
		$this->assertSame( 0, $calls );
	}

	public function test_returns_failure_without_exposing_api_key(): void {
		$client = new CRM_Leads_Capture_Brevo_Client(
			'secret-api-key',
			static fn(): array => array(
				'response' => array( 'code' => 400 ),
				'body'     => '{"code":"invalid_parameter","message":"Attribute SOURCE does not exist","details":{"field":"attributes.SOURCE","nested":{"ignored":"value"}}}',
			)
		);

		$result = $client->create_or_update_contact( array( 'email' => 'lead@example.com' ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 400, $result->status_code() );
		$this->assertStringNotContainsString( 'secret-api-key', $result->message() );
		$this->assertSame( 'invalid_parameter', $result->data()['error_summary']['code'] );
		$this->assertArrayNotHasKey( 'message', $result->data()['error_summary'] );
		$this->assertSame( array( 'field', 'nested' ), $result->data()['error_summary']['detail_keys'] );
		$this->assertStringNotContainsString( 'attributes.SOURCE', serialize( $result->data()['error_summary'] ) );
	}

	public function test_requires_configured_api_key(): void {
		$client = new CRM_Leads_Capture_Brevo_Client( '' );

		$result = $client->create_or_update_contact( array( 'email' => 'lead@example.com' ) );

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'Brevo API key is not configured.', $result->message() );
	}
}
