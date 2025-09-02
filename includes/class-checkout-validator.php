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
            'modal_title' => get_option('wpwevo_checkout_modal_title', __('Atenção!', 'wp-whatsevolution')),
            'modal_message' => get_option('wpwevo_checkout_modal_message', __('O número informado não parece ser um WhatsApp válido. Deseja prosseguir mesmo assim?', 'wp-whatsevolution')),
            'modal_button_text' => get_option('wpwevo_checkout_modal_button_text', __('Prosseguir sem WhatsApp', 'wp-whatsevolution')),
            'validation_success_message' => get_option('wpwevo_checkout_validation_success', __('✓ Número de WhatsApp válido', 'wp-whatsevolution')),
            'validation_error_message' => get_option('wpwevo_checkout_validation_error', __('⚠ Este número não possui WhatsApp', 'wp-whatsevolution'))
		];

		// Adiciona menu e configurações
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_init', [$this, 'register_settings']);
		
		// Carrega CSS admin
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

		// Only add functionality if enabled
		if ($this->settings['enabled'] === 'yes') {
			// Add WhatsApp validation via AJAX
			add_action('wp_ajax_wpwevo_validate_checkout_number', [$this, 'handle_ajax_validation']);
			add_action('wp_ajax_nopriv_wpwevo_validate_checkout_number', [$this, 'handle_ajax_validation']);
			
			// Enqueue validation script with high priority to avoid conflicts
			if ($this->settings['validation'] === 'yes') {
				add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 999);
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
            __('Validação no Checkout', 'wp-whatsevolution'),
            __('Validação no Checkout', 'wp-whatsevolution'),
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
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $cart_abandonment_active = is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php');
		?>
		<div class="wrap wpwevo-checkout-page" style="max-width: none;">
			<h1>📱 Validação de WhatsApp no Checkout</h1>
			
			<?php /* Compatibilidade com Cart Abandonment Recovery mantida, mensagem oculta por decisão de UX */ ?>
			
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
	 * Handle AJAX validation request
	 */
	public function handle_ajax_validation() {
		check_ajax_referer('wpwevo_validate_checkout', 'nonce');

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';

		if (empty($number)) {
            wp_send_json_error(__('Número é obrigatório.', 'wp-whatsevolution'));
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
		// Only run on checkout page
		if (!is_checkout()) {
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
			['jquery'], // wc-checkout pode não estar sempre disponível
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-checkout-validator', 'wpwevoCheckout', [
			'ajaxurl'        => admin_url('admin-ajax.php'),
			'nonce'          => wp_create_nonce('wpwevo_validate_checkout'),
			'show_modal'     => $this->settings['show_modal'],
			'modal_title'    => $this->settings['modal_title'],
			'modal_message'  => $this->settings['modal_message'],
			'modal_button_text' => $this->settings['modal_button_text'],
			'validation_success' => $this->settings['validation_success_message'],
			'validation_error'   => $this->settings['validation_error_message'],
			'debug'          => defined('WP_DEBUG') && WP_DEBUG,
		]);
	}
} 