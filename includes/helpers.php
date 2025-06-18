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
	$api_url = get_option('wpwevo_api_url');
	$api_key = get_option('wpwevo_api_key');
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
	$api_url = get_option('wpwevo_api_url');
	$api_key = get_option('wpwevo_api_key');
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
 * Valida e formata um número de telefone
 */
function wpwevo_validate_phone($phone) {
	// Remove tudo que não for número
	$phone = preg_replace('/[^0-9]/', '', $phone);
	
	// Valida o formato básico (10-13 dígitos)
	if (strlen($phone) < 10 || strlen($phone) > 13) {
		return false;
	}
	
	// Normaliza o número brasileiro
	if (strlen($phone) == 10 && !preg_match('/^55/', $phone)) {
		// 10 dígitos: adiciona código do país (telefone fixo)
		$phone = '55' . $phone;
	} elseif (strlen($phone) == 11 && !preg_match('/^55/', $phone)) {
		// 11 dígitos: adiciona código do país (celular)
		$phone = '55' . $phone;
	}
	
	// Valida código do país (55) e DDD após normalização
	if (!preg_match('/^55[1-9][1-9]/', $phone)) {
		return false;
	}
	
	return $phone;
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