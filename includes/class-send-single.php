<?php
namespace WpWhatsAppEvolution;

/**
 * Gerencia o envio único de mensagens
 */
class Send_Single {
	private static $instance = null;
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
		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('wp_ajax_wpwevo_send_single', [$this, 'handle_ajax_send']);
		add_action('wp_ajax_wpwevo_validate_number', [$this, 'handle_ajax_validate']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	public function setup() {
		$this->menu_title = __('Envio Único', 'wp-whatsapp-evolution');
		$this->page_title = __('Envio Único', 'wp-whatsapp-evolution');
		
		$this->i18n = [
			'sending' => __('Enviando...', 'wp-whatsapp-evolution'),
			'validating' => __('Validando...', 'wp-whatsapp-evolution'),
			'success' => __('Mensagem enviada com sucesso!', 'wp-whatsapp-evolution'),
			'error' => __('Erro ao enviar mensagem: ', 'wp-whatsapp-evolution'),
			'invalidNumber' => __('Número inválido', 'wp-whatsapp-evolution'),
			'validNumber' => __('Número válido', 'wp-whatsapp-evolution')
		];
	}

	public function add_menu() {
		add_submenu_page(
			'wpwevo-settings',
			$this->page_title,
			$this->menu_title,
			'manage_options',
			'wpwevo-send-single',
			[$this, 'render_page']
		);
	}

	public function enqueue_scripts($hook) {
		if ($hook !== 'whatsapp-evolution_page_wpwevo-send-single') {
			return;
		}

		wp_enqueue_script(
			'wpwevo-send-single',
			WPWEVO_URL . 'assets/js/send-single.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-send-single', 'wpwevoSendSingle', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_send_single'),
			'validateNonce' => wp_create_nonce('wpwevo_validate_number'),
			'i18n' => $this->i18n
		]);

		// Adiciona CSS inline para melhorar a visibilidade das variáveis
		wp_add_inline_style('wpwevo-admin', '
			.wpwevo-variables-help code {
				padding: 4px 8px;
				background: #f0f0f1;
				border-radius: 3px;
				display: inline-block;
				margin: 2px 0;
				border: 1px solid #ddd;
				font-family: monospace;
				user-select: all;
				cursor: text;
			}
			.wpwevo-variables-help li {
				margin-bottom: 8px;
			}
		');
	}

	public function render_page() {
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html($this->page_title); ?></h1>

			<div class="wpwevo-send-single-form">
				<form id="wpwevo-send-single-form">
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="wpwevo-number"><?php _e('Número', 'wp-whatsapp-evolution'); ?></label>
							</th>
							<td>
								<input type="text" id="wpwevo-number" name="number" class="regular-text" 
									   placeholder="5511999999999" required>
								<p class="description">
									<?php _e('Digite o número com código do país e DDD (apenas números)', 'wp-whatsapp-evolution'); ?>
								</p>
								<div id="wpwevo-number-validation" class="wpwevo-validation-result"></div>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="wpwevo-message"><?php _e('Mensagem', 'wp-whatsapp-evolution'); ?></label>
							</th>
							<td>
								<textarea id="wpwevo-message" name="message" class="large-text" rows="5" required></textarea>
								<p class="description">
									<?php _e('Digite a mensagem que deseja enviar', 'wp-whatsapp-evolution'); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="wpwevo-form-actions">
						<button type="submit" class="button button-primary">
							<?php _e('Enviar Mensagem', 'wp-whatsapp-evolution'); ?>
						</button>
						<span class="spinner"></span>
					</div>

					<div id="wpwevo-send-result" class="wpwevo-send-result" style="display: none;"></div>
				</form>
			</div>

			<div class="wpwevo-variables-help">
				<h3><?php _e('Variáveis Disponíveis', 'wp-whatsapp-evolution'); ?></h3>
				<p><?php _e('Use as variáveis abaixo para personalizar sua mensagem:', 'wp-whatsapp-evolution'); ?></p>
				<ul>
					<li><code>{store_name}</code> - <?php _e('Nome da loja', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{store_url}</code> - <?php _e('URL da loja', 'wp-whatsapp-evolution'); ?></li>
					<li><code>{store_email}</code> - <?php _e('E-mail da loja', 'wp-whatsapp-evolution'); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	public function handle_ajax_send() {
		check_ajax_referer('wpwevo_send_single', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';
		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

		if (empty($number) || empty($message)) {
			wp_send_json_error(__('Número e mensagem são obrigatórios.', 'wp-whatsapp-evolution'));
		}

		// Processa as variáveis na mensagem
		$message = wpwevo_replace_vars($message);

		// Envia a mensagem
		$api = Api_Connection::get_instance();
		$result = $api->send_message($number, $message);

		if ($result['success']) {
			wp_send_json_success($result['message']);
		} else {
			wp_send_json_error($result['message']);
		}
	}

	public function handle_ajax_validate() {
		check_ajax_referer('wpwevo_validate_number', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';

		if (empty($number)) {
			wp_send_json_error(__('Número é obrigatório.', 'wp-whatsapp-evolution'));
		}

		// Valida o número
		$api = Api_Connection::get_instance();
		$result = $api->validate_number($number);

		wp_send_json($result);
	}
} 