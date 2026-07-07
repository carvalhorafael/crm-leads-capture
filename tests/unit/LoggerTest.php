<?php
/**
 * Logger unit tests.
 *
 * @package CRM_Leads_Capture
 */

namespace CRMLeadsCapture\Tests\Unit;

use CRMLeadsCapture\Tests\TestCase;
use CRM_Leads_Capture_Logger;

class LoggerTest extends TestCase {
	public function test_redacts_sensitive_context_values(): void {
		$logger = new CRM_Leads_Capture_Logger();

		$context = $logger->redact_context(
			array(
				'api_key' => 'secret',
				'email'   => 'lead@example.com',
				'nested'  => array(
					'payload' => array(
						'email' => 'lead@example.com',
					),
				),
				'status_code' => 400,
				'message'     => 'Invalid lead@example.com phone +55 11 99999-9999',
			)
		);

		$this->assertSame( '[redacted]', $context['api_key'] );
		$this->assertSame( '[redacted]', $context['email'] );
		$this->assertSame( '[redacted]', $context['nested']['payload'] );
		$this->assertSame( 400, $context['status_code'] );
		$this->assertSame( 'Invalid [redacted] phone [redacted]', $context['message'] );
	}
}
