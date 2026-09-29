<?php
/**
 * Capture profile persistence and page association tests.
 *
 * @package CRM_Leads_Capture
 */

class CaptureProfileAdminTest extends WP_UnitTestCase {
	private CRM_Leads_Capture_Settings $settings;

	private CRM_Leads_Capture_Profile_Repository $repository;

	private CRM_Leads_Capture_Profile_Admin $admin;

	public function set_up(): void {
		parent::set_up();
		$this->settings   = new CRM_Leads_Capture_Settings();
		$this->repository = new CRM_Leads_Capture_Profile_Repository( $this->settings );
		$this->admin      = new CRM_Leads_Capture_Profile_Admin( $this->repository, $this->settings );
		update_option(
			CRM_Leads_Capture_Settings::OPTION_SETTINGS,
			array(
				'active_provider' => 'brevo',
				'providers'       => array( 'brevo' => array( 'default_list_id' => 10 ) ),
			)
		);
	}

	public function tear_down(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_sanitizes_profile_and_registers_it_for_runtime_reuse(): void {
		$config = $this->repository->sanitize_profile( $this->profile_input() );

		$this->assertNotNull( $config );
		$this->assertSame( array( 12, 18 ), $config['providers']['brevo']['list_ids'] );
		$this->assertSame( 'COMPANY', $config['providers']['brevo']['attribute_map']['custom_fields.company'] );
		update_option( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES, array( 'coo' => $config ) );

		$registry = new CRM_Leads_Capture_Profile_Registry();
		$this->repository->register_profiles( $registry );
		$this->assertInstanceOf( CRM_Leads_Capture_Profile::class, $registry->resolve( 'coo' ) );
		$this->assertSame( array( 12, 18 ), $registry->resolve( 'coo' )->provider_config( 'brevo' )['list_ids'] );
	}

	public function test_saving_active_provider_preserves_inactive_provider_configuration(): void {
		$existing = array(
			'providers' => array(
				'rd_station' => array(
					'conversion_identifier' => 'coo-interest',
					'tags'                  => array( 'coo' ),
				),
			),
		);
		$config = $this->repository->sanitize_profile( $this->profile_input(), $existing );

		$this->assertSame( 'coo-interest', $config['providers']['rd_station']['conversion_identifier'] );
		$this->assertSame( array( 12, 18 ), $config['providers']['brevo']['list_ids'] );
		$this->assertArrayNotHasKey( 'provider', $config );
	}

	public function test_profile_can_be_associated_with_multiple_pages_and_rest_exposes_only_slug(): void {
		$config = $this->repository->sanitize_profile( $this->profile_input() );
		update_option( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES, array( 'coo' => $config ) );
		$this->repository->register_page_meta();
		$registered = get_registered_meta_keys( 'post', 'page' );

		$this->assertTrue( (bool) $registered[ CRM_Leads_Capture_Profile_Repository::PAGE_PROFILE_META ]['show_in_rest'] );
		$this->assertArrayNotHasKey( CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META, $registered );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		foreach ( array( self::factory()->post->create( array( 'post_type' => 'page' ) ), self::factory()->post->create( array( 'post_type' => 'page' ) ) ) as $page_id ) {
			$_POST = array(
				CRM_Leads_Capture_Profile_Admin::PAGE_NONCE_FIELD => wp_create_nonce( CRM_Leads_Capture_Profile_Admin::PAGE_NONCE_ACTION ),
				'crm_leads_capture_page_profile' => 'coo',
			);
			$this->admin->save_page_association( $page_id );
			$this->assertSame( 'coo', $this->repository->page_profile_slug( $page_id ) );
		}
	}

	public function test_page_save_requires_nonce_and_capability_and_preserves_inactive_override(): void {
		$config  = $this->repository->sanitize_profile( $this->profile_input() );
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES, array( 'coo' => $config ) );
		update_post_meta(
			$page_id,
			CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META,
			array( 'rd_station' => array( 'conversion_identifier' => 'saved-rd' ) )
		);

		$_POST = array( 'crm_leads_capture_page_profile' => 'coo' );
		$this->admin->save_page_association( $page_id );
		$this->assertSame( '', $this->repository->page_profile_slug( $page_id ) );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$_POST = array(
			CRM_Leads_Capture_Profile_Admin::PAGE_NONCE_FIELD => wp_create_nonce( CRM_Leads_Capture_Profile_Admin::PAGE_NONCE_ACTION ),
			'crm_leads_capture_page_profile' => 'coo',
			'crm_leads_capture_override_enabled' => '1',
			'crm_leads_capture_page_overrides' => array(
				'brevo' => array(
					'list_ids'      => '90, 91',
					'attribute_map' => "custom_fields.company=COMPANY_PAGE",
				),
			),
		);
		$this->admin->save_page_association( $page_id );

		$this->assertSame( 'coo', $this->repository->page_profile_slug( $page_id ) );
		$this->assertSame( array( 90, 91 ), $this->repository->page_provider_overrides( $page_id, 'brevo' )['list_ids'] );
		$stored = get_post_meta( $page_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META, true );
		$this->assertSame( 'saved-rd', $stored['rd_station']['conversion_identifier'] );
	}

	public function test_admin_page_shows_only_active_provider_destination(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$this->admin->register_setting();
		$_GET = array( 'page' => CRM_Leads_Capture_Profile_Admin::PAGE_SLUG );

		ob_start();
		$this->admin->render_page();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Destino: Brevo', $output );
		$this->assertStringContainsString( '[providers][brevo]', $output );
		$this->assertStringNotContainsString( '[providers][rd_station]', $output );
		$this->assertStringNotContainsString( 'seletor de provider', strtolower( $output ) );
	}

	/** @return array<string, mixed> */
	private function profile_input(): array {
		return array(
			'name'   => 'COO as a Service',
			'slug'   => 'coo',
			'source' => 'coo_as_a_service',
			'fields' => array(
				array( 'name' => 'name', 'type' => 'text', 'group' => 'lead', 'required' => '1' ),
				array( 'name' => 'email', 'type' => 'email', 'group' => 'lead', 'required' => '1' ),
				array( 'name' => 'company', 'type' => 'text', 'group' => 'custom_fields', 'required' => '1' ),
			),
			'success_message' => 'Recebemos seu contato.',
			'redirect_url'    => 'https://example.com/obrigado',
			'providers'       => array(
				'brevo' => array(
					'list_ids'      => '12, 18, 12',
					'attribute_map' => "custom_fields.company=COMPANY",
				),
			),
		);
	}
}
