<?php
namespace WpWhatsAppEvolution;

/**
 * Página de visualização de logs
 */
class Logs_Page {
	private static $instance = null;

	public static function init() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action('admin_menu', [$this, 'add_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wpwevo_clear_all_logs', [$this, 'handle_clear_logs']);
	}

	public function add_menu() {
		add_submenu_page(
			'wpwevo-settings',
			__('Logs de Envio', 'wp-whatsevolution'),
			__('Logs', 'wp-whatsevolution'),
			'manage_options',
			'wpwevo-logs',
			[$this, 'render_page']
		);
	}

	public function enqueue_scripts($hook) {
		if (strpos($hook, 'wpwevo-logs') === false) {
			return;
		}

		wp_enqueue_style(
			'wpwevo-logs',
			WPWEVO_URL . 'assets/css/admin.css',
			[],
			WPWEVO_VERSION
		);
	}

	public function render_page() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpwevo_logs';

		// Filtros
		$level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
		$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
		$per_page = 50;
		$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
		$offset = ($page - 1) * $per_page;

		// Monta query
		$where = [];
		$params = [];

		if (!empty($level_filter)) {
			$where[] = 'level = %s';
			$params[] = $level_filter;
		}

		if (!empty($search)) {
			$where[] = '(message LIKE %s OR context LIKE %s)';
			$params[] = '%' . $wpdb->esc_like($search) . '%';
			$params[] = '%' . $wpdb->esc_like($search) . '%';
		}

		$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

		// Conta total
		$count_query = "SELECT COUNT(*) FROM $table_name $where_clause";
		if (!empty($params)) {
			$count_query = $wpdb->prepare($count_query, $params);
		}
		$total_items = $wpdb->get_var($count_query);

		// Busca logs
		$query = "SELECT * FROM $table_name $where_clause ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		$params[] = $per_page;
		$params[] = $offset;
		$logs = $wpdb->get_results($wpdb->prepare($query, $params));

		$total_pages = ceil($total_items / $per_page);

		?>
		<div class="wrap">
			<h1>📋 <?php _e('Logs de Envio', 'wp-whatsevolution'); ?></h1>

			<div class="wpwevo-card" style="margin: 20px 0; padding: 20px;">
				<form method="get" style="display: flex; gap: 10px; align-items: end;">
					<input type="hidden" name="page" value="wpwevo-logs">

					<div>
						<label><?php _e('Nível:', 'wp-whatsevolution'); ?></label><br>
						<select name="level" style="padding: 5px; min-width: 120px;">
							<option value=""><?php _e('Todos', 'wp-whatsevolution'); ?></option>
							<option value="debug" <?php selected($level_filter, 'debug'); ?>>Debug</option>
							<option value="info" <?php selected($level_filter, 'info'); ?>>Info</option>
							<option value="warning" <?php selected($level_filter, 'warning'); ?>>Warning</option>
							<option value="error" <?php selected($level_filter, 'error'); ?>>Error</option>
						</select>
					</div>

					<div>
						<label><?php _e('Buscar:', 'wp-whatsevolution'); ?></label><br>
						<input type="text" name="search" value="<?php echo esc_attr($search); ?>"
							   placeholder="<?php _e('Pesquisar logs...', 'wp-whatsevolution'); ?>"
							   style="padding: 5px; min-width: 300px;">
					</div>

					<button type="submit" class="button button-primary">
						🔍 <?php _e('Filtrar', 'wp-whatsevolution'); ?>
					</button>

					<button type="button" id="clear-logs" class="button button-secondary" style="margin-left: auto; background: #dc3545; color: white; border-color: #dc3545;">
						🗑️ <?php _e('Limpar Logs', 'wp-whatsevolution'); ?>
					</button>
				</form>
			</div>

			<?php if (empty($logs)): ?>
				<div class="wpwevo-card" style="padding: 40px; text-align: center;">
					<p style="font-size: 16px; color: #666;">
						<?php _e('Nenhum log encontrado.', 'wp-whatsevolution'); ?>
					</p>
				</div>
			<?php else: ?>
				<div class="wpwevo-card" style="padding: 20px;">
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 140px;"><?php _e('Data/Hora', 'wp-whatsevolution'); ?></th>
								<th style="width: 80px;"><?php _e('Nível', 'wp-whatsevolution'); ?></th>
								<th><?php _e('Mensagem', 'wp-whatsevolution'); ?></th>
								<th style="width: 40%;"><?php _e('Contexto', 'wp-whatsevolution'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($logs as $log): ?>
								<?php
								$level_colors = [
									'debug' => '#6c757d',
									'info' => '#17a2b8',
									'warning' => '#ffc107',
									'error' => '#dc3545'
								];
								$color = $level_colors[$log->level] ?? '#6c757d';
								?>
								<tr>
									<td style="font-size: 11px; color: #666;">
										<?php echo date_i18n('d/m/Y H:i:s', strtotime($log->timestamp)); ?>
									</td>
									<td>
										<span style="display: inline-block; padding: 4px 8px; border-radius: 4px; background: <?php echo $color; ?>; color: white; font-size: 11px; font-weight: bold; text-transform: uppercase;">
											<?php echo esc_html($log->level); ?>
										</span>
									</td>
									<td>
										<?php echo esc_html($log->message); ?>
									</td>
									<td>
										<?php if (!empty($log->context)): ?>
											<details>
												<summary style="cursor: pointer; color: #0073aa;">
													<?php _e('Ver detalhes', 'wp-whatsevolution'); ?>
												</summary>
												<pre style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px; font-size: 11px; overflow-x: auto; max-height: 200px;"><?php
													$context = json_decode($log->context, true);
													if (json_last_error() === JSON_ERROR_NONE) {
														echo esc_html(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
													} else {
														echo esc_html($log->context);
													}
												?></pre>
											</details>
										<?php else: ?>
											<span style="color: #999;">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ($total_pages > 1): ?>
						<div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
							<?php
							$base_url = add_query_arg([
								'page' => 'wpwevo-logs',
								'level' => $level_filter,
								'search' => $search
							], admin_url('admin.php'));

							if ($page > 1): ?>
								<a href="<?php echo esc_url(add_query_arg('paged', $page - 1, $base_url)); ?>" class="button">
									← <?php _e('Anterior', 'wp-whatsevolution'); ?>
								</a>
							<?php endif; ?>

							<span style="padding: 8px 15px; background: #f0f0f1; border-radius: 4px;">
								<?php printf(__('Página %d de %d', 'wp-whatsevolution'), $page, $total_pages); ?>
							</span>

							<?php if ($page < $total_pages): ?>
								<a href="<?php echo esc_url(add_query_arg('paged', $page + 1, $base_url)); ?>" class="button">
									<?php _e('Próxima', 'wp-whatsevolution'); ?> →
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#clear-logs').on('click', function() {
				if (!confirm('<?php _e('Tem certeza que deseja limpar TODOS os logs? Esta ação não pode ser desfeita.', 'wp-whatsevolution'); ?>')) {
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true).text('<?php _e('Limpando...', 'wp-whatsevolution'); ?>');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpwevo_clear_all_logs',
						nonce: '<?php echo wp_create_nonce('wpwevo_clear_all_logs'); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							var errorMsg = (response.data && response.data.message) ? response.data.message : '<?php _e('Erro ao limpar logs.', 'wp-whatsevolution'); ?>';
							alert(errorMsg);
							$btn.prop('disabled', false).text('🗑️ <?php _e('Limpar Logs', 'wp-whatsevolution'); ?>');
						}
					},
					error: function() {
						alert('<?php _e('Erro ao limpar logs.', 'wp-whatsevolution'); ?>');
						$btn.prop('disabled', false).text('🗑️ <?php _e('Limpar Logs', 'wp-whatsevolution'); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}

	public function handle_clear_logs() {
		check_ajax_referer('wpwevo_clear_all_logs', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permissão negada.', 'wp-whatsevolution')]);
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wpwevo_logs';
		$wpdb->query("TRUNCATE TABLE $table_name");

		wp_send_json_success(['message' => __('Logs limpos com sucesso!', 'wp-whatsevolution')]);
	}
}
