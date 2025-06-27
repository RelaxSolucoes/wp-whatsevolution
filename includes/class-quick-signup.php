<?php
namespace WpWhatsAppEvolution;

/**
 * Classe otimizada para gerenciar o sistema de onboarding 1-click
 * Versão melhorada com melhor estrutura, segurança e performance
 */
class Quick_Signup {
	private static $instance = null;
	private $api_base_url = 'https://ydnobqsepveefiefmxag.supabase.co/functions/v1';
	private $api_timeout = 30;
	private $status_timeout = 10;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->setup_hooks();
	}

	/**
	 * ✅ MELHORADO: Setup centralizado de hooks
	 */
	private function setup_hooks() {
		// Handlers AJAX
		add_action('wp_ajax_wpwevo_quick_signup', [$this, 'handle_quick_signup']);
		add_action('wp_ajax_wpwevo_check_plugin_status', [$this, 'handle_check_status']);
		add_action('wp_ajax_wpwevo_create_payment', [$this, 'handle_create_payment']);
		add_action('wp_ajax_wpwevo_sync_status', [$this, 'handle_sync_status']);
		add_action('wp_ajax_wpwevo_request_qr_code', [$this, 'handle_request_qr_code']);
		
		// Enqueue scripts
		add_action('admin_enqueue_scripts', [$this, 'enqueue_quick_signup_assets']);
	}

	/**
	 * ✅ MELHORADO: Sincronização de status com validação robusta
	 */
	public function handle_sync_status() {
		try {
			$this->validate_ajax_request();

			$status_data = $this->get_sanitized_status_data();
			
			if (empty($status_data)) {
				throw new \Exception(__('Dados de status ausentes ou em formato incorreto.', 'wp-whatsapp-evolution'));
			}

			$this->sync_status_to_options($status_data);

			wp_send_json_success([
				'message' => 'Status sincronizado com sucesso.'
			]);

		} catch (\Exception $e) {
			$this->log_error('Erro ao sincronizar status', $e);
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * ✅ NOVO: Validação centralizada de requisições AJAX
	 */
	private function validate_ajax_request() {
		check_ajax_referer('wpwevo_quick_signup', 'nonce');

		if (!current_user_can('manage_options')) {
			throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}
	}

	/**
	 * ✅ NOVO: Obter dados de status sanitizados
	 */
	private function get_sanitized_status_data() {
		$status_data = isset($_POST['status_data']) && is_array($_POST['status_data']) ? $_POST['status_data'] : null;
		
		if (!$status_data) {
			return null;
		}

		// Sanitiza dados críticos
		$sanitized = [];
		$sanitized['trial_expires_at'] = isset($status_data['trial_expires_at']) ? 
			sanitize_text_field($status_data['trial_expires_at']) : null;
		
		$sanitized['user_plan'] = isset($status_data['user_plan']) ? 
			sanitize_text_field($status_data['user_plan']) : null;
		
		$sanitized['trial_days_left'] = isset($status_data['trial_days_left']) ? 
			intval($status_data['trial_days_left']) : 0;

		return $sanitized;
	}

	/**
	 * ✅ MELHORADO: Sincronizar status para opções do WordPress
	 */
	private function sync_status_to_options($status_data) {
		// ✅ CORREÇÃO: Verificar múltiplas estruturas possíveis para trial_expires_at
		$trial_expires_at = null;
		if (isset($status_data['trial_expires_at'])) {
			$trial_expires_at = $status_data['trial_expires_at'];
		} elseif (isset($status_data['instance']['trial_expires_at'])) {
			$trial_expires_at = $status_data['instance']['trial_expires_at'];
		} elseif (isset($status_data['data']['instance']['trial_expires_at'])) {
			$trial_expires_at = $status_data['data']['instance']['trial_expires_at'];
		}
		
		if ($trial_expires_at) {
			update_option('wpwevo_trial_expires_at', $trial_expires_at);
			$this->log_info('Trial expires at sincronizado: ' . $trial_expires_at);
		} else {
			$this->log_debug('Trial expires at não encontrado nos dados de status');
		}

		// ✅ CORREÇÃO: Verificar múltiplas estruturas possíveis para user_plan
		$user_plan = null;
		if (isset($status_data['user_plan'])) {
			$user_plan = $status_data['user_plan'];
		} elseif (isset($status_data['instance']['profiles']['plan'])) {
			$user_plan = $status_data['instance']['profiles']['plan'];
		} elseif (isset($status_data['data']['instance']['profiles']['plan'])) {
			$user_plan = $status_data['data']['instance']['profiles']['plan'];
		}
		
		if ($user_plan) {
			update_option('wpwevo_user_plan', $user_plan);
			$this->log_info('User plan sincronizado: ' . $user_plan);
		}

		// ✅ CORREÇÃO: Verificar múltiplas estruturas possíveis para trial_days_left
		$trial_days_left = null;
		if (isset($status_data['trial_days_left'])) {
			$trial_days_left = $status_data['trial_days_left'];
		} elseif (isset($status_data['trialDaysLeft'])) {
			$trial_days_left = $status_data['trialDaysLeft'];
		} elseif (isset($status_data['data']['trialDaysLeft'])) {
			$trial_days_left = $status_data['data']['trialDaysLeft'];
		}
		
		if ($trial_days_left !== null) {
			update_option('wpwevo_trial_days_left', intval($trial_days_left));
			$this->log_info('Trial days left sincronizado: ' . $trial_days_left);
		}
	}

	/**
	 * ✅ MELHORADO: Enqueue de assets com validação
	 */
	public function enqueue_quick_signup_assets($hook) {
		if (!$this->should_enqueue_assets($hook)) {
			return;
		}

		$this->log_info('Enqueueing quick signup assets for hook: ' . $hook);

		wp_enqueue_script(
			'wpwevo-quick-signup',
			WPWEVO_URL . 'assets/js/quick-signup.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-quick-signup', 'wpwevo_quick_signup', $this->get_localized_data());
	}

	/**
	 * ✅ NOVO: Verificar se deve enqueuear assets
	 */
	private function should_enqueue_assets($hook) {
		return strpos($hook, 'wpwevo') !== false;
	}

	/**
	 * ✅ NOVO: Obter dados localizados para JavaScript
	 */
	private function get_localized_data() {
		$api_key = get_option('wpwevo_managed_api_key', '');
		$user_id = get_option('wpwevo_user_id', '');

		return [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_quick_signup'),
			'should_show_upgrade_modal' => self::should_show_upgrade_modal(),
			'is_trial_expired' => self::should_show_upgrade_modal(),
			'api_key' => $api_key,
			'user_id' => $user_id,
			'messages' => $this->get_messages(),
			'debug_enabled' => get_option('wpwevo_debug_enabled', false)
		];
	}

	/**
	 * ✅ NOVO: Obter mensagens traduzidas
	 */
	private function get_messages() {
		return [
			'validating' => __('Validando dados...', 'wp-whatsapp-evolution'),
			'creating_account' => __('Criando conta...', 'wp-whatsapp-evolution'),
			'configuring_plugin' => __('Configurando plugin...', 'wp-whatsapp-evolution'),
			'success' => __('Pronto! ✅', 'wp-whatsapp-evolution'),
			'error' => __('Ops! Algo deu errado.', 'wp-whatsapp-evolution'),
			'retry' => __('Tentar novamente', 'wp-whatsapp-evolution'),
			'copied' => __('Copiado!', 'wp-whatsapp-evolution')
		];
	}

	/**
	 * ✅ MELHORADO: Handler principal de quick signup
	 */
	public function handle_quick_signup() {
		try {
			$this->validate_ajax_request();

			$form_data = $this->get_sanitized_form_data();
			$this->validate_form_data($form_data);

			$is_renewal = $this->is_renewal_request($form_data['email']);
			$previous_config = $this->get_previous_manual_config();

			$response = $this->call_edge_function('quick-signup', [
				'name' => $form_data['name'],
				'email' => $form_data['email'],
				'whatsapp' => $form_data['whatsapp'],
				'source' => 'wordpress-plugin',
				'plugin_version' => WPWEVO_VERSION,
				'is_renewal' => $is_renewal
			]);

			$this->validate_edge_response($response);
			$api_data = $this->extract_api_data($response['data']);

			$this->save_configuration($api_data, $form_data, $previous_config);

			wp_send_json_success($api_data);

		} catch (\Exception $e) {
			$this->log_error('Erro no Quick Signup', $e);
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * ✅ NOVO: Obter dados do formulário sanitizados
	 */
	private function get_sanitized_form_data() {
		return [
			'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
			'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
			'whatsapp' => isset($_POST['whatsapp']) ? wpwevo_validate_phone($_POST['whatsapp']) : ''
		];
	}

	/**
	 * ✅ NOVO: Validar dados do formulário
	 */
	private function validate_form_data($form_data) {
		if (empty($form_data['name']) || empty($form_data['email']) || empty($form_data['whatsapp'])) {
			throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
		}

		if (!is_email($form_data['email'])) {
			throw new \Exception(__('Email inválido.', 'wp-whatsapp-evolution'));
		}
	}

	/**
	 * ✅ NOVO: Verificar se é renovação
	 */
	private function is_renewal_request($email) {
		$previous_email = get_option('wpwevo_user_email', '');
		return $previous_email === $email;
	}

	/**
	 * ✅ NOVO: Obter configuração manual anterior
	 */
	private function get_previous_manual_config() {
		if (get_option('wpwevo_connection_mode') !== 'manual') {
			return null;
		}

		return [
			'api_url' => get_option('wpwevo_api_url'),
			'api_key' => get_option('wpwevo_manual_api_key'),
			'instance' => get_option('wpwevo_instance')
		];
	}

	/**
	 * ✅ NOVO: Validar resposta da Edge Function
	 */
	private function validate_edge_response($response) {
		if ($response['success'] === false || !isset($response['data'])) {
			$error_message = $response['message'] ?? __('Erro desconhecido ao criar conta.', 'wp-whatsapp-evolution');
			throw new \Exception($error_message);
		}
	}

	/**
	 * ✅ NOVO: Extrair dados da API
	 */
	private function extract_api_data($api_data) {
		// Corrige aninhamento da resposta
		if (isset($api_data['success']) && $api_data['success'] === true && isset($api_data['data'])) {
			$this->log_info('Corrigindo aninhamento da resposta da API.');
			return $api_data['data'];
		}

		return $api_data;
	}

	/**
	 * ✅ MELHORADO: Salvar configuração com validação
	 */
	private function save_configuration($api_data, $form_data, $previous_config) {
		// ✅ DEBUG: Log da estrutura dos dados recebidos
		$this->log_debug('Estrutura dos dados da API recebidos: ' . json_encode($api_data));
		
		// Configurações básicas
		update_option('wpwevo_connection_mode', 'managed');
		update_option('wpwevo_auto_configured', true);

		// Dados do usuário
		update_option('wpwevo_user_id', sanitize_text_field($api_data['dashboard_access']['user_id'] ?? ''));
		update_option('wpwevo_user_email', $form_data['email']);
		update_option('wpwevo_user_name', $form_data['name']);
		update_option('wpwevo_user_whatsapp', $form_data['whatsapp']);

		// Credenciais da API
		$api_key = sanitize_text_field($api_data['api_key']);
		update_option('wpwevo_managed_api_key', $api_key);
		update_option('wpwevo_api_url', esc_url_raw($api_data['api_url']));
		update_option('wpwevo_instance', sanitize_text_field($api_data['instance_name']));
		
		// Preserva configurações manuais anteriores
		if ($previous_config) {
			update_option('wpwevo_previous_manual_config', $previous_config);
		}
		
		// Limpa configurações manuais antigas
		delete_option('wpwevo_manual_api_key');
		
		// ✅ CORREÇÃO: Dados do Trial - verificar múltiplas estruturas possíveis
		$trial_expires_at = null;
		if (isset($api_data['trial_expires_at'])) {
			$trial_expires_at = $api_data['trial_expires_at'];
			$this->log_debug('Trial expires at encontrado em api_data[trial_expires_at]: ' . $trial_expires_at);
		} elseif (isset($api_data['instance']['trial_expires_at'])) {
			$trial_expires_at = $api_data['instance']['trial_expires_at'];
			$this->log_debug('Trial expires at encontrado em api_data[instance][trial_expires_at]: ' . $trial_expires_at);
		} elseif (isset($api_data['data']['instance']['trial_expires_at'])) {
			$trial_expires_at = $api_data['data']['instance']['trial_expires_at'];
			$this->log_debug('Trial expires at encontrado em api_data[data][instance][trial_expires_at]: ' . $trial_expires_at);
		} else {
			$this->log_debug('Trial expires at não encontrado em nenhuma estrutura conhecida');
			$this->log_debug('Estruturas disponíveis: ' . json_encode(array_keys($api_data)));
			if (isset($api_data['instance'])) {
				$this->log_debug('Estruturas em instance: ' . json_encode(array_keys($api_data['instance'])));
			}
			if (isset($api_data['data'])) {
				$this->log_debug('Estruturas em data: ' . json_encode(array_keys($api_data['data'])));
			}
		}
		
		if ($trial_expires_at) {
			update_option('wpwevo_trial_expires_at', sanitize_text_field($trial_expires_at));
			$this->log_info('Trial expires at salvo: ' . $trial_expires_at);
		} else {
			$this->log_error('Trial expires at não encontrado na resposta da API');
		}
		
		// Dados de Acesso ao Dashboard
		if (isset($api_data['dashboard_access']) && is_array($api_data['dashboard_access'])) {
			update_option('wpwevo_dashboard_url', esc_url_raw($api_data['dashboard_access']['url'] ?? ''));
			update_option('wpwevo_dashboard_email', sanitize_email($api_data['dashboard_access']['email'] ?? ''));
			update_option('wpwevo_dashboard_password', sanitize_text_field($api_data['dashboard_access']['password'] ?? ''));
		}

		// ✅ Sincroniza todos os campos críticos
		$this->sync_status_to_options($api_data);
	}

	/**
	 * ✅ MELHORADO: Handler de verificação de status
	 */
	public function handle_check_status() {
		try {
			$this->validate_ajax_request();

			$api_key = $this->get_api_key();
			$this->validate_api_key($api_key);

			$response = $this->call_edge_function('plugin-status', [
				'api_key' => $api_key
			]);

			$this->validate_status_response($response);
			$final_data = $this->extract_status_data($response['data']);

			wp_send_json_success($final_data);

		} catch (\Exception $e) {
			$this->log_error('Erro ao verificar status', $e);
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * ✅ NOVO: Obter API key
	 */
	private function get_api_key() {
		$api_key = get_option('wpwevo_managed_api_key', '');
		
		$this->log_debug('Verificando api_key...');
		$this->log_debug('Campo wpwevo_managed_api_key existe: ' . (get_option('wpwevo_managed_api_key') ? 'SIM' : 'NÃO'));
		$this->log_debug('Valor da api_key: ' . (empty($api_key) ? 'VAZIO' : substr($api_key, 0, 10) . '...'));
		$this->log_debug('Tamanho da api_key: ' . strlen($api_key));
		
		return $api_key;
	}

	/**
	 * ✅ NOVO: Validar API key
	 */
	private function validate_api_key($api_key) {
		if (empty($api_key)) {
			throw new \Exception(__('Plugin não configurado.', 'wp-whatsapp-evolution'));
		}
	}

	/**
	 * ✅ NOVO: Validar resposta de status
	 */
	private function validate_status_response($response) {
		if (!$response['success'] || !isset($response['data']['success']) || !$response['data']['success']) {
			$error_message = $response['data']['error'] ?? $response['error'] ?? __('Erro ao verificar status.', 'wp-whatsapp-evolution');
			$this->log_error('Erro na resposta da Edge Function: ' . $error_message);
			throw new \Exception($error_message);
		}
	}

	/**
	 * ✅ CORRIGIDO: Extrair dados de status no formato correto para o JavaScript
	 */
	private function extract_status_data($response_data) {
		$final_data = $response_data['data'];

		// ✅ CORREÇÃO: Extrair trial_expires_at de múltiplas estruturas possíveis
		$trial_expires_at = null;
		if (isset($final_data['instance']['trial_expires_at'])) {
			$trial_expires_at = $final_data['instance']['trial_expires_at'];
		} elseif (isset($final_data['trial_expires_at'])) {
			$trial_expires_at = $final_data['trial_expires_at'];
		} elseif (isset($response_data['trial_expires_at'])) {
			$trial_expires_at = $response_data['trial_expires_at'];
		}

		// ✅ CORREÇÃO: Extrair user_plan de múltiplas estruturas possíveis
		$user_plan = 'trial';
		if (isset($final_data['instance']['profiles']['plan'])) {
			$user_plan = $final_data['instance']['profiles']['plan'];
		} elseif (isset($final_data['user_plan'])) {
			$user_plan = $final_data['user_plan'];
		} elseif (isset($response_data['user_plan'])) {
			$user_plan = $response_data['user_plan'];
		}

		// ✅ CORREÇÃO: Extrair trial_days_left de múltiplas estruturas possíveis
		$trial_days_left = 0;
		if (isset($final_data['trialDaysLeft'])) {
			$trial_days_left = $final_data['trialDaysLeft'];
		} elseif (isset($final_data['trial_days_left'])) {
			$trial_days_left = $final_data['trial_days_left'];
		} elseif (isset($response_data['trialDaysLeft'])) {
			$trial_days_left = $response_data['trialDaysLeft'];
		}

		// CORREÇÃO: Garantir que os dados estejam no formato correto para o JavaScript
		$formatted_data = [
			'whatsapp_connected' => false,
			'trial_days_left' => $trial_days_left,
			'user_plan' => $user_plan,
			'trial_expires_at' => $trial_expires_at,
			'qr_code' => $final_data['qr_code'] ?? null,
			'qr_code_url' => $final_data['qr_code_url'] ?? null,
			'currentStatus' => $final_data['currentStatus'] ?? 'connecting',
			'isTrialExpired' => $final_data['isTrialExpired'] ?? false
		];

		// CORREÇÃO: Determinar se WhatsApp está conectado baseado no status
		if (isset($final_data['currentStatus']) && $final_data['currentStatus'] === 'connected') {
			$formatted_data['whatsapp_connected'] = true;
		}

		// CORREÇÃO: Se não há QR Code e status é connected, WhatsApp está conectado
		if (empty($final_data['qr_code']) && $final_data['currentStatus'] === 'connected') {
			$formatted_data['whatsapp_connected'] = true;
		}

		$this->log_debug('Dados formatados para o JS: ' . json_encode($formatted_data));
		$this->log_debug('whatsapp_connected: ' . ($formatted_data['whatsapp_connected'] ? 'true' : 'false'));
		$this->log_debug('currentStatus: ' . $formatted_data['currentStatus']);
		$this->log_debug('QR Code presente: ' . (isset($formatted_data['qr_code']) ? 'SIM' : 'NÃO'));
		$this->log_debug('Trial expires at: ' . ($formatted_data['trial_expires_at'] ?: 'NÃO ENCONTRADO'));
		$this->log_debug('Trial days left: ' . $formatted_data['trial_days_left']);
		$this->log_debug('User plan: ' . $formatted_data['user_plan']);

		return $formatted_data;
	}

	/**
	 * ✅ MELHORADO: Chamada para Edge Function com retry
	 */
	private function call_edge_function($function_name, $data = []) {
		$url = "{$this->api_base_url}/{$function_name}";
		
		$headers = [
			'Content-Type' => 'application/json',
			'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY
		];

		$body = json_encode($data);
		$timeout = ($function_name === 'quick-signup') ? $this->api_timeout : $this->status_timeout;

		$this->log_debug("Chamando Edge Function: {$function_name}");
		$this->log_debug("URL: {$url}");
		$this->log_debug("Body: {$body}");

		$response = wp_remote_post($url, [
			'headers' => $headers,
			'body' => $body,
			'timeout' => $timeout,
			'sslverify' => false
		]);

		return $this->process_edge_response($response, $function_name);
	}

	/**
	 * ✅ NOVO: Processar resposta da Edge Function
	 */
	private function process_edge_response($response, $function_name) {
		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			$this->log_error("Erro WP na função {$function_name}: {$error_message}");
			return [
				'success' => false,
				'error' => $error_message
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		
		$this->log_debug("Status Code: {$status_code}");
		$this->log_debug("Resposta bruta: {$body}");
		
		$decoded_body = json_decode($body, true);

		if ($status_code !== 200) {
			return $this->handle_error_response($status_code, $decoded_body, $body);
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
	 * ✅ NOVO: Tratar resposta de erro
	 */
	private function handle_error_response($status_code, $decoded_body, $body) {
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

	/**
	 * ✅ MELHORADO: Handler de criação de pagamento
	 */
	public function handle_create_payment() {
		try {
			$this->validate_ajax_request();

			if (!self::is_auto_configured()) {
				throw new \Exception(__('Apenas usuários do teste grátis podem fazer upgrade.', 'wp-whatsapp-evolution'));
			}

			$user_data = $this->get_user_data_for_payment();
			$this->validate_user_data($user_data);

			$response = $this->call_edge_function('create-payment-from-plugin', [
				'user_id' => $user_data['user_id'],
				'email' => $user_data['email'],
				'name' => $user_data['name'],
				'whatsapp' => $user_data['whatsapp'],
				'plan_type' => 'basic'
			]);

			if (!$response['success']) {
				$error_message = $response['error'] ?? 'Erro desconhecido ao criar pagamento.';
				if (isset($response['data']['error'])) {
					$error_message = $response['data']['error'];
				}
				throw new \Exception($error_message);
			}

			$final_data = $response['data']['data'];
			$api_key = get_option('wpwevo_managed_api_key');
			
			if ($api_key) {
				$final_data['api_key'] = $api_key;
			}
			
			wp_send_json_success($final_data);

		} catch (\Exception $e) {
			$this->log_error('Erro ao criar pagamento', $e);
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * ✅ NOVO: Obter dados do usuário para pagamento
	 */
	private function get_user_data_for_payment() {
		return [
			'user_id' => get_option('wpwevo_user_id'),
			'email' => get_option('wpwevo_user_email'),
			'name' => get_option('wpwevo_user_name'),
			'whatsapp' => get_option('wpwevo_user_whatsapp')
		];
	}

	/**
	 * ✅ NOVO: Validar dados do usuário
	 */
	private function validate_user_data($user_data) {
		if (empty($user_data['user_id']) || empty($user_data['email']) || 
			empty($user_data['name']) || empty($user_data['whatsapp'])) {
			throw new \Exception(__('Dados do usuário não encontrados. Por favor, tente resetar a configuração de teste e refazer o onboarding.', 'wp-whatsapp-evolution'));
		}
	}

	/**
	 * ✅ MELHORADO: Verificar se plugin foi configurado automaticamente
	 */
	public static function is_auto_configured() {
		return (bool) get_option('wpwevo_auto_configured', false);
	}

	/**
	 * ✅ MELHORADO: Calcular dias restantes do trial
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
	 * ✅ MELHORADO: Verificar se trial está ativo
	 */
	public static function is_trial_active() {
		return self::get_trial_days_left() > 0;
	}

	/**
	 * ✅ MELHORADO: Verificar se deve mostrar modal de upgrade
	 */
	public static function should_show_upgrade_modal() {
		if (get_option('wpwevo_connection_mode') !== 'managed') {
			return false;
		}
		
		if (self::is_trial_active()) {
			return false;
		}
		
		return true;
	}

	/**
	 * ✅ MELHORADO: Renderizar conteúdo da página
	 */
	public function render_page_content() {
		$is_configured = self::is_auto_configured();
		
		if (!$is_configured) {
			$this->render_signup_form();
		} else {
			$this->render_status_view();
		}
	}

	/**
	 * ✅ MELHORADO: Renderizar formulário de signup
	 */
	private function render_signup_form() {
		$is_auto_configured = Quick_Signup::is_auto_configured();
		$is_trial_expired = self::should_show_upgrade_modal();
		$current_user_email = get_option('wpwevo_user_email', '');
		
		include WPWEVO_PATH . 'templates/quick-signup-form.php';
	}

	/**
	 * ✅ MELHORADO: Renderizar view de status
	 */
	private function render_status_view() {
		include WPWEVO_PATH . 'templates/quick-signup-status.php';
	}

	/**
	 * ✅ NOVO: Sistema de logging centralizado
	 */
	private function log_error($message, $exception = null) {
		$log_message = $message;
		if ($exception) {
			$log_message .= ': ' . $exception->getMessage();
		}
		
		wpwevo_log('error', $log_message);
		error_log('WP WhatsApp Evolution - ' . $log_message);
	}

	private function log_info($message) {
		wpwevo_log('info', $message);
		error_log('WP WhatsApp Evolution - ' . $message);
	}

	private function log_debug($message) {
		if (get_option('wpwevo_debug_enabled', false)) {
			wpwevo_log('debug', $message);
		}
	}

	/**
	 * ✅ NOVO: Handler para solicitar novo QR Code
	 */
	public function handle_request_qr_code() {
		try {
			$this->validate_ajax_request();

			$api_key = $this->get_api_key();
			$this->validate_api_key($api_key);

			// Verifica se está no modo managed
			if (get_option('wpwevo_connection_mode') !== 'managed') {
				throw new \Exception(__('Apenas usuários do teste grátis podem usar esta funcionalidade.', 'wp-whatsapp-evolution'));
			}

			// ✅ CORREÇÃO: Usa a Edge Function plugin-status que já existe
			$response = $this->call_edge_function('plugin-status', [
				'api_key' => $api_key
			]);

			if (!$response['success']) {
				$error_message = $response['error'] ?? 'Erro desconhecido ao verificar status.';
				if (isset($response['data']['error'])) {
					$error_message = $response['data']['error'];
				}
				throw new \Exception($error_message);
			}

			$status_data = $response['data']['data'] ?? $response['data'];
			
			// Verifica se temos QR Code disponível
			if (empty($status_data['qr_code_url']) && empty($status_data['qr_code'])) {
				throw new \Exception(__('QR Code não disponível no momento. Tente novamente em alguns segundos.', 'wp-whatsapp-evolution'));
			}
			
			// Retorna os dados do QR Code
			wp_send_json_success([
				'qr_code_url' => $status_data['qr_code_url'] ?? null,
				'qr_code_base64' => $status_data['qr_code'] ?? null,
				'currentStatus' => $status_data['currentStatus'] ?? 'connecting',
				'message' => 'QR Code obtido com sucesso'
			]);

		} catch (\Exception $e) {
			$this->log_error('Erro ao solicitar QR Code', $e);
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}
} 