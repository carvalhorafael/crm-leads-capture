<?php
/**
 * Capture profile administration and page association UI.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Profile_Admin {
	public const SETTINGS_GROUP = 'crm_leads_capture_profiles';
	public const PAGE_SLUG = 'crm-leads-capture-profiles';
	public const PAGE_NONCE_ACTION = 'crm_leads_capture_save_page_profile';
	public const PAGE_NONCE_FIELD = 'crm_leads_capture_page_nonce';

	private CRM_Leads_Capture_Profile_Repository $repository;

	private CRM_Leads_Capture_Settings $settings;

	public function __construct( CRM_Leads_Capture_Profile_Repository $repository, CRM_Leads_Capture_Settings $settings ) {
		$this->repository = $repository;
		$this->settings   = $settings;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'add_meta_boxes_page', array( $this, 'register_page_meta_box' ) );
		add_action( 'save_post_page', array( $this, 'save_page_association' ) );
	}

	/** @param string $hook_suffix Current admin page hook. */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$path = CRM_LEADS_CAPTURE_DIR . 'assets/js/capture-profile-admin.js';
		wp_enqueue_script(
			'crm-leads-capture-profile-admin',
			plugins_url( 'assets/js/capture-profile-admin.js', CRM_LEADS_CAPTURE_FILE ),
			array(),
			is_file( $path ) ? (string) filemtime( $path ) : CRM_LEADS_CAPTURE_VERSION,
			true
		);
	}

	public function register_page(): void {
		add_submenu_page(
			'options-general.php',
			__( 'Perfis de captura', 'crm-leads-capture' ),
			__( 'Perfis de captura', 'crm-leads-capture' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_setting(): void {
		register_setting(
			self::SETTINGS_GROUP,
			CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this->repository, 'sanitize_option' ),
				'default'           => array(),
			)
		);
	}

	public function register_page_meta_box(): void {
		add_meta_box(
			'crm-leads-capture-page-profile',
			__( 'Perfil de captura', 'crm-leads-capture' ),
			array( $this, 'render_page_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$profiles = $this->repository->all();
		$request  = wp_unslash( $_GET );
		$slug     = sanitize_key( $request['profile'] ?? '' );
		$config   = isset( $profiles[ $slug ] ) && is_array( $profiles[ $slug ] ) ? $profiles[ $slug ] : array();
		$provider = $this->settings->active_provider();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Perfis de captura', 'crm-leads-capture' ); ?></h1>
			<p><?php echo esc_html__( 'Os perfis definem validação e destinos no servidor. O tema continua responsável pelo markup do formulário.', 'crm-leads-capture' ); ?></p>
			<?php settings_errors( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>
			<ul>
				<?php foreach ( $profiles as $profile_slug => $profile ) : ?>
					<li><a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'profile' => $profile_slug ), admin_url( 'options-general.php' ) ) ); ?>"><?php echo esc_html( (string) ( $profile['name'] ?? $profile_slug ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<p><a class="button" href="<?php echo esc_url( add_query_arg( 'page', self::PAGE_SLUG, admin_url( 'options-general.php' ) ) ); ?>"><?php echo esc_html__( 'Novo perfil', 'crm-leads-capture' ); ?></a></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[original_slug]" value="<?php echo esc_attr( $slug ); ?>">
				<table class="form-table" role="presentation">
					<tr><th><label for="crm-profile-name"><?php echo esc_html__( 'Nome', 'crm-leads-capture' ); ?></label></th><td><input class="regular-text" required id="crm-profile-name" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[name]" value="<?php echo esc_attr( (string) ( $config['name'] ?? '' ) ); ?>"></td></tr>
					<tr><th><label for="crm-profile-slug"><?php echo esc_html__( 'Slug', 'crm-leads-capture' ); ?></label></th><td><input class="regular-text" required id="crm-profile-slug" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[slug]" value="<?php echo esc_attr( (string) ( $config['slug'] ?? '' ) ); ?>"></td></tr>
					<tr><th><label for="crm-profile-source"><?php echo esc_html__( 'Origem', 'crm-leads-capture' ); ?></label></th><td><input class="regular-text" id="crm-profile-source" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[source]" value="<?php echo esc_attr( (string) ( $config['context']['source'] ?? '' ) ); ?>"></td></tr>
					<tr><th><?php echo esc_html__( 'Campos', 'crm-leads-capture' ); ?></th><td><?php $this->render_fields( $config['fields'] ?? array() ); ?></td></tr>
					<tr><th><label for="crm-profile-success"><?php echo esc_html__( 'Mensagem de sucesso', 'crm-leads-capture' ); ?></label></th><td><textarea class="large-text" id="crm-profile-success" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[success_message]" rows="3"><?php echo esc_textarea( (string) ( $config['success']['message'] ?? '' ) ); ?></textarea></td></tr>
					<tr><th><label for="crm-profile-redirect"><?php echo esc_html__( 'Redirect de sucesso', 'crm-leads-capture' ); ?></label></th><td><input type="url" class="large-text" id="crm-profile-redirect" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[redirect_url]" value="<?php echo esc_attr( (string) ( $config['success']['redirect_url'] ?? '' ) ); ?>"></td></tr>
				</table>
				<h2><?php echo esc_html( sprintf( __( 'Destino: %s', 'crm-leads-capture' ), $this->provider_label( $provider ) ) ); ?></h2>
				<?php $this->render_provider_fields( $provider, $config['providers'][ $provider ] ?? array(), CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES . '[providers][' . $provider . ']' ); ?>
				<?php $this->render_diagnostics( $config, $provider ); ?>
				<?php submit_button( __( 'Salvar perfil', 'crm-leads-capture' ) ); ?>
				<?php if ( '' !== $slug ) : ?>
					<label><input type="checkbox" name="<?php echo esc_attr( CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES ); ?>[delete]" value="1"> <?php echo esc_html__( 'Excluir este perfil ao salvar', 'crm-leads-capture' ); ?></label>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/** @param mixed $post */
	public function render_page_meta_box( $post ): void {
		$post_id   = $post instanceof WP_Post ? (int) $post->ID : 0;
		$selected  = $this->repository->page_profile_slug( $post_id );
		$enabled   = '1' === (string) get_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDE_ENABLED_META, true );
		$provider  = $this->settings->active_provider();
		$overrides = get_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META, true );
		$config    = is_array( $overrides ) && isset( $overrides[ $provider ] ) && is_array( $overrides[ $provider ] ) ? $overrides[ $provider ] : array();
		wp_nonce_field( self::PAGE_NONCE_ACTION, self::PAGE_NONCE_FIELD );
		?>
		<p><label for="crm-leads-capture-page-profile"><?php echo esc_html__( 'Perfil reutilizável', 'crm-leads-capture' ); ?></label></p>
		<select class="widefat" id="crm-leads-capture-page-profile" name="crm_leads_capture_page_profile">
			<option value=""><?php echo esc_html__( 'Nenhum', 'crm-leads-capture' ); ?></option>
			<?php foreach ( $this->repository->all() as $slug => $profile ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $selected, $slug ); ?>><?php echo esc_html( (string) ( $profile['name'] ?? $slug ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<p><label><input type="checkbox" name="crm_leads_capture_override_enabled" value="1" <?php checked( $enabled ); ?>> <?php echo esc_html__( 'Sobrescrever o destino deste perfil nesta página', 'crm-leads-capture' ); ?></label></p>
		<div class="crm-leads-capture-page-overrides">
			<strong><?php echo esc_html( $this->provider_label( $provider ) ); ?></strong>
			<?php $this->render_provider_fields( $provider, $config, 'crm_leads_capture_page_overrides[' . $provider . ']' ); ?>
		</div>
		<?php
	}

	public function save_page_association( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$request = wp_unslash( $_POST );
		$nonce   = isset( $request[ self::PAGE_NONCE_FIELD ] ) && is_scalar( $request[ self::PAGE_NONCE_FIELD ] ) ? (string) $request[ self::PAGE_NONCE_FIELD ] : '';
		if ( '' === $nonce || false === wp_verify_nonce( $nonce, self::PAGE_NONCE_ACTION ) ) {
			return;
		}

		$slug = isset( $request['crm_leads_capture_page_profile'] ) ? sanitize_key( $request['crm_leads_capture_page_profile'] ) : '';
		if ( '' !== $slug && isset( $this->repository->all()[ $slug ] ) ) {
			update_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_PROFILE_META, $slug );
		} else {
			delete_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_PROFILE_META );
		}

		$enabled = ! empty( $request['crm_leads_capture_override_enabled'] );
		update_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDE_ENABLED_META, $enabled ? '1' : '0' );

		$provider  = $this->settings->active_provider();
		$submitted = isset( $request['crm_leads_capture_page_overrides'][ $provider ] ) && is_array( $request['crm_leads_capture_page_overrides'][ $provider ] ) ? $request['crm_leads_capture_page_overrides'][ $provider ] : array();
		$overrides = get_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META, true );
		$overrides = is_array( $overrides ) ? $overrides : array();
		$overrides[ $provider ] = $this->repository->sanitize_provider_config( $provider, $submitted );
		update_post_meta( $post_id, CRM_Leads_Capture_Profile_Repository::PAGE_OVERRIDES_META, $overrides );
	}

	/** @param mixed $fields */
	private function render_fields( $fields ): void {
		$fields = is_array( $fields ) ? array_values( $fields ) : array();
		if ( array() === $fields ) {
			$fields[] = array();
		}
		?>
		<div data-crm-profile-fields data-next-index="<?php echo esc_attr( (string) count( $fields ) ); ?>">
			<div data-crm-profile-field-list>
				<?php foreach ( $fields as $index => $field ) : ?>
					<?php $this->render_field_row( is_array( $field ) ? $field : array(), (string) $index ); ?>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" data-crm-profile-add-field><?php echo esc_html__( 'Adicionar campo', 'crm-leads-capture' ); ?></button></p>
			<template data-crm-profile-field-template>
				<?php $this->render_field_row( array(), '__INDEX__' ); ?>
			</template>
		</div>
		<?php
	}

	/** @param array<string, mixed> $field */
	private function render_field_row( array $field, string $index ): void {
		$base           = CRM_Leads_Capture_Profile_Repository::OPTION_PROFILES . '[fields][' . $index . ']';
		$allowed_values = implode( ', ', (array) ( $field['allowed_values'] ?? array() ) );
		?>
		<p data-crm-profile-field-row>
			<input placeholder="<?php echo esc_attr__( 'nome_do_campo', 'crm-leads-capture' ); ?>" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( (string) ( $field['name'] ?? '' ) ); ?>">
			<select name="<?php echo esc_attr( $base ); ?>[type]">
				<?php foreach ( $this->repository->field_types() as $type ) : ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field['type'] ?? 'text', $type ); ?>><?php echo esc_html( $type ); ?></option><?php endforeach; ?>
			</select>
			<select name="<?php echo esc_attr( $base ); ?>[group]">
				<?php foreach ( $this->repository->field_groups() as $group ) : ?><option value="<?php echo esc_attr( $group ); ?>" <?php selected( $field['group'] ?? 'custom_fields', $group ); ?>><?php echo esc_html( $group ); ?></option><?php endforeach; ?>
			</select>
			<input placeholder="<?php echo esc_attr__( 'valores do select, separados por vírgula', 'crm-leads-capture' ); ?>" name="<?php echo esc_attr( $base ); ?>[allowed_values]" value="<?php echo esc_attr( $allowed_values ); ?>">
			<label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>> <?php echo esc_html__( 'Obrigatório', 'crm-leads-capture' ); ?></label>
			<button type="button" class="button-link-delete" data-crm-profile-remove-field><?php echo esc_html__( 'Remover', 'crm-leads-capture' ); ?></button>
		</p>
		<?php
	}

	/** @param array<string, mixed> $config */
	private function render_provider_fields( string $provider, array $config, string $base ): void {
		if ( 'brevo' === $provider ) {
			$list_ids = implode( ', ', array_map( 'intval', (array) ( $config['list_ids'] ?? array() ) ) );
			echo '<p><label>' . esc_html__( 'IDs das listas', 'crm-leads-capture' ) . '<input class="widefat" name="' . esc_attr( $base ) . '[list_ids]" value="' . esc_attr( $list_ids ) . '"></label></p>';
			$this->render_map_field( $base . '[attribute_map]', $config['attribute_map'] ?? array(), __( 'Mapa de atributos', 'crm-leads-capture' ) );
			return;
		}

		echo '<p><label>' . esc_html__( 'Identificador de conversão', 'crm-leads-capture' ) . '<input class="widefat" name="' . esc_attr( $base ) . '[conversion_identifier]" value="' . esc_attr( (string) ( $config['conversion_identifier'] ?? '' ) ) . '"></label></p>';
		echo '<p><label>' . esc_html__( 'Tags', 'crm-leads-capture' ) . '<input class="widefat" name="' . esc_attr( $base ) . '[tags]" value="' . esc_attr( implode( ', ', (array) ( $config['tags'] ?? array() ) ) ) . '"></label></p>';
		$this->render_map_field( $base . '[field_map]', $config['field_map'] ?? array(), __( 'Mapa de campos', 'crm-leads-capture' ) );
	}

	/** @param mixed $map */
	private function render_map_field( string $name, $map, string $label ): void {
		$lines = array();
		foreach ( is_array( $map ) ? $map : array() as $source => $target ) {
			$lines[] = $source . '=' . $target;
		}
		echo '<p><label>' . esc_html( $label ) . '<textarea class="widefat" rows="5" name="' . esc_attr( $name ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea></label></p>';
	}

	/** @param array<string, mixed> $config */
	private function render_diagnostics( array $config, string $provider ): void {
		if ( array() === $config ) {
			return;
		}
		$provider_config = isset( $config['providers'][ $provider ] ) && is_array( $config['providers'][ $provider ] ) ? $config['providers'][ $provider ] : array();
		$incomplete      = 'brevo' === $provider
			? array() === ( $provider_config['list_ids'] ?? array() ) && 0 >= $this->settings->brevo_default_list_id()
			: '' === (string) ( $provider_config['conversion_identifier'] ?? '' ) && '' === $this->settings->rd_station_default_conversion_identifier();
		if ( $incomplete ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Configuração incompleta: defina o destino do provider ativo ou um padrão global.', 'crm-leads-capture' ) . '</p></div>';
		}

		$map     = 'brevo' === $provider ? ( $provider_config['attribute_map'] ?? array() ) : ( $provider_config['field_map'] ?? array() );
		$missing = array();
		foreach ( (array) ( $config['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$group = (string) ( $field['group'] ?? '' );
			$name  = (string) ( $field['name'] ?? '' );
			$path  = $group . '.' . $name;
			if ( '' !== $name && ! $this->is_provider_standard_field( $provider, $group, $name ) && ! isset( $map[ $path ] ) && ! isset( $map[ $name ] ) ) {
				$missing[] = $path;
			}
		}
		if ( array() !== $missing ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Mapeamentos ausentes: %s', 'crm-leads-capture' ), implode( ', ', $missing ) ) ) . '</p></div>';
		}
	}

	private function is_provider_standard_field( string $provider, string $group, string $name ): bool {
		$standard = 'brevo' === $provider
			? array(
				'lead'     => array( 'email', 'name', 'whatsapp' ),
				'tracking' => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_name' ),
			)
			: array(
				'lead'     => array( 'email', 'name', 'whatsapp', 'job_title', 'company_name', 'company_site' ),
				'tracking' => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term' ),
			);

		return in_array( $name, $standard[ $group ] ?? array(), true );
	}

	private function provider_label( string $provider ): string {
		return 'rd_station' === $provider ? __( 'RD Station', 'crm-leads-capture' ) : __( 'Brevo', 'crm-leads-capture' );
	}
}
