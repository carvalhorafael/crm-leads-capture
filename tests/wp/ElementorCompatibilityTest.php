<?php
/**
 * Elementor compatibility integration tests.
 *
 * @package CRM_Leads_Capture
 */

class ElementorCompatibilityTest extends WP_UnitTestCase {
	public function test_preserves_legacy_control_names(): void {
		$controls = ( new CRM_Leads_Capture_Elementor_Form_Mapper() )->controls();

		foreach (
			array(
				'brevo_api_key',
				'brevo_list_id',
				'brevo_email_field',
				'brevo_name_field',
				'brevo_last_name_field',
				'brevo_whatsapp_field',
				'brevo_who_is_field',
				'brevo_already_sell_field',
				'brevo_what_sells_field',
				'brevo_current_situation_field',
				'brevo_is_seeking_help_field',
				'brevo_faturamento_digital_field',
				'brevo_biggest_challenge_field',
				'brevo_utm_source_field',
				'brevo_utm_medium_field',
				'brevo_utm_campaign_field',
				'brevo_utm_content_field',
				'brevo_utm_name_field',
				'brevo_utm_term_field',
			) as $control_name
		) {
			$this->assertArrayHasKey( $control_name, $controls );
		}
	}
}
