<?php
/**
 * Bootstrap file for unit tests without WordPress.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fixtures/wordpress/' );
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		return trim( strip_tags( preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return (string) filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/class-result.php';
require_once dirname( __DIR__ ) . '/includes/class-logger.php';
require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-lead-payload.php';
require_once dirname( __DIR__ ) . '/includes/class-provider-interface.php';
require_once dirname( __DIR__ ) . '/includes/class-provider-registry.php';
require_once dirname( __DIR__ ) . '/includes/class-brevo-client.php';
require_once dirname( __DIR__ ) . '/includes/class-brevo-provider.php';
require_once dirname( __DIR__ ) . '/includes/class-rd-station-client.php';
require_once dirname( __DIR__ ) . '/includes/class-rd-station-provider.php';
require_once dirname( __DIR__ ) . '/includes/integrations/class-elementor-form-mapper.php';
