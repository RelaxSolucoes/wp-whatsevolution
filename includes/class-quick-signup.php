<?php
namespace WpWhatsAppEvolution;

/**
 * Classe para gerenciar o sistema de onboarding 1-click
 */
class Quick_Signup {
	private static $instance = null;
	private $supabase_url = 'https://ydnobqsepveefiefmxag.supabase.co';
	private $anon_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o';

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

			$name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
			$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
			$whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field($_POST['whatsapp']) : '';

			// Validações básicas
			if (empty($name) || empty($email) || empty($whatsapp)) {
				throw new \Exception(__('Todos os campos são obrigatórios.', 'wp-whatsapp-evolution'));
			}

			if (!is_email($email)) {
				throw new \Exception(__('Email inválido.', 'wp-whatsapp-evolution'));
			}

			// Chama a Edge Function quick-signup
			$response = $this->call_edge_function('quick-signup', [
				'name' => $name,
				'email' => $email,
				'whatsapp' => $whatsapp,
				'source' => 'wordpress_plugin'
			]);

			// Se falhou, tenta modo de demonstração para desenvolvimento
			if (!$response['success']) {
				error_log('WP WhatsApp Evolution - Tentativa de modo demo devido ao erro: ' . ($response['error'] ?? 'erro desconhecido'));
				
				// Verifica se é um erro de validação de WhatsApp (sempre ativo para demonstração)
				if (strpos($response['error'] ?? '', 'WhatsApp') !== false || 
					strpos($response['error'] ?? '', 'número') !== false ||
					strpos($response['error'] ?? '', 'inválido') !== false ||
					strpos($response['error'] ?? '', 'ativo') !== false) {
					
					// Simula uma resposta de sucesso para demonstração
					$demo_response = [
						'success' => true,
						'data' => [
							'api_url' => 'https://demo.evolution-api.com',
							'api_key' => 'demo_' . uniqid(),
							'instance_name' => 'demo_instance_' . uniqid(),
							'trial_expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
							'trial_days_left' => 7,
							'qr_code_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=demo_whatsapp_connection'
						]
					];
					
					error_log('WP WhatsApp Evolution - Usando modo DEMO devido a validação de WhatsApp');
					
					wp_send_json_success([
						'message' => __('Conta de demonstração criada! (Modo desenvolvimento)', 'wp-whatsapp-evolution'),
						'data' => $demo_response['data']
					]);
					return;
				}
				
				throw new \Exception($response['error'] ?? __('Erro ao criar conta.', 'wp-whatsapp-evolution'));
			}

			wp_send_json_success([
				'message' => __('Conta criada com sucesso!', 'wp-whatsapp-evolution'),
				'data' => $response['data']
			]);

		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro no quick signup: ' . $e->getMessage());
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

			// Chama a Edge Function plugin-status
			$response = $this->call_edge_function('plugin-status', [
				'api_key' => $api_key
			]);

			if (!$response['success']) {
				throw new \Exception($response['error'] ?? __('Erro ao verificar status.', 'wp-whatsapp-evolution'));
			}

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
	 * Chama uma Edge Function do Supabase
	 */
	private function call_edge_function($function_name, $data) {
		$url = $this->supabase_url . '/functions/v1/' . $function_name;
		
		// Log da requisição
		error_log('WP WhatsApp Evolution - Chamando Edge Function: ' . $function_name);
		error_log('WP WhatsApp Evolution - URL: ' . $url);
		error_log('WP WhatsApp Evolution - Dados enviados: ' . json_encode($data));
		
		$response = wp_remote_post($url, [
			'timeout' => 45,
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->anon_key
			],
			'body' => json_encode($data)
		]);

		if (is_wp_error($response)) {
			$error_message = $response->get_error_message();
			error_log('WP WhatsApp Evolution - Erro WP: ' . $error_message);
			return [
				'success' => false,
				'error' => $error_message
			];
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		
		// Log da resposta
		error_log('WP WhatsApp Evolution - Status Code: ' . $status_code);
		error_log('WP WhatsApp Evolution - Resposta bruta: ' . $body);
		
		$decoded = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			error_log('WP WhatsApp Evolution - Erro JSON: ' . json_last_error_msg());
			return [
				'success' => false,
				'error' => __('Resposta inválida da API.', 'wp-whatsapp-evolution') . ' JSON Error: ' . json_last_error_msg()
			];
		}

		// Se não é 200, mas tem resposta válida, retorna a resposta mesmo assim
		if ($status_code !== 200) {
			error_log('WP WhatsApp Evolution - Status não-200, mas JSON válido');
		}

		error_log('WP WhatsApp Evolution - Resposta decodificada: ' . json_encode($decoded));
		
		return $decoded;
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
} 