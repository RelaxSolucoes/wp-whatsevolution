<?php
namespace WpWhatsAppEvolution;

/**
 * Gerencia o envio de mensagens por status do pedido
 */
class Send_By_Status {
	private static $instance = null;
	private $available_statuses;
	private $menu_title;
	private $page_title;
	private $i18n;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Verifica se o WooCommerce está ativo e compatível
		if (!class_exists('WooCommerce')) {
			return;
		}

		// Declara compatibilidade com HPOS
		add_action('before_woocommerce_init', function() {
			if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			}
		});

		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wpwevo_save_status_messages', [$this, 'handle_save_messages']);
		add_action('wp_ajax_wpwevo_preview_message', [$this, 'handle_preview_message']);
		add_action('woocommerce_order_status_changed', [$this, 'handle_status_change'], 10, 4);
	}

	public function setup() {
		$this->menu_title = __('Envio por Status', 'wp-whatsapp-evolution');
		$this->page_title = __('Envio por Status', 'wp-whatsapp-evolution');
		
		// Carrega os status do WooCommerce de forma segura
		$this->available_statuses = [];
		if (function_exists('wc_get_order_statuses')) {
			$wc_statuses = wc_get_order_statuses();
			foreach ($wc_statuses as $status => $label) {
				$status = str_replace('wc-', '', $status);
				$this->available_statuses[$status] = $label;
			}
		}

		$this->i18n = [
			'saving' => __('Salvando...', 'wp-whatsapp-evolution'),
			'saved' => __('Configurações salvas!', 'wp-whatsapp-evolution'),
			'error' => __('Erro ao salvar: ', 'wp-whatsapp-evolution'),
			'preview' => __('Visualizando...', 'wp-whatsapp-evolution')
		];
	}

	public function add_menu() {
		add_submenu_page(
			'wpwevo-settings',
			$this->page_title,
			$this->menu_title,
			'manage_options',
			'wpwevo-send-by-status',
			[$this, 'render_page']
		);
	}

	public function enqueue_scripts($hook) {
		if ($hook !== 'whatsapp-evolution_page_wpwevo-send-by-status') {
			return;
		}

		wp_enqueue_script(
			'wpwevo-send-by-status',
			WPWEVO_URL . 'assets/js/send-by-status.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-send-by-status', 'wpwevoSendByStatus', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_status_messages'),
			'previewNonce' => wp_create_nonce('wpwevo_preview_message'),
			'i18n' => $this->i18n
		]);
	}

	public function render_page() {
		$settings = get_option('wpwevo_status_messages', []);
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html($this->page_title); ?></h1>

			<div class="wpwevo-send-by-status-form">
				<form id="wpwevo-status-messages-form">
					<?php foreach ($this->available_statuses as $status => $label) : 
						$enabled = isset($settings[$status]['enabled']) ? $settings[$status]['enabled'] : false;
						$message = isset($settings[$status]['message']) ? $settings[$status]['message'] : '';
					?>
						<div class="wpwevo-status-message">
							<div class="wpwevo-status-header">
								<label class="wpwevo-status-toggle">
									<input type="checkbox" name="status[<?php echo esc_attr($status); ?>][enabled]" 
										   value="1" <?php checked($enabled); ?>>
									<span class="wpwevo-status-name"><?php echo esc_html($label); ?></span>
								</label>
								<button type="button" class="button wpwevo-preview-message" 
										data-status="<?php echo esc_attr($status); ?>">
									<?php _e('Visualizar', 'wp-whatsapp-evolution'); ?>
								</button>
							</div>

							<div class="wpwevo-status-content">
								<textarea name="status[<?php echo esc_attr($status); ?>][message]" 
										  class="large-text" rows="4"><?php echo esc_textarea($message); ?></textarea>
								<div class="wpwevo-message-preview" id="preview-<?php echo esc_attr($status); ?>"></div>
							</div>
						</div>
					<?php endforeach; ?>

					<div class="wpwevo-form-actions">
						<button type="submit" class="button button-primary">
							<?php _e('Salvar Configurações', 'wp-whatsapp-evolution'); ?>
						</button>
						<span class="spinner"></span>
					</div>

					<div id="wpwevo-save-result" class="wpwevo-save-result" style="display: none;"></div>
				</form>
			</div>

			<div class="wpwevo-variables-help">
				<h3><?php _e('Variáveis Disponíveis', 'wp-whatsapp-evolution'); ?></h3>
				<p><?php _e('Você pode usar as seguintes variáveis em suas mensagens:', 'wp-whatsapp-evolution'); ?></p>
				<ul>
					<li><code>{customer_name}</code> - <?php _e('Nome do cliente', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{order_id}</code> - <?php _e('Número do pedido', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{order_total}</code> - <?php _e('Valor total do pedido', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{order_status}</code> - <?php _e('Status do pedido', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{payment_method}</code> - <?php _e('Método de pagamento', 'wp-whatsapp-evolution'); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	public function handle_save_messages() {
		check_ajax_referer('wpwevo_status_messages', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$status_messages = [];
		$posted_statuses = isset($_POST['status']) ? $_POST['status'] : [];

		foreach ($this->available_statuses as $status => $label) {
			if (isset($posted_statuses[$status])) {
				$status_messages[$status] = [
					'enabled' => isset($posted_statuses[$status]['enabled']),
					'message' => sanitize_textarea_field($posted_statuses[$status]['message'])
				];
			}
		}

		update_option('wpwevo_status_messages', $status_messages);
		wp_send_json_success(__('Configurações salvas com sucesso!', 'wp-whatsapp-evolution'));
	}

	public function handle_preview_message() {
		check_ajax_referer('wpwevo_preview_message', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		if (!function_exists('wc_get_orders')) {
			wp_send_json_error(__('WooCommerce não está ativo.', 'wp-whatsapp-evolution'));
		}

		$status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

		if (empty($status) || empty($message)) {
			wp_send_json_error(__('Status e mensagem são obrigatórios.', 'wp-whatsapp-evolution'));
		}

		try {
			// Pega o último pedido com este status para preview
			$orders = wc_get_orders([
				'status' => $status,
				'limit' => 1,
				'orderby' => 'date',
				'order' => 'DESC'
			]);

			if (empty($orders)) {
				wp_send_json_error(__('Nenhum pedido encontrado com este status.', 'wp-whatsapp-evolution'));
			}

			$order = $orders[0];
			if (!$order instanceof \WC_Order) {
				wp_send_json_error(__('Pedido inválido.', 'wp-whatsapp-evolution'));
			}

			$preview = wpwevo_replace_vars($message, $order);
			wp_send_json_success($preview);
		} catch (\Exception $e) {
			wp_send_json_error(__('Erro ao gerar preview: ', 'wp-whatsapp-evolution') . $e->getMessage());
		}
	}

	public function handle_status_change($order_id, $old_status, $new_status, $order) {
		if (!function_exists('wc_get_order')) {
			return;
		}

		try {
			$settings = get_option('wpwevo_status_messages', []);
			
			if (!isset($settings[$new_status]) || !$settings[$new_status]['enabled']) {
				return;
			}

			$message = $settings[$new_status]['message'];
			if (empty($message)) {
				return;
			}

			// Verifica se o pedido é válido
			if (!$order instanceof \WC_Order) {
				$order = wc_get_order($order_id);
				if (!$order) {
					return;
				}
			}

			$message = wpwevo_replace_vars($message, $order);
			$billing_phone = $order->get_billing_phone();
			
			if (empty($billing_phone)) {
				return;
			}

			$api = Api_Connection::get_instance();
			$api->send_message($billing_phone, $message);
		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao enviar mensagem de status: ' . $e->getMessage());
		}
	}
} 