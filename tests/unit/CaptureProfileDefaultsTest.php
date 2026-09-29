<?php
/**
 * Built-in commercial capture profile tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Profile_Defaults;
use CRM_Leads_Capture_Profile_Registry;
use CRM_Leads_Capture_Settings;

class CaptureProfileDefaultsTestSettings extends CRM_Leads_Capture_Settings {
	public function service_success_message(): string {
		return 'Mensagem COO.';
	}
}

class CaptureProfileDefaultsTest extends TestCase {
	public function test_registers_complete_coo_and_speaker_schemas(): void {
		$registry = new CRM_Leads_Capture_Profile_Registry();
		( new CRM_Leads_Capture_Profile_Defaults( new CaptureProfileDefaultsTestSettings() ) )->register( $registry );

		$coo     = $registry->resolve( CRM_Leads_Capture_Profile_Defaults::COO_SLUG );
		$speaker = $registry->resolve( CRM_Leads_Capture_Profile_Defaults::SPEAKER_SLUG );

		$this->assertNotNull( $coo );
		$this->assertNotNull( $speaker );
		$this->assertSame(
			array( 'name', 'email', 'whatsapp', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name', 'company', 'role', 'company_url', 'challenge', 'consent' ),
			array_keys( $coo->fields() )
		);
		$this->assertSame(
			array( 'name', 'email', 'whatsapp', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name', 'organization', 'event_name', 'objective_context', 'audience_profile', 'audience_size', 'event_date', 'location', 'format', 'consent' ),
			array_keys( $speaker->fields() )
		);
		$this->assertNotSame( $coo->success_behavior()['message'], $speaker->success_behavior()['message'] );
	}

	public function test_maps_every_commercial_field_for_both_providers(): void {
		$defaults = new CRM_Leads_Capture_Profile_Defaults( new CaptureProfileDefaultsTestSettings() );
		$profiles = array( $defaults->coo_profile(), $defaults->speaker_profile() );

		foreach ( $profiles as $profile ) {
			$brevo_map = $profile->provider_config( 'brevo' )['attribute_map'];
			$rd_map    = $profile->provider_config( 'rd_station' )['field_map'];
			foreach ( $profile->fields() as $field ) {
				if ( in_array( $field->name(), array( 'name', 'email', 'whatsapp', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term' ), true ) ) {
					continue;
				}
				$path = $field->group() . '.' . $field->name();
				if ( ! in_array( $field->name(), array( 'utm_content', 'utm_name' ), true ) ) {
					$this->assertArrayHasKey( $path, $brevo_map, $profile->slug() . ' Brevo map is incomplete.' );
				}
				$this->assertArrayHasKey( $path, $rd_map, $profile->slug() . ' RD Station map is incomplete.' );
			}
			$this->assertArrayHasKey( 'context.page_url', $brevo_map );
			$this->assertArrayHasKey( 'context.page_url', $rd_map );
		}
	}
}
