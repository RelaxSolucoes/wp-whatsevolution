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
		// Define as propriedades ANTES dos hooks
        $this->menu_title = __('Envio Único', 'wp-whatsevolution');
        $this->page_title = __('Envio Único', 'wp-whatsevolution');
		
		$this->i18n = [
            'sending' => __('Enviando...', 'wp-whatsevolution'),
            'validating' => __('Validando...', 'wp-whatsevolution'),
            'success' => __('Mensagem enviada com sucesso!', 'wp-whatsevolution'),
            'error' => __('Erro ao enviar mensagem: ', 'wp-whatsevolution'),
            'invalidNumber' => __('Número inválido', 'wp-whatsevolution'),
            'validNumber' => __('Número válido', 'wp-whatsevolution')
		];

		add_action('admin_menu', [$this, 'add_menu']);
		add_action('wp_ajax_wpwevo_send_single', [$this, 'handle_ajax_send']);
		add_action('wp_ajax_wpwevo_validate_number', [$this, 'handle_ajax_validate']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
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
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		// Carrega CSS admin
		wp_enqueue_style('wpwevo-admin');

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

		// CSS moderno para os cards
		wp_add_inline_style('wpwevo-admin', '
			.wpwevo-send-result.success {
				background: #d4edda;
				color: #155724;
				border: 1px solid #c3e6cb;
				border-radius: 6px;
				padding: 12px;
			}
			.wpwevo-send-result.error {
				background: #f8d7da;
				color: #721c24;
				border: 1px solid #f5c6cb;
				border-radius: 6px;
				padding: 12px;
			}
			.wpwevo-validation-result.valid {
				color: #28a745;
				font-weight: 500;
			}
			.wpwevo-validation-result.invalid {
				color: #dc3545;
				font-weight: 500;
			}
		');
	}

	public function render_page() {
		?>
		<div class="wrap wpwevo-panel" style="max-width: none;">
			<h1>📱 Envio Individual de Mensagens</h1>

			<!-- Cards de Envio Individual -->
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
				
				<!-- Card 1: Formulário de Envio -->
				<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
						<div style="display: flex; align-items: center; margin-bottom: 20px;">
							<div style="background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">💬</div>
							<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Enviar Mensagem</h3>
						</div>
						
						<form id="wpwevo-send-single-form" method="post">
							<div style="display: grid; gap: 20px;">
								<!-- Campo Número -->
								<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
									<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">📞 Número do WhatsApp</label>
									<input type="text" id="wpwevo-number" name="number" 
										   style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;" 
										   placeholder="5511999999999" required>
									<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 12px;">
										Digite o número com código do país e DDD (apenas números)
									</p>
									<div id="wpwevo-number-validation" style="margin-top: 8px;"></div>
								</div>
								
								<!-- Campo Mensagem -->
								<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
									<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">💭 Mensagem</label>
									<textarea id="wpwevo-message" name="message" rows="6" 
											  style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px; resize: vertical;" 
											  placeholder="Digite sua mensagem aqui..." required></textarea>
									<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 12px;">
										Use as variáveis disponíveis para personalizar sua mensagem
									</p>
								</div>
							</div>
							
							<!-- Botão de Envio -->
							<div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
								<button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 24px; font-size: 14px; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
									🚀 Enviar Mensagem
								</button>
								<span class="spinner"></span>
							</div>
							
							<div id="wpwevo-send-result" style="display: none; margin-top: 15px;"></div>
						</form>
					</div>
				</div>

				<!-- Card 2: Variáveis Disponíveis -->
				<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
						<div style="display: flex; align-items: center; margin-bottom: 20px;">
							<div style="background: #4facfe; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🏷️</div>
							<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Variáveis Disponíveis</h3>
						</div>
						
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
							<p style="margin: 0 0 15px 0; color: #2d3748; font-weight: 500;">Clique nas variáveis para inserir na mensagem:</p>
							
							<div style="display: grid; gap: 10px;">
								<div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
									<code onclick="insertVariable('{store_name}')" style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; min-width: 100px; text-align: center;" title="Clique para inserir">{store_name}</code>
									<small style="color: #4a5568; flex: 1;">Nome da loja</small>
								</div>
								
								<div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
									<code onclick="insertVariable('{store_url}')" style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; min-width: 100px; text-align: center;" title="Clique para inserir">{store_url}</code>
									<small style="color: #4a5568; flex: 1;">URL da loja</small>
								</div>
								
								<div style="display: flex; align-items: center; gap: 10px; padding: 8px; background: white; border-radius: 6px; border: 1px solid #e2e8f0;">
									<code onclick="insertVariable('{store_email}')" style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; cursor: pointer; min-width: 100px; text-align: center;" title="Clique para inserir">{store_email}</code>
									<small style="color: #4a5568; flex: 1;">E-mail da loja</small>
								</div>
							</div>
							
							<div style="background: #e7f3ff; padding: 10px; margin-top: 15px; border: 1px solid #bee5eb; border-radius: 4px; font-size: 12px;">
								<strong>💡 Dica:</strong> As variáveis serão substituídas automaticamente pelos valores reais da sua loja quando a mensagem for enviada.
							</div>
							
							<!-- Seção de Dicas Extras -->
							<div style="margin-top: 20px;">
								<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">📋 Dicas de Uso</h4>
								<div style="display: grid; gap: 8px;">
									<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #4facfe; font-size: 12px;">
										<strong>✅ Validação:</strong> O número será validado automaticamente
									</div>
									<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #4facfe; font-size: 12px;">
										<strong>📱 Formato:</strong> Use apenas números (5511999999999)
									</div>
									<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #4facfe; font-size: 12px;">
										<strong>🚀 Envio:</strong> Mensagem enviada em tempo real
									</div>
									<div style="background: white; padding: 8px; border-radius: 4px; border-left: 3px solid #4facfe; font-size: 12px;">
										<strong>📝 Histórico:</strong> Todos os envios são registrados
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

			</div>

			<!-- Card de Exemplo de Mensagem -->
			<div style="margin-top: 20px;">
				<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(168, 237, 234, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
						<div style="display: flex; align-items: center; margin-bottom: 15px;">
							<div style="background: #a8edea; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">📝</div>
							<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Exemplo de Mensagem</h3>
						</div>
						
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
							<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #a8edea;">
								<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">📋 Template com Variáveis</h4>
								<pre style="background: #2d3748; color: #e2e8f0; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.4; margin: 0; white-space: pre-wrap;">Olá! 👋

Aqui é da {store_name}!

Temos uma promoção especial para você. Confira em nosso site:
🌐 {store_url}

Dúvidas? Entre em contato:
📧 {store_email}

Obrigado! 😊</pre>
							</div>
							
							<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #a8edea;">
								<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px;">✨ Resultado Final</h4>
								<div style="background: #e8f5e8; padding: 12px; border-radius: 6px; font-size: 12px; line-height: 1.4; border: 2px solid #4CAF50;">
									Olá! 👋<br><br>
									Aqui é da <strong>Minha Loja</strong>!<br><br>
									Temos uma promoção especial para você. Confira em nosso site:<br>
									🌐 <span style="color: #1976d2;">https://minhaloja.com</span><br><br>
									Dúvidas? Entre em contato:<br>
									📧 <span style="color: #1976d2;">contato@minhaloja.com</span><br><br>
									Obrigado! 😊
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<script>
			function insertVariable(variable) {
				const textarea = document.getElementById('wpwevo-message');
				const cursorPos = textarea.selectionStart;
				const textBefore = textarea.value.substring(0, cursorPos);
				const textAfter = textarea.value.substring(cursorPos);
				textarea.value = textBefore + variable + textAfter;
				textarea.focus();
				textarea.setSelectionRange(cursorPos + variable.length, cursorPos + variable.length);
			}
			</script>
		</div>
		<?php
	}

	public function handle_ajax_send() {
		check_ajax_referer('wpwevo_send_single', 'nonce');

		if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
		}

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';
		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

		if (empty($number) || empty($message)) {
            wp_send_json_error(__('Número e mensagem são obrigatórios.', 'wp-whatsevolution'));
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
            wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
		}

		$number = isset($_POST['number']) ? sanitize_text_field($_POST['number']) : '';

		if (empty($number)) {
            wp_send_json_error(__('Número é obrigatório.', 'wp-whatsevolution'));
		}

		// Valida o número
		$api = Api_Connection::get_instance();
		$result = $api->validate_number($number);

		wp_send_json($result);
	}
} 