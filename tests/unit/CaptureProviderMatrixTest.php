<?php
/**
 * Regression matrix from capture profile to provider request payload.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Brevo_Client;
use CRM_Leads_Capture_Brevo_Provider;
use CRM_Leads_Capture_Processor;
use CRM_Leads_Capture_Commercial_Profile_Fixtures;
use CRM_Leads_Capture_Profile_Registry;
use CRM_Leads_Capture_Provider_Registry;
use CRM_Leads_Capture_RD_Station_Client;
use CRM_Leads_Capture_RD_Station_Provider;
use CRM_Leads_Capture_Settings;

class CaptureProviderMatrixTestSettings extends CRM_Leads_Capture_Settings {
	public function brevo_api_key(): string {
		return 'brevo-test-key';
	}

	public function brevo_default_list_id(): int {
		return 99;
	}

	public function rd_station_api_key(): string {
		return 'rd-test-key';
	}

	public function rd_station_default_conversion_identifier(): string {
		return 'default-conversion';
	}

}

class CaptureProviderMatrixTest extends TestCase {
	/** @var array<string, mixed> */
	private array $brevo_contact = array();

	/** @var array<string, mixed> */
	private array $rd_event = array();

	private CaptureProviderMatrixTestSettings $settings;
	private CRM_Leads_Capture_Profile_Registry $profiles;
	private CRM_Leads_Capture_Provider_Registry $providers;

	protected function set_up(): void {
		parent::set_up();
		$this->settings = new CaptureProviderMatrixTestSettings();
		$this->profiles = new CRM_Leads_Capture_Profile_Registry();
		CRM_Leads_Capture_Commercial_Profile_Fixtures::register( $this->profiles );

		$this->providers = new CRM_Leads_Capture_Provider_Registry();
		$this->providers->register(
			new CRM_Leads_Capture_Brevo_Provider(
				$this->settings,
				null,
				fn( string $api_key ): CRM_Leads_Capture_Brevo_Client => new CRM_Leads_Capture_Brevo_Client(
					$api_key,
					function ( string $url, array $args ): array {
						unset( $url );
						$this->brevo_contact = json_decode( $args['body'], true );
						return array( 'response' => array( 'code' => 201 ), 'body' => '{"id":42}' );
					}
				)
			)
		);
		$this->providers->register(
			new CRM_Leads_Capture_RD_Station_Provider(
				$this->settings,
				fn( string $api_key ): CRM_Leads_Capture_RD_Station_Client => new CRM_Leads_Capture_RD_Station_Client(
					$api_key,
					function ( string $url, array $args ): array {
						unset( $url );
						$this->rd_event = json_decode( $args['body'], true );
						return array( 'response' => array( 'code' => 200 ), 'body' => '{"event_uuid":"event-42"}' );
					}
				)
			)
		);
	}

	public function test_brevo_global_covers_material_coo_and_speaker(): void {
		$this->assertMaterialReachesProvider( 'brevo' );
		$this->assertCommercialProfileReachesProvider( 'brevo', CRM_Leads_Capture_Commercial_Profile_Fixtures::COO_SLUG, $this->coo_input() );
		$this->assertSame( 'Acme', $this->brevo_contact['attributes']['COMPANY'] );
		$this->assertSame( 'https://example.com/coo', $this->brevo_contact['attributes']['PAGE_URL'] );

		$this->assertCommercialProfileReachesProvider( 'brevo', CRM_Leads_Capture_Commercial_Profile_Fixtures::SPEAKER_SLUG, $this->speaker_input() );
		$this->assertSame( 'Summit 2027', $this->brevo_contact['attributes']['EVENT_NAME'] );
		$this->assertSame( 500, $this->brevo_contact['attributes']['AUDIENCE_SIZE'] );
		$this->assertSame( 'hibrido', $this->brevo_contact['attributes']['EVENT_FORMAT'] );
		$this->assertSame( 'https://example.com/speaker', $this->brevo_contact['attributes']['PAGE_URL'] );
	}

	public function test_rd_station_global_covers_material_coo_and_speaker(): void {
		$this->assertMaterialReachesProvider( 'rd_station' );
		$this->assertCommercialProfileReachesProvider( 'rd_station', CRM_Leads_Capture_Commercial_Profile_Fixtures::COO_SLUG, $this->coo_input() );
		$payload = $this->rd_event['payload'];
		$this->assertSame( 'Acme', $payload['company_name'] );
		$this->assertSame( 'ceo', $payload['job_title'] );
		$this->assertSame( 'https://example.com/coo', $payload['cf_page_url'] );

		$this->assertCommercialProfileReachesProvider( 'rd_station', CRM_Leads_Capture_Commercial_Profile_Fixtures::SPEAKER_SLUG, $this->speaker_input() );
		$payload = $this->rd_event['payload'];
		$this->assertSame( 'Summit 2027', $payload['cf_event_name'] );
		$this->assertSame( '500', $payload['cf_audience_size'] );
		$this->assertSame( 'hibrido', $payload['cf_event_format'] );
		$this->assertSame( 'https://example.com/speaker', $payload['cf_page_url'] );
	}

	private function assertMaterialReachesProvider( string $provider_id ): void {
		$provider = $this->providers->get( $provider_id );
		$this->assertNotNull( $provider );
		$context = 'brevo' === $provider_id
			? array( 'list_id' => 45 )
			: array( 'conversion_identifier' => 'material-download', 'tags' => array( 'material' ) );
		$result = $provider->send_lead(
			array(
				'name' => 'Rafael Carvalho', 'email' => 'rafael@example.com', 'whatsapp' => '+5521999999999',
				'source' => 'free_material', 'material' => 'Guia', 'utm_source' => 'newsletter',
			),
			$context
		);

		$this->assertTrue( $result->is_successful() );
		if ( 'brevo' === $provider_id ) {
			$this->assertSame( array( 45 ), $this->brevo_contact['listIds'] );
			$this->assertSame( 'Guia', $this->brevo_contact['attributes']['MATERIAL'] );
		} else {
			$this->assertSame( 'material-download', $this->rd_event['payload']['conversion_identifier'] );
			$this->assertSame( array( 'material' ), $this->rd_event['payload']['tags'] );
		}
	}

	/** @param array<string, mixed> $input */
	private function assertCommercialProfileReachesProvider( string $provider_id, string $profile_slug, array $input ): void {
		$processor = new CRM_Leads_Capture_Processor(
			$this->profiles,
			$this->providers,
			static fn(): string => $provider_id,
			static fn( string $nonce, string $action ): bool => 'valid' === $nonce && '' !== $action
		);
		$result = $processor->process(
			$profile_slug,
			array_merge( array( '_wpnonce' => 'valid' ), $input ),
			array( 'page_url' => 'https://example.com/' . ( CRM_Leads_Capture_Commercial_Profile_Fixtures::COO_SLUG === $profile_slug ? 'coo' : 'speaker' ) )
		);

		$this->assertTrue( $result->is_successful(), $result->message() );
		$this->assertSame( $provider_id, $result->data()['provider'] );
	}

	/** @return array<string, mixed> */
	private function coo_input(): array {
		return array(
			'name' => 'Rafael Carvalho', 'email' => 'rafael@example.com', 'whatsapp' => '+55 21 99999-9999',
			'company' => 'Acme', 'role' => 'ceo', 'company_url' => 'https://acme.example',
			'challenge' => 'Dependência operacional.', 'consent' => '1',
			'utm_source' => 'linkedin', 'utm_medium' => 'social', 'utm_campaign' => 'coo',
			'utm_term' => 'operacoes', 'utm_content' => 'post', 'utm_name' => 'setembro',
		);
	}

	/** @return array<string, mixed> */
	private function speaker_input(): array {
		return array(
			'name' => 'Rafael Carvalho', 'email' => 'rafael@example.com', 'whatsapp' => '+55 21 99999-9999',
			'organization' => 'Evento SA', 'event_name' => 'Summit 2027',
			'objective_context' => 'Debater operações.', 'audience_profile' => 'Executivos.',
			'audience_size' => '500', 'event_date' => '2027-05-20', 'location' => 'São Paulo',
			'format' => 'hibrido', 'consent' => '1', 'utm_source' => 'indicacao',
			'utm_medium' => 'partner', 'utm_campaign' => 'speaker', 'utm_term' => 'evento',
			'utm_content' => 'convite', 'utm_name' => 'summit',
		);
	}
}
