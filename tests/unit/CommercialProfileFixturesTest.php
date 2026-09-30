<?php
/**
 * Site-managed commercial profile fixture tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Commercial_Profile_Fixtures;
use CRM_Leads_Capture_Profile_Registry;

class CommercialProfileFixturesTest extends TestCase {
	public function test_example_site_profiles_are_complete_generic_profiles(): void {
		$registry = new CRM_Leads_Capture_Profile_Registry();
		CRM_Leads_Capture_Commercial_Profile_Fixtures::register( $registry );

		$coo     = $registry->resolve( CRM_Leads_Capture_Commercial_Profile_Fixtures::COO_SLUG );
		$speaker = $registry->resolve( CRM_Leads_Capture_Commercial_Profile_Fixtures::SPEAKER_SLUG );

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

	public function test_example_site_profiles_map_every_nonstandard_field(): void {
		$registry = new CRM_Leads_Capture_Profile_Registry();
		CRM_Leads_Capture_Commercial_Profile_Fixtures::register( $registry );

		foreach ( $registry->all() as $profile ) {
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
		}
	}
}
