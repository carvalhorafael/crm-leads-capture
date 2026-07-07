<?php
/**
 * RD Station provider unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_RD_Station_Provider;
use CRM_Leads_Capture_Settings;

class RDStationProviderTest extends TestCase {
	public function test_exposes_expected_structural_contract(): void {
		$provider = new CRM_Leads_Capture_RD_Station_Provider( new CRM_Leads_Capture_Settings() );

		$this->assertSame( 'rd_station', $provider->id() );
		$this->assertArrayHasKey( 'api_key', $provider->settings_fields() );
		$this->assertArrayHasKey( 'default_conversion_identifier', $provider->settings_fields() );
		$this->assertArrayHasKey( 'conversion_identifier', $provider->material_fields() );
		$this->assertArrayHasKey( 'rd_station_error', $provider->error_codes() );
	}
}
