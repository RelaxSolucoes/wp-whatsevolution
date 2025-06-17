<?php
/**
 * Plugin Name: WP WhatsApp Evolution
 * Plugin URI: https://relaxsolucoes.online/
 * Description: Integração avançada do WhatsApp com WooCommerce usando Evolution API
 * Version: 1.0.4
 * Author: Relax Soluções
 * Author URI: https://relaxsolucoes.online/
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
define('WPWEVO_VERSION', '1.0.4');
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

// Desinstalação
register_uninstall_hook(__FILE__, 'wpwevo_uninstall');
function wpwevo_uninstall() {
	global $wpdb;

	// Remove a tabela de logs
	$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpwevo_logs");

	// Remove todas as opções
	delete_option('wpwevo_version');
	delete_option('wpwevo_api_url');
	delete_option('wpwevo_api_key');
	delete_option('wpwevo_instance_name');
	delete_option('wpwevo_status_messages');

	// Remove transients
	delete_transient('wpwevo_connection_status');
	delete_transient('wpwevo_instance_status');

	// Remove opções de agendamento
	wp_clear_scheduled_hook('wpwevo_cleanup_logs');
	wp_clear_scheduled_hook('wpwevo_abandoned_cart_cron');
	wp_clear_scheduled_hook('wpwevo_bulk_send_cron');
}

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
}

// Limpa dados
function wpwevo_cleanup() {
	// Remove dados temporários
	delete_transient('wpwevo_connection_status');
	delete_transient('wpwevo_instance_status');
}

// Adiciona verificação de atualizações
add_filter('pre_set_site_transient_update_plugins', 'wpwevo_check_update');
function wpwevo_check_update($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $remote = wp_remote_get(
        'https://api.github.com/repos/' . WPWEVO_GITHUB_REPO . '/releases/latest',
        [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]
    );

    if (
        is_wp_error($remote) 
        || 200 !== wp_remote_retrieve_response_code($remote) 
        || empty(wp_remote_retrieve_body($remote))
    ) {
        return $transient;
    }

    $remote = json_decode(wp_remote_retrieve_body($remote));
    
    // Se não encontrou release, retorna
    if (!isset($remote->tag_name)) {
        return $transient;
    }

    // Remove o 'v' do início da tag se existir
    $new_version = ltrim($remote->tag_name, 'v');

    // Compara versões
    if (
        version_compare($new_version, WPWEVO_VERSION, '<=')
    ) {
        return $transient;
    }

    $plugin_slug = plugin_basename(__FILE__);
    $item = (object)[
        'id'            => $plugin_slug,
        'slug'          => dirname($plugin_slug),
        'plugin'        => $plugin_slug,
        'new_version'   => $new_version,
        'url'           => 'https://github.com/' . WPWEVO_GITHUB_REPO,
        'package'       => $remote->zipball_url,
        'icons'         => [],
        'banners'       => [],
        'banners_rtl'   => [],
        'tested'        => '',
        'requires_php'  => WPWEVO_MIN_PHP_VERSION,
        'compatibility' => new stdClass(),
    ];

    // Adiciona nossa atualização
    $transient->response[$plugin_slug] = $item;

    return $transient;
}

// Modifica a mensagem de atualização disponível
add_filter('in_plugin_update_message-' . plugin_basename(__FILE__), 'wpwevo_show_upgrade_notification', 10, 2);
function wpwevo_show_upgrade_notification($current, $new) {
    // Se não tem release notes, retorna
    if (empty($new->releases_notes)) {
        return;
    }

    echo '<br><br><strong>Notas da atualização:</strong><br>';
    echo wp_kses_post($new->releases_notes);
} 