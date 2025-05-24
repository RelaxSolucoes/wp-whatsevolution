<?php
namespace WpWhatsAppEvolution;

// Gerenciador de templates de mensagens
class Templates_Manager {
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
		add_action('init', [$this, 'setup']);
		add_action('admin_menu', [$this, 'add_submenu']);
		add_action('wp_ajax_wpwevo_save_template', [$this, 'save_template']);
		add_action('wp_ajax_wpwevo_delete_template', [$this, 'delete_template']);
		add_action('wp_ajax_wpwevo_preview_template', [$this, 'preview_template']);
	}

	public function setup() {
		$this->menu_title = __('Templates', 'wp-whatsapp-evolution');
		$this->page_title = __('Templates de Mensagem', 'wp-whatsapp-evolution');
		
		$this->i18n = [
			'connection_error' => __('A conexão com o WhatsApp não está ativa. Verifique as configurações.', 'wp-whatsapp-evolution'),
			'no_templates' => __('Nenhum template salvo ainda.', 'wp-whatsapp-evolution'),
			'save_error' => __('Erro ao salvar template. Tente novamente.', 'wp-whatsapp-evolution'),
			'delete_confirm' => __('Tem certeza que deseja excluir este template?', 'wp-whatsapp-evolution'),
			'variables' => [
				'title' => __('Variáveis Disponíveis', 'wp-whatsapp-evolution'),
				'customer_name' => __('Nome do cliente', 'wp-whatsapp-evolution'),
				'order_id' => __('Número do pedido', 'wp-whatsapp-evolution'),
				'order_total' => __('Valor total do pedido', 'wp-whatsapp-evolution'),
				'order_status' => __('Status do pedido', 'wp-whatsapp-evolution'),
				'payment_method' => __('Método de pagamento', 'wp-whatsapp-evolution'),
				'cart_total' => __('Valor total do carrinho', 'wp-whatsapp-evolution'),
				'cart_items' => __('Lista de produtos no carrinho', 'wp-whatsapp-evolution'),
				'cart_url' => __('Link para recuperar o carrinho', 'wp-whatsapp-evolution')
			]
		];
	}

	public function add_submenu() {
		add_submenu_page(
			'wpwevo-settings',
			$this->page_title,
			$this->menu_title,
			'manage_options',
			'wpwevo-templates',
			[$this, 'render_page']
		);
	}

	public function render_page() {
		if ( ! wpwevo_check_instance() ) {
			echo '<div class="notice notice-error"><p>' . 
				esc_html($this->i18n['connection_error']) . 
				'</p></div>';
			return;
		}

		$templates = get_option( 'wpwevo_templates', [] );
		?>
		<div class="wrap wpwevo-panel">
			<h1><?php echo esc_html($this->page_title); ?></h1>
			
			<div class="wpwevo-templates-form">
				<div class="wpwevo-variables-help">
					<h3><?php echo esc_html($this->i18n['variables']['title']); ?></h3>
					<ul>
						<li><code>{customer_name}</code> - <?php echo esc_html($this->i18n['variables']['customer_name']); ?></li>
						<li><code>{order_id}</code> - <?php echo esc_html($this->i18n['variables']['order_id']); ?></li>
						<li><code>{order_total}</code> - <?php echo esc_html($this->i18n['variables']['order_total']); ?></li>
						<li><code>{order_status}</code> - <?php echo esc_html($this->i18n['variables']['order_status']); ?></li>
						<li><code>{payment_method}</code> - <?php echo esc_html($this->i18n['variables']['payment_method']); ?></li>
						<li><code>{cart_total}</code> - <?php echo esc_html($this->i18n['variables']['cart_total']); ?></li>
						<li><code>{cart_items}</code> - <?php echo esc_html($this->i18n['variables']['cart_items']); ?></li>
						<li><code>{cart_url}</code> - <?php echo esc_html($this->i18n['variables']['cart_url']); ?></li>
					</ul>
				</div>

				<form id="wpwevo-template-form">
					<?php wp_nonce_field( 'wpwevo_template', 'wpwevo_template_nonce' ); ?>
					
					<table class="form-table">
						<tr>
							<th scope="row"><?php _e( 'Nome do Template', 'wp-whatsapp-evolution' ); ?></th>
							<td>
								<input type="text" name="wpwevo_template_name" id="wpwevo-template-name" 
									   class="regular-text" required
									   placeholder="<?php esc_attr_e( 'Ex: Boas-vindas', 'wp-whatsapp-evolution' ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row"><?php _e( 'Mensagem', 'wp-whatsapp-evolution' ); ?></th>
							<td>
								<textarea name="wpwevo_template_message" id="wpwevo-template-message" 
										  rows="4" class="large-text" required
										  placeholder="<?php esc_attr_e( 'Digite sua mensagem aqui...', 'wp-whatsapp-evolution' ); ?>"></textarea>
								<p class="description">
									<?php _e( 'Use as variáveis disponíveis para personalizar a mensagem.', 'wp-whatsapp-evolution' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<div class="wpwevo-template-actions">
						<button type="submit" class="button button-primary">
							<?php _e( 'Salvar Template', 'wp-whatsapp-evolution' ); ?>
						</button>
						<button type="button" class="button" id="wpwevo-preview-template">
							<?php _e( 'Visualizar', 'wp-whatsapp-evolution' ); ?>
						</button>
						<span class="spinner"></span>
					</div>

					<div id="wpwevo-template-preview" style="display: none;"></div>
				</form>
			</div>

			<div class="wpwevo-templates-list">
				<h3><?php _e( 'Templates Salvos', 'wp-whatsapp-evolution' ); ?></h3>
				
				<?php if ( empty( $templates ) ) : ?>
					<p><?php echo esc_html($this->i18n['no_templates']); ?></p>
				<?php else : ?>
					<table class="widefat">
						<thead>
							<tr>
								<th><?php _e( 'Nome', 'wp-whatsapp-evolution' ); ?></th>
								<th><?php _e( 'Mensagem', 'wp-whatsapp-evolution' ); ?></th>
								<th><?php _e( 'Ações', 'wp-whatsapp-evolution' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $templates as $id => $template ) : ?>
								<tr>
									<td><?php echo esc_html( $template['name'] ); ?></td>
									<td><?php echo esc_html( $template['message'] ); ?></td>
									<td>
										<button type="button" class="button wpwevo-edit-template" 
												data-id="<?php echo esc_attr( $id ); ?>"
												data-name="<?php echo esc_attr( $template['name'] ); ?>"
												data-message="<?php echo esc_attr( $template['message'] ); ?>">
											<?php _e( 'Editar', 'wp-whatsapp-evolution' ); ?>
										</button>
										<button type="button" class="button wpwevo-delete-template" 
												data-id="<?php echo esc_attr( $id ); ?>">
											<?php _e( 'Excluir', 'wp-whatsapp-evolution' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var $form = $('#wpwevo-template-form');
			var $preview = $('#wpwevo-template-preview');
			var currentId = '';

			// Salvar template
			$form.on('submit', function(e) {
				e.preventDefault();
				var $button = $(this).find('button[type="submit"]');
				var $spinner = $(this).find('.spinner');

				$button.prop('disabled', true);
				$spinner.addClass('is-active');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpwevo_save_template',
						id: currentId,
						name: $('#wpwevo-template-name').val(),
						message: $('#wpwevo-template-message').val(),
						nonce: $('#wpwevo_template_nonce').val()
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data);
						}
					},
					error: function() {
						alert('<?php echo esc_js($this->i18n['save_error']); ?>');
					},
					complete: function() {
						$button.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// Editar template
			$('.wpwevo-edit-template').on('click', function() {
				var $button = $(this);
				currentId = $button.data('id');
				$('#wpwevo-template-name').val($button.data('name'));
				$('#wpwevo-template-message').val($button.data('message'));
				$('html, body').animate({ scrollTop: $form.offset().top - 50 }, 500);
			});

			// Excluir template
			$('.wpwevo-delete-template').on('click', function() {
				if ( ! confirm('<?php echo esc_js($this->i18n['delete_confirm']); ?>') ) {
					return;
				}

				var $button = $(this);
				$button.prop('disabled', true);

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpwevo_delete_template',
						id: $button.data('id'),
						nonce: $('#wpwevo_template_nonce').val()
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert(response.data);
							$button.prop('disabled', false);
						}
					},
					error: function() {
						alert('<?php echo esc_js($this->i18n['save_error']); ?>');
						$button.prop('disabled', false);
					}
				});
			});

			// Preview do template
			$('#wpwevo-preview-template').on('click', function() {
				var $button = $(this);
				var $spinner = $form.find('.spinner');

				$button.prop('disabled', true);
				$spinner.addClass('is-active');
				$preview.hide();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'wpwevo_preview_template',
						message: $('#wpwevo-template-message').val(),
						nonce: $('#wpwevo_template_nonce').val()
					},
					success: function(response) {
						if (response.success) {
							$preview.html(response.data).fadeIn();
						} else {
							alert(response.data);
						}
					},
					error: function() {
						alert('<?php echo esc_js($this->i18n['save_error']); ?>');
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

	public function save_template() {
		check_ajax_referer( 'wpwevo_template', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissão negada.', 'wp-whatsapp-evolution' ) );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';

		if ( empty( $name ) || empty( $message ) ) {
			wp_send_json_error( __( 'Nome e mensagem são obrigatórios.', 'wp-whatsapp-evolution' ) );
		}

		$templates = get_option( 'wpwevo_templates', [] );
		
		if ( empty( $id ) ) {
			$id = uniqid( 'template_' );
		}

		$templates[ $id ] = [
			'name' => $name,
			'message' => $message,
		];

		update_option( 'wpwevo_templates', $templates );
		wp_send_json_success( __( 'Template salvo com sucesso!', 'wp-whatsapp-evolution' ) );
	}

	public function delete_template() {
		check_ajax_referer( 'wpwevo_template', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissão negada.', 'wp-whatsapp-evolution' ) );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( $_POST['id'] ) : '';
		if ( empty( $id ) ) {
			wp_send_json_error( __( 'ID do template é obrigatório.', 'wp-whatsapp-evolution' ) );
		}

		$templates = get_option( 'wpwevo_templates', [] );
		if ( ! isset( $templates[ $id ] ) ) {
			wp_send_json_error( __( 'Template não encontrado.', 'wp-whatsapp-evolution' ) );
		}

		unset( $templates[ $id ] );
		update_option( 'wpwevo_templates', $templates );

		wp_send_json_success( __( 'Template excluído com sucesso!', 'wp-whatsapp-evolution' ) );
	}

	public function preview_template() {
		check_ajax_referer( 'wpwevo_template', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permissão negada.', 'wp-whatsapp-evolution' ) );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
		if ( empty( $message ) ) {
			wp_send_json_error( __( 'Mensagem é obrigatória.', 'wp-whatsapp-evolution' ) );
		}

		// Simula dados para preview
		$preview = wpwevo_replace_vars( $message, [
			'customer_name' => 'João Silva',
			'order_id' => '123',
			'order_total' => 'R$ 150,00',
			'order_status' => 'Processando',
			'payment_method' => 'Cartão de Crédito',
			'cart_total' => 'R$ 200,00',
			'cart_items' => '2x Camiseta Preta, 1x Calça Jeans',
			'cart_url' => 'https://loja.exemplo.com/carrinho/123'
		] );

		wp_send_json_success( nl2br( esc_html( $preview ) ) );
	}
} 