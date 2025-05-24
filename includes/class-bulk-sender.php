<?php
namespace WpWhatsAppEvolution;

// Envio em massa de mensagens WhatsApp
class Bulk_Sender {
	private static $instance = null;
	private $menu_title;
	private $page_title;
	private $i18n;
	private $api;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_submenu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wpwevo_bulk_send', [$this, 'handle_bulk_send']);
		add_action('wp_ajax_wpwevo_preview_customers', [$this, 'preview_customers']);
		add_action('wpwevo_bulk_send_cron', [$this, 'process_bulk_queue']);

		// Inicializa a API
		$this->api = Api_Connection::get_instance();

		// Registra o cron se não existir
		if (!wp_next_scheduled('wpwevo_bulk_send_cron')) {
			wp_schedule_event(time(), 'minute', 'wpwevo_bulk_send_cron');
		}
	}

	public function setup() {
		$this->menu_title = __('Envio em Massa', 'wp-whatsapp-evolution');
		$this->page_title = __('Envio em Massa', 'wp-whatsapp-evolution');
		
		$this->i18n = [
			'connection_error' => __('A conexão com o WhatsApp não está ativa. Verifique as configurações.', 'wp-whatsapp-evolution'),
			'sending' => __('Enviando...', 'wp-whatsapp-evolution'),
			'preview' => __('Visualizar', 'wp-whatsapp-evolution'),
			'variables' => [
				'title' => __('Variáveis Disponíveis', 'wp-whatsapp-evolution'),
				'customer_name' => __('Nome do cliente', 'wp-whatsapp-evolution'),
				'customer_email' => __('Email do cliente', 'wp-whatsapp-evolution'),
				'total_orders' => __('Total de pedidos do cliente', 'wp-whatsapp-evolution'),
				'last_order_date' => __('Data do último pedido', 'wp-whatsapp-evolution')
			],
			'tabs' => [
				'customers' => __('Clientes WooCommerce', 'wp-whatsapp-evolution'),
				'csv' => __('Importar CSV', 'wp-whatsapp-evolution'),
				'manual' => __('Lista Manual', 'wp-whatsapp-evolution')
			],
			'form' => [
				'order_status' => __('Filtrar por Status', 'wp-whatsapp-evolution'),
				'status_help' => __('Selecione os status dos pedidos para filtrar os clientes.', 'wp-whatsapp-evolution'),
				'period' => __('Período', 'wp-whatsapp-evolution'),
				'to' => __('até', 'wp-whatsapp-evolution'),
				'min_value' => __('Valor Mínimo', 'wp-whatsapp-evolution'),
				'preview_customers' => __('Visualizar Clientes', 'wp-whatsapp-evolution'),
				'csv_file' => __('Arquivo CSV', 'wp-whatsapp-evolution'),
				'csv_help' => __('O arquivo deve ter as colunas: nome, telefone (com DDD e país)', 'wp-whatsapp-evolution'),
				'number_list' => __('Lista de Números', 'wp-whatsapp-evolution'),
				'number_placeholder' => __('Um número por linha, com DDD e país', 'wp-whatsapp-evolution'),
				'message' => __('Mensagem', 'wp-whatsapp-evolution'),
				'message_placeholder' => __('Digite sua mensagem aqui...', 'wp-whatsapp-evolution'),
				'schedule' => __('Agendamento', 'wp-whatsapp-evolution'),
				'schedule_enable' => __('Agendar envio', 'wp-whatsapp-evolution'),
				'schedule_help' => __('Data e hora para iniciar o envio das mensagens.', 'wp-whatsapp-evolution'),
				'interval' => __('Intervalo', 'wp-whatsapp-evolution'),
				'interval_help' => __('segundos entre cada envio', 'wp-whatsapp-evolution'),
				'start_sending' => __('Iniciar Envio', 'wp-whatsapp-evolution')
			]
		];
	}

	public function add_submenu() {
		add_submenu_page(
			'wpwevo-settings',
			$this->page_title,
			$this->menu_title,
			'manage_options',
			'wpwevo-bulk-send',
			[$this, 'render_page']
		);
	}

	public function enqueue_scripts($hook) {
		if ('whatsapp-evolution_page_wpwevo-bulk-send' !== $hook) {
			return;
		}

		wp_enqueue_script(
			'wpwevo-bulk-send',
			WPWEVO_URL . 'assets/js/bulk-send.js',
			['jquery'],
			WPWEVO_VERSION,
			true
		);

		wp_localize_script('wpwevo-bulk-send', 'wpwevoBulkSend', [
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('wpwevo_bulk_send'),
			'i18n' => [
				'sending' => $this->i18n['sending'],
				'preview' => $this->i18n['preview']
			]
		]);
	}

	public function render_page() {
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html($this->page_title); ?></h1>
			
			<div class="wpwevo-bulk-form">
				<form method="post" id="wpwevo-bulk-form" enctype="multipart/form-data">
					<?php wp_nonce_field('wpwevo_bulk_send', 'wpwevo_bulk_send_nonce'); ?>
					
					<div class="wpwevo-variables-help">
						<h3><?php echo esc_html($this->i18n['variables']['title']); ?></h3>
						<ul>
							<li><code>{customer_name}</code> - <?php echo esc_html($this->i18n['variables']['customer_name']); ?></li>
							<li><code>{customer_email}</code> - <?php echo esc_html($this->i18n['variables']['customer_email']); ?></li>
							<li><code>{total_orders}</code> - <?php echo esc_html($this->i18n['variables']['total_orders']); ?></li>
							<li><code>{last_order_date}</code> - <?php echo esc_html($this->i18n['variables']['last_order_date']); ?></li>
						</ul>
					</div>

					<div class="wpwevo-tabs">
						<button type="button" class="wpwevo-tab-button active" data-tab="customers">
							<?php echo esc_html($this->i18n['tabs']['customers']); ?>
						</button>
						<button type="button" class="wpwevo-tab-button" data-tab="csv">
							<?php echo esc_html($this->i18n['tabs']['csv']); ?>
						</button>
						<button type="button" class="wpwevo-tab-button" data-tab="manual">
							<?php echo esc_html($this->i18n['tabs']['manual']); ?>
						</button>
					</div>

					<div class="wpwevo-tab-content active" id="tab-customers">
						<table class="form-table">
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['order_status']); ?></th>
								<td>
									<select name="wpwevo_order_status[]" multiple class="regular-text">
										<?php
										$statuses = wc_get_order_statuses();
										foreach ($statuses as $status => $label) {
											printf(
												'<option value="%s">%s</option>',
												esc_attr($status),
												esc_html($label)
											);
										}
										?>
									</select>
									<p class="description">
										<?php echo esc_html($this->i18n['form']['status_help']); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['period']); ?></th>
								<td>
									<input type="date" name="wpwevo_date_from" class="regular-text">
									<span class="description"><?php echo esc_html($this->i18n['form']['to']); ?></span>
									<input type="date" name="wpwevo_date_to" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['min_value']); ?></th>
								<td>
									<input type="number" name="wpwevo_min_total" min="0" step="0.01" class="regular-text">
								</td>
							</tr>
						</table>

						<button type="button" class="button" id="wpwevo-preview-customers">
							<?php echo esc_html($this->i18n['form']['preview_customers']); ?>
						</button>

						<div id="wpwevo-customers-preview"></div>
					</div>

					<div class="wpwevo-tab-content" id="tab-csv">
						<table class="form-table">
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['csv_file']); ?></th>
								<td>
									<input type="file" name="wpwevo_csv_file" accept=".csv">
									<p class="description">
										<?php echo esc_html($this->i18n['form']['csv_help']); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="wpwevo-tab-content" id="tab-manual">
						<table class="form-table">
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['number_list']); ?></th>
								<td>
									<textarea name="wpwevo_manual_numbers" rows="5" class="large-text"
											  placeholder="<?php echo esc_attr($this->i18n['form']['number_placeholder']); ?>"></textarea>
								</td>
							</tr>
						</table>
					</div>

					<table class="form-table">
						<tr>
							<th scope="row"><?php echo esc_html($this->i18n['form']['message']); ?></th>
							<td>
								<textarea name="wpwevo_bulk_message" id="wpwevo-bulk-message" 
										  rows="4" class="large-text" required
										  placeholder="<?php echo esc_attr($this->i18n['form']['message_placeholder']); ?>"></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html($this->i18n['form']['schedule']); ?></th>
							<td>
								<label>
									<input type="checkbox" name="wpwevo_schedule_enabled" value="1">
									<?php echo esc_html($this->i18n['form']['schedule_enable']); ?>
								</label>
								<div class="wpwevo-schedule-options" style="display: none;">
									<input type="datetime-local" name="wpwevo_schedule_date" class="regular-text">
									<p class="description">
										<?php echo esc_html($this->i18n['form']['schedule_help']); ?>
									</p>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html($this->i18n['form']['interval']); ?></th>
							<td>
								<input type="number" name="wpwevo_interval" value="5" min="1" max="60" class="small-text">
								<span class="description">
									<?php echo esc_html($this->i18n['form']['interval_help']); ?>
								</span>
							</td>
						</tr>
					</table>

					<div class="wpwevo-bulk-actions">
						<button type="submit" class="button button-primary" id="wpwevo-bulk-submit">
							<?php echo esc_html($this->i18n['form']['start_sending']); ?>
						</button>
						<span class="spinner"></span>
					</div>

					<div id="wpwevo-bulk-progress" style="display: none;">
						<div class="wpwevo-progress-bar">
							<div class="wpwevo-progress-fill"></div>
						</div>
						<p class="wpwevo-progress-status"></p>
					</div>
				</form>
			</div>

			<div class="wpwevo-bulk-history">
				<h3><?php _e( 'Histórico de Envios', 'wp-whatsapp-evolution' ); ?></h3>
				<?php
				$history = get_option( 'wpwevo_bulk_history', [] );
				if ( empty( $history ) ) {
					echo '<p>' . esc_html__( 'Nenhum envio em massa realizado ainda.', 'wp-whatsapp-evolution' ) . '</p>';
				} else {
					echo '<table class="widefat">';
					echo '<thead><tr>';
					echo '<th>' . esc_html__( 'Data', 'wp-whatsapp-evolution' ) . '</th>';
					echo '<th>' . esc_html__( 'Total', 'wp-whatsapp-evolution' ) . '</th>';
					echo '<th>' . esc_html__( 'Enviados', 'wp-whatsapp-evolution' ) . '</th>';
					echo '<th>' . esc_html__( 'Status', 'wp-whatsapp-evolution' ) . '</th>';
					echo '</tr></thead><tbody>';
					
					foreach ( array_reverse( $history ) as $item ) {
						printf(
							'<tr>
								<td>%s</td>
								<td>%d</td>
								<td>%d</td>
								<td>%s</td>
							</tr>',
							esc_html( date_i18n( 'd/m/Y H:i', $item['date'] ) ),
							esc_html( $item['total'] ),
							esc_html( $item['sent'] ),
							esc_html( $item['status'] )
						);
					}
					
					echo '</tbody></table>';
				}
				?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Tabs
			$('.wpwevo-tab-button').on('click', function() {
				var tab = $(this).data('tab');
				$('.wpwevo-tab-button').removeClass('active');
				$('.wpwevo-tab-content').removeClass('active');
				$(this).addClass('active');
				$('#tab-' + tab).addClass('active');
			});

			// Agendamento
			$('input[name="wpwevo_schedule_enabled"]').on('change', function() {
				$('.wpwevo-schedule-options').toggle(this.checked);
			});

			// Preview de clientes
			$('#wpwevo-preview-customers').on('click', function() {
				var $button = $(this);
				var $preview = $('#wpwevo-customers-preview');
				
				$button.prop('disabled', true);
				$preview.html('<div class="spinner is-active"></div>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpwevo_preview_customers',
						status: $('select[name="wpwevo_order_status[]"]').val(),
						date_from: $('input[name="wpwevo_date_from"]').val(),
						date_to: $('input[name="wpwevo_date_to"]').val(),
						min_total: $('input[name="wpwevo_min_total"]').val(),
						nonce: wpwevoBulkSend.nonce
					},
					success: function(response) {
						if (response.success) {
							$preview.html(response.data);
						} else {
							$preview.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
						}
					},
					error: function() {
						$preview.html('<div class="notice notice-error"><p><?php 
							esc_html_e( 'Erro ao carregar preview. Tente novamente.', 'wp-whatsapp-evolution' ); 
						?></p></div>');
					},
					complete: function() {
						$button.prop('disabled', false);
					}
				});
			});

			// Envio em massa
			$('#wpwevo-bulk-form').on('submit', function(e) {
				e.preventDefault();
				var $form = $(this);
				var $button = $('#wpwevo-bulk-submit');
				var $spinner = $form.find('.spinner');
				var $progress = $('#wpwevo-bulk-progress');
				var $fill = $progress.find('.wpwevo-progress-fill');
				var $status = $progress.find('.wpwevo-progress-status');

				$button.prop('disabled', true);
				$spinner.addClass('is-active');
				$progress.show();

				var formData = new FormData(this);
				formData.append('action', 'wpwevo_bulk_send');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							$status.html(response.data);
							$form.trigger('reset');
						} else {
							$status.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error"><p><?php 
							esc_html_e( 'Erro ao iniciar envio. Tente novamente.', 'wp-whatsapp-evolution' ); 
						?></p></div>');
					},
					complete: function() {
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});
		});
		</script>
		<?php
	}

	public function preview_customers() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', $_POST['statuses']) : [];
		$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
		$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
		$min_total = isset($_POST['min_total']) ? floatval($_POST['min_total']) : 0;

		$customers = $this->get_customers_numbers($statuses, $date_from, $date_to, $min_total);

		if (empty($customers)) {
			wp_send_json_error(__('Nenhum cliente encontrado com os filtros selecionados.', 'wp-whatsapp-evolution'));
		}

		ob_start();
		?>
		<div class="wpwevo-preview-table">
			<h4><?php printf(__('Total de clientes: %d', 'wp-whatsapp-evolution'), count($customers)); ?></h4>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php _e('Nome', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Telefone', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Total de Pedidos', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Último Pedido', 'wp-whatsapp-evolution'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($customers as $customer) : ?>
						<tr>
							<td><?php echo esc_html($customer['name']); ?></td>
							<td><?php echo esc_html($customer['phone']); ?></td>
							<td><?php echo esc_html($customer['total_orders']); ?></td>
							<td><?php echo esc_html($customer['last_order']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		wp_send_json_success(ob_get_clean());
	}

	public function handle_bulk_send() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		$message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
		if (empty($message)) {
			wp_send_json_error(__('Mensagem é obrigatória.', 'wp-whatsapp-evolution'));
		}

		$numbers = [];
		$source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';

		switch ($source) {
			case 'customers':
				$statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', $_POST['statuses']) : [];
				$date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
				$date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
				$min_total = isset($_POST['min_total']) ? floatval($_POST['min_total']) : 0;
				$numbers = $this->get_customers_numbers($statuses, $date_from, $date_to, $min_total);
				break;

			case 'csv':
				if (!isset($_FILES['csv_file'])) {
					wp_send_json_error(__('Arquivo CSV é obrigatório.', 'wp-whatsapp-evolution'));
				}
				$numbers = $this->process_csv_file($_FILES['csv_file']);
				break;

			case 'manual':
				$manual_numbers = isset($_POST['manual_numbers']) ? sanitize_textarea_field($_POST['manual_numbers']) : '';
				if (empty($manual_numbers)) {
					wp_send_json_error(__('Lista de números é obrigatória.', 'wp-whatsapp-evolution'));
				}
				$numbers = array_map('trim', explode("\n", $manual_numbers));
				break;

			default:
				wp_send_json_error(__('Fonte de números inválida.', 'wp-whatsapp-evolution'));
		}

		if (empty($numbers)) {
			wp_send_json_error(__('Nenhum número encontrado para envio.', 'wp-whatsapp-evolution'));
		}

		$schedule_enabled = isset($_POST['schedule_enabled']) && $_POST['schedule_enabled'] === '1';
		$schedule_date = isset($_POST['schedule_date']) ? sanitize_text_field($_POST['schedule_date']) : '';
		$interval = isset($_POST['interval']) ? intval($_POST['interval']) : 5;

		$queue = [
			'message' => $message,
			'numbers' => $numbers,
			'interval' => $interval,
			'schedule_date' => $schedule_enabled ? strtotime($schedule_date) : time(),
			'current_index' => 0,
			'total' => count($numbers),
			'success' => 0,
			'failed' => 0
		];

		update_option('wpwevo_bulk_queue', $queue);
		wp_send_json_success(__('Envio em massa iniciado!', 'wp-whatsapp-evolution'));
	}

	private function get_customers_numbers($statuses, $date_from, $date_to, $min_total) {
		global $wpdb;

		$args = [
			'status' => $statuses,
			'limit' => -1,
			'return' => 'ids'
		];

		if ($date_from) {
			$args['date_created'] = '>=' . strtotime($date_from);
		}

		if ($date_to) {
			if (isset($args['date_created'])) {
				$args['date_created'] = [
					$args['date_created'],
					'<=' . strtotime($date_to)
				];
			} else {
				$args['date_created'] = '<=' . strtotime($date_to);
			}
		}

		$order_ids = wc_get_orders($args);
		if (empty($order_ids)) {
			return [];
		}

		$customers = [];
		foreach ($order_ids as $order_id) {
			$order = wc_get_order($order_id);
			$phone = $order->get_billing_phone();
			
			if (empty($phone)) {
				continue;
			}

			$customer_id = $order->get_customer_id();
			if (!isset($customers[$customer_id])) {
				$customer = new \WC_Customer($customer_id);
				$total_spent = $customer->get_total_spent();
				
				if ($min_total > 0 && $total_spent < $min_total) {
					continue;
				}

				$customers[$customer_id] = [
					'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'phone' => preg_replace('/[^0-9]/', '', $phone),
					'total_orders' => $customer->get_order_count(),
					'last_order' => $order->get_date_created()->date_i18n('d/m/Y H:i:s')
				];
			}
		}

		return array_values($customers);
	}

	private function process_csv_file($file) {
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return [];
		}

		$handle = fopen($file['tmp_name'], 'r');
		if (!$handle) {
			return [];
		}

		$numbers = [];
		while (($data = fgetcsv($handle)) !== false) {
			if (isset($data[1])) {
				$numbers[] = preg_replace('/[^0-9]/', '', $data[1]);
			}
		}

		fclose($handle);
		return $numbers;
	}

	public function process_bulk_queue() {
		$queue = get_option('wpwevo_bulk_queue');
		if (!$queue || $queue['current_index'] >= $queue['total']) {
			return;
		}

		if ($queue['schedule_date'] > time()) {
			return;
		}

		$api = Api_Connection::get_instance();
		$number = $queue['numbers'][$queue['current_index']];
		$result = $api->send_message($number, $queue['message']);

		if ($result['success']) {
			$queue['success']++;
		} else {
			$queue['failed']++;
			wpwevo_log_error('Erro no envio em massa: ' . $result['message'], [
				'number' => $number,
				'index' => $queue['current_index']
			]);
		}

		$queue['current_index']++;
		update_option('wpwevo_bulk_queue', $queue);

		if ($queue['current_index'] >= $queue['total']) {
			wpwevo_log_info('Envio em massa concluído', [
				'total' => $queue['total'],
				'success' => $queue['success'],
				'failed' => $queue['failed']
			]);
		}
	}
} 