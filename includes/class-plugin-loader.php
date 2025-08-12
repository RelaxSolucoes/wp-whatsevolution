<?php
namespace WpWhatsAppEvolution;

// Loader principal do plugin
class Plugin_Loader {
	private static $instance = null;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->setup_hooks();
	}

	private function setup_hooks() {
        // Hooks de ativação/desativação movidos para o arquivo principal do plugin para evitar duplicação

		// Carrega traduções e inicializa módulos
		add_action('init', [$this, 'load_textdomain'], 0);
		add_action('init', [$this, 'init_modules'], 1);

		// Adiciona intervalos de cron personalizados
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Adiciona link de configurações
        add_filter('plugin_action_links_' . plugin_basename(WPWEVO_PATH . 'wp-whatsevolution.php'), 
			[$this, 'add_settings_link']
		);
	}

	public function init_modules() {
		// Inicializa os módulos principais após carregar as traduções
		Settings_Page::init();
		Send_Single::init();
		Send_By_Status::init();
		Cart_Abandonment::init();
		Bulk_Sender::init();
		Checkout_Validator::init();
		Quick_Signup::init();
	}

	public function activate() {
		// Cria tabelas e configurações iniciais se necessário
		if (!get_option('wpwevo_version')) {
			update_option('wpwevo_version', WPWEVO_VERSION);
		}
	}

	public function deactivate() {
		// Remove crons
		wp_clear_scheduled_hook('wpwevo_bulk_send_cron');
	}

	public function add_cron_schedules($schedules) {
        $schedules['every_5_minutes'] = [
			'interval' => 300, // 5 minutes
            'display' => __('A cada 5 minutos', 'wp-whatsevolution')
		];
		return $schedules;
	}

	public function load_textdomain() {
        load_plugin_textdomain(
            'wp-whatsevolution',
            false,
            dirname(plugin_basename(WPWEVO_PATH . 'wp-whatsevolution.php')) . '/languages'
		);
	}

	public function add_settings_link($links) {
        $settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url('admin.php?page=wpwevo-settings'),
            __('Configurações', 'wp-whatsevolution')
		);
		array_unshift($links, $settings_link);
		return $links;
	}
} 