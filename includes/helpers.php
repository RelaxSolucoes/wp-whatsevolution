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
		'{customer_name}' => '',
		'{order_id}' => '',
		'{order_total}' => '',
		'{order_status}' => '',
		'{payment_method}' => '',
		'{cart_total}' => '',
		'{cart_items}' => '',
		'{cart_url}' => '',
	];

	if ($order instanceof \WC_Order) {
		$replacements['{customer_name}'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		$replacements['{order_id}'] = $order->get_id();
		$replacements['{order_total}'] = wc_price($order->get_total());
		$replacements['{order_status}'] = wc_get_order_status_name($order->get_status());
		$replacements['{payment_method}'] = $order->get_payment_method_title();
	}

	if (function_exists('WC') && WC()->cart) {
		$replacements['{cart_total}'] = wc_price(WC()->cart->get_total());
		$replacements['{cart_url}'] = wc_get_cart_url();
		
		$items = [];
		foreach (WC()->cart->get_cart() as $cart_item) {
			$product = $cart_item['data'];
			$items[] = sprintf(
				'%s (x%d) - %s',
				$product->get_name(),
				$cart_item['quantity'],
				wc_price($cart_item['line_total'])
			);
		}
		$replacements['{cart_items}'] = implode("\n", $items);
	}

	return str_replace(
		array_keys($replacements),
		array_values($replacements),
		$message
	);
}

/**
 * Registra um erro no log
 */
function wpwevo_log_error($message, $data = []) {
	if (!function_exists('wc_get_logger')) {
		return;
	}

	$logger = wc_get_logger();
	$context = array_merge(['source' => 'wpwevo'], $data);
	$logger->error($message, $context);
}

/**
 * Carrega os estilos do admin
 */
function wpwevo_admin_enqueue_styles() {
	wp_enqueue_style('wpwevo-admin-style', WPWEVO_URL . 'assets/css/admin.css', [], WPWEVO_VERSION);
}
add_action('admin_enqueue_scripts', 'wpwevo_admin_enqueue_styles'); 