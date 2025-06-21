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
		$this->menu_title = __('Envio por Status', 'wp-whatsapp-evolution');
		$this->page_title = __('Envio por Status', 'wp-whatsapp-evolution');
		
		$this->i18n = [
			'saving' => __('Salvando...', 'wp-whatsapp-evolution'),
			'saved' => __('Configurações salvas com sucesso!', 'wp-whatsapp-evolution'),
			'error' => __('Erro ao salvar: ', 'wp-whatsapp-evolution'),
			'preview' => __('Visualizando...', 'wp-whatsapp-evolution'),
			'networkError' => __('Erro de conexão. Tente novamente.', 'wp-whatsapp-evolution'),
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
		
		// Hook para novos pedidos - prioridade mais alta para garantir que seja executado antes da mudança de status
		add_action('woocommerce_new_order', [$this, 'handle_new_order'], 5, 1);
		
		// Hook para mudança de status
		add_action('woocommerce_order_status_changed', [$this, 'handle_status_change'], 10, 4);

		// Log para debug apenas uma vez
		wpwevo_log('info', 'Send_By_Status initialized successfully');
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

Obrigado por comprar conosco! 😊', 'wp-whatsapp-evolution')
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

Obrigado por comprar conosco! 😊', 'wp-whatsapp-evolution')
			],
			'processing' => [
				'enabled' => true,
				'message' => __('✅ *Pedido Aprovado!*

Olá {customer_name}!

Seu pedido #{order_id} foi aprovado e já estamos preparando tudo! 📦

🚚 *Envio:* {shipping_method}
📍 *Endereço:* {shipping_address_full}

Obrigado pela confiança! 🙏', 'wp-whatsapp-evolution')
			],
			'completed' => [
				'enabled' => true,
				'message' => __('🎉 *Pedido Concluído!*

Olá {customer_name}!

Seu pedido #{order_id} foi concluído com sucesso!

Esperamos que tenha gostado dos produtos! ⭐

🔗 Acompanhe outros pedidos em:
{order_url}

Agradecemos a preferência! 💚', 'wp-whatsapp-evolution')
			],
			'cancelled' => [
				'enabled' => true,
				'message' => __('❌ *Pedido Cancelado*

Olá {customer_name},

Seu pedido #{order_id} foi cancelado.

❓ Se houver alguma dúvida, acesse:
{order_url}

Ou entre em contato conosco! 📞', 'wp-whatsapp-evolution')
			],
			'refunded' => [
				'enabled' => true,
				'message' => __('💰 *Reembolso Processado*

Olá {customer_name},

O reembolso do seu pedido #{order_id} no valor de *{order_total}* foi processado! ✅

💳 O valor será creditado via {payment_method}.

Em breve aparecerá na sua conta! ⏰', 'wp-whatsapp-evolution')
			],
			'failed' => [
				'enabled' => true,
				'message' => __('⚠️ *Problema no Pedido*

Olá {customer_name},

Infelizmente houve um problema com seu pedido #{order_id}.

🔗 Acesse para mais detalhes:
{order_url}

📞 Ou entre em contato conosco para resolvermos juntos!', 'wp-whatsapp-evolution')
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

		// Monta o endereço completo
		$shipping_address_parts = [];
		if (!empty($shipping_address_1)) $shipping_address_parts[] = $shipping_address_1;
		if (!empty($shipping_city)) $shipping_address_parts[] = $shipping_city;
		if (!empty($shipping_state)) $shipping_address_parts[] = $shipping_state;
		if (!empty($shipping_postcode)) $shipping_address_parts[] = $shipping_postcode;
		
		$shipping_address_full = implode(', ', $shipping_address_parts);

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
			'{shipping_name}' => $shipping_name,
			'{shipping_address_line_1}' => $shipping_address_1,
			'{shipping_address_line_2}' => $order->get_shipping_address_2(),
			'{shipping_city}' => $shipping_city,
			'{shipping_state}' => $shipping_state,
			'{shipping_postcode}' => $shipping_postcode,
			'{shipping_address_full}' => $shipping_address_full
		];

		foreach ($variables as $key => $value) {
			$message = str_replace($key, $value, $message);
		}

		return $message;
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

		// Debug: Log dos dados de localização
		wpwevo_log('debug', 'Enqueue scripts - Hook: ' . $hook);
		wpwevo_log('debug', 'Localize data: ' . print_r($localize_data, true));

		wp_localize_script('wpwevo-send-by-status', 'wpwevoSendByStatus', $localize_data);
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
										<button type="button" 
												class="wpwevo-reset-message" 
												data-default="<?php echo esc_attr($default_message); ?>"
												data-status="<?php echo esc_attr($status); ?>"
												style="background: <?php echo $color; ?>; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 12px;">
											🔄 Restaurar Padrão
										</button>
									</div>

									<textarea name="status[<?php echo esc_attr($status); ?>][message]" 
											  class="wpwevo-auto-resize-textarea"
											  rows="1"
											  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.4; resize: vertical; min-height: 50px; overflow: hidden;"
											  placeholder="Digite a mensagem para este status..."><?php echo esc_textarea($message); ?></textarea>
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
						
						<p style="color: #4a5568; font-size: 14px; margin-bottom: 15px;">📝 Clique nas variáveis para adicioná-las na mensagem:</p>
						
						<div style="display: grid; gap: 8px;">
							<?php 
							$variables = [
								'{customer_name}' => 'Nome do cliente',
								'{order_id}' => 'Número do pedido',
								'{order_total}' => 'Valor total do pedido',
								'{payment_method}' => 'Método de pagamento',
								'{shipping_method}' => 'Método de envio',
								'{order_url}' => 'URL do pedido',
								'{payment_url}' => 'URL de pagamento',
								'{first_product}' => 'Primeiro produto',
								'{all_products}' => 'Todos os produtos',
								'{shipping_name}' => 'Nome destinatário',
								'{shipping_address_line_1}' => 'Endereço linha 1 (com número)',
								'{shipping_address_line_2}' => 'Endereço linha 2',
								'{shipping_address_full}' => 'Endereço completo de entrega',
								'{shipping_city}' => 'Cidade de entrega',
								'{shipping_state}' => 'Estado de entrega',
								'{shipping_postcode}' => 'CEP de entrega',
								'{billing_address_line_1}' => 'Endereço cobrança linha 1',
								'{billing_address_line_2}' => 'Endereço cobrança linha 2',
								'{billing_address_full}' => 'Endereço completo de cobrança',
								'{billing_city}' => 'Cidade de cobrança',
								'{billing_state}' => 'Estado de cobrança',
								'{billing_postcode}' => 'CEP de cobrança'
							];
							
							foreach ($variables as $var => $desc) : ?>
								<div class="wpwevo-variable" 
									 data-variable="<?php echo esc_attr($var); ?>"
									 style="background: #e6fffa; padding: 10px; border-radius: 6px; cursor: pointer; border: 1px solid #b2f5ea; transition: all 0.2s;" 
									 onmouseover="this.style.background='#b2f5ea'; this.style.transform='translateY(-1px)'" 
									 onmouseout="this.style.background='#e6fffa'; this.style.transform='translateY(0)'">
									<code class="wpwevo-variable-code" style="background: #319795; color: white; padding: 3px 6px; border-radius: 4px; font-size: 11px; margin-right: 8px;"><?php echo esc_html($var); ?></code>
									<span style="color: #2d3748; font-size: 12px;"><?php echo esc_html($desc); ?></span>
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

Endereço: {shipping_address_full}

Valor: {order_total}
🎯 {order_url}</pre>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #fcb69f;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">📲 Resultado Final</h4>
							<div style="background: #25d366; color: white; padding: 12px; border-radius: 6px; font-size: 13px; line-height: 1.4; font-family: system-ui;">
								Olá João Silva, seu pedido #12345 foi aprovado e já estamos preparando tudo para o envio via PAC.<br><br>
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
				'https://seusite.com.br/pedido/12345',
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

		$api->send_message($billing_phone, $message);
	}

	/**
	 * Manipula a mudança de status dos pedidos
	 */
	public function handle_status_change($order_id, $old_status, $new_status, $order) {
		wpwevo_log('info', "Send_By_Status: Mudança de status detectada - Pedido #$order_id: $old_status -> $new_status");
		
		if (!$order instanceof \WC_Order) {
			$order = wc_get_order($order_id);
			if (!$order) {
				wpwevo_log('error', "Send_By_Status: Pedido #$order_id não encontrado");
				return;
			}
		}

		$settings = get_option('wpwevo_status_messages', []);
		wpwevo_log('info', "Send_By_Status: Configurações carregadas - " . count($settings) . " status disponíveis");

		// Verifica se o novo status está ativo e tem mensagem
		if (!isset($settings[$new_status])) {
			wpwevo_log('warning', "Send_By_Status: Status '$new_status' não configurado");
			return;
		}
		
		if (empty($settings[$new_status]['enabled'])) {
			wpwevo_log('info', "Send_By_Status: Status '$new_status' está desabilitado");
			return;
		}

		$message = $settings[$new_status]['message'];
		if (empty($message)) {
			wpwevo_log('warning', "Send_By_Status: Mensagem para status '$new_status' está vazia");
			return;
		}

		$billing_phone = wpwevo_get_order_phone($order);
		if (empty($billing_phone)) {
			wpwevo_log('warning', "Send_By_Status: Telefone não encontrado para pedido #$order_id");
			return;
		}
		
		wpwevo_log('info', "Send_By_Status: Telefone encontrado - $billing_phone");

		// Formata a mensagem com os dados do pedido
		$message = $this->replace_variables($message, $order);
		wpwevo_log('info', "Send_By_Status: Mensagem formatada - " . substr($message, 0, 100) . "...");

		// Envia a mensagem
		$api = Api_Connection::get_instance();
		if (!$api->is_configured()) {
			wpwevo_log('error', "Send_By_Status: API não configurada");
			return;
		}

		wpwevo_log('info', "Send_By_Status: Tentando enviar mensagem via API...");
		$result = $api->send_message($billing_phone, $message);
		
		if ($result && isset($result['success']) && $result['success']) {
			wpwevo_log('success', "Send_By_Status: Mensagem enviada com sucesso para pedido #$order_id");
		} else {
			$error_msg = isset($result['message']) ? $result['message'] : 'Erro desconhecido';
			wpwevo_log('error', "Send_By_Status: Falha ao enviar mensagem para pedido #$order_id - $error_msg");
		}
	}
} 