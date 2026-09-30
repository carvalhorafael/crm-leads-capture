<?php
/**
 * Elementor global provider routing tests.
 *
 * @package CRM_Leads_Capture
 */

namespace ElementorPro\Modules\Forms\Classes {
	if ( ! class_exists( Action_Base::class ) ) {
		abstract class Action_Base {}
	}
}

namespace Elementor {
	if ( ! class_exists( Controls_Manager::class ) ) {
		class Controls_Manager {
			public const TEXT = 'text';
		}
	}
}

namespace {
	require_once dirname( __DIR__, 2 ) . '/includes/integrations/class-elementor-form-action.php';

	class CRM_Leads_Capture_Elementor_Global_Test_Provider implements CRM_Leads_Capture_Provider_Interface {
		/** @var array<string, mixed>|null */
		public ?array $last_payload = null;

		/** @var array<string, mixed>|null */
		public ?array $last_context = null;

		public function id(): string {
			return 'rd_station';
		}

		public function label(): string {
			return 'RD Station';
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

			return CRM_Leads_Capture_Result::success( 200 );
		}

		public function error_codes(): array {
			return array();
		}
	}

	class CRM_Leads_Capture_Elementor_Test_Record {
		/** @var array<string, mixed> */
		private array $settings;

		/** @var array<string, mixed> */
		private array $fields;

		/**
		 * @param array<string, mixed> $settings Form settings.
		 * @param array<string, mixed> $fields Submitted fields.
		 */
		public function __construct( array $settings, array $fields ) {
			$this->settings = $settings;
			$this->fields   = $fields;
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get( string $key ): array {
			return 'form_settings' === $key ? $this->settings : $this->fields;
		}
	}

	class CRM_Leads_Capture_Elementor_Test_Ajax_Handler {
		/** @var array<int, string> */
		public array $errors = array();

		/** @var array<int, string> */
		public array $successes = array();

		public function add_error_message( string $message ): void {
			$this->errors[] = $message;
		}

		public function add_success_message( string $message ): void {
			$this->successes[] = $message;
		}
	}

	class ElementorGlobalProviderTest extends WP_UnitTestCase {
		public function test_legacy_constructor_signature_remains_usable(): void {
			$action = new CRM_Leads_Capture_Elementor_Form_Action(
				new CRM_Leads_Capture_Settings(),
				new CRM_Leads_Capture_Elementor_Form_Mapper(),
				new CRM_Leads_Capture_Lead_Payload()
			);

			$this->assertSame( 'brevo', $action->get_name() );
		}

		public function test_legacy_brevo_action_routes_through_global_rd_station_provider(): void {
			update_option(
				CRM_Leads_Capture_Settings::OPTION_SETTINGS,
				array(
					'active_provider' => 'rd_station',
					'providers'       => array(
						'rd_station' => array(
							'enabled'                       => true,
							'api_key'                       => 'rd-key',
							'default_conversion_identifier' => 'Elementor form',
							'default_tags'                  => 'site, elementor',
						),
					),
				)
			);

			$provider = new CRM_Leads_Capture_Elementor_Global_Test_Provider();
			$providers = new CRM_Leads_Capture_Provider_Registry();
			$providers->register( $provider );
			$action = new CRM_Leads_Capture_Elementor_Form_Action(
				new CRM_Leads_Capture_Settings(),
				new CRM_Leads_Capture_Elementor_Form_Mapper(),
				new CRM_Leads_Capture_Lead_Payload(),
				null,
				$providers
			);
			$record = new CRM_Leads_Capture_Elementor_Test_Record(
				array(
					'brevo_api_key'    => 'legacy-brevo-key',
					'brevo_list_id'    => 999,
					'brevo_email_field' => 'email',
					'brevo_name_field' => 'name',
				),
				array(
					'email' => array( 'value' => 'lead@example.com' ),
					'name'  => array( 'value' => 'Lead Teste' ),
				)
			);
			$ajax_handler = new CRM_Leads_Capture_Elementor_Test_Ajax_Handler();

			$action->run( $record, $ajax_handler );

			$this->assertSame( 'brevo', $action->get_name() );
			$this->assertSame( 'lead@example.com', $provider->last_payload['email'] );
			$this->assertSame( 'Elementor form', $provider->last_context['conversion_identifier'] );
			$this->assertSame( 'site, elementor', $provider->last_context['tags'] );
			$this->assertSame( array(), $ajax_handler->errors );
			$this->assertSame( array( 'Contato enviado ao CRM.' ), $ajax_handler->successes );
		}
	}
}
