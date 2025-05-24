<?php
/**
 * Plugin Name: WP WhatsApp Evolution
 * Plugin URI: https://wpwhatsappevolution.com
 * Description: Integração avançada do WhatsApp com WooCommerce usando Evolution API
 * Version: 1.0.0
 * Author: Seu Nome
 * Author URI: https://seusite.com
 * Text Domain: wp-whatsapp-evolution
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Requires at least: 5.8
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
	exit;
}

// Constantes
define('WPWEVO_VERSION', '1.0.0');
define('WPWEVO_FILE', __FILE__);
define('WPWEVO_PATH', plugin_dir_path(__FILE__));
define('WPWEVO_URL', plugin_dir_url(__FILE__));

// Declara compatibilidade com HPOS
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// Autoloader
require_once WPWEVO_PATH . 'includes/class-autoloader.php';
\WpWhatsAppEvolution\Autoloader::register();

// Funções auxiliares
require_once WPWEVO_PATH . 'includes/helpers.php';

/**
 * Inicializa o plugin
 */
function wpwevo_init() {
	// Verifica requisitos
	if (!wpwevo_check_requirements()) {
		return;
	}

	// Inicializa o loader principal
	\WpWhatsAppEvolution\Plugin_Loader::init();
}
add_action('plugins_loaded', 'wpwevo_init');

// Ativação
register_activation_hook(__FILE__, 'wpwevo_activate');
function wpwevo_activate() {
	// Cria tabelas e opções necessárias
	wpwevo_create_tables();
	wpwevo_create_options();
}

// Desativação
register_deactivation_hook(__FILE__, 'wpwevo_deactivate');
function wpwevo_deactivate() {
	// Limpa dados temporários
	wpwevo_cleanup();
}

/**
 * Verifica os requisitos do sistema
 */
function wpwevo_check_requirements() {
	$requirements = [
		'php' => '7.4',
		'wp' => '5.8',
		'wc' => '5.0'
	];

	// Armazena os erros para exibir depois
	$errors = [];

	// Verifica PHP
	if (version_compare(PHP_VERSION, $requirements['php'], '<')) {
		$errors[] = 'php';
	}

	// Verifica WordPress
	if (version_compare(get_bloginfo('version'), $requirements['wp'], '<')) {
		$errors[] = 'wp';
	}

	// Verifica WooCommerce
	if (!class_exists('WooCommerce') || version_compare(WC_VERSION, $requirements['wc'], '<')) {
		$errors[] = 'wc';
	}

	if (!empty($errors)) {
		add_action('init', function() use ($errors, $requirements) {
			wpwevo_display_requirement_errors($errors, $requirements);
		});
		return false;
	}

	return true;
}

/**
 * Exibe as mensagens de erro de requisitos
 */
function wpwevo_display_requirement_errors($errors, $requirements) {
	foreach ($errors as $error) {
		add_action('admin_notices', function() use ($error, $requirements) {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					switch ($error) {
						case 'php':
							printf(
								/* translators: %s: Versão do PHP requerida */
								esc_html__('WP WhatsApp Evolution requer PHP %s ou superior.', 'wp-whatsapp-evolution'),
								$requirements['php']
							);
							break;
						case 'wp':
							printf(
								/* translators: %s: Versão do WordPress requerida */
								esc_html__('WP WhatsApp Evolution requer WordPress %s ou superior.', 'wp-whatsapp-evolution'),
								$requirements['wp']
							);
							break;
						case 'wc':
							printf(
								/* translators: %s: Versão do WooCommerce requerida */
								esc_html__('WP WhatsApp Evolution requer WooCommerce %s ou superior.', 'wp-whatsapp-evolution'),
								$requirements['wc']
							);
							break;
					}
					?>
				</p>
			</div>
			<?php
		});
	}
}

// Cria tabelas
function wpwevo_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Tabela de logs
	$table_name = $wpdb->prefix . 'wpwevo_logs';
	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		timestamp datetime DEFAULT CURRENT_TIMESTAMP,
		level varchar(20) NOT NULL,
		message text NOT NULL,
		context text,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

// Cria opções
function wpwevo_create_options() {
	// Opções de conexão
	add_option('wpwevo_api_url', '');
	add_option('wpwevo_api_key', '');
	add_option('wpwevo_instance_name', '');

	// Opções de mensagens por status
	add_option('wpwevo_status_messages', []);

	// Opções de templates
	add_option('wpwevo_message_templates', []);
}

// Limpa dados
function wpwevo_cleanup() {
	// Remove dados temporários
	delete_transient('wpwevo_connection_status');
	delete_transient('wpwevo_instance_status');
} 