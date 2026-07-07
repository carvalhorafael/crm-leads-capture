<?php
/**
 * Controlled debug logger.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Logger {
	private const REDACTED = '[redacted]';

	/**
	 * @param array<string, mixed> $context
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		error_log( '[crm-leads-capture] ' . $message . ' ' . wp_json_encode( $this->redact_context( $context ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	public function is_enabled(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * @param array<string, mixed> $context
	 *
	 * @return array<string, mixed>
	 */
	public function redact_context( array $context ): array {
		foreach ( $context as $key => $value ) {
			if ( $this->is_sensitive_key( (string) $key ) ) {
				$context[ $key ] = self::REDACTED;
				continue;
			}

			if ( is_array( $value ) ) {
				$context[ $key ] = $this->redact_context( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$context[ $key ] = $this->redact_sensitive_fragments( $value );
			}
		}

		return $context;
	}

	private function is_sensitive_key( string $key ): bool {
		$key = strtolower( $key );

		foreach ( array( 'api', 'key', 'token', 'secret', 'password', 'email', 'whatsapp', 'phone', 'payload', 'body' ) as $needle ) {
			if ( str_contains( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	private function redact_sensitive_fragments( string $value ): string {
		$value = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', self::REDACTED, $value );
		$value = preg_replace( '/\+?\d[\d\s().-]{7,}\d/', self::REDACTED, (string) $value );

		return (string) $value;
	}
}
