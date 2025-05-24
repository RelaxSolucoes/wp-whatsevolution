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
		$this->setup_autoloader();
		$this->setup_hooks();
	}

	private function setup_autoloader() {
		spl_autoload_register(function ($class) {
			// Verifica se a classe pertence ao namespace do plugin
			if (strpos($class, 'WpWhatsAppEvolution\\') !== 0) {
				return;
			}

			// Remove o namespace e converte para o caminho do arquivo
			$class_path = str_replace('WpWhatsAppEvolution\\', '', $class);
			$class_path = str_replace('_', '-', strtolower($class_path));
			$file_path = WPWEVO_PATH . 'includes/class-' . $class_path . '.php';

			if (file_exists($file_path)) {
				require_once $file_path;
			}
		});
	}

	private function setup_hooks() {
		// Hooks de ativação/desativação
		register_activation_hook(WPWEVO_PATH . 'wp-whatsapp-evolution.php', [$this, 'activate']);
		register_deactivation_hook(WPWEVO_PATH . 'wp-whatsapp-evolution.php', [$this, 'deactivate']);

		// Carrega traduções e inicializa módulos
		add_action('init', [$this, 'load_textdomain'], 0);
		add_action('init', [$this, 'init_modules'], 1);

		// Adiciona link de configurações
		add_filter('plugin_action_links_' . plugin_basename(WPWEVO_PATH . 'wp-whatsapp-evolution.php'), 
			[$this, 'add_settings_link']
		);
	}

	public function init_modules() {
		// Inicializa os módulos principais após carregar as traduções
		Settings_Page::init();
		Send_Single::init();
		Send_By_Status::init();
		Abandoned_Cart::init();
		Bulk_Sender::init();
		Templates_Manager::init();
	}

	public function activate() {
		// Cria tabelas e configurações iniciais se necessário
		if (!get_option('wpwevo_version')) {
			update_option('wpwevo_version', WPWEVO_VERSION);
		}
	}

	public function deactivate() {
		// Remove crons
		wp_clear_scheduled_hook('wpwevo_abandoned_cart_cron');
		wp_clear_scheduled_hook('wpwevo_bulk_send_cron');
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-whatsapp-evolution',
			false,
			dirname(plugin_basename(WPWEVO_PATH . 'wp-whatsapp-evolution.php')) . '/languages'
		);
	}

	public function add_settings_link($links) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url('admin.php?page=wpwevo-settings'),
			__('Configurações', 'wp-whatsapp-evolution')
		);
		array_unshift($links, $settings_link);
		return $links;
	}
} 