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
		check_ajax_referer('wpwevo_validate_settings', 'nonce');

		$api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
		$api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
		$instance = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';

		if (empty($api_url) || empty($api_key) || empty($instance)) {
			wp_send_json_error(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
		}

		update_option('wpwevo_api_url', $api_url);
		update_option('wpwevo_api_key', $api_key);
		update_option('wpwevo_instance', $instance);

		$api = Api_Connection::get_instance();
		$result = $api->check_connection();

		wp_send_json($result);
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
		?>
		<div class="wpwevo-connection-form">
			<form method="post" action="options.php" id="wpwevo-settings-form">
				<?php settings_fields('wpwevo_settings'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e('URL da API', 'wp-whatsapp-evolution'); ?></th>
						<td>
							<input type="url" name="wpwevo_api_url" 
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
							<input type="text" name="wpwevo_api_key" 
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
							<input type="text" name="wpwevo_instance" 
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