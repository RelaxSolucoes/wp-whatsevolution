<?php
/**
 * Plugin Name: WP WhatsEvolution
 * Plugin URI: https://github.com/RelaxSolucoes/wp-whatsevolution
 * Description: Integração avançada com WooCommerce usando Evolution API para envio de mensagens
 * Version:           1.3.0
 * Author:            WhatsEvolution
 * Author URI:        https://whatsevolution.com.br/
 * Text Domain: wp-whatsevolution
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Requires at least: 5.8
 * GitHub Plugin URI: RelaxSolucoes/wp-whatsevolution
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
	exit;
}

// Constantes
define('WPWEVO_VERSION', '1.3.0');
define('WPWEVO_FILE', __FILE__);
define('WPWEVO_PATH', plugin_dir_path(__FILE__));
define('WPWEVO_URL', plugin_dir_url(__FILE__));
define('WPWEVO_MIN_PHP_VERSION', '7.4');
define('WPWEVO_MIN_WP_VERSION', '5.8');
define('WPWEVO_MIN_WC_VERSION', '5.0');
define('WPWEVO_GITHUB_REPO', 'RelaxSolucoes/wp-whatsapp-evolution');

// Declara compatibilidade com HPOS
add_action('before_woocommerce_init', function() {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
});

// Configurações da API
require_once WPWEVO_PATH . 'includes/config.php';

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
	
	// Migra configurações antigas se necessário
	wpwevo_migrate_old_options();
}

// Desativação
register_deactivation_hook(__FILE__, 'wpwevo_deactivate');
function wpwevo_deactivate() {
	// Limpa dados temporários
	wpwevo_cleanup();
}

// A desinstalação é tratada pelo arquivo uninstall.php

/**
 * Verifica os requisitos do sistema
 */
function wpwevo_check_requirements() {
	$requirements = [
		'php' => WPWEVO_MIN_PHP_VERSION,
		'wp' => WPWEVO_MIN_WP_VERSION,
		'wc' => WPWEVO_MIN_WC_VERSION
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
	if (!class_exists('WooCommerce')) {
		$errors[] = 'wc';
	} else {
		// Verifica se WC_VERSION está definido
		if (defined('WC_VERSION')) {
			if (version_compare(WC_VERSION, $requirements['wc'], '<')) {
				$errors[] = 'wc';
			}
		} else {
			// Fallback: verifica se WooCommerce está ativo via função
			if (!function_exists('WC') || !WC()) {
				$errors[] = 'wc';
			}
		}
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
	add_option('wpwevo_instance', '');

	// Opções de mensagens por status
	add_option('wpwevo_status_messages', []);
}

// Limpa dados
function wpwevo_cleanup() {
	// Remove dados temporários
	delete_transient('wpwevo_connection_status');
	delete_transient('wpwevo_instance_status');
}

// Migra opções antigas para o formato correto
function wpwevo_migrate_old_options() {
	// Se existir a opção antiga wpwevo_instance_name, migra para wpwevo_instance
	$old_instance = get_option('wpwevo_instance_name');
	if ($old_instance && !get_option('wpwevo_instance')) {
		update_option('wpwevo_instance', $old_instance);
		delete_option('wpwevo_instance_name');
	}
}

// ===== AUTO-UPDATE GITHUB =====
function wp_whatsevolution_init_auto_updater() {
    require_once WPWEVO_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
    $myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/' . WPWEVO_GITHUB_REPO,
        __FILE__,
        'wp-whatsevolution'
    );
}
add_action('init', 'wp_whatsevolution_init_auto_updater');
// ===== FIM AUTO-UPDATE ===== 