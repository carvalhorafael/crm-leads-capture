<?php
/**
 * Global plugin settings.
 *
 * @package CRM_Leads_Capture
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CRM_Leads_Capture_Settings {
	public const OPTION_DEFAULT_LIST_ID = 'crm_leads_capture_default_list_id';
	public const OPTION_SETTINGS = 'crm_leads_capture_settings';
	public const SETTINGS_GROUP = 'crm_leads_capture_settings';
	public const SETTINGS_PAGE = 'crm-leads-capture';

	public const LEGACY_OPTION_SETTINGS = 'brevo_leads_capture_settings';
	public const LEGACY_OPTION_DEFAULT_LIST_ID = 'brevo_leads_capture_default_list_id';

	public const ERROR_MESSAGE_CODES = array(
		'invalid_nonce',
		'spam',
		'invalid_material',
		'missing_list',
		'missing_delivery',
		'invalid_lead',
		'invalid_payload',
		'provider_error',
		'brevo_invalid_parameter',
		'brevo_missing_parameter',
		'brevo_duplicate_parameter',
		'brevo_document_not_found',
		'brevo_permission_error',
		'brevo_bad_request',
		'brevo_error',
		'rd_station_bad_request',
		'rd_station_permission_error',
		'rd_station_error',
	);

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_page(): void {
		add_options_page(
			__( 'CRM Leads Capture', 'crm-leads-capture' ),
			__( 'CRM Leads Capture', 'crm-leads-capture' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'crm_leads_capture_general_section', __( 'General', 'crm-leads-capture' ), array( $this, 'render_provider_section' ), $this->settings_tab_page( 'general' ) );
		add_settings_field( 'crm_leads_capture_active_provider', __( 'Default provider', 'crm-leads-capture' ), array( $this, 'render_active_provider_field' ), $this->settings_tab_page( 'general' ), 'crm_leads_capture_general_section', array( 'label_for' => 'crm_leads_capture_active_provider' ) );
		add_settings_field( 'crm_leads_capture_default_delivery_url', __( 'URL de entrega padrão', 'crm-leads-capture' ), array( $this, 'render_default_delivery_url_field' ), $this->settings_tab_page( 'general' ), 'crm_leads_capture_general_section', array( 'label_for' => 'crm_leads_capture_default_delivery_url' ) );

		add_settings_section( 'crm_leads_capture_messages_section', __( 'Messages', 'crm-leads-capture' ), array( $this, 'render_messages_section' ), $this->settings_tab_page( 'messages' ) );
		add_settings_field( 'crm_leads_capture_success_message', __( 'Mensagem de sucesso', 'crm-leads-capture' ), array( $this, 'render_success_message_field' ), $this->settings_tab_page( 'messages' ), 'crm_leads_capture_messages_section', array( 'label_for' => 'crm_leads_capture_success_message' ) );
		add_settings_field( 'crm_leads_capture_error_messages', __( 'Mensagens de erro', 'crm-leads-capture' ), array( $this, 'render_error_messages_field' ), $this->settings_tab_page( 'messages' ), 'crm_leads_capture_messages_section' );

		add_settings_section( 'crm_leads_capture_rd_station_section', __( 'RD Station', 'crm-leads-capture' ), array( $this, 'render_rd_station_section' ), $this->settings_tab_page( 'rd_station' ) );
		add_settings_field( 'crm_leads_capture_rd_station_enabled', __( 'Ativo', 'crm-leads-capture' ), array( $this, 'render_rd_station_enabled_field' ), $this->settings_tab_page( 'rd_station' ), 'crm_leads_capture_rd_station_section' );
		add_settings_field( 'crm_leads_capture_rd_station_api_key', __( 'API key RD Station', 'crm-leads-capture' ), array( $this, 'render_rd_station_api_key_field' ), $this->settings_tab_page( 'rd_station' ), 'crm_leads_capture_rd_station_section', array( 'label_for' => 'crm_leads_capture_rd_station_api_key' ) );
		add_settings_field( 'crm_leads_capture_rd_station_default_conversion_identifier', __( 'Conversão padrão', 'crm-leads-capture' ), array( $this, 'render_rd_station_conversion_identifier_field' ), $this->settings_tab_page( 'rd_station' ), 'crm_leads_capture_rd_station_section', array( 'label_for' => 'crm_leads_capture_rd_station_default_conversion_identifier' ) );
		add_settings_field( 'crm_leads_capture_rd_station_default_tags', __( 'Tags padrão', 'crm-leads-capture' ), array( $this, 'render_rd_station_tags_field' ), $this->settings_tab_page( 'rd_station' ), 'crm_leads_capture_rd_station_section', array( 'label_for' => 'crm_leads_capture_rd_station_default_tags' ) );

		add_settings_section( 'crm_leads_capture_brevo_section', __( 'Brevo', 'crm-leads-capture' ), array( $this, 'render_brevo_section' ), $this->settings_tab_page( 'brevo' ) );
		add_settings_field( 'crm_leads_capture_brevo_enabled', __( 'Ativo', 'crm-leads-capture' ), array( $this, 'render_brevo_enabled_field' ), $this->settings_tab_page( 'brevo' ), 'crm_leads_capture_brevo_section' );
		add_settings_field( 'crm_leads_capture_brevo_api_key', __( 'API key Brevo', 'crm-leads-capture' ), array( $this, 'render_brevo_api_key_field' ), $this->settings_tab_page( 'brevo' ), 'crm_leads_capture_brevo_section', array( 'label_for' => 'crm_leads_capture_brevo_api_key' ) );
		add_settings_field( 'crm_leads_capture_brevo_default_list_id', __( 'Lista padrão Brevo', 'crm-leads-capture' ), array( $this, 'render_brevo_default_list_id_field' ), $this->settings_tab_page( 'brevo' ), 'crm_leads_capture_brevo_section', array( 'label_for' => 'crm_leads_capture_brevo_default_list_id' ) );
	}

	/**
	 * @param mixed $input
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_options( $input ): array {
		$current = $this->options();
		$input   = is_array( $input ) ? $input : array();

		$provider = $this->clean_string( $input['active_provider'] ?? ( $current['active_provider'] ?? 'brevo' ) );
		if ( ! in_array( $provider, array( 'brevo', 'rd_station' ), true ) ) {
			$provider = 'brevo';
		}

		$current_providers = isset( $current['providers'] ) && is_array( $current['providers'] ) ? $current['providers'] : array();
		$input_providers   = isset( $input['providers'] ) && is_array( $input['providers'] ) ? $input['providers'] : array();

		$brevo_current = isset( $current_providers['brevo'] ) && is_array( $current_providers['brevo'] ) ? $current_providers['brevo'] : array();
		$brevo_input   = isset( $input_providers['brevo'] ) && is_array( $input_providers['brevo'] ) ? $input_providers['brevo'] : array();
		$brevo_api_key = $brevo_current['api_key'] ?? $this->legacy_api_key();
		if ( ! $this->brevo_api_key_constant_configured() && array_key_exists( 'api_key', $brevo_input ) ) {
			$new_api_key = $this->clean_string( $brevo_input['api_key'] );
			if ( '' !== $new_api_key ) {
				$brevo_api_key = $new_api_key;
			}
		}

		$rd_current = isset( $current_providers['rd_station'] ) && is_array( $current_providers['rd_station'] ) ? $current_providers['rd_station'] : array();
		$rd_input   = isset( $input_providers['rd_station'] ) && is_array( $input_providers['rd_station'] ) ? $input_providers['rd_station'] : array();
		$rd_api_key = $rd_current['api_key'] ?? '';
		if ( ! $this->rd_station_api_key_constant_configured() && array_key_exists( 'api_key', $rd_input ) ) {
			$new_api_key = $this->clean_string( $rd_input['api_key'] );
			if ( '' !== $new_api_key ) {
				$rd_api_key = $new_api_key;
			}
		}

		return array(
			'active_provider' => $provider,
			'providers'       => array(
				'brevo'      => array(
					'enabled'         => $this->sanitize_provider_enabled( 'brevo', $input_providers, $brevo_current, true ),
					'api_key'         => $brevo_api_key,
					'default_list_id' => $this->absint( $brevo_input['default_list_id'] ?? ( $brevo_current['default_list_id'] ?? $this->legacy_default_list_id() ) ),
				),
				'rd_station' => array(
					'enabled'                       => $this->sanitize_provider_enabled( 'rd_station', $input_providers, $rd_current, 'rd_station' === $provider ),
					'api_key'                       => $rd_api_key,
					'default_conversion_identifier' => $this->clean_string( $rd_input['default_conversion_identifier'] ?? ( $rd_current['default_conversion_identifier'] ?? '' ) ),
					'default_tags'                  => $this->clean_string( $rd_input['default_tags'] ?? ( $rd_current['default_tags'] ?? '' ) ),
				),
			),
			'error_messages'  => $this->sanitize_error_messages( $input['error_messages'] ?? ( $current['error_messages'] ?? array() ) ),
			'success_message' => $this->clean_textarea( $input['success_message'] ?? ( $current['success_message'] ?? '' ) ),
			'default_delivery_url' => $this->clean_url( $input['default_delivery_url'] ?? ( $current['default_delivery_url'] ?? '' ) ),
		);
	}

	public function active_provider(): string {
		$provider = $this->option_string( 'active_provider' );

		return in_array( $provider, array( 'brevo', 'rd_station' ), true ) ? $provider : 'brevo';
	}

	public function brevo_api_key(): string {
		if ( defined( 'BREVO_LEADS_CAPTURE_API_KEY' ) && is_string( BREVO_LEADS_CAPTURE_API_KEY ) ) {
			return trim( BREVO_LEADS_CAPTURE_API_KEY );
		}
		if ( defined( 'CRM_LEADS_CAPTURE_BREVO_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_BREVO_API_KEY ) ) {
			return trim( CRM_LEADS_CAPTURE_BREVO_API_KEY );
		}
		if ( defined( 'CRM_LEADS_CAPTURE_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_API_KEY ) ) {
			return trim( CRM_LEADS_CAPTURE_API_KEY );
		}

		return $this->provider_option_string( 'brevo', 'api_key', $this->legacy_api_key() );
	}

	public function api_key(): string {
		return $this->brevo_api_key();
	}

	public function brevo_default_list_id(): int {
		if ( defined( 'BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID' ) ) {
			return $this->absint( BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID );
		}
		if ( defined( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID' ) ) {
			return $this->absint( CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID );
		}
		if ( defined( 'CRM_LEADS_CAPTURE_DEFAULT_LIST_ID' ) ) {
			return $this->absint( CRM_LEADS_CAPTURE_DEFAULT_LIST_ID );
		}

		return $this->absint( $this->provider_option_string( 'brevo', 'default_list_id', (string) $this->legacy_default_list_id() ) );
	}

	public function default_list_id(): int {
		return $this->brevo_default_list_id();
	}

	public function has_api_key(): bool {
		return '' !== $this->brevo_api_key();
	}

	public function rd_station_api_key(): string {
		if ( defined( 'CRM_LEADS_CAPTURE_RD_STATION_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_RD_STATION_API_KEY ) ) {
			return trim( CRM_LEADS_CAPTURE_RD_STATION_API_KEY );
		}

		return $this->provider_option_string( 'rd_station', 'api_key' );
	}

	public function rd_station_default_conversion_identifier(): string {
		return $this->provider_option_string( 'rd_station', 'default_conversion_identifier' );
	}

	public function rd_station_default_tags(): string {
		return $this->provider_option_string( 'rd_station', 'default_tags' );
	}

	public function provider_enabled( string $provider ): bool {
		$default = 'brevo' === $provider;

		return $this->provider_option_bool( $provider, 'enabled', $default );
	}

	public function error_message( string $code ): string {
		$messages = $this->error_messages();

		return $messages[ $code ] ?? $messages['provider_error'];
	}

	public function success_message(): string {
		$message = $this->option_string( 'success_message' );

		return '' !== $message ? $message : __( 'Cadastro recebido. Você será redirecionado para a página do material em 5 segundos.', 'crm-leads-capture' );
	}

	public function default_delivery_url(): string {
		return $this->option_url( 'default_delivery_url' );
	}

	/**
	 * @return array<string, string>
	 */
	public function error_messages(): array {
		$messages = $this->default_error_messages();
		$stored   = $this->options()['error_messages'] ?? array();
		if ( is_array( $stored ) ) {
			foreach ( self::ERROR_MESSAGE_CODES as $code ) {
				$value = $stored[ $code ] ?? '';
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					$messages[ $code ] = trim( $value );
				}
			}
		}

		return $messages;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php $this->render_tabs(); ?>
			<form action="options.php" method="post">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>
				<?php do_settings_sections( $this->settings_tab_page( $this->current_tab() ) ); ?>
				<?php submit_button( __( 'Salvar configurações', 'crm-leads-capture' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function render_status_panel(): void {
		?>
		<div class="notice notice-info inline">
			<p><strong><?php echo esc_html__( 'Status da configuração', 'crm-leads-capture' ); ?></strong></p>
			<ul>
				<li><?php echo esc_html__( 'Provider ativo:', 'crm-leads-capture' ) . ' ' . esc_html( $this->active_provider_label() ); ?></li>
				<li><?php echo esc_html__( 'Brevo API key:', 'crm-leads-capture' ) . ' ' . esc_html( '' !== $this->brevo_api_key() ? __( 'Configurada', 'crm-leads-capture' ) : __( 'Não configurada', 'crm-leads-capture' ) ); ?></li>
				<li><?php echo esc_html__( 'RD Station API key:', 'crm-leads-capture' ) . ' ' . esc_html( '' !== $this->rd_station_api_key() ? __( 'Configurada', 'crm-leads-capture' ) : __( 'Não configurada', 'crm-leads-capture' ) ); ?></li>
				<li><?php echo esc_html__( 'Logs técnicos:', 'crm-leads-capture' ) . ' ' . esc_html( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? __( 'Ativos via WP_DEBUG', 'crm-leads-capture' ) : __( 'Inativos', 'crm-leads-capture' ) ); ?></li>
			</ul>
		</div>
		<?php
	}

	public function render_provider_section(): void {
		echo '<p>' . esc_html__( 'Escolha o provider padrão usado quando um material gratuito não define um provider próprio.', 'crm-leads-capture' ) . '</p>';
	}

	public function render_active_provider_field(): void {
		?>
		<select id="crm_leads_capture_active_provider" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[active_provider]">
			<option value="brevo" <?php selected( 'brevo', $this->active_provider() ); ?>><?php echo esc_html( $this->provider_select_label( 'brevo', __( 'Brevo', 'crm-leads-capture' ) ) ); ?></option>
			<option value="rd_station" <?php selected( 'rd_station', $this->active_provider() ); ?>><?php echo esc_html( $this->provider_select_label( 'rd_station', __( 'RD Station', 'crm-leads-capture' ) ) ); ?></option>
		</select>
		<p class="description"><?php echo esc_html__( 'Cada material pode sobrescrever este padrão escolhendo um provider próprio.', 'crm-leads-capture' ); ?></p>
		<?php
	}

	public function render_default_delivery_url_field(): void {
		?>
		<input
			type="url"
			id="crm_leads_capture_default_delivery_url"
			name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[default_delivery_url]"
			value="<?php echo esc_attr( $this->default_delivery_url() ); ?>"
			class="regular-text"
		/>
		<p class="description"><?php echo esc_html__( 'Usada quando o material gratuito não define uma URL de entrega própria.', 'crm-leads-capture' ); ?></p>
		<?php
	}

	public function render_brevo_section(): void {
		echo '<p>' . esc_html__( 'Configure a integração com Brevo. Um material pode usar este provider mesmo quando outro CRM é o padrão global.', 'crm-leads-capture' ) . '</p>';
	}

	public function render_brevo_enabled_field(): void {
		$this->render_enabled_field( 'brevo', 'crm_leads_capture_brevo_enabled', __( 'Permitir envio de leads para Brevo.', 'crm-leads-capture' ) );
	}

	public function render_brevo_api_key_field(): void {
		$this->render_secret_field( 'brevo', 'api_key', 'crm_leads_capture_brevo_api_key', $this->brevo_api_key_constant_configured(), __( 'A API key Brevo está configurada por constante.', 'crm-leads-capture' ), '' !== $this->provider_option_string( 'brevo', 'api_key' ) );
	}

	public function render_brevo_default_list_id_field(): void {
		$constant_configured = defined( 'BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID' ) || defined( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID' ) || defined( 'CRM_LEADS_CAPTURE_DEFAULT_LIST_ID' );
		?>
		<input type="number" min="0" step="1" id="crm_leads_capture_brevo_default_list_id" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[providers][brevo][default_list_id]" value="<?php echo esc_attr( (string) $this->brevo_default_list_id() ); ?>" class="regular-text" <?php disabled( $constant_configured ); ?> />
		<p class="description"><?php echo esc_html__( 'Usada quando o material gratuito não define uma lista Brevo própria.', 'crm-leads-capture' ); ?></p>
		<?php
	}

	public function render_rd_station_section(): void {
		echo '<p>' . esc_html__( 'Configure a integração com RD Station. Conversões usam a API Marketing em /platform/conversions.', 'crm-leads-capture' ) . '</p>';
	}

	public function render_rd_station_enabled_field(): void {
		$this->render_enabled_field( 'rd_station', 'crm_leads_capture_rd_station_enabled', __( 'Permitir envio de leads para RD Station.', 'crm-leads-capture' ) );
	}

	public function render_rd_station_api_key_field(): void {
		$this->render_secret_field( 'rd_station', 'api_key', 'crm_leads_capture_rd_station_api_key', $this->rd_station_api_key_constant_configured(), __( 'A API key RD Station está configurada por constante.', 'crm-leads-capture' ), '' !== $this->provider_option_string( 'rd_station', 'api_key' ) );
	}

	public function render_rd_station_conversion_identifier_field(): void {
		$this->render_text_field( 'rd_station', 'default_conversion_identifier', 'crm_leads_capture_rd_station_default_conversion_identifier', $this->rd_station_default_conversion_identifier(), __( 'Nome do evento de conversão enviado para a RD Station, por exemplo "Download - Guia ENEM". Quando vazio, o título do material é usado.', 'crm-leads-capture' ) );
	}

	public function render_rd_station_tags_field(): void {
		$this->render_text_field( 'rd_station', 'default_tags', 'crm_leads_capture_rd_station_default_tags', $this->rd_station_default_tags(), __( 'Tags adicionadas ao lead na RD Station para segmentação e automações. Separe múltiplas tags por vírgulas.', 'crm-leads-capture' ) );
	}

	public function render_messages_section(): void {
		echo '<p>' . esc_html__( 'Configure os textos públicos exibidos após a captura. Detalhes técnicos do CRM continuam restritos aos logs.', 'crm-leads-capture' ) . '</p>';
	}

	public function render_error_messages_field(): void {
		$messages = $this->error_messages();
		$labels   = $this->error_message_labels();
		?>
		<div class="crm-leads-capture-error-messages">
			<?php foreach ( self::ERROR_MESSAGE_CODES as $code ) : ?>
				<p>
					<label for="<?php echo esc_attr( 'crm_leads_capture_error_message_' . $code ); ?>">
						<strong><?php echo esc_html( $labels[ $code ] ?? $code ); ?></strong>
						<code><?php echo esc_html( $code ); ?></code>
					</label><br>
					<textarea id="<?php echo esc_attr( 'crm_leads_capture_error_message_' . $code ); ?>" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[error_messages][<?php echo esc_attr( $code ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( $messages[ $code ] ?? '' ); ?></textarea>
				</p>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php echo esc_html__( 'Deixe um campo em branco para voltar ao texto padrão.', 'crm-leads-capture' ); ?></p>
		<?php
	}

	public function render_success_message_field(): void {
		?>
		<textarea id="crm_leads_capture_success_message" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[success_message]" rows="2" class="large-text"><?php echo esc_textarea( $this->success_message() ); ?></textarea>
		<p class="description"><?php echo esc_html__( 'Texto exibido quando a captura for concluída antes do redirecionamento automático.', 'crm-leads-capture' ); ?></p>
		<?php
	}

	public function render_tabs(): void {
		$current = $this->current_tab();
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'CRM Leads Capture settings tabs', 'crm-leads-capture' ) . '">';
		foreach ( $this->tabs() as $tab => $label ) {
			$url     = add_query_arg(
				array(
					'page' => self::SETTINGS_PAGE,
					'tab'  => $tab,
				),
				admin_url( 'options-general.php' )
			);
			$classes = 'nav-tab' . ( $current === $tab ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function options(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}

		$options = get_option( self::OPTION_SETTINGS, array() );

		return is_array( $options ) ? $options : array();
	}

	private function render_enabled_field( string $provider, string $id, string $description ): void {
		?>
		<label for="<?php echo esc_attr( $id ); ?>">
			<input
				type="checkbox"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[providers][<?php echo esc_attr( $provider ); ?>][enabled]"
				value="1"
				<?php checked( $this->provider_enabled( $provider ) ); ?>
			/>
			<?php echo esc_html( $description ); ?>
		</label>
		<?php
	}

	private function render_secret_field( string $provider, string $key, string $id, bool $constant_configured, string $constant_message, bool $stored_configured ): void {
		?>
		<input type="password" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[providers][<?php echo esc_attr( $provider ); ?>][<?php echo esc_attr( $key ); ?>]" value="" autocomplete="new-password" class="regular-text" <?php disabled( $constant_configured ); ?> />
		<?php if ( $constant_configured ) : ?>
			<p class="description"><?php echo esc_html( $constant_message ); ?></p>
		<?php elseif ( $stored_configured ) : ?>
			<p class="description"><?php echo esc_html__( 'Uma credencial já está salva. Deixe em branco para mantê-la.', 'crm-leads-capture' ); ?></p>
		<?php else : ?>
			<p class="description"><?php echo esc_html__( 'A credencial será salva no banco de dados do WordPress. Prefira constantes em produção.', 'crm-leads-capture' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private function render_text_field( string $provider, string $key, string $id, string $value, string $description ): void {
		?>
		<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( self::OPTION_SETTINGS ); ?>[providers][<?php echo esc_attr( $provider ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}

	private function option_string( string $key, string $default = '' ): string {
		$value = $this->options()[ $key ] ?? $default;

		return $this->clean_string( $value );
	}

	private function option_url( string $key, string $default = '' ): string {
		$value = $this->options()[ $key ] ?? $default;

		return $this->clean_url( $value );
	}

	private function provider_option_string( string $provider, string $key, string $default = '' ): string {
		$options   = $this->options();
		$providers = isset( $options['providers'] ) && is_array( $options['providers'] ) ? $options['providers'] : array();
		$settings  = isset( $providers[ $provider ] ) && is_array( $providers[ $provider ] ) ? $providers[ $provider ] : array();

		if ( 'brevo' === $provider && ! array_key_exists( $key, $settings ) && array_key_exists( $key, $options ) ) {
			return $this->clean_string( $options[ $key ] );
		}

		return $this->clean_string( $settings[ $key ] ?? $default );
	}

	private function provider_option_bool( string $provider, string $key, bool $default = false ): bool {
		$options   = $this->options();
		$providers = isset( $options['providers'] ) && is_array( $options['providers'] ) ? $options['providers'] : array();
		$settings  = isset( $providers[ $provider ] ) && is_array( $providers[ $provider ] ) ? $providers[ $provider ] : array();

		if ( ! array_key_exists( $key, $settings ) ) {
			return $default;
		}

		return (bool) $settings[ $key ];
	}

	/**
	 * @param array<string, mixed> $input_providers
	 * @param array<string, mixed> $current_provider
	 */
	private function sanitize_provider_enabled( string $provider, array $input_providers, array $current_provider, bool $default ): bool {
		if ( ! array_key_exists( $provider, $input_providers ) ) {
			return array_key_exists( 'enabled', $current_provider ) ? (bool) $current_provider['enabled'] : $default;
		}

		$input_provider = is_array( $input_providers[ $provider ] ) ? $input_providers[ $provider ] : array();

		return ! empty( $input_provider['enabled'] );
	}

	private function settings_tab_page( string $tab ): string {
		return self::SETTINGS_PAGE . '-' . $tab;
	}

	private function current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? $this->clean_string( wp_unslash( $_GET['tab'] ) ) : 'general';

		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'general';
	}

	/**
	 * @return array<string, string>
	 */
	private function tabs(): array {
		return array(
			'general'    => __( 'General', 'crm-leads-capture' ),
			'messages'   => __( 'Messages', 'crm-leads-capture' ),
			'rd_station' => __( 'RD Station', 'crm-leads-capture' ),
			'brevo'      => __( 'Brevo', 'crm-leads-capture' ),
		);
	}

	private function legacy_api_key(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}
		$options = get_option( self::LEGACY_OPTION_SETTINGS, array() );

		return is_array( $options ) ? $this->clean_string( $options['api_key'] ?? '' ) : '';
	}

	private function legacy_default_list_id(): int {
		if ( ! function_exists( 'get_option' ) ) {
			return 0;
		}
		$options = get_option( self::LEGACY_OPTION_SETTINGS, array() );
		if ( is_array( $options ) && isset( $options['default_list_id'] ) ) {
			return $this->absint( $options['default_list_id'] );
		}

		$new_legacy = $this->absint( get_option( self::OPTION_DEFAULT_LIST_ID, 0 ) );
		if ( 0 < $new_legacy ) {
			return $new_legacy;
		}

		return $this->absint( get_option( self::LEGACY_OPTION_DEFAULT_LIST_ID, 0 ) );
	}

	private function brevo_api_key_constant_configured(): bool {
		return ( defined( 'BREVO_LEADS_CAPTURE_API_KEY' ) && is_string( BREVO_LEADS_CAPTURE_API_KEY ) && '' !== trim( BREVO_LEADS_CAPTURE_API_KEY ) )
			|| ( defined( 'CRM_LEADS_CAPTURE_BREVO_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_BREVO_API_KEY ) && '' !== trim( CRM_LEADS_CAPTURE_BREVO_API_KEY ) )
			|| ( defined( 'CRM_LEADS_CAPTURE_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_API_KEY ) && '' !== trim( CRM_LEADS_CAPTURE_API_KEY ) );
	}

	private function rd_station_api_key_constant_configured(): bool {
		return defined( 'CRM_LEADS_CAPTURE_RD_STATION_API_KEY' ) && is_string( CRM_LEADS_CAPTURE_RD_STATION_API_KEY ) && '' !== trim( CRM_LEADS_CAPTURE_RD_STATION_API_KEY );
	}

	private function active_provider_label(): string {
		return 'rd_station' === $this->active_provider() ? __( 'RD Station', 'crm-leads-capture' ) : __( 'Brevo', 'crm-leads-capture' );
	}

	private function provider_select_label( string $provider, string $label ): string {
		if ( $this->provider_enabled( $provider ) ) {
			return $label;
		}

		return sprintf(
			/* translators: %s: provider label. */
			__( '%s (inativo)', 'crm-leads-capture' ),
			$label
		);
	}

	private function clean_string( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );

		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
	}

	private function clean_textarea( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );

		return '' === $value ? '' : ( function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : $this->clean_string( $value ) );
	}

	private function clean_url( $value ): string {
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}

		$value = trim( (string) $value );

		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_SANITIZE_URL );
	}

	private function absint( $value ): int {
		return max( 0, (int) $value );
	}

	private function sanitize_error_messages( $messages ): array {
		$messages  = is_array( $messages ) ? $messages : array();
		$sanitized = array();
		foreach ( self::ERROR_MESSAGE_CODES as $code ) {
			$value = $messages[ $code ] ?? '';
			$value = is_array( $value ) || is_object( $value ) ? '' : trim( (string) $value );
			if ( '' !== $value ) {
				$sanitized[ $code ] = $this->clean_textarea( $value );
			}
		}

		return $sanitized;
	}

	private function default_error_messages(): array {
		$generic_retry = __( 'Não conseguimos concluir seu cadastro agora. Tente novamente em instantes.', 'crm-leads-capture' );
		$config_error  = __( 'Não conseguimos concluir seu cadastro porque este material ainda não está configurado corretamente.', 'crm-leads-capture' );

		$messages = array_fill_keys( self::ERROR_MESSAGE_CODES, $generic_retry );

		return array_merge(
			$messages,
			array(
				'invalid_nonce'    => __( 'A sessão do formulário expirou. Recarregue a página e tente novamente.', 'crm-leads-capture' ),
				'invalid_material' => $config_error,
				'missing_list'     => $config_error,
				'missing_delivery' => $config_error,
				'invalid_lead'     => __( 'Revise os dados informados e tente novamente.', 'crm-leads-capture' ),
			)
		);
	}

	private function error_message_labels(): array {
		return array(
			'invalid_nonce'               => __( 'Nonce inválido ou expirado', 'crm-leads-capture' ),
			'spam'                        => __( 'Honeypot preenchido', 'crm-leads-capture' ),
			'invalid_material'            => __( 'Material inválido', 'crm-leads-capture' ),
			'missing_list'                => __( 'Configuração de lista ausente', 'crm-leads-capture' ),
			'missing_delivery'            => __( 'URL de entrega ausente', 'crm-leads-capture' ),
			'invalid_lead'                => __( 'Dados do lead inválidos', 'crm-leads-capture' ),
			'invalid_payload'             => __( 'Payload inválido', 'crm-leads-capture' ),
			'provider_error'              => __( 'Erro genérico do provider', 'crm-leads-capture' ),
			'brevo_invalid_parameter'     => __( 'Parâmetro inválido na Brevo', 'crm-leads-capture' ),
			'brevo_missing_parameter'     => __( 'Parâmetro ausente na Brevo', 'crm-leads-capture' ),
			'brevo_duplicate_parameter'   => __( 'Parâmetro duplicado na Brevo', 'crm-leads-capture' ),
			'brevo_document_not_found'    => __( 'Registro não encontrado na Brevo', 'crm-leads-capture' ),
			'brevo_permission_error'      => __( 'Erro de permissão na Brevo', 'crm-leads-capture' ),
			'brevo_bad_request'           => __( 'Requisição recusada pela Brevo', 'crm-leads-capture' ),
			'brevo_error'                 => __( 'Erro genérico da Brevo', 'crm-leads-capture' ),
			'rd_station_bad_request'      => __( 'Requisição recusada pelo RD Station', 'crm-leads-capture' ),
			'rd_station_permission_error' => __( 'Erro de permissão no RD Station', 'crm-leads-capture' ),
			'rd_station_error'            => __( 'Erro genérico do RD Station', 'crm-leads-capture' ),
		);
	}
}
