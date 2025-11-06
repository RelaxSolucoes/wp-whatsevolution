<?php
/**
 * Plugin Name: WP WhatsEvolution
 * Plugin URI: https://github.com/RelaxSolucoes/wp-whatsevolution
 * Description: Integração avançada com WooCommerce usando Evolution API para envio de mensagens
 * Version:           1.4.6
 * Author:            Relax Soluções
 * Author URI:        https://relaxsolucoes.online/
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
define('WPWEVO_VERSION', '1.4.6');
define('WPWEVO_FILE', __FILE__);
define('WPWEVO_PATH', plugin_dir_path(__FILE__));
define('WPWEVO_URL', plugin_dir_url(__FILE__));
define('WPWEVO_MIN_PHP_VERSION', '7.4');
define('WPWEVO_MIN_WP_VERSION', '5.8');
define('WPWEVO_MIN_WC_VERSION', '5.0');
 define('WPWEVO_GITHUB_REPO', 'RelaxSolucoes/wp-whatsevolution');

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

/**
 * Compila automaticamente o arquivo .mo a partir do .po se necessário
 */
add_action('init', function() {
    $lang_dir = WPWEVO_PATH . 'languages/';
    $po_file = $lang_dir . 'wp-whatsevolution-pt_BR.po';
    $mo_file = $lang_dir . 'wp-whatsevolution-pt_BR.mo';

    // Só tenta gerar se existir .po e não existir .mo
    if (file_exists($po_file) && !file_exists($mo_file)) {
        // Tenta carregar classes POMO do WordPress
        $po_class = ABSPATH . WPINC . '/pomo/po.php';
        $mo_class = ABSPATH . WPINC . '/pomo/mo.php';
        if (file_exists($po_class) && file_exists($mo_class)) {
            require_once $po_class;
            require_once $mo_class;
            if (class_exists('PO') && class_exists('MO')) {
                $po = new \PO();
                if ($po->import_from_file($po_file)) {
                    $mo = new \MO();
                    $mo->entries = $po->entries;
                    $mo->headers = $po->headers;
                    // Ignora falhas silenciosamente para não quebrar o site
                    @$mo->export_to_file($mo_file);
                }
            }
        }
    }
});

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
                                esc_html__('WP WhatsApp Evolution requer PHP %s ou superior.', 'wp-whatsevolution'),
								$requirements['php']
							);
							break;
						case 'wp':
                            printf(
                                /* translators: %s: Versão do WordPress requerida */
                                esc_html__('WP WhatsApp Evolution requer WordPress %s ou superior.', 'wp-whatsevolution'),
								$requirements['wp']
							);
							break;
						case 'wc':
                            printf(
                                /* translators: %s: Versão do WooCommerce requerida */
                                esc_html__('WP WhatsApp Evolution requer WooCommerce %s ou superior.', 'wp-whatsevolution'),
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

	// Opções de mensagens por status - inicializa vazio para ser preenchido automaticamente
	add_option('wpwevo_status_messages', []);
	
	// Inicializa mensagens padrão se o WooCommerce estiver ativo
	if (class_exists('WooCommerce')) {
		// Aguarda um pouco para garantir que o WooCommerce esteja totalmente carregado
		wp_schedule_single_event(time() + 5, 'wpwevo_init_default_messages');
	}
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

// Inicializa mensagens padrão após ativação
add_action('wpwevo_init_default_messages', 'wpwevo_init_default_messages');
function wpwevo_init_default_messages() {
	if (class_exists('WpWhatsAppEvolution\Send_By_Status')) {
		$send_by_status = WpWhatsAppEvolution\Send_By_Status::init();
		$send_by_status->setup();
	}
}

// ===== AUTO-UPDATE GITHUB =====
function wp_whatsevolution_init_auto_updater() {
	// Só carrega a biblioteca se ela ainda não foi carregada por outro plugin
	if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
		require_once WPWEVO_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	}
	
	$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/' . WPWEVO_GITHUB_REPO,
		__FILE__,
		'wp-whatsevolution'
	);
}
add_action('init', 'wp_whatsevolution_init_auto_updater');
// ===== FIM AUTO-UPDATE =====
