<?php
/**
 * Provider registry unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Brevo_Provider;
use CRM_Leads_Capture_Provider_Registry;
use CRM_Leads_Capture_RD_Station_Provider;
use CRM_Leads_Capture_Settings;

class ProviderRegistryTest extends TestCase {
	public function test_registers_initial_crm_providers(): void {
		$settings = new CRM_Leads_Capture_Settings();
		$registry = new CRM_Leads_Capture_Provider_Registry();
		$registry->register( new CRM_Leads_Capture_Brevo_Provider( $settings ) );
		$registry->register( new CRM_Leads_Capture_RD_Station_Provider( $settings ) );

		$this->assertArrayHasKey( 'brevo', $registry->all() );
		$this->assertArrayHasKey( 'rd_station', $registry->all() );
		$this->assertSame( 'brevo', $registry->active( 'unknown' )->id() );
	}
}
