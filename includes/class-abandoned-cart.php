<?php
namespace WpWhatsAppEvolution;

// Carrinho abandonado WooCommerce
class Abandoned_Cart {
	private static $instance = null;
	private $menu_title;
	private $page_title;
	private $i18n;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Verifica se o WooCommerce está ativo e compatível
		if (!class_exists('WooCommerce')) {
			return;
		}

		// Declara compatibilidade com HPOS
		add_action('before_woocommerce_init', function() {
			if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
			}
		});

		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_submenu']);
		add_action('woocommerce_init', [$this, 'track_cart']);
		add_action('wpwevo_abandoned_cart_cron', [$this, 'process_abandoned_carts']);
		add_action('woocommerce_after_checkout_billing_form', [$this, 'add_phone_field']);
		
		// Registra o cron se não existir
		if (!wp_next_scheduled('wpwevo_abandoned_cart_cron')) {
			wp_schedule_event(time(), 'minute', 'wpwevo_abandoned_cart_cron');
		}
	}

	public function setup() {
		$this->menu_title = __('Carrinho Abandonado', 'wp-whatsapp-evolution');
		$this->page_title = __('Carrinho Abandonado', 'wp-whatsapp-evolution');
		
		$this->i18n = [
			'connection_error' => __('A conexão com o WhatsApp não está ativa. Verifique as configurações.', 'wp-whatsapp-evolution'),
			'settings_saved' => __('Configurações salvas!', 'wp-whatsapp-evolution'),
			'variables' => [
				'title' => __('Variáveis Disponíveis', 'wp-whatsapp-evolution'),
				'customer_name' => __('Nome do cliente', 'wp-whatsapp-evolution'),
				'cart_total' => __('Valor total do carrinho', 'wp-whatsapp-evolution'),
				'cart_items' => __('Lista de produtos no carrinho', 'wp-whatsapp-evolution'),
				'cart_url' => __('Link para recuperar o carrinho', 'wp-whatsapp-evolution')
			],
			'form' => [
				'enable_recovery' => __('Ativar Recuperação', 'wp-whatsapp-evolution'),
				'enable_help' => __('Ative para enviar mensagens automáticas para carrinhos abandonados.', 'wp-whatsapp-evolution'),
				'wait_time' => __('Tempo de Espera', 'wp-whatsapp-evolution'),
				'wait_help' => __('minutos antes de considerar o carrinho abandonado', 'wp-whatsapp-evolution'),
				'message' => __('Mensagem', 'wp-whatsapp-evolution'),
				'message_placeholder' => __('Olá {customer_name}! Notamos que você deixou alguns produtos no carrinho...', 'wp-whatsapp-evolution'),
				'message_help' => __('Mensagem que será enviada para recuperar o carrinho abandonado.', 'wp-whatsapp-evolution'),
				'save_settings' => __('Salvar Configurações', 'wp-whatsapp-evolution')
			],
			'stats' => [
				'title' => __('Estatísticas', 'wp-whatsapp-evolution'),
				'monitored_carts' => __('Carrinhos Monitorados', 'wp-whatsapp-evolution'),
				'recovered_carts' => __('Carrinhos Recuperados', 'wp-whatsapp-evolution')
			]
		];
	}

	public function add_submenu() {
		add_submenu_page(
			'wpwevo-settings',
			$this->page_title,
			$this->menu_title,
			'manage_options',
			'wpwevo-abandoned-cart',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! wpwevo_check_instance() ) {
			echo '<div class="notice notice-error"><p>' . 
				esc_html( $this->i18n['connection_error'] ) . 
				'</p></div>';
			return;
		}

		if ( isset( $_POST['wpwevo_abandoned_cart_nonce'] ) && wp_verify_nonce( $_POST['wpwevo_abandoned_cart_nonce'], 'wpwevo_abandoned_cart' ) ) {
			$minutes = absint( $_POST['wpwevo_abandoned_cart_minutes'] );
			$message = sanitize_textarea_field( $_POST['wpwevo_abandoned_cart_message'] );
			$enabled = isset( $_POST['wpwevo_abandoned_cart_enabled'] ) ? '1' : '0';
			
			update_option( 'wpwevo_abandoned_cart_minutes', $minutes );
			update_option( 'wpwevo_abandoned_cart_message', $message );
			update_option( 'wpwevo_abandoned_cart_enabled', $enabled );
			
			echo '<div class="notice notice-success"><p>' . esc_html( $this->i18n['settings_saved'] ) . '</p></div>';
		}

		$minutes = get_option( 'wpwevo_abandoned_cart_minutes', 30 );
		$message = get_option( 'wpwevo_abandoned_cart_message', '' );
		$enabled = get_option( 'wpwevo_abandoned_cart_enabled', '0' );
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html( $this->page_title ); ?></h1>
			
			<div class="wpwevo-abandoned-form">
				<form method="post">
					<?php wp_nonce_field( 'wpwevo_abandoned_cart', 'wpwevo_abandoned_cart_nonce' ); ?>
					
					<div class="wpwevo-variables-help">
						<h3><?php echo esc_html( $this->i18n['variables']['title'] ); ?></h3>
						<ul>
							<li><code>{customer_name}</code> - <?php echo esc_html( $this->i18n['variables']['customer_name'] ); ?></li>
							<li><code>{cart_total}</code> - <?php echo esc_html( $this->i18n['variables']['cart_total'] ); ?></li>
							<li><code>{cart_items}</code> - <?php echo esc_html( $this->i18n['variables']['cart_items'] ); ?></li>
							<li><code>{cart_url}</code> - <?php echo esc_html( $this->i18n['variables']['cart_url'] ); ?></li>
						</ul>
					</div>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label class="wpwevo-status-label">
									<input type="checkbox" 
										   name="wpwevo_abandoned_cart_enabled" 
										   value="1" 
										   <?php checked( $enabled, '1' ); ?>>
									<?php echo esc_html( $this->i18n['form']['enable_recovery'] ); ?>
								</label>
							</th>
							<td>
								<p class="description">
									<?php echo esc_html( $this->i18n['form']['enable_help'] ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html( $this->i18n['form']['wait_time'] ); ?></th>
							<td>
								<input type="number" 
									   name="wpwevo_abandoned_cart_minutes" 
									   value="<?php echo esc_attr( $minutes ); ?>" 
									   min="5" max="1440" step="5"
									   class="small-text">
								<span class="description">
									<?php echo esc_html( $this->i18n['form']['wait_help'] ); ?>
								</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html( $this->i18n['form']['message'] ); ?></th>
							<td>
								<textarea name="wpwevo_abandoned_cart_message" 
										  rows="4" class="large-text"
										  placeholder="<?php echo esc_attr( $this->i18n['form']['message_placeholder'] ); ?>"><?php 
									echo esc_textarea( $message ); 
								?></textarea>
								<p class="description">
									<?php echo esc_html( $this->i18n['form']['message_help'] ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( $this->i18n['form']['save_settings'] ); ?>
				</form>
			</div>

			<div class="wpwevo-abandoned-stats">
				<h3><?php echo esc_html( $this->i18n['stats']['title'] ); ?></h3>
				<?php
				$carts = get_option( 'wpwevo_abandoned_carts', [] );
				$total = count( $carts );
				$recovered = get_option( 'wpwevo_recovered_carts', 0 );
				?>
				<div class="wpwevo-stats-grid">
					<div class="wpwevo-stat-box">
						<span class="wpwevo-stat-number"><?php echo esc_html( $total ); ?></span>
						<span class="wpwevo-stat-label"><?php echo esc_html( $this->i18n['stats']['monitored_carts'] ); ?></span>
					</div>
					<div class="wpwevo-stat-box">
						<span class="wpwevo-stat-number"><?php echo esc_html( $recovered ); ?></span>
						<span class="wpwevo-stat-label"><?php echo esc_html( $this->i18n['stats']['recovered_carts'] ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function track_cart() {
		if (!get_option('wpwevo_abandoned_cart_enabled', '0')) {
			return;
		}

		// Verifica se estamos no frontend e se o WooCommerce está disponível
		if (is_admin() || !function_exists('WC') || !did_action('woocommerce_init')) {
			return;
		}

		// Verifica se o carrinho e a sessão estão disponíveis
		if (!WC()->cart || !WC()->session) {
			return;
		}

		// Verifica se o carrinho está vazio
		if (WC()->cart->is_empty()) {
			return;
		}

		try {
			// Tenta obter o telefone da sessão ou cookie
			$phone = WC()->session->get('billing_phone');
			if (!$phone && isset($_COOKIE['wpwevo_phone'])) {
				$phone = sanitize_text_field($_COOKIE['wpwevo_phone']);
			}

			if ($phone) {
				$cart_data = [
					'time' => time(),
					'cart' => WC()->cart->get_cart(),
					'total' => WC()->cart->get_total(),
					'items' => [],
				];

				foreach (WC()->cart->get_cart() as $cart_item) {
					$product = $cart_item['data'];
					if ($product && is_object($product) && $product instanceof \WC_Product) {
						$cart_data['items'][] = [
							'name' => $product->get_name(),
							'quantity' => $cart_item['quantity'],
							'price' => $cart_item['line_total'],
						];
					}
				}

				$carts = get_option('wpwevo_abandoned_carts', []);
				$carts[$phone] = $cart_data;
				update_option('wpwevo_abandoned_carts', $carts);
			}
		} catch (\Exception $e) {
			// Log o erro mas não interrompe o fluxo
			error_log('WP WhatsApp Evolution - Erro ao rastrear carrinho: ' . $e->getMessage());
		}
	}

	public function process_abandoned_carts() {
		if (!get_option('wpwevo_abandoned_cart_enabled', '0')) {
			return;
		}

		// Verifica se o WooCommerce está ativo
		if (!function_exists('WC')) {
			return;
		}

		try {
			$minutes = get_option('wpwevo_abandoned_cart_minutes', 30);
			$message_template = get_option('wpwevo_abandoned_cart_message', '');
			$carts = get_option('wpwevo_abandoned_carts', []);

			foreach ($carts as $phone => $data) {
				if (time() - $data['time'] > $minutes * 60) {
					$message = str_replace(
						[
							'{cart_total}',
							'{cart_items}',
							'{cart_url}',
						],
						[
							wc_price($data['total']),
							$this->format_cart_items($data['items']),
							wc_get_cart_url(),
						],
						$message_template
					);

					$api = Api_Connection::get_instance();
					$result = $api->send_message($phone, $message);

					if ($result['success']) {
						unset($carts[$phone]);
						update_option('wpwevo_abandoned_carts', $carts);
						update_option('wpwevo_recovered_carts', get_option('wpwevo_recovered_carts', 0) + 1);
					}
				}
			}
		} catch (\Exception $e) {
			error_log('WP WhatsApp Evolution - Erro ao processar carrinhos abandonados: ' . $e->getMessage());
		}
	}

	private function format_cart_items( $items ) {
		$formatted = [];
		foreach ( $items as $item ) {
			$formatted[] = sprintf(
				'%dx %s - %s',
				$item['quantity'],
				$item['name'],
				wc_price( $item['price'] )
			);
		}
		return implode( "\n", $formatted );
	}

	public function add_phone_field( $checkout ) {
		woocommerce_form_field( 'billing_phone', [
			'type' => 'tel',
			'class' => ['form-row-wide'],
			'label' => __('Telefone', 'wp-whatsapp-evolution'),
			'placeholder' => __('Seu número de WhatsApp com DDD', 'wp-whatsapp-evolution'),
			'required' => true,
			'clear' => true
		], $checkout->get_value( 'billing_phone' ) );
	}
} 