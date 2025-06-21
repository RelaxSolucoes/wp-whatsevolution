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
		add_action('wp_ajax_wpwevo_save_quick_config', [$this, 'handle_save_config']);
		add_action('wp_ajax_wpwevo_check_plugin_status', [$this, 'handle_check_status']);
		add_action('wp_ajax_wpwevo_get_qr_code', [$this, 'handle_get_qr_code']);
		
		// Enqueue scripts específicos para quick signup
		add_action('admin_enqueue_scripts', [$this, 'enqueue_quick_signup_assets']);
	}

	public function enqueue_quick_signup_assets($hook) {
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		wp_enqueue_script(
			'wpwevo-quick-signup',
			WPWEVO_URL . 'assets/js/quick-signup.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-quick-signup', 'wpwevo_quick_signup', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_quick_signup'),
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
	 * Handler AJAX para criação rápida de conta
	 */
	public function handle_quick_signup() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
			$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
			$whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field($_POST['whatsapp']) : '';

			if (empty($email) || empty($name) || empty($whatsapp)) {
				throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
			}

			// Log apenas se debug estiver habilitado
			if (get_option('wpwevo_debug_enabled', false)) {
				wpwevo_log('info', 'Iniciando quick signup para: ' . $email);
			}

			// MODO DEMO - Remove em produção
			if (defined('WPWEVO_DEMO_MODE') && WPWEVO_DEMO_MODE) {
				if (get_option('wpwevo_debug_enabled', false)) {
					wpwevo_log('info', 'MODO DEMO ATIVADO - Pulando Edge Function');
				}
				
				// Simula resposta de sucesso
				$demo_response = [
					'success' => true,
					'data' => [
						'api_url' => 'https://demo-api.evolution.com',
						'api_key' => 'demo_key_' . time(),
						'instance' => 'demo_instance_' . time(),
						'status' => 'created'
					]
				];
				
				if (get_option('wpwevo_debug_enabled', false)) {
					wpwevo_log('info', 'Retornando dados demo: ' . json_encode($demo_response));
				}
				
				wp_send_json_success($demo_response);
				return;
			}

			// Chama a Edge Function para criar a conta
			$response = $this->call_edge_function('quick-signup', [
				'email' => $email,
				'name' => $name,
				'whatsapp' => $whatsapp
			]);

			if (!$response['success']) {
				throw new \Exception($response['error'] ?? __('Erro ao criar conta.', 'wp-whatsapp-evolution'));
			}

			wp_send_json_success($response);

		} catch (\Exception $e) {
			// Log apenas erros reais
			wpwevo_log('error', 'Erro no quick signup: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Handler AJAX para salvar configurações automáticas
	 */
	public function handle_save_config() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$api_url = isset($_POST['api_url']) ? esc_url_raw($_POST['api_url']) : '';
			$api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
			$instance_name = isset($_POST['instance_name']) ? sanitize_text_field($_POST['instance_name']) : '';
			$trial_expires_at = isset($_POST['trial_expires_at']) ? sanitize_text_field($_POST['trial_expires_at']) : '';

			if (empty($api_url) || empty($api_key) || empty($instance_name)) {
				throw new \Exception(__('Dados de configuração incompletos.', 'wp-whatsapp-evolution'));
			}

			// Salva as configurações
			update_option('wpwevo_api_url', $api_url);
			update_option('wpwevo_api_key', $api_key);
			update_option('wpwevo_instance', $instance_name);
			update_option('wpwevo_auto_configured', true);
			update_option('wpwevo_trial_started_at', current_time('timestamp'));
			update_option('wpwevo_trial_expires_at', $trial_expires_at);

			wp_send_json_success([
				'message' => __('Plugin configurado automaticamente!', 'wp-whatsapp-evolution')
			]);

		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao salvar config automática: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Handler AJAX para verificar status da instância
	 */
	public function handle_check_status() {
		try {
			check_ajax_referer('wpwevo_quick_signup', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$api_key = get_option('wpwevo_api_key', '');
			
			if (empty($api_key)) {
				throw new \Exception(__('Plugin não configurado.', 'wp-whatsapp-evolution'));
			}

			error_log('WP WhatsApp Evolution - Verificando status via Edge Function plugin-status');

			// Chama a Edge Function plugin-status que já existe
			$response = $this->call_edge_function('plugin-status', [
				'api_key' => $api_key
			]);

			if (!$response['success']) {
				throw new \Exception($response['error'] ?? __('Erro ao verificar status.', 'wp-whatsapp-evolution'));
			}

			error_log('WP WhatsApp Evolution - Resposta plugin-status: ' . json_encode($response['data']));

			wp_send_json_success([
				'data' => $response['data']
			]);

		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao verificar status: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Handler AJAX para buscar QR code
	 */
	public function handle_get_qr_code() {
		try {
			check_ajax_referer('wpwevo_get_qr', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$instance_name = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';
			
			if (empty($instance_name)) {
				throw new \Exception(__('Nome da instância é obrigatório.', 'wp-whatsapp-evolution'));
			}

			// Log apenas se debug estiver habilitado
			if (get_option('wpwevo_debug_enabled', false)) {
				wpwevo_log('info', 'Buscando QR Code para instância: ' . $instance_name);
			}

			// Constrói a URL para buscar o QR Code
			$api_url = get_option('wpwevo_api_url');
			$api_key = get_option('wpwevo_api_key');

			if (empty($api_url) || empty($api_key)) {
				throw new \Exception(__('API não configurada.', 'wp-whatsapp-evolution'));
			}

			$url = rtrim($api_url, '/') . '/instance/connect/' . $instance_name;

			$response = wp_remote_get($url, [
				'headers' => [
					'apikey' => $api_key,
					'Content-Type' => 'application/json'
				],
				'timeout' => 30,
				'sslverify' => false
			]);

			if (is_wp_error($response)) {
				// Log apenas erros reais
				wpwevo_log('error', 'Erro ao buscar QR: ' . $response->get_error_message());
				throw new \Exception(__('Erro ao conectar com a API.', 'wp-whatsapp-evolution'));
			}

			$status_code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);

			// Log apenas se debug estiver habilitado
			if (get_option('wpwevo_debug_enabled', false)) {
				wpwevo_log('info', 'Status QR: ' . $status_code);
				wpwevo_log('info', 'Resposta QR: ' . substr($body, 0, 200) . '...');
			}

			$data = json_decode($body, true);

			if ($status_code !== 200) {
				$error_message = isset($data['message']) ? $data['message'] : __('Erro desconhecido na API.', 'wp-whatsapp-evolution');
				throw new \Exception($error_message);
			}

			if (!isset($data['qrcode'])) {
				throw new \Exception(__('QR Code não encontrado na resposta da API.', 'wp-whatsapp-evolution'));
			}

			wp_send_json_success([
				'qrcode' => $data['qrcode'],
				'status' => $data['status'] ?? 'unknown'
			]);

		} catch (\Exception $e) {
			// Log apenas erros reais
			wpwevo_log('error', 'Erro ao buscar QR code: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}

	/**
	 * Chama uma Edge Function do Supabase
	 */
	private function call_edge_function($function_name, $data = []) {
		// Log apenas se debug estiver habilitado
		if (get_option('wpwevo_debug_enabled', false)) {
			wpwevo_log('info', 'Chamando Edge Function: ' . $function_name);
		}

		$url = WHATSEVOLUTION_API_BASE . '/functions/v1/' . $function_name;
		
		$timeout = ($function_name === 'quick-signup') ? WHATSEVOLUTION_TIMEOUT : WHATSEVOLUTION_STATUS_TIMEOUT;

		$response = wp_remote_post($url, [
			'headers' => [
				'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY,
				'Content-Type' => 'application/json'
			],
			'body' => json_encode($data),
			'timeout' => $timeout,
			'sslverify' => false
		]);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			// Log apenas erros reais
			wpwevo_log('error', 'Erro WP: ' . $error_message);
			
			return [
				'success' => false,
				'error' => $error_message
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		// Log apenas se debug estiver habilitado
		if (get_option('wpwevo_debug_enabled', false)) {
			wpwevo_log('info', 'Status Code: ' . $status_code);
			wpwevo_log('info', 'Resposta bruta: ' . $body);
		}

		if ($status_code !== 200) {
			return [
				'success' => false,
				'error' => sprintf(__('Erro HTTP %d: %s', 'wp-whatsapp-evolution'), $status_code, $body)
			];
		}

		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return [
				'success' => false,
				'error' => __('Resposta inválida do servidor.', 'wp-whatsapp-evolution')
			];
		}

		return [
			'success' => true,
			'data' => $data
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

	public function check_plugin_status() {
		try {
			check_ajax_referer('wpwevo_check_status', 'nonce');

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			$instance_name = isset($_POST['instance']) ? sanitize_text_field($_POST['instance']) : '';
			
			if (empty($instance_name)) {
				throw new \Exception(__('Nome da instância é obrigatório.', 'wp-whatsapp-evolution'));
			}

			// Log apenas se debug estiver habilitado
			if (get_option('wpwevo_debug_enabled', false)) {
				wpwevo_log('info', 'Verificando status via Edge Function plugin-status');
			}

			// Chama a Edge Function para verificar o status
			$response = $this->call_edge_function('plugin-status', [
				'instance_name' => $instance_name
			]);

			if (!$response['success']) {
				throw new \Exception($response['error'] ?? __('Erro ao verificar status.', 'wp-whatsapp-evolution'));
			}

			// Log apenas se debug estiver habilitado
			if (get_option('wpwevo_debug_enabled', false)) {
				wpwevo_log('info', 'Resposta plugin-status: ' . json_encode($response['data']));
			}

			wp_send_json_success($response['data']);

		} catch (\Exception $e) {
			// Log apenas erros reais
			wpwevo_log('error', 'Erro ao verificar status: ' . $e->getMessage());
			wp_send_json_error([
				'message' => $e->getMessage()
			]);
		}
	}
} 