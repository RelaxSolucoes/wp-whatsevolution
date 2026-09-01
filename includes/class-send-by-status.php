<?php
namespace WpWhatsAppEvolution;

/**
 * Gerencia o envio de mensagens por status do pedido
 */
class Send_By_Status {
	private static $instance = null;
	private static $hooks_initialized = false;
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

		// Evita inicialização múltipla dos hooks
		if (self::$hooks_initialized) {
			return;
		}
		self::$hooks_initialized = true;

		// Define as propriedades ANTES dos hooks
        $this->menu_title = __('Envio por Status', 'wp-whatsevolution');
        $this->page_title = __('Envio por Status', 'wp-whatsevolution');
		
		$this->i18n = [
            'saving' => __('Salvando...', 'wp-whatsevolution'),
            'saved' => __('Configurações salvas com sucesso!', 'wp-whatsevolution'),
            'error' => __('Erro ao salvar: ', 'wp-whatsevolution'),
            'preview' => __('Visualizando...', 'wp-whatsevolution'),
            'networkError' => __('Erro de conexão. Tente novamente.', 'wp-whatsevolution'),
		];

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
		
		// Hook para mudança de status
		add_action('woocommerce_order_status_changed', [$this, 'handle_status_change'], 10, 4);

		// Notificação ao admin: callback próprio, no mesmo hook. As duas mensagens
		// são sequenciais porque a API não envia as duas numa chamada só, mas são
		// logicamente independentes — o admin precisa ser avisado mesmo que o
		// pedido não tenha telefone, que o número do cliente esteja errado ou que
		// a mensagem ao cliente esteja desligada naquele status. Até a v1.6.0 este
		// bloco morava dentro do `else` do envio ao cliente e herdava todos os
		// guards dele, então na prática só saía quando o cliente recebia primeiro.
		// Prioridade 20 mantém a ordem de hoje (cliente, depois admin) sem criar
		// dependência entre os dois.
		//
		// Continua exclusivo de woocommerce_order_status_changed, de propósito:
		// enganchar woocommerce_new_order faria o pedido de quem usa gateway
		// disparar duas vezes (nasce num status, o gateway muda em seguida).
		add_action('woocommerce_order_status_changed', [$this, 'handle_admin_notification'], 20, 4);
	}

	/**
	 * Retorna as mensagens padrão para cada status
	 */
	private function get_default_messages() {
		return [
			'pending' => [
				'enabled' => true,
				'message' => __('🛒 *Pedido Recebido!*

Olá {customer_name}!

Recebemos seu pedido #{order_id} no valor de *{order_total}*.

💳 *Pagamento:* {payment_method}
📋 Estamos aguardando a confirmação do pagamento para dar continuidade.

🔗 Acompanhe seu pedido em:
{order_url}

Obrigado por comprar conosco! 😊', 'wp-whatsevolution')
			],
			'on-hold' => [
				'enabled' => true,
				'message' => __('🛒 *Pedido Recebido!*

Olá {customer_name}!

Recebemos seu pedido #{order_id} no valor de *{order_total}*.

💳 *Pagamento:* {payment_method}
📋 Estamos aguardando a confirmação do pagamento para dar continuidade.

🔗 Acompanhe seu pedido em:
{order_url}

Obrigado por comprar conosco! 😊', 'wp-whatsevolution')
			],
			'processing' => [
				'enabled' => true,
				'message' => __('✅ *Pedido Aprovado!*

Olá {customer_name}!

Seu pedido #{order_id} foi aprovado e já estamos preparando tudo! 📦

🚚 *Envio:* {shipping_method}
📍 *Endereço:* {shipping_address_full}

Obrigado pela confiança! 🙏', 'wp-whatsevolution')
			],
			'completed' => [
				'enabled' => true,
				'message' => __('🎉 *Pedido Concluído!*

Olá {customer_name}!

Seu pedido #{order_id} foi concluído com sucesso!

Esperamos que tenha gostado dos produtos! ⭐

🔗 Acompanhe outros pedidos em:
{order_url}

Agradecemos a preferência! 💚', 'wp-whatsevolution')
			],
			'cancelled' => [
				'enabled' => true,
				'message' => __('❌ *Pedido Cancelado*

Olá {customer_name},

Seu pedido #{order_id} foi cancelado.

❓ Se houver alguma dúvida, acesse:
{order_url}

Ou entre em contato conosco! 📞', 'wp-whatsevolution')
			],
			'refunded' => [
				'enabled' => true,
				'message' => __('💰 *Reembolso Processado*

Olá {customer_name},

O reembolso do seu pedido #{order_id} no valor de *{order_total}* foi processado! ✅

💳 O valor será creditado via {payment_method}.

Em breve aparecerá na sua conta! ⏰', 'wp-whatsevolution')
			],
			'failed' => [
				'enabled' => true,
				'message' => __('⚠️ *Problema no Pedido*

Olá {customer_name},

Infelizmente houve um problema com seu pedido #{order_id}.

🔗 Acesse para mais detalhes:
{order_url}

📞 Ou entre em contato conosco para resolvermos juntos!', 'wp-whatsevolution')
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

		// Sistema de fallback para endereços de envio
		// Se o endereço de envio estiver vazio, usa o de cobrança
		$shipping_address_1 = $order->get_shipping_address_1();
		$shipping_city = $order->get_shipping_city();
		$shipping_state = $order->get_shipping_state();
		$shipping_postcode = $order->get_shipping_postcode();
		$shipping_name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();

		// Se o endereço de envio estiver vazio, usa o de cobrança como fallback
		if (empty($shipping_address_1)) {
			$shipping_address_1 = $order->get_billing_address_1();
			$shipping_city = $order->get_billing_city();
			$shipping_state = $order->get_billing_state();
			$shipping_postcode = $order->get_billing_postcode();
			$shipping_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		// Captura campos customizados do Brazilian Market (se existirem)
		$shipping_number = $order->get_meta('_shipping_number');
		$shipping_neighborhood = $order->get_meta('_shipping_neighborhood');

		// Monta o endereço completo incluindo campos customizados
		$shipping_address_parts = [];
		if (!empty($shipping_address_1)) $shipping_address_parts[] = $shipping_address_1;
		if (!empty($shipping_number)) $shipping_address_parts[] = $shipping_number;
		if (!empty($shipping_neighborhood)) $shipping_address_parts[] = $shipping_neighborhood;
		if (!empty($shipping_city)) $shipping_address_parts[] = $shipping_city;
		if (!empty($shipping_state)) $shipping_address_parts[] = $shipping_state;
		if (!empty($shipping_postcode)) $shipping_address_parts[] = $shipping_postcode;
		
		$shipping_address_full = implode(', ', $shipping_address_parts);

		$variables = [
			// Cliente/Customer
			'{customer_name}' => $order->get_billing_first_name(),
			'{customer_email}' => $order->get_billing_email(),
			'{customer_phone}' => $order->get_billing_phone(),
			
			// Pedido/Order
			'{order_id}' => $order->get_order_number(),
			'{order_status}' => wc_get_order_status_name($order->get_status()),
			'{order_total}' => $total,
			'{order_date}' => date_i18n('d/m/Y', strtotime($order->get_date_created())),
			'{payment_method}' => $order->get_payment_method_title(),
			'{shipping_method}' => $order->get_shipping_method(),
			'{order_url}' => $order->get_view_order_url(),
			// Link do painel, para as mensagens do admin. {order_url} aponta para
			// a área do cliente e o admin não consegue abrir.
			'{admin_order_url}' => $order->get_edit_order_url(),
			'{payment_url}' => $order->get_checkout_payment_url(),
			
			// Produtos
			'{first_product}' => $first_product,
			'{all_products}' => implode(', ', $all_products),
			
			// Dados de Cobrança
			'{billing_first_name}' => $order->get_billing_first_name(),
			'{billing_last_name}' => $order->get_billing_last_name(),
			'{billing_company}' => $order->get_billing_company(),
			'{billing_address_line_1}' => $order->get_billing_address_1(),
			'{billing_address_line_2}' => $order->get_billing_address_2(),
			'{billing_city}' => $order->get_billing_city(),
			'{billing_state}' => $order->get_billing_state(),
			'{billing_postcode}' => $order->get_billing_postcode(),
			'{billing_country}' => $order->get_billing_country(),
			'{billing_address_full}' => $this->build_address_full(
				$order->get_billing_address_1(),
				$order->get_meta('_billing_number'),
				$order->get_meta('_billing_neighborhood'),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode()
			),
			
			// Dados de Envio
			'{shipping_name}' => $shipping_name,
			'{shipping_company}' => $order->get_shipping_company(),
			'{shipping_address_line_1}' => $shipping_address_1,
			'{shipping_address_line_2}' => $order->get_shipping_address_2(),
			'{shipping_city}' => $shipping_city,
			'{shipping_state}' => $shipping_state,
			'{shipping_postcode}' => $order->get_shipping_postcode(),
			'{shipping_country}' => $order->get_shipping_country(),
			'{shipping_address_full}' => $shipping_address_full,

			// Rastreamento (Melhor Envio e outros)
			'{tracking_code}' => $this->get_tracking_code($order),
			'{tracking_url}' => $this->get_tracking_url($this->get_tracking_code($order)),
			'{shipping_company}' => $this->get_shipping_company($order),

			// Data do último pedido (alias para order_date)
			'{last_order_date}' => date_i18n('d/m/Y', strtotime($order->get_date_created()))
		];

		foreach ($variables as $key => $value) {
			$message = str_replace($key, $value, $message);
		}

		return $message;
	}

	/**
	 * Obtém o código de rastreio do Melhor Envio
	 * Suporta Melhor Envio e outros plugins brasileiros
	 */
	private function get_tracking_code($order) {
		// 1. Melhor Envio (mais comum no Brasil)
		$tracking = $order->get_meta('melhorenvio_tracking');
		if (!empty($tracking)) {
			return $tracking;
		}

		// 2. WooCommerce Shipment Tracking (plugin oficial)
		$tracking_items = $order->get_meta('_wc_shipment_tracking_items');
		if (!empty($tracking_items) && is_array($tracking_items)) {
			$first_tracking = reset($tracking_items);
			if (!empty($first_tracking['tracking_number'])) {
				return $first_tracking['tracking_number'];
			}
		}

		// 3. Plugins genéricos
		$generic_tracking = $order->get_meta('_tracking_code');
		if (!empty($generic_tracking)) {
			return $generic_tracking;
		}

		$tracking_number = $order->get_meta('_tracking_number');
		if (!empty($tracking_number)) {
			return $tracking_number;
		}

		return '';
	}

	/**
	 * Gera URL de rastreamento usando Melhor Rastreio
	 */
	private function get_tracking_url($tracking_code) {
		if (empty($tracking_code)) {
			return '';
		}

		// Se for código dos Correios (formato BR ou padrão brasileiro)
		if (preg_match('/^[A-Z]{2}[0-9]{9}[A-Z]{2}$/', $tracking_code)) {
			return 'https://melhorrastreio.com.br/app/correios/' . $tracking_code;
		}

		return '';
	}

	/**
	 * Obtém o nome da transportadora
	 */
	private function get_shipping_company($order) {
		// Tenta pegar do WooCommerce Shipment Tracking
		$tracking_items = $order->get_meta('_wc_shipment_tracking_items');
		if (!empty($tracking_items) && is_array($tracking_items)) {
			$first_tracking = reset($tracking_items);
			if (!empty($first_tracking['tracking_provider'])) {
				return $first_tracking['tracking_provider'];
			}
		}

		// Tenta pegar do método de envio padrão
		$shipping_method = $order->get_shipping_method();
		if (!empty($shipping_method)) {
			return $shipping_method;
		}

		return '';
	}

	/**
	 * Constrói endereço completo incluindo campos customizados do Brazilian Market
	 * Funciona com ou sem o plugin Brazilian Market
	 */
	private function build_address_full($address_1, $number, $neighborhood, $city, $state, $postcode) {
		$address_parts = [];
		
		// Endereço principal
		if (!empty($address_1)) $address_parts[] = $address_1;
		
		// Campos customizados do Brazilian Market (se existirem)
		if (!empty($number)) $address_parts[] = $number;
		if (!empty($neighborhood)) $address_parts[] = $neighborhood;
		
		// Campos padrão do WooCommerce
		if (!empty($city)) $address_parts[] = $city;
		if (!empty($state)) $address_parts[] = $state;
		if (!empty($postcode)) $address_parts[] = $postcode;
		
		return implode(', ', $address_parts);
	}

	/**
	 * Inicializa as mensagens padrão se não existirem configurações salvas
	 */
	private function initialize_default_messages() {
		$saved_settings = get_option('wpwevo_status_messages', []);
		
		// Se não há configurações salvas, inicializa com as padrões
		if (empty($saved_settings)) {
			$default_messages = $this->get_default_messages();
			$initial_settings = [];
			
			// Carrega os status do WooCommerce
			$wc_statuses = wc_get_order_statuses();
			foreach ($wc_statuses as $status => $label) {
				$clean_status = str_replace('wc-', '', $status);
				
				// Se existe mensagem padrão para este status, usa ela
				if (isset($default_messages[$clean_status])) {
					$initial_settings[$clean_status] = $default_messages[$clean_status];
				} else {
					// Se não existe padrão, cria uma configuração vazia
					$initial_settings[$clean_status] = [
						'enabled' => false,
						'message' => ''
					];
				}
			}
			
			// Salva as configurações iniciais
			update_option('wpwevo_status_messages', $initial_settings);
			
			return $initial_settings;
		}
		
		return $saved_settings;
	}

	public function setup() {
		// Carrega os status do WooCommerce de forma segura
		$this->available_statuses = [];
		if (function_exists('wc_get_order_statuses')) {
			$wc_statuses = wc_get_order_statuses();
			foreach ($wc_statuses as $status => $label) {
				$status = str_replace('wc-', '', $status);
				$this->available_statuses[$status] = $label;
			}
		}
		
		// Inicializa mensagens padrão se necessário
		$this->initialize_default_messages();
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
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		wp_enqueue_script(
			'wpwevo-send-by-status',
			WPWEVO_URL . 'assets/js/send-by-status.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		$localize_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpwevo_status_messages'),
            'previewNonce' => wp_create_nonce('wpwevo_preview_message'),
			'i18n' => $this->i18n
		];

		wp_localize_script('wpwevo-send-by-status', 'wpwevoSendByStatus', $localize_data);
	}

	public function render_page() {
		// Inicializa mensagens padrão se necessário e carrega as configurações
		$settings = $this->initialize_default_messages();
		$default_messages = $this->get_default_messages();
		
		// Mescla as configurações salvas com as padrões para garantir que todos os status estejam presentes
		foreach ($this->available_statuses as $status => $label) {
			if (!isset($settings[$status])) {
				if (isset($default_messages[$status])) {
					$settings[$status] = $default_messages[$status];
				} else {
					$settings[$status] = [
						'enabled' => false,
						'message' => ''
					];
				}
			}
		}
		?>
		<div class="wrap">
			<!-- Header com Gradiente -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 25px; margin: 20px 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
				<div style="display: flex; align-items: center; color: white;">
					<div style="background: rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 20px;">
						📊
					</div>
					<div>
						<h1 style="margin: 0; color: white; font-size: 28px; font-weight: 600;"><?php echo esc_html($this->page_title); ?></h1>
						<p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">Configure mensagens automáticas por status do pedido</p>
					</div>
				</div>
			</div>

			<!-- Layout em Grid -->
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
				
				<!-- Coluna Esquerda: Configurações dos Status -->
				<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 25px;">
						<div style="display: flex; align-items: center; margin-bottom: 20px;">
							<div style="background: #4facfe; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">⚙️</div>
							<h2 style="margin: 0; color: #2d3748; font-size: 20px;">Configurar Mensagens por Status</h2>
						</div>

						<form id="wpwevo-status-messages-form" method="post" style="space-y: 15px;">
							<?php 
							$colors = [
								'#667eea', '#f093fb', '#4facfe', '#a8edea', '#ffecd2', 
								'#ff9a9e', '#96e6a1', '#fbc2eb', '#84fab0', '#667eea'
							];
							$color_index = 0;
							
							foreach ($this->available_statuses as $status => $label) : 
								$real_default_messages = $this->get_default_messages();
								$default_message = isset($real_default_messages[$status]) ? $real_default_messages[$status]['message'] : '';
								$enabled = isset($settings[$status]['enabled']) ? $settings[$status]['enabled'] : false;
								$message = isset($settings[$status]['message']) ? $settings[$status]['message'] : $default_message;
								$color = $colors[$color_index % count($colors)];
								$color_index++;
							?>
								<div style="background: #f7fafc; border-radius: 8px; padding: 20px; margin-bottom: 15px; border-left: 4px solid <?php echo $color; ?>;">
									<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
										<label style="display: flex; align-items: center; cursor: pointer; font-weight: 600; color: #2d3748;">
											<input type="checkbox"
												   name="status[<?php echo esc_attr($status); ?>][enabled]"
												   value="1"
												   <?php checked($enabled, true); ?>
												   style="margin-right: 10px; transform: scale(1.2);">
											<span style="font-size: 16px;"><?php echo esc_html($label); ?></span>
										</label>
										<div style="display: flex; gap: 10px; align-items: center;">
											<!-- NOVO: Checkbox Notificar Admin -->
											<label style="display: flex; align-items: center; cursor: pointer; font-size: 14px; color: #4a5568;">
												<input type="checkbox"
													   class="wpwevo-notify-admin-toggle"
													   name="status[<?php echo esc_attr($status); ?>][notify_admin]"
													   value="1"
													   data-status="<?php echo esc_attr($status); ?>"
													   <?php checked(isset($settings[$status]['notify_admin']) && $settings[$status]['notify_admin'], true); ?>
													   style="margin-right: 5px; transform: scale(1.1);">
												<span>🔔 Notificar Admin</span>
											</label>

											<button type="button"
													class="wpwevo-reset-message"
													data-default="<?php echo esc_attr($default_message); ?>"
													data-status="<?php echo esc_attr($status); ?>"
													style="background: <?php echo $color; ?>; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px;">
												🔄 Restaurar Padrão
											</button>
										</div>
									</div>

									<!-- Mensagem para CLIENTE -->
									<div style="margin-bottom: 10px;">
										<label style="font-size: 12px; color: #4a5568; margin-bottom: 5px; display: block; font-weight: 500;">
											📱 Mensagem para o Cliente:
										</label>
										<textarea name="status[<?php echo esc_attr($status); ?>][message]"
												  class="wpwevo-auto-resize-textarea"
												  rows="1"
												  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.4; resize: vertical; min-height: 50px; overflow: hidden;"
												  placeholder="Digite a mensagem para este status..."><?php echo esc_textarea($message); ?></textarea>
									</div>

									<!-- NOVO: Mensagem para ADMIN (oculta por padrão) -->
									<div class="wpwevo-admin-message-container"
										 data-status="<?php echo esc_attr($status); ?>"
										 style="display: <?php echo (isset($settings[$status]['notify_admin']) && $settings[$status]['notify_admin']) ? 'block' : 'none'; ?>;
												margin-top: 15px;
												padding-top: 15px;
												border-top: 2px dashed #e2e8f0;">
										<label style="font-size: 12px; color: #667eea; margin-bottom: 5px; display: block; font-weight: 600;">
											🔔 Mensagem para o Admin:
										</label>
										<textarea name="status[<?php echo esc_attr($status); ?>][admin_message]"
												  class="wpwevo-auto-resize-textarea"
												  rows="1"
												  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.4; resize: vertical; min-height: 50px; overflow: hidden;"
												  placeholder="Digite a mensagem que o admin receberá quando este status for ativado..."><?php echo esc_textarea(isset($settings[$status]['admin_message']) ? $settings[$status]['admin_message'] : ''); ?></textarea>
										<p style="margin: 8px 0 0 0; color: #718096; font-size: 12px;">
											Em branco, é usado o modelo padrão. A notificação ao admin é independente da mensagem ao cliente:
											sai mesmo com o checkbox de ativar desmarcado, com o pedido sem telefone ou com o envio ao cliente falhando.
											Use <code>{admin_order_url}</code> para o link do painel.
										</p>
									</div>
								</div>
							<?php endforeach; ?>

							<div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;">
								<button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 15px 30px; font-size: 16px; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); font-weight: 600;">
									💾 Salvar Configurações
								</button>
								<span class="spinner" style="float: none; margin: 0;"></span>
							</div>

							<div id="wpwevo-save-result" style="display: none; margin-top: 15px; padding: 12px; border-radius: 6px;"></div>
						</form>
					</div>
				</div>

				<!-- Coluna Direita: Variáveis Disponíveis -->
				<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(168, 237, 234, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
						<div style="display: flex; align-items: center; margin-bottom: 15px;">
							<div style="background: #a8edea; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🏷️</div>
							<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Variáveis Disponíveis</h3>
						</div>
						
						<p style="color: #4a5568; font-size: 14px; margin-bottom: 15px;">📝 Copie e cole as variáveis nas mensagens:</p>
						
						<div style="display: grid; gap: 8px;">
							<?php 
							$variables = [
								// Cliente/Customer
								'{customer_name}' => 'Nome do cliente',
								'{customer_email}' => 'Email do cliente',
								'{customer_phone}' => 'Telefone do cliente',
								
								// Pedido/Order
								'{order_id}' => 'Número do pedido',
								'{order_status}' => 'Status do pedido',
								'{order_total}' => 'Valor total do pedido',
								'{order_date}' => 'Data do pedido',
								'{payment_method}' => 'Método de pagamento',
								'{shipping_method}' => 'Método de envio',
								'{order_url}' => 'URL do pedido (área do cliente)',
								'{admin_order_url}' => 'URL do pedido no painel (mensagens do admin)',
								'{payment_url}' => 'URL de pagamento',
								
								// Produtos
								'{first_product}' => 'Primeiro produto',
								'{all_products}' => 'Todos os produtos',
								
								// Dados de Cobrança
								'{billing_first_name}' => 'Nome de cobrança',
								'{billing_last_name}' => 'Sobrenome de cobrança',
								'{billing_company}' => 'Empresa de cobrança',
								'{billing_address_line_1}' => 'Endereço cobrança linha 1',
								'{billing_address_line_2}' => 'Endereço cobrança linha 2',
								'{billing_city}' => 'Cidade de cobrança',
								'{billing_state}' => 'Estado de cobrança',
								'{billing_postcode}' => 'CEP de cobrança',
								'{billing_country}' => 'País de cobrança',
								'{billing_address_full}' => 'Endereço completo de cobrança',
								
								// Dados de Envio
								'{shipping_name}' => 'Nome destinatário',
								'{shipping_company}' => 'Empresa de envio',
								'{shipping_address_line_1}' => 'Endereço entrega linha 1',
								'{shipping_address_line_2}' => 'Endereço entrega linha 2',
								'{shipping_city}' => 'Cidade de entrega',
								'{shipping_state}' => 'Estado de entrega',
								'{shipping_postcode}' => 'CEP de entrega',
								'{shipping_country}' => 'País de entrega',
								'{shipping_address_full}' => 'Endereço completo de entrega',

								// Rastreamento (Melhor Envio / Correios)
								'{tracking_code}' => 'Código de rastreio',
								'{tracking_url}' => 'Link de rastreamento',

								// Data (compatibilidade)
								'{last_order_date}' => 'Data do último pedido'
							];
							
							foreach ($variables as $var => $desc) : ?>
								<div style="background: #e6fffa; padding: 10px; border-radius: 6px; border: 1px solid #b2f5ea;">
									<div style="margin-bottom: 4px;">
										<code style="background: #319795; color: white; padding: 3px 6px; border-radius: 4px; font-size: 11px; user-select: all; cursor: text;"><?php echo esc_html($var); ?></code>
									</div>
									<div style="color: #2d3748; font-size: 12px; line-height: 1.3; user-select: none;"><?php echo esc_html($desc); ?></div>
								</div>
							<?php endforeach; ?>
						</div>

						<!-- Dicas de Uso -->
						<div style="margin-top: 20px; padding: 15px; background: #f0fff4; border-radius: 8px; border-left: 4px solid #48bb78;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">💡 Dicas de Uso</h4>
							<ul style="margin: 0; padding-left: 15px; color: #4a5568; font-size: 12px; line-height: 1.4;">
								<li>Use emojis para tornar as mensagens mais atrativas</li>
								<li>Personalize conforme seu tipo de negócio</li>
								<li>Teste as mensagens antes de ativar</li>
								<li>Mantenha as mensagens concisas e claras</li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Card de Exemplo em Largura Total -->
			<div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(255, 236, 210, 0.2); overflow: hidden; margin-top: 20px;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #ffecd2; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">📱</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Exemplo de Mensagem Processando</h3>
					</div>
					
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #fcb69f;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">📝 Template</h4>
							<pre style="background: #2d3748; color: #e2e8f0; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.4; margin: 0; white-space: pre-wrap;">Olá {customer_name}, seu pedido #{order_id} foi aprovado e já estamos preparando tudo para o envio via {shipping_method}. 

Data do pedido: {last_order_date}
Endereço: {shipping_address_full}

Valor: {order_total}
�� {order_url}</pre>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #fcb69f;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">📲 Resultado Final</h4>
							<div style="background: #25d366; color: white; padding: 12px; border-radius: 6px; font-size: 13px; line-height: 1.4; font-family: system-ui;">
								Olá João Silva, seu pedido #12345 foi aprovado e já estamos preparando tudo para o envio via PAC.<br><br>
								Data do pedido: 15/12/2024<br><br>
								Endereço: Rua das Flores, 123, São Paulo-SP<br><br>
								Valor: R$ 150,00<br>
								🎯 https://seusite.com.br/pedido/12345
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Todo JavaScript agora está no arquivo send-by-status.js -->
		<?php
	}

	public function handle_save_messages() {
		// Verifica o nonce
		check_ajax_referer('wpwevo_status_messages', 'nonce');

		// Verifica permissões
		if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permissão negada.', 'wp-whatsevolution')]);
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
					        wp_kses_post(stripslashes($posted_statuses[$status]['message'])) : '',
				'notify_admin' => isset($posted_statuses[$status]['notify_admin']) &&
					        filter_var($posted_statuses[$status]['notify_admin'], FILTER_VALIDATE_BOOLEAN),
				'admin_message' => isset($posted_statuses[$status]['admin_message']) ?
					        wp_kses_post(stripslashes($posted_statuses[$status]['admin_message'])) : ''
			];
		}

		// Salva as configurações
		$saved = update_option('wpwevo_status_messages', $status_messages);

		if ($saved) {
            wp_send_json_success(['message' => __('Configurações salvas com sucesso!', 'wp-whatsevolution')]);
		} else {
			// Verifica se os dados são diferentes dos já salvos
			$current_data = get_option('wpwevo_status_messages', []);
			if ($current_data == $status_messages) {
                wp_send_json_success(['message' => __('Configurações salvas com sucesso!', 'wp-whatsevolution')]);
			} else {
                wp_send_json_error(['message' => __('Erro ao salvar as configurações. Tente novamente.', 'wp-whatsevolution')]);
			}
		}
	}

	public function handle_preview_message() {
		check_ajax_referer('wpwevo_preview_message', 'nonce');

		if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
		}

		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

		if (empty($message)) {
            wp_send_json_error(__('Por favor, digite uma mensagem para visualizar.', 'wp-whatsevolution'));
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
				'{admin_order_url}',
				'{shipping_name}',
				'{shipping_address_line_1}',
				'{shipping_address_line_2}',
				'{shipping_city}',
				'{shipping_state}',
				'{shipping_postcode}',
				'{last_order_date}'
			],
			[
				'João Silva',
				'12345',
				'R$ 150,00',
				'Cartão de Crédito',
				'PAC',
				'https://seusite.com.br/pedido/12345',
				'https://seusite.com.br/wp-admin/post.php?post=12345&action=edit',
				'João Silva',
				'Rua das Flores, 123',
				'Apto 45',
				'São Paulo',
				'SP',
				'01234-567',
				'15/12/2024'
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

		$billing_phone = wpwevo_get_order_phone($order);
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

		$result = $api->send_message($billing_phone, $message);
		
		if (!$result || !isset($result['success']) || !$result['success']) {
			$error_msg = isset($result['message']) ? $result['message'] : 'Erro desconhecido';
			wpwevo_log('error', "Send_By_Status: Falha ao enviar mensagem para novo pedido #$order_id - $error_msg");
		} else {
			// Adiciona nota no pedido
			$order->add_order_note(
				sprintf(
					'Mensagem de WhatsApp enviada automaticamente para %s devido à mudança de status para %s: %s',
					$billing_phone,
					wc_get_order_status_name($status),
					$message
				),
				false
			);
		}
	}

	/**
	 * Manipula a mudança de status dos pedidos
	 */
	public function handle_status_change($order_id, $old_status, $new_status, $order) {
		// Verifica se o WooCommerce está ativo
		if (!class_exists('WooCommerce')) {
			return;
		}
		
		if (!$order instanceof \WC_Order) {
			$order = wc_get_order($order_id);
			if (!$order) {
				return;
			}
		}

		// Verifica se a propriedade available_statuses foi inicializada
		if (empty($this->available_statuses)) {
			$this->setup();
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

		$billing_phone = wpwevo_get_order_phone($order);
		if (empty($billing_phone)) {
			// Tenta campos alternativos
			$alternative_phones = [
				$order->get_billing_phone(),
				$order->get_meta('_billing_cellphone'),
				$order->get_meta('_billing_phone'),
				$order->get_meta('billing_phone_number'),
				$order->get_meta('wcf_billing_phone')
			];
			
			foreach ($alternative_phones as $phone) {
				if (!empty($phone)) {
					$billing_phone = $phone;
					break;
				}
			}
			
			if (empty($billing_phone)) {
				return;
			}
		}

		// Formata a mensagem com os dados do pedido
		$message = $this->replace_variables($message, $order);

		// Envia a mensagem
		$api = Api_Connection::get_instance();
		if (!$api->is_configured()) {
			return;
		}

		$result = $api->send_message($billing_phone, $message);
		
		if (!$result || !isset($result['success']) || !$result['success']) {
			$error_msg = isset($result['message']) ? $result['message'] : 'Erro desconhecido';
			wpwevo_log('error', "Send_By_Status: Falha ao enviar mensagem para pedido #$order_id - $error_msg");
		} else {
			// Adiciona nota no pedido
			$order->add_order_note(
				sprintf(
					'Mensagem de WhatsApp enviada automaticamente para %s devido à mudança de status para %s: %s',
					$billing_phone,
					wc_get_order_status_name($new_status),
					$message
				),
				false
			);
		}
	}

	/**
	 * Notifica o admin na mudança de status. Roda como callback separado de
	 * handle_status_change() e só depende do que diz respeito ao admin: o
	 * sininho "Notificar Admin" do status e o número configurado em Conexão.
	 *
	 * O checkbox "Ativar" do status governa apenas a mensagem do cliente — um
	 * lojista pode querer ser avisado de um status sem mandar nada ao cliente.
	 *
	 * Dispara uma vez por transição de status, igual à mensagem do cliente.
	 */
	public function handle_admin_notification($order_id, $old_status, $new_status, $order) {
		if (!class_exists('WooCommerce')) {
			return;
		}

		if (!$order instanceof \WC_Order) {
			$order = wc_get_order($order_id);
			if (!$order) {
				return;
			}
		}

		if (empty($this->available_statuses)) {
			$this->setup();
		}

		$settings = get_option('wpwevo_status_messages', []);

		if (empty($settings[$new_status]['notify_admin'])) {
			return;
		}

		// Até a v1.6.0 este caso não logava nada em lugar nenhum — era o motivo
		// de o usuário não achar rastro do problema. No modo managed o campo era
		// impossível de preencher, então caía sempre aqui.
		$admin_phone = get_option('wpwevo_admin_whatsapp');
		if (empty($admin_phone)) {
			wpwevo_log('warning', "Send_By_Status: 'Notificar Admin' está ligado no status '$new_status' (pedido #$order_id), mas o número do WhatsApp Admin está vazio. Preencha em Whats Evolution → Conexão → WhatsApp Admin.");
			return;
		}

		$api = Api_Connection::get_instance();
		if (!$api->is_configured()) {
			wpwevo_log('warning', "Send_By_Status: 'Notificar Admin' está ligado no status '$new_status' (pedido #$order_id), mas a API não está configurada.");
			return;
		}

		// Mensagem em branco continua caindo no template padrão: quem depende
		// dele hoje não pode parar de receber silenciosamente.
		if (!empty($settings[$new_status]['admin_message'])) {
			$admin_message = $settings[$new_status]['admin_message'];
		} else {
			$admin_message = "🔔 *Notificação de Pedido*\n\n" .
							"📋 Pedido: #{order_id}\n" .
							"📊 Status: {order_status}\n" .
							"👤 Cliente: {customer_name}\n" .
							"📱 Contato: {customer_phone}\n" .
							"💰 Valor: {order_total}\n\n" .
							"🔗 Ver pedido: {admin_order_url}";
		}

		$admin_message = $this->replace_variables($admin_message, $order);

		$admin_result = $api->send_message($admin_phone, $admin_message);

		if ($admin_result && isset($admin_result['success']) && $admin_result['success']) {
			$order->add_order_note(
				sprintf(
					'Notificação de status enviada ao admin: %s',
					$admin_phone
				),
				false
			);
		} else {
			// Falha no envio ao admin não afeta o cliente — o envio dele roda em
			// outro callback e já terminou quando este aqui começa.
			$error_msg = isset($admin_result['message']) ? $admin_result['message'] : 'Erro desconhecido';
			wpwevo_log('error', "Send_By_Status: Falha ao enviar notificação ao admin para pedido #$order_id - $error_msg");
		}
	}
} 