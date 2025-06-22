<?php
namespace WpWhatsAppEvolution;

/**
 * Handles WhatsApp number validation during WooCommerce checkout
 */
class Checkout_Validator {
	private static $instance = null;
	private $settings = [];
	private $menu_slug = 'wpwevo-checkout';
	private $parent_slug = 'wpwevo-settings';

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Load settings
		$this->settings = [
			'enabled' => get_option('wpwevo_checkout_enabled', 'yes'),
			'validation' => get_option('wpwevo_checkout_validation', 'yes'),
			'show_modal' => get_option('wpwevo_checkout_show_modal', 'yes'),
			'modal_title' => get_option('wpwevo_checkout_modal_title', __('Atenção!', 'wp-whatsapp-evolution')),
			'modal_message' => get_option('wpwevo_checkout_modal_message', __('O número informado não parece ser um WhatsApp válido. Deseja prosseguir mesmo assim?', 'wp-whatsapp-evolution')),
			'modal_button_text' => get_option('wpwevo_checkout_modal_button_text', __('Prosseguir sem WhatsApp', 'wp-whatsapp-evolution')),
			'validation_success_message' => get_option('wpwevo_checkout_validation_success', __('✓ Número de WhatsApp válido', 'wp-whatsapp-evolution')),
			'validation_error_message' => get_option('wpwevo_checkout_validation_error', __('⚠ Este número não possui WhatsApp', 'wp-whatsapp-evolution'))
		];

		// Adiciona menu e configurações
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		
		// Carrega CSS admin
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

		// Only add functionality if enabled
		if ($this->settings['enabled'] === 'yes') {
			// Modifica o placeholder e descrição dos campos de telefone
			add_filter('woocommerce_billing_fields', [$this, 'modify_phone_fields']);
			
			// Validate phone fields during checkout
			if ($this->settings['validation'] === 'yes') {
				add_action('woocommerce_checkout_process', [$this, 'validate_phone_fields']);
			}
			
			// Add WhatsApp validation via AJAX
			add_action('wp_ajax_wpwevo_validate_checkout_number', [$this, 'handle_ajax_validation']);
			add_action('wp_ajax_nopriv_wpwevo_validate_checkout_number', [$this, 'handle_ajax_validation']);
			
			// Enqueue validation script
			if ($this->settings['validation'] === 'yes') {
				add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
			}
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts($hook) {
		// Só carrega nas páginas do plugin
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		wp_enqueue_style(
			'wpwevo-admin-checkout',
			WPWEVO_URL . 'assets/css/admin-checkout.css',
			[],
			WPWEVO_VERSION
		);
	}

	/**
	 * Add submenu page
	 */
	public function add_menu() {
		add_submenu_page(
			$this->parent_slug,
			__('Validação no Checkout', 'wp-whatsapp-evolution'),
			__('Validação no Checkout', 'wp-whatsapp-evolution'),
			'manage_options',
			$this->menu_slug,
			[$this, 'render_page']
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_enabled');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_validation');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_show_modal');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_modal_title');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_modal_message');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_modal_button_text');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_validation_success');
		register_setting('wpwevo_checkout_settings', 'wpwevo_checkout_validation_error');
	}

	/**
	 * Render settings page
	 */
	public function render_page() {
		?>
		<div class="wrap wpwevo-checkout-page" style="max-width: none;">
			<h1>📱 Validação de WhatsApp no Checkout</h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields('wpwevo_checkout_settings');
				do_settings_sections('wpwevo_checkout_settings');
				?>
				
				<!-- Cards de Configuração Organizados -->
				<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
					
					<!-- Card 1: Configurações Principais -->
					<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden;">
						<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
							<div style="display: flex; align-items: center; margin-bottom: 20px;">
								<div style="background: #4facfe; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">⚙️</div>
								<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Configurações Principais</h3>
							</div>
							
							<div style="display: grid; gap: 20px;">
								<!-- Ativar Validação -->
								<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
									<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="wpwevo_checkout_enabled" value="yes" <?php checked($this->settings['enabled'], 'yes'); ?> style="transform: scale(1.2);">
										<strong style="color: #2d3748;">✅ Ativar validação de WhatsApp</strong>
									</label>
									<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 14px;">
										Os campos de telefone e celular do checkout serão validados para garantir que são números de WhatsApp válidos.
									</p>
								</div>
								
								<!-- Validação Tempo Real -->
								<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
									<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="wpwevo_checkout_validation" value="yes" <?php checked($this->settings['validation'], 'yes'); ?> style="transform: scale(1.2);">
										<strong style="color: #2d3748;">⚡ Validação em tempo real</strong>
									</label>
									<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 14px;">
										O número será validado enquanto o cliente digita.
									</p>
								</div>
								
								<!-- Modal de Confirmação -->
								<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
									<label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="wpwevo_checkout_show_modal" value="yes" <?php checked($this->settings['show_modal'], 'yes'); ?> style="transform: scale(1.2);">
										<strong style="color: #2d3748;">💬 Modal de confirmação</strong>
									</label>
									<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 14px;">
										Exibe um modal quando o número não for um WhatsApp válido.
									</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Card 2: Configuração do Modal -->
					<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(168, 237, 234, 0.2); overflow: hidden;">
						<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
							<div style="display: flex; align-items: center; margin-bottom: 20px;">
								<div style="background: #a8edea; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">💬</div>
								<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Personalização do Modal</h3>
							</div>
							
							<div style="display: grid; gap: 15px;">
								<div>
									<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #2d3748;">📝 Título do Modal</label>
									<input type="text" name="wpwevo_checkout_modal_title" value="<?php echo esc_attr($this->settings['modal_title']); ?>" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
								</div>
								
								<div>
									<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #2d3748;">💭 Mensagem do Modal</label>
									<textarea name="wpwevo_checkout_modal_message" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; resize: vertical;"><?php echo esc_textarea($this->settings['modal_message']); ?></textarea>
								</div>
								
								<div>
									<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #2d3748;">🔘 Texto do Botão</label>
									<input type="text" name="wpwevo_checkout_modal_button_text" value="<?php echo esc_attr($this->settings['modal_button_text']); ?>" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
								</div>
							</div>
						</div>
					</div>

					<!-- Card 3: Mensagens de Validação -->
					<div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(255, 236, 210, 0.2); overflow: hidden;">
						<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
							<div style="display: flex; align-items: center; margin-bottom: 20px;">
								<div style="background: #ffecd2; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">💬</div>
								<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Mensagens de Validação</h3>
							</div>
							
							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
								<div>
									<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #2d3748;">✅ Mensagem de Sucesso</label>
									<input type="text" name="wpwevo_checkout_validation_success" value="<?php echo esc_attr($this->settings['validation_success_message']); ?>" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
									<p style="margin: 5px 0 0 0; color: #4a5568; font-size: 12px;">Exibida quando o número for válido</p>
								</div>
								
								<div>
									<label style="display: block; margin-bottom: 5px; font-weight: 500; color: #2d3748;">⚠️ Mensagem de Erro</label>
									<input type="text" name="wpwevo_checkout_validation_error" value="<?php echo esc_attr($this->settings['validation_error_message']); ?>" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
									<p style="margin: 5px 0 0 0; color: #4a5568; font-size: 12px;">Exibida quando o número for inválido</p>
								</div>
							</div>
						</div>
					</div>

				</div>

				<div style="margin-top: 30px;">
					<?php submit_button('💾 Salvar Configurações', 'primary', 'submit', false, 'style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 24px; font-size: 16px; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);"'); ?>
				</div>
			</form>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Função para atualizar visibilidade dos campos do modal
			function updateModalFieldsVisibility() {
				var showModal = $('input[name="wpwevo_checkout_show_modal"]').is(':checked');
				$('.wpwevo-modal-fields').toggleClass('hidden', !showModal);
			}

			// Atualiza no carregamento da página
			updateModalFieldsVisibility();

			// Atualiza quando o checkbox mudar
			$('input[name="wpwevo_checkout_show_modal"]').on('change', updateModalFieldsVisibility);
		});
		</script>
		<?php
	}

	/**
	 * Modify phone fields to indicate WhatsApp
	 */
	public function modify_phone_fields($fields) {
		$whatsapp_field_config = [
			'placeholder' => __('Ex: 1133334444 ou 11999999999', 'wp-whatsapp-evolution'),
			'description' => __('Digite seu número de WhatsApp com DDD (fixo ou celular)', 'wp-whatsapp-evolution')
		];

		// Modifica o campo padrão do WooCommerce
		if (isset($fields['billing_phone'])) {
			$fields['billing_phone'] = array_merge($fields['billing_phone'], $whatsapp_field_config);
		}

		// Modifica o campo do Brazilian Market se existir
		if (isset($fields['billing_cellphone'])) {
			$fields['billing_cellphone'] = array_merge($fields['billing_cellphone'], $whatsapp_field_config);
		}

		return $fields;
	}

	/**
	 * Validate phone fields during checkout
	 */
	public function validate_phone_fields() {
		// Tenta pegar o número do campo de celular primeiro
		$phone = isset($_POST['billing_cellphone']) ? sanitize_text_field($_POST['billing_cellphone']) : '';
		
		// Se não tiver celular, tenta o campo de telefone
		if (empty($phone)) {
			$phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';
		}
		
		if (empty($phone)) {
			return; // WooCommerce já valida se é obrigatório
		}

		// Clean the phone number (remove all non-digits)
		$phone = preg_replace('/\D/', '', $phone);
		
		// Basic format validation (numbers only, correct length for Brazil)
		// Aceita:
		// - 10 dígitos: DDD + 8 dígitos (1133334444) - telefone fixo
		// - 11 dígitos: DDD + 9 dígitos (11999999999) - celular
		// - 12 dígitos: Código do país + DDD + 8 dígitos (551133334444)
		// - 13 dígitos: Código do país + DDD + 9 dígitos (5511999999999)
		if (!preg_match('/^\d{10,13}$/', $phone)) {
			wc_add_notice(
				__('O número de WhatsApp deve conter apenas números, incluindo DDD (Ex: 11999999999).', 'wp-whatsapp-evolution'),
				'error'
			);
			return;
		}

		// Normaliza o número para o formato internacional brasileiro
		if (strlen($phone) == 10) {
			// Se tem 10 dígitos, assume que é DDD + número fixo sem código do país
			$phone = '55' . $phone;
		} elseif (strlen($phone) == 11) {
			// Se tem 11 dígitos, assume que é DDD + número celular sem código do país
			$phone = '55' . $phone;
		} elseif (strlen($phone) == 12 && substr($phone, 0, 2) !== '55') {
			// Se tem 12 dígitos e não começa com 55, pode ser um número incorreto
			wc_add_notice(
				__('Número de WhatsApp inválido. Certifique-se de incluir o código do país (55) e DDD.', 'wp-whatsapp-evolution'),
				'error'
			);
			return;
		}

		// Validate through API
		$api = Api_Connection::get_instance();
		$result = $api->validate_number($phone);

		if (!$result['success']) {
			wc_add_notice(
				__('Número de WhatsApp inválido. Por favor, verifique e tente novamente.', 'wp-whatsapp-evolution'),
				'error'
			);
		}
	}

	/**
	 * Handle AJAX validation request
	 */
	public function handle_ajax_validation() {
		check_ajax_referer('wpwevo_validate_checkout', 'nonce');

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';

		if (empty($number)) {
			wp_send_json_error(__('Número é obrigatório.', 'wp-whatsapp-evolution'));
		}

		// Validate through API
		$api = Api_Connection::get_instance();
		$result = $api->validate_number($number);

		wp_send_json($result);
	}

	/**
	 * Enqueue validation script
	 */
	public function enqueue_scripts() {
		// Verifica se estamos em qualquer página de checkout do WooCommerce
		if (!is_checkout() && !is_wc_endpoint_url('order-received') && !is_wc_endpoint_url('order-pay')) {
			return;
		}

		// Verifica se o WooCommerce está ativo
		if (!class_exists('WooCommerce')) {
			return;
		}

		// Verifica se estamos na página de finalizar compra ou em páginas específicas do checkout
		global $wp;
		$checkout_page_id = wc_get_page_id('checkout');
		$current_page_id = get_queried_object_id();
		
		if ($checkout_page_id !== $current_page_id && 
			!isset($wp->query_vars['order-pay']) && 
			!isset($wp->query_vars['order-received']) &&
			!is_wc_endpoint_url('order-received') &&
			!is_wc_endpoint_url('order-pay')) {
			return;
		}

		wp_enqueue_style(
			'wpwevo-checkout-validator',
			WPWEVO_URL . 'assets/css/checkout-validator.css',
			[],
			WPWEVO_VERSION
		);

		wp_enqueue_script(
			'wpwevo-checkout-validator',
			WPWEVO_URL . 'assets/js/checkout-validator.js',
			['jquery', 'wc-checkout'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-checkout-validator', 'wpwevoCheckoutValidator', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_validate_phone'),
			'validation_url' => admin_url('admin-ajax.php'),
			'validation_action' => 'wpwevo_validate_phone'
		]);
	}
} 