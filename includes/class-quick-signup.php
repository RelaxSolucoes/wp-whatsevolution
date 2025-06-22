<?php
namespace WpWhatsAppEvolution;

/**
 * Classe para gerenciar o sistema de onboarding 1-click
 */
class Quick_Signup {
	private static $instance = null;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Handlers AJAX
		add_action('wp_ajax_wpwevo_quick_signup', [$this, 'handle_quick_signup']);
		add_action('wp_ajax_wpwevo_check_plugin_status', [$this, 'handle_check_status']);
		add_action('wp_ajax_wpwevo_create_payment', [$this, 'handle_create_payment']);
		add_action('wp_ajax_wpwevo_sync_status', [$this, 'handle_sync_status']);
		
		// Enqueue scripts específicos para quick signup
		add_action('admin_enqueue_scripts', [$this, 'enqueue_quick_signup_assets']);

		// Adiciona hook para resetar o trial
		add_action('admin_init', [$this, 'maybe_reset_trial']);
	}

	/**
	 * 🚀 NOVO: Sincroniza o status do backend com o banco de dados do WordPress.
	 * Isso garante que os dados locais (usados pela aba 'Conexão') estejam sempre atualizados.
	 */
	public function handle_sync_status() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}
			
			// jQuery envia os dados como um array associativo
			$status_data = isset($_POST['status_data']) && is_array($_POST['status_data']) ? $_POST['status_data'] : null;

			if (empty($status_data)) {
				throw new \Exception(__('Dados de status ausentes ou em formato incorreto.', 'wp-whatsapp-evolution'));
			}

			// Sincroniza a data de expiração
			if (isset($status_data['trial_expires_at'])) {
				$sanitized_date = sanitize_text_field($status_data['trial_expires_at']);
				update_option('wpwevo_trial_expires_at', $sanitized_date);
			}

			// Sincroniza o nome do plano (se existir na resposta)
			if (isset($status_data['plan']['name'])) {
				$sanitized_plan = sanitize_text_field($status_data['plan']['name']);
				update_option('wpwevo_plan_name', $sanitized_plan);
			}

			wp_send_json_success([
				'message' => 'Status sincronizado com sucesso.'
			]);

		} catch (\Exception $e) {
			wpwevo_log('error', 'Erro ao sincronizar status: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	public function maybe_reset_trial() {
		if (
			isset($_GET['wpwevo_reset_trial']) && 
			$_GET['wpwevo_reset_trial'] === 'true' && 
			current_user_can('manage_options') &&
			isset($_GET['_wpnonce']) && 
			wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'wpwevo_reset_trial_nonce')
		) {
			// 🚀 LIMPEZA COMPLETA: Remove TODOS os dados salvos durante o quick-signup
			
			// Configurações básicas
			delete_option('wpwevo_api_url');
			delete_option('wpwevo_api_key');
			delete_option('wpwevo_managed_api_key');
			delete_option('wpwevo_instance');
			delete_option('wpwevo_auto_configured');
			delete_option('wpwevo_trial_started_at');
			delete_option('wpwevo_trial_expires_at');
			delete_option('wpwevo_plan_name'); // Limpa o nome do plano também
			
			// Modo de conexão
			delete_option('wpwevo_connection_mode');
			
			// Dados do usuário (salvos no quick-signup)
			delete_option('wpwevo_user_id');
			delete_option('wpwevo_user_email');
			delete_option('wpwevo_user_name');
			delete_option('wpwevo_user_whatsapp');
			
			// Credenciais da API (salvas no quick-signup)
			delete_option('wpwevo_api_key');
			
			// Dados de acesso ao dashboard (salvos no quick-signup)
			delete_option('wpwevo_dashboard_url');
			delete_option('wpwevo_dashboard_email');
			delete_option('wpwevo_dashboard_password');
			
			// Transients
			delete_transient('wpwevo_connection_status');
			delete_transient('wpwevo_instance_status');
			
			// Log da limpeza
			error_log('WP WhatsApp Evolution - Configuração de teste resetada completamente');
			
			wp_redirect(admin_url('admin.php?page=wpwevo-settings&tab=quick-signup&reset_success=1'));
			exit;
		}
	}

	public function enqueue_quick_signup_assets($hook) {
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		error_log('WP WhatsApp Evolution - Enqueueing quick signup assets for hook: ' . $hook);

		wp_enqueue_script(
			'wpwevo-quick-signup',
			WPWEVO_URL . 'assets/js/quick-signup.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		$api_key = get_option('wpwevo_managed_api_key', '');

		wp_localize_script('wpwevo-quick-signup', 'wpwevo_quick_signup', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_quick_signup'),
			'should_show_upgrade_modal' => Quick_Signup::should_show_upgrade_modal(),
			'is_trial_expired' => self::should_show_upgrade_modal(),
			'api_key' => $api_key,
			'messages' => [
				'validating' => __('Validando dados...', 'wp-whatsapp-evolution'),
				'creating_account' => __('Criando conta...', 'wp-whatsapp-evolution'),
				'configuring_plugin' => __('Configurando plugin...', 'wp-whatsapp-evolution'),
				'success' => __('Pronto! ✅', 'wp-whatsapp-evolution'),
				'error' => __('Ops! Algo deu errado.', 'wp-whatsapp-evolution'),
				'retry' => __('Tentar novamente', 'wp-whatsapp-evolution')
			]
		]);
	}

	/**
	 * Handler AJAX para o processo de onboarding 1-click
	 * 🚀 OTIMIZADO: Esta função agora centraliza a chamada à Edge Function E o salvamento dos dados.
	 */
	public function handle_quick_signup() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}
			
			$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
			$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
			$whatsapp = isset($_POST['whatsapp']) ? wpwevo_validate_phone($_POST['whatsapp']) : '';

			if (empty($name) || empty($email) || empty($whatsapp)) {
				throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
			}

			// 🚀 NOVO: Detecta se é uma renovação de conta managed
			$is_renewal = false;
			$previous_managed_email = get_option('wpwevo_user_email', '');
			$previous_manual_config = null;
			
			if ($previous_managed_email === $email) {
				$is_renewal = true; // Indica processo de pagamento (não renovação do trial gratuito)
				// Preserva configurações manuais anteriores se existirem
				if (get_option('wpwevo_connection_mode') === 'manual') {
					$previous_manual_config = [
						'api_url' => get_option('wpwevo_api_url'),
						'api_key' => get_option('wpwevo_manual_api_key'),
						'instance' => get_option('wpwevo_instance')
					];
				}
			}
			
			$payload = [
				'name' => $name,
				'email' => $email,
				'whatsapp' => $whatsapp,
				'source' => 'wordpress-plugin',
				'plugin_version' => WPWEVO_VERSION,
				'is_renewal' => $is_renewal
			];

			$response = $this->call_edge_function('quick-signup', $payload);

			if ($response['success'] === false || !isset($response['data'])) {
				$error_message = $response['message'] ?? __('Erro desconhecido ao criar conta.', 'wp-whatsapp-evolution');
				throw new \Exception($error_message);
			}

			// Resposta da Edge Function
			$api_data = $response['data'];

			// 🚀 CORREÇÃO: Garante que estamos acessando o objeto de dados correto, mesmo se estiver aninhado.
			if (isset($api_data['success']) && $api_data['success'] === true && isset($api_data['data'])) {
				error_log('WP WhatsApp Evolution - Corrigindo aninhamento da resposta da API.');
				$api_data = $api_data['data'];
			}

			// -----------------------------------------------------------------
			// 🚀 CENTRALIZADO: Salvando TODAS as configurações aqui
			// -----------------------------------------------------------------
			update_option('wpwevo_connection_mode', 'managed');
			update_option('wpwevo_auto_configured', true);

			// Dados do usuário
			update_option('wpwevo_user_id', sanitize_text_field($api_data['dashboard_access']['user_id'] ?? ''));
			update_option('wpwevo_user_email', $email);
			update_option('wpwevo_user_name', $name);
			update_option('wpwevo_user_whatsapp', $whatsapp);

			// Credenciais da API
			$api_key = sanitize_text_field($api_data['api_key']);
			update_option('wpwevo_managed_api_key', $api_key);
			update_option('wpwevo_api_url', esc_url_raw($api_data['api_url']));
			update_option('wpwevo_instance', sanitize_text_field($api_data['instance_name']));
			
			// 🚀 NOVO: Preserva configurações manuais anteriores para possível retorno
			if ($previous_manual_config) {
				update_option('wpwevo_previous_manual_config', $previous_manual_config);
			}
			
			// 🚀 NOVO: Limpa configurações manuais antigas para evitar confusão
			delete_option('wpwevo_manual_api_key');
			
			// Dados do Trial
			update_option('wpwevo_trial_expires_at', sanitize_text_field($api_data['trial_expires_at']));
			
			// Dados de Acesso ao Dashboard
			if (isset($api_data['dashboard_access']) && is_array($api_data['dashboard_access'])) {
				update_option('wpwevo_dashboard_url', esc_url_raw($api_data['dashboard_access']['url'] ?? ''));
				update_option('wpwevo_dashboard_email', sanitize_email($api_data['dashboard_access']['email'] ?? ''));
				update_option('wpwevo_dashboard_password', sanitize_text_field($api_data['dashboard_access']['password'] ?? ''));
			}
			// -----------------------------------------------------------------

			// Envia o payload completo para o JS, que precisa dele para a UI.
			wp_send_json_success($api_data);

		} catch (\Exception $e) {
			wpwevo_log('error', 'Erro no Quick Signup: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Handler AJAX para verificar status do plugin/instância
	 */
	public function handle_check_status() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			// 🚀 CORREÇÃO: Buscar no campo correto
			$api_key = get_option('wpwevo_managed_api_key', '');
			
			// Logs para debug
			error_log('WP WhatsApp Evolution - Check Status: Verificando api_key...');
			error_log('WP WhatsApp Evolution - Campo wpwevo_managed_api_key existe: ' . (get_option('wpwevo_managed_api_key') ? 'SIM' : 'NÃO'));
			error_log('WP WhatsApp Evolution - Valor da api_key: ' . (empty($api_key) ? 'VAZIO' : substr($api_key, 0, 10) . '...'));
			error_log('WP WhatsApp Evolution - Tamanho da api_key: ' . strlen($api_key));
			
			if (empty($api_key)) {
				throw new \Exception(__('Plugin não configurado.', 'wp-whatsapp-evolution'));
			}

			error_log('WP WhatsApp Evolution - Verificando status via Edge Function plugin-status');
			error_log('WP WhatsApp Evolution - API Key: ' . substr($api_key, 0, 10) . '...');

			// Chama a Edge Function plugin-status que já existe
			$response = $this->call_edge_function('plugin-status', [
				'api_key' => $api_key
			]);

			error_log('WP WhatsApp Evolution - Resposta bruta da Edge Function: ' . json_encode($response));

			if (!$response['success'] || !isset($response['data']['success']) || !$response['data']['success']) {
				$error_message = $response['data']['error'] ?? $response['error'] ?? __('Erro ao verificar status.', 'wp-whatsapp-evolution');
				error_log('WP WhatsApp Evolution - Erro na resposta da Edge Function: ' . $error_message);
				throw new \Exception($error_message);
			}

			$final_data = $response['data']['data'];
			error_log('WP WhatsApp Evolution - Dados finais para o JS: ' . json_encode($final_data));
			error_log('WP WhatsApp Evolution - Campo qr_code presente: ' . (isset($final_data['qr_code']) ? 'SIM' : 'NÃO'));
			if (isset($final_data['qr_code'])) {
				error_log('WP WhatsApp Evolution - Tamanho do qr_code: ' . strlen($final_data['qr_code']));
			}

			// Retorna apenas o payload final para o JS
			wp_send_json_success($final_data);

		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao verificar status: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Chama uma Edge Function do Supabase
	 */
	private function call_edge_function($function_name, $data = []) {
		$url = "https://ydnobqsepveefiefmxag.supabase.co/functions/v1/{$function_name}";
		
		$headers = [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY // ANON KEY sempre
		];

		$body = json_encode($data);

		// Adiciona log detalhado se o debug estiver ativo
		if (get_option('wpwevo_debug_enabled', false)) {
			wpwevo_log('info', 'Chamando Edge Function: ' . $function_name);
			wpwevo_log('info', 'URL: ' . $url);
			wpwevo_log('info', 'Headers: ' . json_encode($headers));
			wpwevo_log('info', 'Body: ' . $body);
		}

		$timeout = ($function_name === 'quick-signup') ? WHATSEVOLUTION_TIMEOUT : WHATSEVOLUTION_STATUS_TIMEOUT;

		$response = wp_remote_post($url, [
			'headers' => $headers,
			'body' => $body,
			'timeout' => $timeout,
			'sslverify' => false
		]);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			wpwevo_log('error', 'Erro WP: ' . $error_message);
			return [
				'success' => false,
				'error' => $error_message
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if (get_option('wpwevo_debug_enabled', false)) {
			wpwevo_log('info', 'Status Code: ' . $status_code);
			wpwevo_log('info', 'Resposta bruta: ' . $body);
		}
		
		$decoded_body = json_decode($body, true);

		if ($status_code !== 200) {
			$error_message = sprintf(__('Erro HTTP %d', 'wp-whatsapp-evolution'), $status_code);
			if (isset($decoded_body['error'])) {
				$error_message .= ': ' . $decoded_body['error'];
			} elseif (!empty($body)) {
				$error_message .= ' - ' . substr($body, 0, 100);
			}
			return [
				'success' => false,
				'error' => $error_message,
				'data' => $decoded_body
			];
		}

		if (json_last_error() !== JSON_ERROR_NONE) {
			return [
				'success' => false,
				'error' => __('Resposta inválida do servidor.', 'wp-whatsapp-evolution')
			];
		}

		return [
			'success' => $decoded_body['success'] ?? ($status_code === 200),
			'data' => $decoded_body,
			'error' => $decoded_body['error'] ?? null
		];
	}

	/**
	 * Verifica se o plugin foi configurado automaticamente
	 */
	public static function is_auto_configured() {
		return (bool) get_option('wpwevo_auto_configured', false);
	}

	/**
	 * Calcula dias restantes do trial
	 */
	public static function get_trial_days_left() {
		$trial_expires_at = get_option('wpwevo_trial_expires_at', '');
		
		if (empty($trial_expires_at)) {
			return 0;
		}

		$expires_timestamp = strtotime($trial_expires_at);
		$current_timestamp = current_time('timestamp');
		
		if ($expires_timestamp <= $current_timestamp) {
			return 0;
		}

		$diff_seconds = $expires_timestamp - $current_timestamp;
		return ceil($diff_seconds / (24 * 60 * 60));
	}

	/**
	 * Verifica se o trial ainda está ativo
	 */
	public static function is_trial_active() {
		return self::get_trial_days_left() > 0;
	}

	/**
	 * Handler AJAX para criar pagamento via plugin
	 */
	public function handle_create_payment() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			if (!self::is_auto_configured()) {
				throw new \Exception(__('Apenas usuários do teste grátis podem fazer upgrade.', 'wp-whatsapp-evolution'));
			}

			// Buscar dados do usuário salvos no wp_options (CONFORME MANUAL)
			$user_id = get_option('wpwevo_user_id');
			$email = get_option('wpwevo_user_email');
			$name = get_option('wpwevo_user_name');
			$whatsapp = get_option('wpwevo_user_whatsapp');

			if (empty($user_id) || empty($email) || empty($name) || empty($whatsapp)) {
				throw new \Exception(__('Dados do usuário (ID, email, nome ou WhatsApp) não encontrados. Por favor, tente resetar a configuração de teste e refazer o onboarding para corrigir.', 'wp-whatsapp-evolution'));
			}

			// Chamar Edge Function para criar pagamento
			$response = $this->call_edge_function(
				'create-payment-from-plugin',
				[
					'user_id' => $user_id,
					'email' => $email,
					'name' => $name,
					'whatsapp' => $whatsapp,
					'plan_type' => 'basic'
				]
			);

			if (!$response['success']) {
				$error_message = $response['error'] ?? 'Erro desconhecido ao criar pagamento.';
				if (isset($response['data']['error'])) {
					$error_message = $response['data']['error'];
				}
				throw new \Exception($error_message);
			}

			// 🚀 CORREÇÃO: Envia o objeto 'data' COMPLETO da Edge Function para o JS.
			// Adiciona também a api_key para permitir o polling de status no frontend.
			$final_data = $response['data']['data'];
			$api_key = get_option('wpwevo_managed_api_key');
			if ($api_key) {
				$final_data['api_key'] = $api_key;
			}
			wp_send_json_success($final_data);

		} catch (\Exception $e) {
			wpwevo_log('error', 'Erro ao criar pagamento: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Verifica se deve mostrar modal de upgrade
	 */
	public static function should_show_upgrade_modal() {
		// Só mostrar se:
		// 1. O modo for 'managed'
		// 2. O Trial expirou
		
		if (get_option('wpwevo_connection_mode') !== 'managed') {
			return false; // Não é usuário do nosso sistema
		}
		
		if (self::is_trial_active()) {
			return false; // Trial ainda ativo
		}
		
		return true; // Mostrar modal de upgrade
	}

	public function render_page_content() {
		$is_configured = self::is_auto_configured();
		
		if (!$is_configured) {
			$this->render_signup_form();
		} else {
			$this->render_status_view();
		}
	}
	
	private function render_signup_form() {
		$is_auto_configured = Quick_Signup::is_auto_configured();
		$is_trial_expired = self::should_show_upgrade_modal();
		$current_user_email = get_option('wpwevo_user_email', '');
		?>
		<div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
			
			<?php if (!$is_auto_configured): ?>
			<!-- Formulário de Teste Grátis -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
				<div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 30px;">
					<div style="text-align: center; margin-bottom: 30px;">
						<h2 style="margin: 0 0 10px 0; color: #2d3748; font-size: 28px;">🚀 Teste Grátis por 7 Dias</h2>
						<p style="margin: 0; color: #4a5568; font-size: 16px; line-height: 1.5;">
							Não tem Evolution API? Sem problema! Teste nossa solução completa:
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
									   placeholder="Seu nome completo">
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
									   placeholder="(11) 99999-9999">
								<div id="whatsapp-error" style="color: #e53e3e; font-size: 12px; margin-top: 5px; display: none;"></div>
							</div>
						</div>
						
						<button type="submit" id="wpwevo-signup-btn" disabled
								style="width: 100%; margin-top: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 15px; font-size: 16px; font-weight: 600; border-radius: 8px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
							<?php echo $current_user_email ? '🔄 Renovar Conta' : '🚀 Criar Conta e Testar Agora'; ?>
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
		</div>
		<?php
	}
	
	private function render_status_view() {
		?>
		<div id="wpwevo-status-container">
			<div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);">
				
				<div style="padding: 25px; text-align: center;">
					<img src="<?php echo esc_url(WPWEVO_URL . 'assets/images/icon-clock.png'); ?>" alt="Clock Icon" style="width: 60px; height: 60px; margin-bottom: 15px;">
					<h2 id="connection-status-message" style="margin: 0 0 10px 0; color: #1f2937; font-size: 24px; font-weight: 600;">
						Carregando status...
					</h2>
					<p style="margin: 0; color: #4b5563; font-size: 16px;" id="trial-days-left-container">
						Aguarde um instante.
					</p>
				</div>

				<div id="wpwevo-trial-expired-notice" style="background: #fef2f2; border-top: 1px solid #e5e7eb; padding: 15px 25px; display: none; align-items: center; gap: 10px;">
					<span style="color: #ef4444; font-size: 20px;">⚠️</span>
					<p style="margin: 0; color: #991b1b; font-weight: 500;">
						Trial expirado! Faça upgrade para continuar usando.
					</p>
				</div>

			</div>

			<div style="margin-top: 20px; text-align: center;">
				<?php
				$reset_url = wp_nonce_url(admin_url('admin.php?page=wpwevo-settings&tab=quick-signup&wpwevo_reset_trial=true'), 'wpwevo_reset_trial_nonce');
				?>
				<a href="<?php echo esc_url($reset_url); ?>" 
				   onclick="return confirm('<?php _e('Tem certeza que deseja resetar sua configuração de teste? Todos os dados da API serão apagados.', 'wp-whatsapp-evolution'); ?>');"
				   style="color: #6b7280; font-size: 12px; text-decoration: none;">
					<?php _e('Resetar configuração de teste', 'wp-whatsapp-evolution'); ?>
				</a>
			</div>
		</div>

		<div id="wpwevo-status-details" style="margin-top: 30px;">
			<div style="background: #f9fafb; border: 1px solid #e5e7eb; padding: 20px; border-radius: 10px;">
				<h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">📱 Conecte seu WhatsApp</h3>
				<div id="wpwevo-qr-container" style="text-align: center; margin-bottom: 20px; display: none;">
					<!-- QR Code e status inserido via JS -->
				</div>
				<div id="wpwevo-connection-success" style="display: none; text-align: center; padding: 20px; background: #f0fdf4; border-radius: 8px;">
					<p style="margin:0; color: #166534; font-size: 16px;">✅ WhatsApp conectado e funcionando!</p>
				</div>
			</div>
		</div>

		<?php
	}
} 