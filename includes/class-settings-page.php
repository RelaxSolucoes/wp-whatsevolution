<?php
namespace WpWhatsAppEvolution;

/**
 * Página de configurações do plugin
 */
class Settings_Page {
	private static $instance = null;
	private static $menu_title = 'WhatsApp Evolution';
	private static $page_title = 'WhatsApp Evolution';

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		add_action('admin_post_wpwevo_test_connection', [$this, 'test_connection']);
		add_action('wp_ajax_wpwevo_validate_settings', [$this, 'validate_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
	}

	public function setup() {
		self::$menu_title = __('WhatsApp Evolution', 'wp-whatsapp-evolution');
		self::$page_title = __('WhatsApp Evolution', 'wp-whatsapp-evolution');
	}

	public function add_menu() {
		add_menu_page(
			self::$page_title,
			self::$menu_title,
			'manage_options',
			'wpwevo-settings',
			[$this, 'render_page'],
			'dashicons-whatsapp',
			56
		);
	}

	public function register_settings() {
		register_setting('wpwevo_settings', 'wpwevo_api_url', [
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => ''
		]);

		register_setting('wpwevo_settings', 'wpwevo_api_key', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		]);

		register_setting('wpwevo_settings', 'wpwevo_instance', [
			'type' => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => ''
		]);
	}

	public function test_connection() {
		check_admin_referer('wpwevo_test_connection');
		
		$api = Api_Connection::get_instance();
		$result = $api->check_connection();
		
		wp_redirect(add_query_arg(
			[
				'page' => 'wpwevo-settings',
				'connection' => $result['success'] ? 'success' : 'error',
				'message' => urlencode($result['message'])
			],
			admin_url('admin.php')
		));
		exit;
	}

	public function validate_settings() {
		try {
			check_ajax_referer('wpwevo_validate_settings', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
			$api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
			$instance = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';

			if (empty($api_url) || empty($api_key) || empty($instance)) {
				throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
			}

			// Valida formato da URL
			if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
				throw new \Exception(__('URL da API inválida.', 'wp-whatsapp-evolution'));
			}

			// Valida formato da API Key (deve ter 36 caracteres no formato UUID)
			if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $api_key)) {
				throw new \Exception(__('Formato da API Key inválido.', 'wp-whatsapp-evolution'));
			}

			// Atualiza as opções
			update_option('wpwevo_api_url', $api_url);
			update_option('wpwevo_api_key', $api_key);
			update_option('wpwevo_instance', $instance);

			// Testa a conexão
			$api = Api_Connection::get_instance();
			$result = $api->check_connection();

			if ($result['success']) {
				wp_send_json_success([
					'message' => __('Configurações salvas com sucesso!', 'wp-whatsapp-evolution'),
					'connection_status' => $result
				]);
			} else {
				throw new \Exception(sprintf(
					__('Configurações salvas, mas %s', 'wp-whatsapp-evolution'),
					strtolower($result['message'])
				));
			}
		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao salvar configurações: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	public function enqueue_admin_assets($hook) {
		if (strpos($hook, 'wpwevo-settings') === false) {
			return;
		}

		wp_enqueue_style(
			'wpwevo-admin',
			WPWEVO_URL . 'assets/css/admin.css',
			[],
			WPWEVO_VERSION
		);

		wp_enqueue_script(
			'wpwevo-admin',
			WPWEVO_URL . 'assets/js/admin.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-admin', 'wpwevo_admin', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_validate_settings'),
			'error_message' => __('Erro ao salvar as configurações. Tente novamente.', 'wp-whatsapp-evolution')
		]);
	}

	public function render_page() {
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';
		$tabs = [
			'connection' => __('Conexão', 'wp-whatsapp-evolution'),
			'help' => __('Ajuda', 'wp-whatsapp-evolution'),
		];

		if (isset($_GET['connection'])) {
			$type = $_GET['connection'] === 'success' ? 'success' : 'error';
			$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
			echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html($message) . '</p></div>';
		}
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html__('WhatsApp Evolution - Configurações', 'wp-whatsapp-evolution'); ?></h1>
			
			<h2 class="nav-tab-wrapper">
				<?php foreach ($tabs as $tab => $label) : ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=wpwevo-settings&tab=' . $tab)); ?>" 
					   class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html($label); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			switch ($active_tab) {
				case 'connection':
					$this->render_connection_tab();
					break;
				case 'help':
					$this->render_help_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	private function render_connection_tab() {
		$api = Api_Connection::get_instance();
		$connection_status = $api->is_configured() ? $api->check_connection() : null;
		?>
		<div class="wpwevo-connection-form">
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpwevo-settings-form">
				<?php settings_fields('wpwevo_settings'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e('URL da API', 'wp-whatsapp-evolution'); ?></th>
						<td>
							<input type="url" name="api_url" 
								   value="<?php echo esc_attr(get_option('wpwevo_api_url', '')); ?>" 
								   class="regular-text wpwevo-api-field" required
								   placeholder="https://sua-api.exemplo.com">
							<p class="description">
								<?php _e('URL completa onde a Evolution API está instalada', 'wp-whatsapp-evolution'); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('API KEY', 'wp-whatsapp-evolution'); ?></th>
						<td>
							<input type="text" name="api_key" 
								   value="<?php echo esc_attr(get_option('wpwevo_api_key', '')); ?>" 
								   class="regular-text wpwevo-api-field" required>
							<p class="description">
								<?php _e('Chave de API gerada nas configurações da Evolution API', 'wp-whatsapp-evolution'); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Nome da Instância', 'wp-whatsapp-evolution'); ?></th>
						<td>
							<input type="text" name="instance" 
								   value="<?php echo esc_attr(get_option('wpwevo_instance', '')); ?>" 
								   class="regular-text wpwevo-api-field" required>
							<p class="description">
								<?php _e('Nome da instância criada na Evolution API', 'wp-whatsapp-evolution'); ?>
							</p>
						</td>
					</tr>
				</table>

				<div class="wpwevo-form-actions">
					<button type="submit" class="button button-primary">
						<?php _e('Salvar Configurações', 'wp-whatsapp-evolution'); ?>
					</button>
					
					<?php if ($api->is_configured()): ?>
						<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpwevo_test_connection'), 'wpwevo_test_connection'); ?>" 
						   class="button button-secondary">
							<?php _e('Testar Conexão', 'wp-whatsapp-evolution'); ?>
						</a>
						
						<?php if ($connection_status): ?>
							<div class="wpwevo-connection-status <?php echo $connection_status['success'] ? 'success' : 'error'; ?>">
								<span class="dashicons <?php echo $connection_status['success'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
								<?php echo esc_html($connection_status['message']); ?>
							</div>
						<?php endif; ?>
					<?php endif; ?>
					
					<span class="spinner"></span>
				</div>

				<div id="wpwevo-validation-result" class="wpwevo-validation-result" style="display: none;"></div>
			</form>
		</div>
		<?php
	}

	private function render_help_tab() {
		?>
		<div class="wpwevo-help">
			<h3><?php _e('Como Configurar', 'wp-whatsapp-evolution'); ?></h3>
			<ol>
				<li><?php _e('Instale a Evolution API em seu servidor', 'wp-whatsapp-evolution'); ?></li>
				<li><?php _e('Crie uma instância na Evolution API', 'wp-whatsapp-evolution'); ?></li>
				<li><?php _e('Gere uma chave de API nas configurações', 'wp-whatsapp-evolution'); ?></li>
				<li><?php _e('Configure os dados acima', 'wp-whatsapp-evolution'); ?></li>
				<li><?php _e('Escaneie o QR Code na Evolution API', 'wp-whatsapp-evolution'); ?></li>
			</ol>

			<h3><?php _e('Links Úteis', 'wp-whatsapp-evolution'); ?></h3>
			<ul>
				<li><a href="https://doc.evolution-api.com" target="_blank"><?php _e('Documentação da Evolution API', 'wp-whatsapp-evolution'); ?></a></li>
				<li><a href="https://github.com/evolution-api/evolution-api" target="_blank"><?php _e('Repositório no GitHub', 'wp-whatsapp-evolution'); ?></a></li>
			</ul>
		</div>
		<?php
	}
} 