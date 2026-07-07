<?php
/**
 * Standardized operation result.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Result {
	private bool $success;

	private int $status_code;

	private string $message;

	/**
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * @param array<string, mixed> $data
	 */
	private function __construct( bool $success, int $status_code, string $message, array $data = array() ) {
		$this->success     = $success;
		$this->status_code = $status_code;
		$this->message     = $message;
		$this->data        = $data;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function success( int $status_code = 200, string $message = '', array $data = array() ): self {
		return new self( true, $status_code, $message, $data );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function failure( int $status_code = 0, string $message = '', array $data = array() ): self {
		return new self( false, $status_code, $message, $data );
	}

	public function is_successful(): bool {
		return $this->success;
	}

	public function status_code(): int {
		return $this->status_code;
	}

	public function message(): string {
		return $this->message;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data(): array {
		return $this->data;
	}
}
