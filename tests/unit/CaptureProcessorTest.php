<?php
/**
 * Generic capture processor unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Field;
use CRM_Leads_Capture_Processor;
use CRM_Leads_Capture_Profile;
use CRM_Leads_Capture_Profile_Registry;
use CRM_Leads_Capture_Logger;
use CRM_Leads_Capture_Provider_Interface;
use CRM_Leads_Capture_Provider_Registry;
use CRM_Leads_Capture_Result;

class CaptureProcessorTestProvider implements CRM_Leads_Capture_Provider_Interface {
	/** @var array<string, mixed>|null */
	public ?array $last_payload = null;

	/** @var array<string, mixed>|null */
	public ?array $last_context = null;

	public bool $should_succeed = true;

	public function id(): string {
		return 'brevo';
	}

	public function label(): string {
		return 'Brevo';
	}

	public function settings_fields(): array {
		return array();
	}

	public function material_fields(): array {
		return array();
	}

	public function sanitize_settings( array $input ): array {
		return $input;
	}

	public function sanitize_material_meta( array $input ): array {
		return $input;
	}

	public function send_lead( array $payload, array $context ): CRM_Leads_Capture_Result {
		$this->last_payload = $payload;
		$this->last_context = $context;

		return $this->should_succeed
			? CRM_Leads_Capture_Result::success( 201 )
			: CRM_Leads_Capture_Result::failure( 400, 'Remote details must not be exposed.' );
	}

	public function error_codes(): array {
		return array();
	}
}

class CaptureProcessorTestLogger extends CRM_Leads_Capture_Logger {
	/** @var array<int, array{message: string, context: array<string, mixed>}> */
	public array $entries = array();

	public function debug( string $message, array $context = array() ): void {
		$this->entries[] = array(
			'message' => $message,
			'context' => $context,
		);
	}
}

class CaptureProcessorTest extends TestCase {
	private CaptureProcessorTestProvider $provider;

	private CaptureProcessorTestLogger $logger;

	private CRM_Leads_Capture_Profile_Registry $profiles;

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Processor $processor;

	protected function set_up(): void {
		parent::set_up();

		$profile = new CRM_Leads_Capture_Profile(
			'coo',
			array(
				new CRM_Leads_Capture_Field( 'name', array( 'required' => true, 'group' => 'lead' ) ),
				new CRM_Leads_Capture_Field( 'email', array( 'type' => 'email', 'required' => true, 'group' => 'lead' ) ),
				new CRM_Leads_Capture_Field( 'whatsapp', array( 'type' => 'phone', 'group' => 'lead' ) ),
				new CRM_Leads_Capture_Field( 'utm_source', array( 'group' => 'tracking' ) ),
				new CRM_Leads_Capture_Field( 'consent', array( 'type' => 'boolean', 'required' => true, 'group' => 'consent' ) ),
				new CRM_Leads_Capture_Field( 'company', array( 'required' => true, 'group' => 'custom_fields' ) ),
			),
			array(
				'context' => array( 'source' => 'coo_as_a_service' ),
				'providers' => array(
					'brevo' => array( 'list_ids' => array( 20, 30 ) ),
				),
				'success' => array( 'message' => 'Recebemos seu contato.' ),
			)
		);

		$this->profiles = new CRM_Leads_Capture_Profile_Registry();
		$this->profiles->register( $profile );

		$this->provider = new CaptureProcessorTestProvider();
		$this->logger = new CaptureProcessorTestLogger();
		$this->providers = new CRM_Leads_Capture_Provider_Registry();
		$this->providers->register( $this->provider );

		$this->processor = new CRM_Leads_Capture_Processor(
			$this->profiles,
			$this->providers,
			static fn(): string => 'brevo',
			static fn( string $nonce, string $action ): bool => 'valid' === $nonce && 'crm_leads_capture_coo' === $action,
			$this->logger
		);
	}

	public function test_builds_canonical_submission_and_ignores_unknown_fields(): void {
		$result = $this->processor->process(
			'coo',
			array(
				'_wpnonce'                => 'valid',
				'crm_leads_capture_website' => '',
				'name'                    => ' Rafael Carvalho ',
				'email'                   => ' RAFAEL@example.com ',
				'whatsapp'                => '+55 (11) 99999-9999',
				'utm_source'              => 'linkedin',
				'consent'                 => 'yes',
				'company'                 => 'Acme',
				'provider'                => 'rd_station',
				'list_ids'                => array( 999 ),
			),
			array( 'page_id' => 42 )
		);

		$this->assertTrue( $result->is_successful() );
		$this->assertSame( 'brevo', $result->data()['provider'] );
		$this->assertSame( array( 'message' => 'Recebemos seu contato.' ), $result->data()['success'] );

		$this->assertSame(
			array(
				'profile_slug' => 'coo',
				'lead' => array(
					'name'     => 'Rafael Carvalho',
					'email'    => 'rafael@example.com',
					'whatsapp' => '+5511999999999',
				),
				'tracking' => array( 'utm_source' => 'linkedin' ),
				'consent' => array( 'consent' => true ),
				'custom_fields' => array( 'company' => 'Acme' ),
				'context' => array(
					'source'  => 'coo_as_a_service',
					'page_id' => 42,
				),
			),
			$this->provider->last_payload
		);
		$this->assertSame( array( 20, 30 ), $this->provider->last_context['list_ids'] );
		$this->assertSame( 'coo', $this->provider->last_context['capture_profile'] );
	}

	public function test_rejects_invalid_nonce_and_honeypot_before_provider_call(): void {
		$result = $this->processor->process( 'coo', array( '_wpnonce' => 'invalid' ) );
		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 'invalid_nonce', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );

		$result = $this->processor->process(
			'coo',
			array(
				'_wpnonce'                => 'valid',
				'crm_leads_capture_website' => 'bot',
			)
		);
		$this->assertSame( 'spam', $result->data()['code'] );
		$this->assertNull( $this->provider->last_payload );
	}

	public function test_returns_controlled_field_errors(): void {
		$result = $this->processor->process(
			'coo',
			array(
				'_wpnonce' => 'valid',
				'name'      => '',
				'email'     => 'invalid',
				'consent'   => 'no',
				'company'   => '',
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 422, $result->status_code() );
		$this->assertSame( 'invalid_fields', $result->data()['code'] );
		$this->assertSame(
			array(
				'name'    => 'required',
				'email'   => 'invalid_email',
				'consent' => 'required',
				'company' => 'required',
			),
			$result->data()['field_errors']
		);
		$this->assertNull( $this->provider->last_payload );
		$this->assertSame(
			array(
				'profile_slug' => 'coo',
				'field_names'  => array( 'name', 'email', 'consent', 'company' ),
			),
			$this->logger->entries[0]['context']
		);
		$this->assertStringNotContainsString( 'invalid', serialize( $this->logger->entries ) );
	}

	public function test_hides_provider_failure_details(): void {
		$this->provider->should_succeed = false;

		$result = $this->processor->process(
			'coo',
			array(
				'_wpnonce' => 'valid',
				'name'      => 'Rafael',
				'email'     => 'rafael@example.com',
				'consent'   => 'yes',
				'company'   => 'Acme',
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 502, $result->status_code() );
		$this->assertSame( 'provider_error', $result->data()['code'] );
		$this->assertStringNotContainsString( 'Remote details', $result->message() );
		$this->assertSame(
			array(
				'profile_slug' => 'coo',
				'provider'     => 'brevo',
				'status_code'  => 400,
			),
			$this->logger->entries[0]['context']
		);
		$this->assertStringNotContainsString( 'rafael@example.com', serialize( $this->logger->entries ) );
	}

	public function test_rejects_unknown_profile(): void {
		$result = $this->processor->process( 'missing', array() );

		$this->assertSame( 404, $result->status_code() );
		$this->assertSame( 'profile_not_found', $result->data()['code'] );
	}

	public function test_returns_controlled_error_when_global_provider_is_unavailable(): void {
		$processor = new CRM_Leads_Capture_Processor(
			$this->profiles,
			$this->providers,
			static fn(): string => 'rd_station',
			static fn( string $nonce, string $action ): bool => true
		);

		$result = $processor->process(
			'coo',
			array(
				'_wpnonce' => 'valid',
				'name'      => 'Rafael',
				'email'     => 'rafael@example.com',
				'consent'   => 'yes',
				'company'   => 'Acme',
			)
		);

		$this->assertFalse( $result->is_successful() );
		$this->assertSame( 503, $result->status_code() );
		$this->assertSame( 'provider_unavailable', $result->data()['code'] );
	}
}
