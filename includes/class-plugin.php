<?php
/**
 * Main plugin bootstrap.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Plugin {
	private static ?CRM_Leads_Capture_Plugin $instance = null;

	private bool $booted = false;

	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Provider_Registry $providers;

	private CRM_Leads_Capture_Profile_Registry $capture_profiles;

	private CRM_Leads_Capture_Profile_Repository $profile_repository;

	private CRM_Leads_Capture_Profile_Admin $profile_admin;

	private CRM_Leads_Capture_Processor $capture_processor;

	private CRM_Leads_Capture_Frontend $frontend;

	private CRM_Leads_Capture_Free_Material_Capture $free_material_capture;

	private CRM_Leads_Capture_Service_Interest_Capture $service_interest_capture;

	private CRM_Leads_Capture_GitHub_Updater $github_updater;

	private CRM_Leads_Capture_Logger $logger;

	private function __construct() {
		$this->logger    = new CRM_Leads_Capture_Logger();
		$this->settings  = new CRM_Leads_Capture_Settings();
		$this->providers = new CRM_Leads_Capture_Provider_Registry();
		$this->providers->register( new CRM_Leads_Capture_Brevo_Provider( $this->settings ) );
		$this->providers->register( new CRM_Leads_Capture_RD_Station_Provider( $this->settings ) );
		$this->capture_profiles = new CRM_Leads_Capture_Profile_Registry();
		$this->profile_repository = new CRM_Leads_Capture_Profile_Repository( $this->settings );
		$this->profile_repository->register_profiles( $this->capture_profiles );
		$this->capture_processor = new CRM_Leads_Capture_Processor(
			$this->capture_profiles,
			$this->providers,
			fn(): string => $this->settings->active_provider(),
			static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action ),
			$this->logger,
			fn( string $provider ): string => $this->settings->provider_configuration_error( $provider )
		);
		$this->frontend = new CRM_Leads_Capture_Frontend( $this->capture_profiles, $this->capture_processor, $this->settings, $this->profile_repository );
		$this->profile_admin = new CRM_Leads_Capture_Profile_Admin( $this->profile_repository, $this->settings );

		$this->free_material_capture = new CRM_Leads_Capture_Free_Material_Capture( $this->settings, $this->providers, null, $this->logger );
		$this->service_interest_capture = new CRM_Leads_Capture_Service_Interest_Capture( $this->settings, $this->providers, null, $this->logger );
		$this->github_updater        = new CRM_Leads_Capture_GitHub_Updater( CRM_LEADS_CAPTURE_FILE, CRM_LEADS_CAPTURE_VERSION );
	}

	public static function instance(): CRM_Leads_Capture_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->settings->register_hooks();
		$this->profile_repository->register_hooks();
		$this->profile_admin->register_hooks();
		$this->frontend->register_hooks();
		$this->free_material_capture->register_hooks();
		$this->service_interest_capture->register_hooks();
		$this->github_updater->register_hooks();
		add_action( 'elementor_pro/forms/actions/register', array( $this, 'register_elementor_form_action' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'crm-leads-capture',
			false,
			dirname( CRM_LEADS_CAPTURE_BASENAME ) . '/languages'
		);
	}

	public function settings(): CRM_Leads_Capture_Settings {
		return $this->settings;
	}

	public function free_material_capture(): CRM_Leads_Capture_Free_Material_Capture {
		return $this->free_material_capture;
	}

	public function service_interest_capture(): CRM_Leads_Capture_Service_Interest_Capture {
		return $this->service_interest_capture;
	}

	public function logger(): CRM_Leads_Capture_Logger {
		return $this->logger;
	}

	public function providers(): CRM_Leads_Capture_Provider_Registry {
		return $this->providers;
	}

	public function capture_profiles(): CRM_Leads_Capture_Profile_Registry {
		return $this->capture_profiles;
	}

	public function capture_processor(): CRM_Leads_Capture_Processor {
		return $this->capture_processor;
	}

	public function profile_repository(): CRM_Leads_Capture_Profile_Repository {
		return $this->profile_repository;
	}

	public function frontend(): CRM_Leads_Capture_Frontend {
		return $this->frontend;
	}

	public function github_updater(): CRM_Leads_Capture_GitHub_Updater {
		return $this->github_updater;
	}

	/**
	 * @param mixed $form_actions_registrar Elementor form actions registrar.
	 */
	public function register_elementor_form_action( $form_actions_registrar ): void {
		if ( ! class_exists( '\ElementorPro\Modules\Forms\Classes\Action_Base' ) ) {
			return;
		}

		require_once CRM_LEADS_CAPTURE_DIR . 'includes/integrations/class-elementor-form-action.php';

		if ( method_exists( $form_actions_registrar, 'register' ) ) {
			$form_actions_registrar->register(
				new CRM_Leads_Capture_Elementor_Form_Action(
					$this->settings,
					new CRM_Leads_Capture_Elementor_Form_Mapper(),
					new CRM_Leads_Capture_Lead_Payload(),
					$this->logger,
					$this->providers
				)
			);
		}
	}
}
