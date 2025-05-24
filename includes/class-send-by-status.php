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
		
		// Hook para novos pedidos - prioridade mais alta para garantir que seja executado antes da mudança de status
		add_action('woocommerce_new_order', [$this, 'handle_new_order'], 5, 1);
		
		// Hook para mudança de status
		add_action('woocommerce_order_status_changed', [$this, 'handle_status_change'], 10, 4);

		// Log para debug
		wpwevo_log_error('Send_By_Status hooks initialized');
	}

	/**
	 * Retorna as mensagens padrão para cada status
	 */
	private function get_default_messages() {
		return [
			'pending' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, recebemos seu pedido #{order_id} no valor de {order_total}. Estamos aguardando a confirmação do pagamento via {payment_method} para dar continuidade. Você pode acompanhar seu pedido em: {order_url}. Obrigado por comprar conosco!', 'wp-whatsapp-evolution')
			],
			'on-hold' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, seu pedido #{order_id} está aguardando confirmação. Assim que confirmado, iniciaremos o processamento. Para mais informações, acesse: {order_url}', 'wp-whatsapp-evolution')
			],
			'processing' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, seu pedido #{order_id} foi aprovado e já estamos preparando tudo para o envio via {shipping_method}. Endereço de entrega: {shipping_address_line_1}, {shipping_city}-{shipping_state}. Em breve você receberá atualizações sobre o envio. Obrigado pela confiança!', 'wp-whatsapp-evolution')
			],
			'completed' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, seu pedido #{order_id} foi concluído com sucesso! Esperamos que tenha gostado dos produtos. Para acompanhar outros pedidos, acesse: {order_url}. Agradecemos a preferência!', 'wp-whatsapp-evolution')
			],
			'cancelled' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, seu pedido #{order_id} foi cancelado. Se houver alguma dúvida, acesse {order_url} ou entre em contato conosco.', 'wp-whatsapp-evolution')
			],
			'refunded' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, o reembolso do seu pedido #{order_id} no valor de {order_total} foi processado. O valor será creditado via {payment_method}.', 'wp-whatsapp-evolution')
			],
			'failed' => [
				'enabled' => true,
				'message' => __('Olá {customer_name}, infelizmente houve um problema com seu pedido #{order_id}. Por favor, acesse {order_url} para mais detalhes ou entre em contato conosco.', 'wp-whatsapp-evolution')
			]
		];
	}

	/**
	 * Substitui as variáveis na mensagem pelos dados do pedido
	 */
	private function replace_variables($message, $order) {
		if (!$order instanceof \WC_Order) {
			return $message;
		}

		// Formata o valor total sem HTML e decodifica entidades
		$total = html_entity_decode(strip_tags(wc_price($order->get_total())));

		// Obtém informações dos produtos
		$items = $order->get_items();
		$first_product = '';
		$all_products = [];
		
		foreach ($items as $item) {
			if (empty($first_product)) {
				$first_product = $item->get_name();
			}
			$all_products[] = $item->get_name();
		}

		$variables = [
			'{customer_name}' => $order->get_billing_first_name(),
			'{order_id}' => $order->get_order_number(),
			'{order_total}' => $total,
			'{payment_method}' => $order->get_payment_method_title(),
			'{shipping_method}' => $order->get_shipping_method(),
			'{order_url}' => $order->get_view_order_url(),
			'{payment_url}' => $order->get_checkout_payment_url(),
			'{first_product}' => $first_product,
			'{all_products}' => implode(', ', $all_products),
			'{shipping_name}' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
			'{shipping_address_line_1}' => $order->get_shipping_address_1(),
			'{shipping_address_line_2}' => $order->get_shipping_address_2(),
			'{shipping_city}' => $order->get_shipping_city(),
			'{shipping_state}' => $order->get_shipping_state(),
			'{shipping_postcode}' => $order->get_shipping_postcode()
		];

		foreach ($variables as $key => $value) {
			$message = str_replace($key, $value, $message);
		}

		return $message;
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
			'preview' => __('Visualizando...', 'wp-whatsapp-evolution'),
			'emptyMessage' => __('Por favor, digite uma mensagem.', 'wp-whatsapp-evolution'),
			'networkError' => __('Erro de conexão. Tente novamente.', 'wp-whatsapp-evolution'),
			'confirmReset' => __('Deseja restaurar a mensagem padrão?', 'wp-whatsapp-evolution')
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
		// Carrega as configurações salvas ou usa as padrões
		$saved_settings = get_option('wpwevo_status_messages', []);
		$default_messages = $this->get_default_messages();
		
		// Mescla as configurações salvas com as padrões
		$settings = [];
		foreach ($this->available_statuses as $status => $label) {
			if (isset($saved_settings[$status])) {
				$settings[$status] = $saved_settings[$status];
			} elseif (isset($default_messages[$status])) {
				$settings[$status] = $default_messages[$status];
			} else {
				$settings[$status] = [
					'enabled' => false,
					'message' => ''
				];
			}
		}
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html($this->page_title); ?></h1>

			<div class="wpwevo-send-by-status-form">
				<form id="wpwevo-status-messages-form" method="post">
					<?php 
					foreach ($this->available_statuses as $status => $label) : 
						$default_message = isset($default_messages[$status]) ? $default_messages[$status]['message'] : '';
						$enabled = isset($settings[$status]['enabled']) ? $settings[$status]['enabled'] : false;
						$message = isset($settings[$status]['message']) ? $settings[$status]['message'] : $default_message;
					?>
						<div class="wpwevo-status-message">
							<div class="wpwevo-status-header">
								<label class="wpwevo-status-toggle">
									<input type="checkbox" 
										   name="status[<?php echo esc_attr($status); ?>][enabled]" 
										   value="1" 
										   <?php checked($enabled, true); ?>>
									<span class="wpwevo-status-name"><?php echo esc_html($label); ?></span>
								</label>
								<button type="button" class="button wpwevo-reset-message" 
										data-default="<?php echo esc_attr($default_message); ?>">
									<?php _e('Restaurar Padrão', 'wp-whatsapp-evolution'); ?>
								</button>
							</div>

							<div class="wpwevo-status-content">
								<textarea name="status[<?php echo esc_attr($status); ?>][message]" 
										  class="large-text" 
										  rows="4"><?php echo esc_textarea($message); ?></textarea>
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
					<li><code>{payment_method}</code> - <?php _e('Método de pagamento', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_method}</code> - <?php _e('Método de envio', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{order_url}</code> - <?php _e('URL do pedido', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{payment_url}</code> - <?php _e('URL de pagamento do pedido', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{first_product}</code> - <?php _e('Nome do primeiro produto', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{all_products}</code> - <?php _e('Lista de todos os produtos', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_name}</code> - <?php _e('Nome do destinatário', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_address_line_1}</code> - <?php _e('Endereço de entrega (linha 1)', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_address_line_2}</code> - <?php _e('Endereço de entrega (linha 2)', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_city}</code> - <?php _e('Cidade de entrega', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_state}</code> - <?php _e('Estado de entrega', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{shipping_postcode}</code> - <?php _e('CEP de entrega', 'wp-whatsapp-evolution'); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	public function handle_save_messages() {
		// Verifica o nonce
		check_ajax_referer('wpwevo_status_messages', 'nonce');

		// Verifica permissões
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permissão negada.', 'wp-whatsapp-evolution')]);
			return;
		}

		$posted_statuses = isset($_POST['status']) ? $_POST['status'] : [];
		$status_messages = [];

		// Processa cada status disponível
		foreach ($this->available_statuses as $status => $label) {
			// Garante que todos os status sejam incluídos, mesmo que não enviados
			$status_messages[$status] = [
				'enabled' => isset($posted_statuses[$status]['enabled']) && 
					        filter_var($posted_statuses[$status]['enabled'], FILTER_VALIDATE_BOOLEAN),
				'message' => isset($posted_statuses[$status]['message']) ? 
					        wp_kses_post(stripslashes($posted_statuses[$status]['message'])) : ''
			];
		}

		// Salva as configurações
		$saved = update_option('wpwevo_status_messages', $status_messages);

		if ($saved) {
			wp_send_json_success(['message' => __('Configurações salvas com sucesso!', 'wp-whatsapp-evolution')]);
		} else {
			// Verifica se os dados são diferentes dos já salvos
			$current_data = get_option('wpwevo_status_messages', []);
			if ($current_data == $status_messages) {
				wp_send_json_success(['message' => __('Configurações salvas com sucesso!', 'wp-whatsapp-evolution')]);
			} else {
				wp_send_json_error(['message' => __('Erro ao salvar as configurações. Tente novamente.', 'wp-whatsapp-evolution')]);
			}
		}
	}

	public function handle_preview_message() {
		check_ajax_referer('wpwevo_preview_message', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

		if (empty($message)) {
			wp_send_json_error(__('Por favor, digite uma mensagem para visualizar.', 'wp-whatsapp-evolution'));
			return;
		}

		// Usa dados de exemplo para preview
		$preview = str_replace(
			[
				'{customer_name}',
				'{order_id}',
				'{order_total}',
				'{payment_method}',
				'{shipping_method}',
				'{order_url}',
				'{shipping_name}',
				'{shipping_address_line_1}',
				'{shipping_address_line_2}',
				'{shipping_city}',
				'{shipping_state}',
				'{shipping_postcode}'
			],
			[
				'João Silva',
				'12345',
				'R$ 150,00',
				'Cartão de Crédito',
				'PAC',
				'https://relaxsolucoes.online/pedido/12345',
				'João Silva',
				'Rua das Flores, 123',
				'Apto 45',
				'São Paulo',
				'SP',
				'01234-567'
			],
			$message
		);

		wp_send_json_success($preview);
	}

	/**
	 * Manipula a criação de novos pedidos
	 */
	public function handle_new_order($order_id) {
		if (!function_exists('wc_get_order')) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}

		$status = $order->get_status();
		$settings = get_option('wpwevo_status_messages', []);

		// Verifica se o status está ativo e tem mensagem
		if (!isset($settings[$status]) || empty($settings[$status]['enabled'])) {
			return;
		}

		$message = $settings[$status]['message'];
		if (empty($message)) {
			return;
		}

		$billing_phone = $order->get_billing_phone();
		if (empty($billing_phone)) {
			return;
		}

		// Formata a mensagem com os dados do pedido
		$message = $this->replace_variables($message, $order);

		// Envia a mensagem
		$api = Api_Connection::get_instance();
		if (!$api->is_configured()) {
			return;
		}

		$api->send_message($billing_phone, $message);
	}

	/**
	 * Manipula a mudança de status dos pedidos
	 */
	public function handle_status_change($order_id, $old_status, $new_status, $order) {
		if (!$order instanceof \WC_Order) {
			$order = wc_get_order($order_id);
			if (!$order) {
				return;
			}
		}

		$settings = get_option('wpwevo_status_messages', []);

		// Verifica se o novo status está ativo e tem mensagem
		if (!isset($settings[$new_status]) || empty($settings[$new_status]['enabled'])) {
			return;
		}

		$message = $settings[$new_status]['message'];
		if (empty($message)) {
			return;
		}

		$billing_phone = $order->get_billing_phone();
		if (empty($billing_phone)) {
			return;
		}

		// Formata a mensagem com os dados do pedido
		$message = $this->replace_variables($message, $order);

		// Envia a mensagem
		$api = Api_Connection::get_instance();
		if (!$api->is_configured()) {
			return;
		}

		$api->send_message($billing_phone, $message);
	}
} 