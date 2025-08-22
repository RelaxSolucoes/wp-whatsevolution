<?php
/**
 * Funções auxiliares do plugin
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Verifica se a instância do WhatsApp está conectada
 */
function wpwevo_check_instance() {
	// ✅ CORREÇÃO: Verificar modo de conexão primeiro
	$connection_mode = get_option('wpwevo_connection_mode', 'manual');
	
	if ($connection_mode === 'managed') {
		// ✅ Modo managed - usar Edge Function
		$api_key = get_option('wpwevo_managed_api_key', '');
		if (empty($api_key)) {
			return false;
		}
		
        $response = wp_remote_post('https://ydnobqsepveefiefmxag.supabase.co/functions/v1/plugin-status', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY
			],
			'body' => json_encode(['api_key' => $api_key]),
            'timeout' => 10
		]);
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$data = json_decode(wp_remote_retrieve_body($response), true);
		return isset($data['success']) && $data['success'] && isset($data['data']['whatsapp_connected']) && $data['data']['whatsapp_connected'];
	}
	
	// ✅ Modo manual - usar Evolution API diretamente
	$api_url = get_option('wpwevo_api_url');
	$api_key = get_option('wpwevo_manual_api_key'); // ✅ Usar manual_api_key
	$instance = get_option('wpwevo_instance');

	if (!$api_url || !$api_key || !$instance) {
		return false;
	}

	$url = trailingslashit($api_url) . 'instance/connectionState/' . $instance;
	$response = wp_remote_get($url, [
		'headers' => [
			'apikey' => $api_key
		]
	]);

	if (is_wp_error($response)) {
		return false;
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body);

	return isset($data->state) && $data->state === 'CONNECTED';
}

/**
 * Envia uma mensagem via WhatsApp
 */
function wpwevo_send_message($number, $message) {
	// ✅ CORREÇÃO: Verificar modo de conexão primeiro
	$connection_mode = get_option('wpwevo_connection_mode', 'manual');
	
	if ($connection_mode === 'managed') {
		// ✅ Modo managed - usar Edge Function
		$api_key = get_option('wpwevo_managed_api_key', '');
		if (empty($api_key)) {
			return false;
		}
		
		// Formata o número
		$number = preg_replace('/[^0-9]/', '', $number);
		
        $response = wp_remote_post('https://ydnobqsepveefiefmxag.supabase.co/functions/v1/send-message', [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY
			],
			'body' => json_encode([
				'api_key' => $api_key,
				'number' => $number,
				'message' => $message
			]),
            'timeout' => 15
		]);
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$data = json_decode(wp_remote_retrieve_body($response), true);
		return isset($data['success']) && $data['success'];
	}
	
	// ✅ Modo manual - usar Evolution API diretamente
	$api_url = get_option('wpwevo_api_url');
	$api_key = get_option('wpwevo_manual_api_key'); // ✅ Usar manual_api_key
	$instance = get_option('wpwevo_instance');

	if (!$api_url || !$api_key || !$instance) {
		return false;
	}

	// Formata o número (remove caracteres não numéricos)
	$number = preg_replace('/[^0-9]/', '', $number);

	$url = trailingslashit($api_url) . 'message/text/' . $instance;
	$response = wp_remote_post($url, [
		'headers' => [
			'apikey' => $api_key,
			'Content-Type' => 'application/json'
		],
		'body' => json_encode([
			'number' => $number,
			'message' => $message
		])
	]);

	if (is_wp_error($response)) {
		return false;
	}

	$body = wp_remote_retrieve_body($response);
	$data = json_decode($body);

	return isset($data->key) && !empty($data->key);
}

/**
 * Substitui variáveis na mensagem
 */
function wpwevo_replace_vars($message, $order = null) {
	$replacements = [
		'{store_name}' => get_bloginfo('name'),
		'{store_url}' => get_bloginfo('url'),
		'{store_email}' => get_bloginfo('admin_email')
	];

	return str_replace(
		array_keys($replacements),
		array_values($replacements),
		$message
	);
}

/**
 * Registra um log com nível específico
 */
function wpwevo_log_error($message, $data = [], $level = 'info') {
	// Verifica se debug está habilitado
	$debug_enabled = get_option('wpwevo_debug_enabled', false);
	
	// Se debug não estiver habilitado, só registra erros reais
	if (!$debug_enabled && $level !== 'error') {
		return;
	}
	
	// Em produção, limita logs de info/debug
	if (!$debug_enabled && in_array($level, ['info', 'debug']) && !is_admin()) {
		return;
	}
	
	if (!function_exists('wc_get_logger')) {
		return;
	}

	$logger = wc_get_logger();
	$context = array_merge(['source' => 'wpwevo'], $data);
	
	// Usa o nível apropriado
	switch($level) {
		case 'error':
			$logger->error($message, $context);
			break;
		case 'warning':
			$logger->warning($message, $context);
			break;
		case 'info':
			$logger->info($message, $context);
			break;
		case 'debug':
		default:
			$logger->debug($message, $context);
			break;
	}
}

/**
 * Carrega os estilos do admin
 */
function wpwevo_admin_enqueue_styles() {
	wp_enqueue_style('wpwevo-admin-style', WPWEVO_URL . 'assets/css/admin.css', [], WPWEVO_VERSION);
}
add_action('admin_enqueue_scripts', 'wpwevo_admin_enqueue_styles');

/**
 * Registra um log no banco de dados
 */
function wpwevo_log($level, $message, $context = []) {
	global $wpdb;
	
	$levels = ['debug', 'info', 'warning', 'error'];
	if (!in_array($level, $levels)) {
		$level = 'info';
	}
	
	$data = [
		'level' => $level,
		'message' => $message,
		'context' => is_array($context) || is_object($context) ? json_encode($context) : $context
	];
	
	$wpdb->insert(
		$wpdb->prefix . 'wpwevo_logs',
		$data,
		['%s', '%s', '%s']
	);
}

/**
 * Validação inteligente de telefone - Aceita números internacionais e brasileiros
 * Funciona com: 8, 9, 10, 11, 12, 13 dígitos, com ou sem código de país
 * Mantém 100% de compatibilidade com números brasileiros
 * Aceita números de qualquer país (códigos 1-99)
 */
function wpwevo_validate_phone($phone) {
	if (empty($phone)) return false;
	
	// Remove TODOS os caracteres não numéricos
	$phone = preg_replace('/[^0-9]/', '', $phone);
	
	// Remove zeros à esquerda (telefones podem vir como 019998...)
	$phone = ltrim($phone, '0');
	
	if (empty($phone)) return false;
	
	// Log de debug interno - apenas para desenvolvimento
	// Para produção, logs são controlados pela chamada da função
	
	// VALIDAÇÃO INTELIGENTE - Aceita qualquer país
	
	// 1. Número já com código do país (12+ dígitos)
	if (strlen($phone) >= 12) {
		$country_code = substr($phone, 0, 2);
		$area_code = substr($phone, 2, 2);
		
		// Validação genérica para qualquer país
		// Código do país: 1-99 (cobre todos os países)
		// Código de área: 1-99 (cobre todas as áreas)
		if ($country_code >= 1 && $country_code <= 99 && $area_code >= 1 && $area_code <= 99) {
			return $phone;
		}
	}
	
	// 2. Número com 11 dígitos - pode ser brasileiro ou internacional
	if (strlen($phone) == 11) {
		$ddd = substr($phone, 0, 2);
		
		// Se parece brasileiro (DDD 11-99), adiciona código 55
		if ($ddd >= 11 && $ddd <= 99) {
			// Verifica se já tem código do Brasil
			if (substr($phone, 0, 2) != '55') {
				return '55' . $phone;
			}
			return $phone;
		}
		
		// Se não parece brasileiro, pode ser internacional
		// Verifica se os primeiros dígitos formam um código de país válido
		$possible_country = substr($phone, 0, 2);
		if ($possible_country >= 1 && $possible_country <= 99) {
			// Pode ser um número internacional válido
			return $phone;
		}
	}
	
	// 3. Número com 10 dígitos (fixo DDD + XXXX-XXXX) - BRASIL
	if (strlen($phone) == 10) {
		$ddd = substr($phone, 0, 2);
		$terceiro_digito = substr($phone, 2, 1);
		
		if ($ddd >= 11 && $ddd <= 99) {
			// Se o terceiro dígito é 6-8, é celular sem o 9
			if ($terceiro_digito >= '6' && $terceiro_digito <= '8') {
				// Adiciona o 9 para celular e código do Brasil
				return '55' . substr($phone, 0, 2) . '9' . substr($phone, 2);
			} else {
				// Telefone fixo normal
				return '55' . $phone;
			}
		}
	}
	
	// 4. Número com 9 dígitos (provavelmente faltou dígito do DDD) - BRASIL
	if (strlen($phone) == 9) {
		// Assume DDD 1X mais comum no Brasil
		return '551' . $phone;
	}
	
	// 5. Número com 8 dígitos (XXXX-XXXX sem DDD) - BRASIL
	if (strlen($phone) == 8) {
		// Assume DDD 11 (São Paulo) como padrão brasileiro
		return '5511' . $phone;
	}
	
	// 6. Casos especiais - tenta adicionar código do Brasil se não tem
	if (strlen($phone) >= 8 && strlen($phone) <= 13) {
		// Se não começar com código de país conhecido, assume Brasil
		if (substr($phone, 0, 2) != '55') {
			return '55' . $phone;
		}
	}
	
	// Log de erro controlado pela aplicação que chama
	
	return false;
}

/**
 * Limpa logs antigos (mais de 30 dias)
 */
function wpwevo_cleanup_logs() {
	global $wpdb;
	
	$table = $wpdb->prefix . 'wpwevo_logs';
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
			30
		)
	);
}

/**
 * Registra um erro de API
 */
function wpwevo_log_api_error($endpoint, $response, $context = []) {
	$error_data = [
		'endpoint' => $endpoint,
		'response' => $response,
		'context' => $context
	];
	
	wpwevo_log('error', 'API Error: ' . $endpoint, $error_data);
}

/**
 * Verifica se uma string é JSON válido
 */
function wpwevo_is_json($string) {
	if (!is_string($string)) {
		return false;
	}
	
	json_decode($string);
	return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Obtém o número de telefone de um pedido, considerando múltiplos campos
 * Prioriza o campo de celular se estiver disponível
 */
function wpwevo_get_order_phone($order) {
	if (!$order instanceof \WC_Order) {
		return '';
	}
	
	// Primeiro tenta o campo billing_cellphone (Brazilian Market plugin)
	$cellphone = $order->get_meta('_billing_cellphone');
	if (!empty($cellphone)) {
		return $cellphone;
	}
	
	// Se não encontrou, usa o campo padrão billing_phone
	$phone = $order->get_billing_phone();
	if (!empty($phone)) {
		return $phone;
	}
	
	// Como último recurso, tenta outros campos comuns de telefone
	$alternative_fields = [
		'_billing_phone',
		'billing_phone_number',
		'wcf_billing_phone'
	];
	
	foreach ($alternative_fields as $field) {
		$phone = $order->get_meta($field);
		if (!empty($phone)) {
			return $phone;
		}
	}
	
	return '';
}

/**
 * Sanitiza dados de entrada baseado no tipo
 */
function wpwevo_sanitize_input($data, $type = 'text') {
	switch ($type) {
		case 'phone':
			return wpwevo_validate_phone($data);
		
		case 'url':
			return esc_url_raw($data);
			
		case 'textarea':
			return sanitize_textarea_field($data);
			
		case 'key':
			return sanitize_key($data);
			
		case 'int':
			return intval($data);
			
		case 'float':
			return floatval(str_replace(',', '.', $data));
			
		case 'bool':
			return (bool) $data;
			
		case 'array':
			return is_array($data) ? array_map('sanitize_text_field', $data) : [];
			
		default:
			return sanitize_text_field($data);
	}
}

// Agenda limpeza de logs
if (!wp_next_scheduled('wpwevo_cleanup_logs')) {
	wp_schedule_event(time(), 'daily', 'wpwevo_cleanup_logs');
}
add_action('wpwevo_cleanup_logs', 'wpwevo_cleanup_logs'); 