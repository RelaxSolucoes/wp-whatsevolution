<?php
namespace WpWhatsAppEvolution;

/**
 * Página de configurações do plugin
 */
class Settings_Page {
	private static $instance = null;
	private static $menu_title = 'Whats Evolution';
	private static $page_title = 'Whats Evolution';

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
        self::$menu_title = __('Whats Evolution', 'wp-whatsevolution');
        self::$page_title = __('Whats Evolution', 'wp-whatsevolution');
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

		// O submenu de Carrinho Abandonado é registrado pela classe Cart_Abandonment
	}

	public function register_settings() {
		register_setting('wpwevo_settings', 'wpwevo_api_url', [
			'type' => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default' => ''
		]);

		register_setting('wpwevo_settings', 'wpwevo_manual_api_key', [
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
                throw new \Exception(__('Permissão negada.', 'wp-whatsevolution'));
			}

			$api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
			$api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
			$instance = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';

            if (empty($api_url) || empty($api_key) || empty($instance)) {
                throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsevolution'));
			}

			// Valida formato da URL
            if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
                throw new \Exception(__('URL da API inválida.', 'wp-whatsevolution'));
			}

			// A Evolution API que valida a chave - não fazemos validação local

			// Atualiza as opções para o modo manual
			update_option('wpwevo_connection_mode', 'manual');
			update_option('wpwevo_api_url', $api_url);
			update_option('wpwevo_manual_api_key', $api_key);
			update_option('wpwevo_instance', $instance);
			
			// Limpa a chave antiga para evitar confusão
			delete_option('wpwevo_api_key');

			// Testa a conexão
			$api = Api_Connection::get_instance();
			$api->force_reload();
			$result = $api->check_connection();

			if ($result['success']) {
				wp_send_json_success([
                    'message' => __('Configurações salvas com sucesso!', 'wp-whatsevolution'),
					'connection_status' => $result
				]);
			} else {
				throw new \Exception(sprintf(
                    __('Configurações salvas, mas %s', 'wp-whatsevolution'),
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
		if (strpos($hook, 'wpwevo') === false) {
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

		// Adiciona o script do quick-signup
		wp_enqueue_script(
			'wpwevo-quick-signup',
			WPWEVO_URL . 'assets/js/quick-signup.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-admin', 'wpwevo_admin', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_validate_settings'),
            'error_message' => __('Erro ao salvar as configurações. Tente novamente.', 'wp-whatsevolution'),
			'saved_api_url' => get_option('wpwevo_api_url', ''),
			'saved_api_key' => get_option('wpwevo_managed_api_key', ''),
			'saved_instance' => get_option('wpwevo_instance', '')
		]);

		// Adiciona as variáveis para o quick-signup
		wp_localize_script('wpwevo-quick-signup', 'wpwevo_quick_signup', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_quick_signup'),
			'api_key' => get_option('wpwevo_managed_api_key', ''),
			'messages' => [
                'validating' => __('Validando dados...', 'wp-whatsevolution'),
                'creating_account' => __('Criando conta...', 'wp-whatsevolution'),
                'configuring_plugin' => __('Configurando plugin...', 'wp-whatsevolution'),
                'success' => __('Pronto!', 'wp-whatsevolution'),
                'error' => __('Erro ao criar conta. Tente novamente.', 'wp-whatsevolution')
			]
		]);
	}

	public function render_page() {
		$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'connection';
		
		// Se plugin não está configurado, mostra aba de teste grátis por padrão
		if (!Quick_Signup::is_auto_configured() && !get_option('wpwevo_api_url', '')) {
			$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'quick-signup';
		}
		
		$tabs = [
            'quick-signup' => __('🚀 Teste Grátis', 'wp-whatsevolution'),
            'connection' => __('Conexão', 'wp-whatsevolution'),
            'help' => __('Ajuda', 'wp-whatsevolution'),
		];

		if (isset($_GET['connection'])) {
			$type = $_GET['connection'] === 'success' ? 'success' : 'error';
			$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
			echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . esc_html($message) . '</p></div>';
		}

		// 🚀 NOVO: Mensagem de sucesso para restauração de configurações manuais
		if (isset($_GET['restored']) && $_GET['restored'] === '1') {
            echo '<div class="notice notice-success"><p>' . esc_html__('Configurações manuais restauradas com sucesso!', 'wp-whatsevolution') . '</p></div>';
		}

		// 🚀 NOVO: Mensagem para ativação do modo manual
		if (isset($_GET['manual_activated']) && $_GET['manual_activated'] === '1') {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__('Modo de configuração manual ativado. Você pode agora inserir suas próprias credenciais da API.', 'wp-whatsevolution') . '</p></div>';
		}
		?>
		<div class="wrap wpwevo-panel" style="max-width: none;">
							<h1>⚙️ Whats Evolution - Configurações</h1>

			<div class="wpwevo-cta-box">
				<div class="wpwevo-cta-content">
					<h3 class="wpwevo-cta-title">
						<span class="wpwevo-cta-emoji">❌</span> Não tem uma API Evolution?
					</h3>
					<p class="wpwevo-cta-description">
						<span class="wpwevo-cta-emoji">🎯</span> Envie mensagens automatizadas para seus clientes em minutos!<br>
						<span class="wpwevo-cta-emoji">✨</span> Ative sua instância agora e aproveite todos os recursos premium do Whats Evolution.<br>
						<span class="wpwevo-cta-emoji">💡</span> <strong>Dica:</strong> Use a aba "🚀 Teste Grátis" para configuração automática em 1-click!
					</p>
				</div>
				<a href="https://whats-evolution-v2.vercel.app/" 
				   class="wpwevo-cta-button" target="_blank" rel="noopener noreferrer">
					<span class="wpwevo-cta-emoji">🚀</span> Teste Grátis Agora Mesmo!
				</a>
			</div>
			
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
				case 'quick-signup':
					$this->render_quick_signup_tab();
					break;
				case 'connection':
					$this->render_connection_tab();
					break;
				case 'help':
					$this->render_help_tab();
					break;
			}
			?>
		</div>
		
		<script>
		function toggleApiKeyVisibility() {
			const apiKeyInput = document.getElementById('wpwevo-api-key');
			const eyeIcon = document.getElementById('wpwevo-eye-icon');
			
			if (apiKeyInput.type === 'password') {
				apiKeyInput.type = 'text';
				eyeIcon.textContent = '🙈';
			} else {
				apiKeyInput.type = 'password';
				eyeIcon.textContent = '👁️';
			}
		}
		</script>

		<style>
		.wpwevo-step.active {
			background: #e6fffa !important;
		}
		.wpwevo-step.active > div {
			background: #38b2ac !important;
			color: white !important;
		}
		.wpwevo-step.completed > div {
			background: #48bb78 !important;
			color: white !important;
		}
		input.error {
			border-color: #e53e3e !important;
		}
		#wpwevo-signup-btn:disabled {
			background: #cbd5e0 !important;
			cursor: not-allowed !important;
		}
		.wpwevo-step {
			transition: all 0.3s ease;
		}
		</style>
		
		<!-- MODAL DE UPGRADE (melhorado) -->
		<div id="wpwevo-upgrade-modal" class="wpwevo-modal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
			<div class="wpwevo-modal-content" style="background-color: #fefefe; margin: 10% auto; padding: 0; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;">
				
				<div class="wpwevo-modal-header" style="padding: 20px 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-bottom: 1px solid #e5e5e5;">
					<h2 style="margin: 0; font-size: 22px; line-height: 1.2;">🚀 Continue Automatizando!</h2>
					<span class="wpwevo-close" onclick="closeUpgradeModal()" style="color: #fff; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; top: 10px; right: 20px;">&times;</span>
				</div>

				<div class="wpwevo-modal-body" style="padding: 25px;">
					<p style="font-size: 16px; color: #333; margin: 0 0 15px;">Seu período de testes terminou. Continue com acesso a todos os recursos por apenas:</p>
					<div class="wpwevo-price" style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
						<span style="font-size: 28px; font-weight: 700; color: #166534;">R$ 29,90</span>
						<span style="font-size: 16px; color: #15803d;">/mês</span>
					</div>
					<ul class="wpwevo-benefits" style="list-style: none; padding: 0; margin: 0 0 25px 0;">
						<li style="margin-bottom: 10px; font-size: 15px; color: #333;"><span style="margin-right: 10px;">✅</span> Envios de mensagens ilimitados</li>
						<li style="margin-bottom: 10px; font-size: 15px; color: #333;"><span style="margin-right: 10px;">✅</span> Suporte prioritário via WhatsApp</li>
						<li style="margin-bottom: 10px; font-size: 15px; color: #333;"><span style="margin-right: 10px;">✅</span> Atualizações automáticas do plugin</li>
						<li style="margin-bottom: 10px; font-size: 15px; color: #333;"><span style="margin-right: 10px;">✅</span> Backup automático das suas configurações</li>
					</ul>
					<div id="wpwevo-payment-feedback" style="display: none; margin: 15px 0; padding: 12px; border-radius: 6px; text-align: center;"></div>
				</div>

				<!-- NOVA SEÇÃO PARA EXIBIR O PIX -->
				<div id="wpwevo-pix-payment-info" class="wpwevo-modal-body" style="padding: 25px; display: none;">
					<h3 style="text-align: center; margin-top: 0; color: #166534;">Pague com PIX para ativar sua conta</h3>
					<p style="text-align: center; font-size: 14px; color: #333;">Abra o app do seu banco e escaneie o código abaixo:</p>
					<div style="text-align: center; margin: 20px 0;">
						<img id="wpwevo-pix-qr-code" src="" alt="PIX QR Code" style="max-width: 250px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 8px;">
					</div>
					<p style="text-align: center; font-size: 14px; color: #333;">Ou copie o código:</p>
					<div style="position: relative;">
						<textarea id="wpwevo-pix-copy-paste" readonly style="width: 100%; height: 100px; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 14px; resize: none;"></textarea>
						<button id="wpwevo-copy-pix-btn" style="position: absolute; top: 10px; right: 10px; background: #667eea; color: white; border: none; border-radius: 4px; padding: 5px 10px; cursor: pointer;">Copiar</button>
					</div>
				</div>

				<div class="wpwevo-modal-footer" style="padding: 20px 25px; background: #f7f7f7; text-align: right; border-top: 1px solid #e5e5e5;">
					<button onclick="closeUpgradeModal()" class="wpwevo-cancel-btn" style="background: transparent; border: 1px solid #ccc; color: #555; padding: 12px 20px; text-align: center; text-decoration: none; display: inline-block; font-size: 14px; margin: 4px 2px; cursor: pointer; border-radius: 8px; font-weight: 600;">
						Talvez depois
					</button>
					<button onclick="createPayment()" class="wpwevo-upgrade-btn" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color: white; padding: 12px 25px; border: none; text-align: center; text-decoration: none; display: inline-block; font-size: 14px; margin: 4px 2px; cursor: pointer; border-radius: 8px; font-weight: 600;">
						💳 Assinar Agora
					</button>
				</div>
			</div>
		</div>
		
		<?php
	}

	private function render_connection_tab() {
		$api = Api_Connection::get_instance();
		$connection_status = $api->is_configured() ? $api->check_connection() : null;
		$connection_mode = get_option('wpwevo_connection_mode', 'manual');
		$is_managed = $connection_mode === 'managed';
		$is_trial_expired = Quick_Signup::should_show_upgrade_modal();
		$has_previous_manual = get_option('wpwevo_previous_manual_config');

		if (isset($_GET['force_manual_mode']) && check_admin_referer('wpwevo_force_manual')) {
			// 🚀 CORREÇÃO: Apenas muda o modo de conexão para 'manual'.
			// Não apaga mais 'wpwevo_managed_api_key' nem 'wpwevo_auto_configured'.
			// Isso preserva a ligação com a conta 'managed' e permite ao usuário
			// voltar para a aba de teste grátis para ver seu status ou pagar.
			update_option('wpwevo_connection_mode', 'manual');

			// Limpa os campos da API no modo manual para evitar que as credenciais do modo
			// gerenciado sejam usadas acidentalmente no modo manual.
			update_option('wpwevo_api_url', '');
			update_option('wpwevo_manual_api_key', '');
			update_option('wpwevo_instance', '');
			
			// Redireciona para a aba de conexão para aplicar as mudanças visualmente.
			wp_redirect(admin_url('admin.php?page=wpwevo-settings&tab=connection&manual_activated=1'));
			exit;
		}

		// 🚀 NOVO: Restaura configurações manuais anteriores
		if (isset($_GET['restore_manual']) && check_admin_referer('wpwevo_restore_manual')) {
			$previous_config = get_option('wpwevo_previous_manual_config');
			if ($previous_config) {
				update_option('wpwevo_connection_mode', 'manual');
				update_option('wpwevo_api_url', $previous_config['api_url']);
				update_option('wpwevo_manual_api_key', $previous_config['api_key']);
				update_option('wpwevo_instance', $previous_config['instance']);
				delete_option('wpwevo_managed_api_key');
				delete_option('wpwevo_auto_configured');
				wp_redirect(admin_url('admin.php?page=wpwevo-settings&restored=1'));
				exit;
			}
		}

		?>
		
		<!-- Cards de Configuração -->
		<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
			
			<!-- Card 1: Configuração da API -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 20px;">
						<div style="background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🔗</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Configuração da Evolution API</h3>
					</div>

					<?php if ($is_managed): ?>
						<?php if ($is_trial_expired): ?>
							<!-- 🚀 NOVO: Aviso de trial expirado -->
							<div style="padding: 15px; background: #fef3c7; border: 1px solid #f59e0b; border-left-width: 4px; border-left-color: #f59e0b; border-radius: 8px; margin-bottom: 20px;">
								<h4 style="margin: 0 0 5px 0; color: #92400e; font-size: 16px;">⏰ Trial Expirado</h4>
								<p style="margin: 0; color: #92400e; font-size: 14px;">
									Seu período de teste gratuito expirou. Você pode renovar sua conta ou voltar para configuração manual.
								</p>
								<div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
									<a href="<?php echo esc_url(admin_url('admin.php?page=wpwevo-settings&tab=quick-signup')); ?>" 
									   style="color: #1d4ed8; font-size: 12px; text-decoration: underline;">
										💳 Ativar plano pago
									</a>
									<?php if ($has_previous_manual): ?>
										<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpwevo-settings&restore_manual=1'), 'wpwevo_restore_manual')); ?>" 
										   style="color: #1d4ed8; font-size: 12px; text-decoration: underline;">
											🔙 Restaurar configuração manual anterior
										</a>
									<?php endif; ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpwevo-settings&force_manual_mode=1'), 'wpwevo_force_manual')); ?>" 
                                   onclick="return confirm('<?php _e('Tem certeza que deseja mudar para o modo manual? Isso pode desconectar sua instância atual se não for reconfigurado corretamente.', 'wp-whatsevolution'); ?>');"
									   style="color: #1d4ed8; font-size: 12px; text-decoration: underline;">
										⚙️ Configurar manualmente
									</a>
								</div>
							</div>
						<?php else: ?>
							<!-- Modo managed ativo -->
							<div style="padding: 15px; background: #f0fdf4; border: 1px solid #bbf7d0; border-left-width: 4px; border-left-color: #4ade80; border-radius: 8px; margin-bottom: 20px;">
								<h4 style="margin: 0 0 5px 0; color: #166534; font-size: 16px;">🚀 Modo de Configuração Automática Ativado</h4>
								<p style="margin: 0; color: #15803d; font-size: 14px;">
									O plugin foi configurado automaticamente através da aba "Teste Grátis". As configurações abaixo não podem ser editadas.
								</p>
                                 <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpwevo-settings&force_manual_mode=1'), 'wpwevo_force_manual')); ?>" 
                                   onclick="return confirm('<?php _e('Tem certeza que deseja mudar para o modo manual? Isso pode desconectar sua instância atual se não for reconfigurado corretamente.', 'wp-whatsevolution'); ?>');"
								   style="color: #1d4ed8; font-size: 12px; text-decoration: underline; margin-top: 10px; display: inline-block;">
                                    <?php _e('Clique aqui para configurar manualmente', 'wp-whatsevolution'); ?>
								</a>
							</div>
						<?php endif; ?>
					<?php endif; ?>
					
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wpwevo-settings-form">
						<?php settings_fields('wpwevo_settings'); ?>
						
						<div style="display: grid; gap: 20px;">
							<!-- URL da API -->
							<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">🌐 URL da API</label>
								<input type="url" name="api_url" 
									   value="<?php echo esc_attr(get_option('wpwevo_api_url', '')); ?>" 
									   style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;" required
									   placeholder="https://sua-api.exemplo.com" <?php disabled($is_managed, true); ?>>
								<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 12px;">
									URL completa onde a Evolution API está instalada
								</p>
							</div>
							
							<!-- API Key -->
							<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">🔑 API KEY</label>
								<input type="text" name="api_key" 
									   value="<?php echo esc_attr(Api_Connection::get_active_api_key()); ?>" 
									   style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;" required
									   placeholder="Sua chave de API" <?php disabled($is_managed, true); ?>>
								<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 12px;">
									Chave de API gerada nas configurações da Evolution API
								</p>
							</div>
							
							<!-- Nome da Instância -->
							<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">📱 Nome da Instância</label>
								<input type="text" name="instance" 
									   value="<?php echo esc_attr(get_option('wpwevo_instance', '')); ?>" 
									   style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; font-size: 14px;" required
									   placeholder="Nome da sua instância" <?php disabled($is_managed, true); ?>>
								<p style="margin: 8px 0 0 0; color: #4a5568; font-size: 12px;">
									Nome da instância criada na Evolution API
								</p>
							</div>
						</div>
						
						<!-- Botões de Ação -->
						<div style="margin-top: 20px; display: flex; gap: 10px; align-items: center;">
							<button type="submit" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px 24px; font-size: 14px; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);" <?php disabled($is_managed, true); ?>>
								💾 Salvar Configurações
							</button>
							
							<?php if ($api->is_configured()): ?>
								<a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=wpwevo_test_connection'), 'wpwevo_test_connection'); ?>" 
								   style="background: #4a5568; color: white; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-size: 14px;">
									🧪 Testar Conexão
								</a>
							<?php endif; ?>
							
							<span class="spinner"></span>
						</div>
						
										<?php if ($connection_status): ?>
					<div style="margin-top: 15px; padding: 12px; border-radius: 8px; background: <?php echo $connection_status['success'] ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $connection_status['success'] ? '#c3e6cb' : '#f5c6cb'; ?>; color: <?php echo $connection_status['success'] ? '#155724' : '#721c24'; ?>;">
						<span style="font-size: 16px; margin-right: 8px;"><?php echo $connection_status['success'] ? '✅' : '❌'; ?></span>
						<?php echo esc_html($connection_status['message']); ?>
					</div>
					
					<?php if ($connection_status['success'] && isset($connection_status['api_version']) && !$connection_status['api_version']['is_v2']): ?>
						<div style="margin-top: 10px; padding: 12px; border-radius: 8px; background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
							<span style="font-size: 16px; margin-right: 8px;">⚠️</span>
							<strong>ATENÇÃO:</strong> NOSSO PLUGIN NÃO É COMPATÍVEL com Evolution API V1 (versão <?php echo esc_html($connection_status['api_version']['version']); ?>). 
							Atualize para a V2 para garantir funcionamento completo.
						</div>
					<?php endif; ?>
				<?php endif; ?>
						
						<div id="wpwevo-validation-result" style="display: none; margin-top: 15px;"></div>
					</form>
				</div>
			</div>

		</div>
		<?php
	}

	private function render_help_tab() {
		?>
		<!-- Cards de Documentação Completa -->
		<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
			
			<!-- Card 1: Configuração Inicial -->
			<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #4facfe; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🚀</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Configuração Inicial</h3>
					</div>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">🔧 Requisitos</h4>
							<ul style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;">
								<li>PHP 7.4 ou superior</li>
								<li>WordPress 5.8+</li>
								<li>WooCommerce 5.0+</li>
								<li>Evolution API v2.0+</li>
							</ul>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #4facfe;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">⚙️ Passos de Configuração</h4>
							<ol style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;">
								<li>Configure sua Evolution API</li>
								<li>Insira URL, API Key e Instância</li>
								<li>Teste a conexão</li>
								<li>Ative as funcionalidades desejadas</li>
							</ol>
						</div>
					</div>
				</div>
			</div>

			<!-- Card 2: Carrinho Abandonado -->
			<div style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(168, 237, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #a8edea; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🛒</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Carrinho Abandonado (NOVO!)</h3>
					</div>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #a8edea;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">✨ Funcionalidades</h4>
							<ul style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;">
								<li>⚡ Interceptação interna automática</li>
								<li>🔒 100% seguro (dados não saem do servidor)</li>
								<li>🎯 Zero configuração de webhook</li>
								<li>📱 Templates personalizáveis</li>
								<li>🏷️ Shortcodes dinâmicos</li>
							</ul>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #a8edea;">
							<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 16px;">🔧 Como Usar</h4>
							<ol style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;">
								<li>Instale "WooCommerce Cart Abandonment Recovery"</li>
								<li>Ative em "Carrinho Abandonado"</li>
								<li>Personalize a mensagem</li>
								<li>Monitore através dos logs</li>
							</ol>
						</div>
					</div>
				</div>
			</div>

			<!-- Card 3: Shortcodes Disponíveis -->
			<div style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(255, 236, 210, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #ffecd2; color: #2d3748; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🏷️</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Shortcodes Disponíveis</h3>
					</div>
					<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{first_name}</code>
							<small style="color: #4a5568;">Nome do cliente</small>
						</div>
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{full_name}</code>
							<small style="color: #4a5568;">Nome completo</small>
						</div>
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{product_names}</code>
							<small style="color: #4a5568;">Produtos no carrinho</small>
						</div>
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{cart_total}</code>
							<small style="color: #4a5568;">Valor formatado (R$ 99,90)</small>
						</div>
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{checkout_url}</code>
							<small style="color: #4a5568;">Link finalizar compra</small>
						</div>
						<div style="background: #f7fafc; padding: 12px; border-radius: 6px; text-align: center;">
							<code style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: block; margin-bottom: 5px;">{coupon_code}</code>
							<small style="color: #4a5568;">Código do cupom</small>
						</div>
					</div>
				</div>
			</div>

			<!-- Card 4: Todas as Funcionalidades -->
			<div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(240, 147, 251, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #f093fb; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">⭐</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Todas as Funcionalidades</h3>
					</div>
					<div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px;">
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #f093fb;">
							<h4 style="margin: 0 0 8px 0; color: #2d3748; font-size: 14px;">🛒 Carrinho Abandonado</h4>
							<ul style="margin: 0; padding-left: 15px; color: #4a5568; font-size: 12px;">
								<li>Interceptação automática</li>
								<li>Templates personalizáveis</li>
								<li>Logs em tempo real</li>
							</ul>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #f093fb;">
							<h4 style="margin: 0 0 8px 0; color: #2d3748; font-size: 14px;">📊 Envio por Status</h4>
							<ul style="margin: 0; padding-left: 15px; color: #4a5568; font-size: 12px;">
								<li>Automação por status</li>
								<li>Templates por status</li>
								<li>Variáveis dinâmicas</li>
							</ul>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #f093fb;">
							<h4 style="margin: 0 0 8px 0; color: #2d3748; font-size: 14px;">📱 Envio Individual</h4>
							<ul style="margin: 0; padding-left: 15px; color: #4a5568; font-size: 12px;">
								<li>Interface simples</li>
								<li>Validação automática</li>
								<li>Histórico completo</li>
							</ul>
						</div>
						<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #f093fb;">
							<h4 style="margin: 0 0 8px 0; color: #2d3748; font-size: 14px;">📢 Envio em Massa</h4>
							<ul style="margin: 0; padding-left: 15px; color: #4a5568; font-size: 12px;">
								<li>Filtros avançados</li>
								<li>Importação CSV</li>
								<li>Controle de velocidade</li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Card 5: Template Padrão -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; margin-bottom: 15px;">
						<div style="background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">📝</div>
						<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Template Padrão Brasileiro</h3>
					</div>
					<div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
						<pre style="background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.4; margin: 0; white-space: pre-wrap;">🛒 Oi {first_name}!

Vi que você adicionou estes itens no carrinho:
📦 {product_names}

💰 Total: {cart_total}

🎁 Use o cupom *{coupon_code}* e ganhe desconto especial!
⏰ Mas corre que é só por hoje!

Finalize agora:
👆 {checkout_url}</pre>
					</div>
				</div>
			</div>

		</div>
		
		<!-- Card de Suporte -->
		<div style="margin-top: 20px;">
			<div style="background: linear-gradient(135deg, #38b2ac 0%, #319795 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(56, 178, 172, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
					<div style="display: flex; align-items: center; justify-content: space-between;">
						<div style="display: flex; align-items: center;">
							<div style="background: #38b2ac; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🆘</div>
							<div>
								<h3 style="margin: 0; color: #2d3748; font-size: 18px;">Precisa de Suporte?</h3>
								<p style="margin: 5px 0 0 0; color: #4a5568; font-size: 14px;">Entre em contato conosco para tirar suas dúvidas sobre qualquer funcionalidade</p>
							</div>
						</div>
						<a href="mailto:chatrelaxbr@gmail.com" style="background: #38b2ac; color: white; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 8px;">
							📧 Enviar Email
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_quick_signup_tab() {
		$is_auto_configured = Quick_Signup::is_auto_configured();
		$is_trial_expired = Quick_Signup::should_show_upgrade_modal();
		$current_user_email = get_option('wpwevo_user_email', '');
		?>
		<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
			
			<?php if (!$is_auto_configured): ?>
			<!-- Formulário de Teste Grátis -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
					<div style="text-align: center; margin-bottom: 30px;">
						<h2 style="margin: 0 0 10px 0; color: #2d3748; font-size: 28px;">
							<?php echo $current_user_email ? '💳 Ativar Plano Pago' : '🚀 Teste Grátis por 7 Dias'; ?>
						</h2>
						<p style="margin: 0; color: #4a5568; font-size: 16px; line-height: 1.5;">
							<?php if ($current_user_email): ?>
								Detectamos que você já tem uma conta. Ative seu plano pago para continuar usando:
							<?php else: ?>
								Não tem Evolution API? Sem problema! Teste nossa solução completa:
							<?php endif; ?>
						</p>
					</div>
					
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
						<div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
							<div style="font-size: 24px; margin-bottom: 10px;">⚡</div>
							<h4 style="margin: 0 0 8px 0; color: #2d3748;">Sem VPS, sem Docker</h4>
							<p style="margin: 0; color: #4a5568; font-size: 14px;">Sem complicação técnica</p>
						</div>
						<div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
							<div style="font-size: 24px; margin-bottom: 10px;">🔧</div>
							<h4 style="margin: 0 0 8px 0; color: #2d3748;">Configuração automática</h4>
							<p style="margin: 0; color: #4a5568; font-size: 14px;">Em 30 segundos</p>
						</div>
						<div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
							<div style="font-size: 24px; margin-bottom: 10px;">🛠️</div>
							<h4 style="margin: 0 0 8px 0; color: #2d3748;">Suporte técnico</h4>
							<p style="margin: 0; color: #4a5568; font-size: 14px;">Incluído no teste</p>
						</div>
						<div style="background: #f7fafc; padding: 20px; border-radius: 10px; text-align: center;">
							<div style="font-size: 24px; margin-bottom: 10px;">💳</div>
							<h4 style="margin: 0 0 8px 0; color: #2d3748;">7 dias grátis</h4>
							<p style="margin: 0; color: #4a5568; font-size: 14px;">Sem cartão de crédito</p>
						</div>
					</div>

					<!-- Formulário -->
					<form id="wpwevo-quick-signup-form" style="max-width: 500px; margin: 0 auto;">
						<div style="display: grid; gap: 20px;">
							<div>
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">👤 Nome completo</label>
								<input type="text" id="wpwevo-name" required
									   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;"
									   placeholder="Seu nome completo" value="<?php echo esc_attr(get_option('wpwevo_user_name', '')); ?>">
								<div id="name-error" style="color: #e53e3e; font-size: 12px; margin-top: 5px; display: none;"></div>
							</div>
							
							<div>
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">📧 Email</label>
								<input type="email" id="wpwevo-email" required
									   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;"
									   placeholder="seu@email.com" value="<?php echo esc_attr($current_user_email); ?>">
								<div id="email-error" style="color: #e53e3e; font-size: 12px; margin-top: 5px; display: none;"></div>
							</div>
							
							<div>
								<label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2d3748;">📱 WhatsApp</label>
								<input type="tel" id="wpwevo-whatsapp" required
									   style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 16px;"
									   placeholder="(11) 99999-9999" value="<?php echo esc_attr(get_option('wpwevo_user_whatsapp', '')); ?>">
								<div id="whatsapp-error" style="color: #e53e3e; font-size: 12px; margin-top: 5px; display: none;"></div>
							</div>
						</div>
						
						<button type="submit" id="wpwevo-signup-btn" disabled
								style="width: 100%; margin-top: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 15px; font-size: 16px; font-weight: 600; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
							<?php echo $current_user_email ? '💳 Ativar Plano Pago' : '🚀 Criar Conta e Testar Agora'; ?>
						</button>
					</form>
				</div>
			</div>
			<?php endif; ?>

			<!-- Container de Progresso -->
			<div id="wpwevo-progress-container" style="display: none;">
				<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
						<div style="text-align: center; margin-bottom: 30px;">
							<h3 style="margin: 0; color: #2d3748; font-size: 20px;">Criando sua conta...</h3>
						</div>
						
						<!-- Steps -->
						<div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
							<div class="wpwevo-step" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; background: #f7fafc; margin: 0 5px;">
								<div style="width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;">1</div>
								<span style="font-size: 12px; color: #4a5568;">Validando dados</span>
							</div>
							<div class="wpwevo-step" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; background: #f7fafc; margin: 0 5px;">
								<div style="width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;">2</div>
								<span style="font-size: 12px; color: #4a5568;">Criando conta</span>
							</div>
							<div class="wpwevo-step" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; background: #f7fafc; margin: 0 5px;">
								<div style="width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;">3</div>
								<span style="font-size: 12px; color: #4a5568;">Configurando plugin</span>
							</div>
							<div class="wpwevo-step" style="flex: 1; text-align: center; padding: 10px; border-radius: 8px; background: #f7fafc; margin: 0 5px;">
								<div style="width: 30px; height: 30px; border-radius: 50%; background: #e2e8f0; margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 14px;">✅</div>
								<span style="font-size: 12px; color: #4a5568;">Pronto!</span>
							</div>
						</div>
						
						<!-- Barra de Progresso -->
						<div style="background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 20px;">
							<div id="wpwevo-progress-bar" style="width: 0%; height: 8px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); transition: width 0.3s ease;"></div>
						</div>
						
						<div style="text-align: center;">
							<p id="wpwevo-progress-text" style="margin: 0; color: #4a5568; font-size: 14px;">Iniciando...</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Container de Sucesso -->
			<div id="wpwevo-success-container" style="display: none;">
				<div style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(72, 187, 120, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
						<div style="text-align: center; margin-bottom: 30px;">
							<div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
							<h2 style="margin: 0 0 10px 0; color: #2d3748; font-size: 24px;">Sua conta de teste está ativa!</h2>
							<p style="margin: 0; color: #4a5568; font-size: 16px;">
								⏰ Trial expira em <strong id="trial-days-left">7</strong> dias<br>
								Aproveite para testar todas as funcionalidades!
							</p>
						</div>
						
						<!-- QR Code -->
						<div id="wpwevo-qr-container" style="display: none; text-align: center; margin-bottom: 30px;">
							<h3 style="margin: 0 0 15px 0; color: #2d3748;">📱 Conecte seu WhatsApp</h3>
							<div style="background: #f7fafc; padding: 20px; border-radius: 10px; display: inline-block;">
								<iframe id="wpwevo-qr-iframe" width="300" height="300" style="border: none; border-radius: 8px;"></iframe>
							</div>
							<p style="margin: 10px 0 0 0; color: #4a5568; font-size: 14px;">
								<span id="connection-indicator">⏳ Aguardando conexão...</span>
							</p>
							<div id="whatsapp-status" class="disconnected"></div>
						</div>
						
						<!-- Próximos Passos -->
						<div style="background: #f7fafc; padding: 20px; border-radius: 10px;">
							<h3 style="margin: 0 0 15px 0; color: #2d3748;">📋 Próximos passos:</h3>
							<div style="display: grid; gap: 10px;">
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="color: #48bb78; font-size: 16px;">✅</span>
									<span style="color: #4a5568;">Conta criada e plugin configurado</span>
								</div>
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="color: #4a5568; font-size: 16px;">🔗</span>
									<span style="color: #4a5568;">Conectar seu WhatsApp (escaneie o QR acima)</span>
								</div>
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="color: #4a5568; font-size: 16px;">📱</span>
									<span style="color: #4a5568;">Testar envio de mensagem</span>
								</div>
								<div style="display: flex; align-items: center; gap: 10px;">
									<span style="color: #4a5568; font-size: 16px;">🛒</span>
									<span style="color: #4a5568;">Configurar carrinho abandonado</span>
								</div>
							</div>
						</div>
						
						<div style="display: flex; gap: 15px; justify-content: center; align-items: center; flex-wrap: wrap;">
							<!-- O botão de upgrade foi movido para o modal -->
						</div>
						
						<!-- Estados do pagamento (agora dentro do modal) -->
						<div id="wpwevo-payment-loading" style="display: none; text-align: center; margin-top: 15px;">
							<p style="color: #4a5568;">⏳ Criando pagamento...</p>
						</div>
						
						<div id="wpwevo-payment-success" style="display: none; text-align: center; margin-top: 15px;">
							<p style="color: #155724;">✅ Pagamento criado! Redirecionando...</p>
						</div>
						
						<div id="wpwevo-payment-error" style="display: none; text-align: center; margin-top: 15px;">
							<p style="color: #721c24;">❌ Erro no pagamento. Tente novamente.</p>
						</div>

					</div>
				</div>
			</div>

			<!-- Container de Erro -->
			<div id="wpwevo-error-container" style="display: none;">
				<div style="background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(245, 101, 101, 0.2); overflow: hidden;">
					<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
						<div style="text-align: center;">
							<div style="font-size: 48px; margin-bottom: 15px;">😞</div>
							<h3 style="margin: 0 0 15px 0; color: #2d3748;">Ops! Algo deu errado</h3>
							<p id="wpwevo-error-message" style="margin: 0 0 20px 0; color: #4a5568;"></p>
							<button id="wpwevo-retry-btn" style="background: #f56565; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 14px; cursor: pointer;">
								🔄 Tentar novamente
							</button>
						</div>
					</div>
				</div>
			</div>

			<?php if ($is_auto_configured): ?>
			<!-- Status do Trial (agora renderizado dinamicamente via JS) -->
			<div id="wpwevo-status-container" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(168, 237, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
					<div style="text-align: center;">
						<div style="font-size: 48px; margin-bottom: 15px;">⏰</div>
						<h2 id="connection-status-message" style="margin: 0 0 10px 0; color: #2d3748; font-size: 24px;">Carregando status...</h2>
						<p id="trial-days-left-container" style="margin: 0 0 20px 0; color: #4a5568; font-size: 16px;">
							Aguarde um instante...
						</p>
						<!-- AVISO ESTÁTICO DE ACESSO AO PAINEL -->
						<div id="wpwevo-dashboard-static-info" style="background: #e0f2fe; border: 1px solid #38bdf8; border-radius: 10px; padding: 18px 24px; margin: 0 auto 24px auto; max-width: 480px; text-align: left; box-shadow: 0 2px 8px rgba(56,189,248,0.08);">
							<h3 style="margin: 0 0 10px 0; color: #0ea5e9; font-size: 18px; display: flex; align-items: center; gap: 8px;">
								<span style="font-size: 22px;">🔑</span> Acesse seu Painel de Controle
							</h3>
							<div style="font-size: 15px; color: #0369a1; margin-bottom: 8px;">
								Para acessar sua dashboard, utilize o link enviado para o seu WhatsApp.<br>
								<span style="color: #64748b; font-size: 13px;">Utilize o email e senha cadastrados.</span>
							</div>
						</div>
						<!-- BOTÃO DE UPGRADE DENTRO DO CARD -->
						<button id="wpwevo-upgrade-btn-from-status" onclick="showUpgradeModal()" style="display: none; background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 600; margin-bottom: 15px;">
							💳 Fazer Upgrade Agora
						</button>

						<div id="wpwevo-trial-expired-notice" style="display: none; background: #f8d7da; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
							<span style="color: #721c24;">⚠️ Trial expirado! Faça upgrade para continuar usando.</span>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

		</div>

		<style>
		.wpwevo-step.active {
			background: #e6fffa !important;
		}
		.wpwevo-step.active > div {
			background: #38b2ac !important;
			color: white !important;
		}
		.wpwevo-step.completed > div {
			background: #48bb78 !important;
			color: white !important;
		}
		input.error {
			border-color: #e53e3e !important;
		}
		#wpwevo-signup-btn:disabled {
			background: #cbd5e0 !important;
			cursor: not-allowed !important;
		}
		.wpwevo-step {
			transition: all 0.3s ease;
		}
		</style>
		<?php
	}
} 