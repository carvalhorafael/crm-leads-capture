<?php
/**
 * Capture profile and registry unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Field;
use CRM_Leads_Capture_Profile;
use CRM_Leads_Capture_Profile_Registry;

class CaptureProfileRegistryTest extends TestCase {
	public function test_registers_and_resolves_a_complete_profile(): void {
		$profile = new CRM_Leads_Capture_Profile(
			'COO Interest',
			array( new CRM_Leads_Capture_Field( 'email', array( 'type' => 'email' ) ) ),
			array(
				'context' => array( 'source' => 'coo_as_a_service' ),
				'providers' => array(
					'brevo' => array( 'list_ids' => array( 12 ) ),
				),
				'success' => array( 'message' => 'Recebemos seu contato.' ),
				'nonce_action' => 'custom_nonce_action',
			)
		);

		$registry = new CRM_Leads_Capture_Profile_Registry();
		$registry->register( $profile );

		$this->assertSame( $profile, $registry->resolve( 'coointerest' ) );
		$this->assertSame( array( 'source' => 'coo_as_a_service' ), $profile->context() );
		$this->assertSame( array( 'list_ids' => array( 12 ) ), $profile->provider_config( 'brevo' ) );
		$this->assertSame( array(), $profile->provider_config( 'rd_station' ) );
		$this->assertSame( array( 'message' => 'Recebemos seu contato.' ), $profile->success_behavior() );
		$this->assertSame( 'custom_nonce_action', $profile->nonce_action() );
	}

	public function test_rejects_empty_profiles_and_duplicate_fields(): void {
		$this->expectException( \InvalidArgumentException::class );
		new CRM_Leads_Capture_Profile( 'empty', array() );
	}

	public function test_rejects_duplicate_field_names(): void {
		$this->expectException( \InvalidArgumentException::class );
		new CRM_Leads_Capture_Profile(
			'duplicate',
			array(
				new CRM_Leads_Capture_Field( 'email' ),
				new CRM_Leads_Capture_Field( 'email' ),
			)
		);
	}
}
