<?php
/**
 * Capture field unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Field;

class CaptureFieldTest extends TestCase {
	public function test_sanitizes_text_and_preserves_textarea_line_breaks(): void {
		$text = new CRM_Leads_Capture_Field( 'name' );
		$textarea = new CRM_Leads_Capture_Field(
			'challenge',
			array( 'type' => 'textarea' )
		);

		$this->assertSame( 'Rafael Ops', $text->normalize( " <b>Rafael</b>\n Ops " )->data()['value'] );
		$this->assertSame( "Line one\nLine two", $textarea->normalize( "Line one\r\nLine two" )->data()['value'] );
	}

	public function test_validates_required_and_typed_fields(): void {
		$required = new CRM_Leads_Capture_Field(
			'email',
			array(
				'type'     => 'email',
				'required' => true,
			)
		);

		$this->assertSame( 'required', $required->normalize( '' )->data()['code'] );
		$this->assertSame( 'invalid_email', $required->normalize( 'not-an-email' )->data()['code'] );
		$this->assertSame( 'lead@example.com', $required->normalize( ' LEAD@example.com ' )->data()['value'] );

		$phone = new CRM_Leads_Capture_Field( 'whatsapp', array( 'type' => 'phone' ) );
		$this->assertSame( '+5511999999999', $phone->normalize( '+55 (11) 99999-9999' )->data()['value'] );
		$this->assertSame( 'invalid_phone', $phone->normalize( '1234' )->data()['code'] );

		$date = new CRM_Leads_Capture_Field( 'event_date', array( 'type' => 'date' ) );
		$this->assertSame( '2026-10-20', $date->normalize( '2026-10-20' )->data()['value'] );
		$this->assertSame( 'invalid_date', $date->normalize( '2026-02-31' )->data()['code'] );
	}

	public function test_validates_boolean_select_and_custom_callback(): void {
		$consent = new CRM_Leads_Capture_Field(
			'consent',
			array(
				'type'     => 'boolean',
				'required' => true,
			)
		);
		$this->assertTrue( $consent->normalize( 'yes' )->data()['value'] );
		$this->assertSame( 'required', $consent->normalize( 'no' )->data()['code'] );

		$format = new CRM_Leads_Capture_Field(
			'format',
			array(
				'type'           => 'select',
				'allowed_values' => array( 'online', 'presencial' ),
			)
		);
		$this->assertSame( 'online', $format->normalize( 'online' )->data()['value'] );
		$this->assertSame( 'invalid_option', $format->normalize( 'hybrid' )->data()['code'] );

		$company = new CRM_Leads_Capture_Field(
			'company',
			array(
				'sanitize_callback' => static fn( string $value ): string => strtoupper( $value ),
				'validate_callback' => static fn( string $value ) => strlen( $value ) >= 3 ? true : 'company_too_short',
			)
		);
		$this->assertSame( 'ACME', $company->normalize( 'Acme' )->data()['value'] );
		$this->assertSame( 'company_too_short', $company->normalize( 'AB' )->data()['code'] );
	}

	public function test_normalizes_url_integer_and_number_types(): void {
		$url = new CRM_Leads_Capture_Field( 'website', array( 'type' => 'url' ) );
		$this->assertSame( 'https://example.com/contact', $url->normalize( ' https://example.com/contact ' )->data()['value'] );
		$this->assertSame( 'invalid_url', $url->normalize( 'not a url' )->data()['code'] );

		$integer = new CRM_Leads_Capture_Field( 'audience_size', array( 'type' => 'integer' ) );
		$this->assertSame( 250, $integer->normalize( '250' )->data()['value'] );
		$this->assertSame( 'invalid_integer', $integer->normalize( '2.5' )->data()['code'] );

		$number = new CRM_Leads_Capture_Field( 'budget', array( 'type' => 'number' ) );
		$this->assertSame( 1500.5, $number->normalize( '1500.5' )->data()['value'] );
		$this->assertSame( 'invalid_number', $number->normalize( 'one thousand' )->data()['code'] );
	}

	public function test_rejects_non_scalar_values(): void {
		$field = new CRM_Leads_Capture_Field( 'name' );

		$this->assertSame( 'invalid_type', $field->normalize( array( 'Rafael' ) )->data()['code'] );
	}
}
