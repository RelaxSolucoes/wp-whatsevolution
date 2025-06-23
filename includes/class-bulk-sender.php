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

	public function __construct() {
		// Define as propriedades ANTES dos hooks
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
				'last_order_date' => __('Data do último pedido', 'wp-whatsapp-evolution'),
				'for_all' => __('Comum a todas as fontes', 'wp-whatsapp-evolution'),
				'for_woo' => __('Específicas para WooCommerce', 'wp-whatsapp-evolution'),
				'for_csv' => __('Para importação CSV', 'wp-whatsapp-evolution'),
				'for_manual' => __('Variáveis não se aplicam à Lista Manual.', 'wp-whatsapp-evolution')
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
				'csv_help' => __('Para melhores resultados, use um arquivo CSV com as colunas "nome" e "telefone". O sistema aceita separação por ponto e vírgula (;) ou vírgula (,).', 'wp-whatsapp-evolution'),
				'csv_example_title' => __('Exemplo Visual da Estrutura:', 'wp-whatsapp-evolution'),
				'csv_download' => __('Baixar Arquivo de Exemplo', 'wp-whatsapp-evolution'),
				'number_list' => __('Lista de Números', 'wp-whatsapp-evolution'),
				'number_placeholder' => __('Um número por linha, com DDD e país', 'wp-whatsapp-evolution'),
				'message' => __('Mensagem', 'wp-whatsapp-evolution'),
				'message_placeholder' => __('Digite sua mensagem aqui...', 'wp-whatsapp-evolution'),
				'schedule' => __('Agendamento', 'wp-whatsapp-evolution'),
				'schedule_enable' => __('Agendar envio', 'wp-whatsapp-evolution'),
				'schedule_help' => __('Data e hora para iniciar o envio das mensagens.', 'wp-whatsapp-evolution'),
				'interval' => __('Intervalo', 'wp-whatsapp-evolution'),
				'interval_help' => __('segundos entre cada envio', 'wp-whatsapp-evolution'),
				'start_sending' => __('Iniciar Envio', 'wp-whatsapp-evolution'),
				'send_button' => __('Iniciar Envio', 'wp-whatsapp-evolution')
			],
			'history' => [
				'title' => __('Histórico de Envios', 'wp-whatsapp-evolution'),
				'clear' => __('Limpar Histórico', 'wp-whatsapp-evolution'),
				'confirm_clear' => __('Tem certeza que deseja limpar todo o histórico de envios?', 'wp-whatsapp-evolution'),
				'no_history' => __('Nenhum envio em massa realizado ainda.', 'wp-whatsapp-evolution'),
				'date' => __('Data', 'wp-whatsapp-evolution'),
				'source' => __('Origem', 'wp-whatsapp-evolution'),
				'total' => __('Total', 'wp-whatsapp-evolution'),
				'sent' => __('Enviados', 'wp-whatsapp-evolution'),
				'status' => __('Status', 'wp-whatsapp-evolution'),
				'sources' => [
					'customers' => __('Clientes WooCommerce', 'wp-whatsapp-evolution'),
					'csv' => __('Importação CSV', 'wp-whatsapp-evolution'),
					'manual' => __('Lista Manual', 'wp-whatsapp-evolution')
				]
			]
		];

		add_action('admin_menu', [$this, 'add_submenu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wpwevo_bulk_send', [$this, 'handle_bulk_send']);
		add_action('wp_ajax_wpwevo_preview_customers', [$this, 'preview_customers']);
		add_action('wp_ajax_wpwevo_get_history', [$this, 'ajax_get_history']);
		add_action('wp_ajax_wpwevo_clear_history', [$this, 'clear_history']);
		add_action('wp_ajax_wpwevo_test_ajax', [$this, 'test_ajax']);

		// Hook de debug para todas as requisições AJAX
		add_action('wp_ajax_nopriv_wpwevo_preview_customers', function() {
			wp_send_json_error('Acesso negado');
		});

		// Inicializa a API
		$this->api = Api_Connection::get_instance();
	}

	/**
	 * Método de teste para verificar se o AJAX está funcionando
	 */
	public function test_ajax() {
		wp_send_json_success(['message' => 'AJAX funcionando!', 'timestamp' => current_time('mysql')]);
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
		// Aplica scripts em qualquer página admin que contenha 'wpwevo'
		if (strpos($hook, 'wpwevo') === false) {
			return;
		}

		// BLINDAGEM DEFENSIVA: Carrega jquery-ui-datepicker se o plugin woo-cart-abandonment-recovery estiver ativo
		if (is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php')) {
			wp_enqueue_script('jquery-ui-datepicker');
		}

		wp_enqueue_style(
			'wpwevo-admin',
			WPWEVO_URL . 'assets/css/admin.css',
			[],
			WPWEVO_VERSION
		);

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
				'loading' => __('Carregando...', 'wp-whatsapp-evolution'),
				'error' => __('Erro ao carregar dados.', 'wp-whatsapp-evolution'),
				'success' => __('Enviado com sucesso!', 'wp-whatsapp-evolution'),
				'confirm' => __('Tem certeza que deseja enviar mensagens para todos os clientes selecionados?', 'wp-whatsapp-evolution'),
				'sending' => __('Enviando...', 'wp-whatsapp-evolution'),
				'send' => __('Iniciar Envio', 'wp-whatsapp-evolution'),
				'statusRequired' => __('Selecione pelo menos um status de pedido.', 'wp-whatsapp-evolution'),
				'confirmClearHistory' => __('Tem certeza que deseja limpar todo o histórico de envios?', 'wp-whatsapp-evolution')
			]
		]);
	}

	public function render_page() {
		?>
		<div class="wrap">
			<!-- Header com Gradiente Azul -->
			<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 25px; margin: 20px 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
				<div style="display: flex; align-items: center; color: white;">
					<div style="background: rgba(255,255,255,0.2); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-right: 20px;">
						📢
					</div>
					<div>
						<h1 style="margin: 0; color: white; font-size: 28px; font-weight: 600;"><?php echo esc_html($this->page_title); ?></h1>
						<p style="margin: 5px 0 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">Envie mensagens para múltiplos clientes de forma automatizada</p>
					</div>
				</div>
			</div>

			<!-- Container Principal -->
			<div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.2); overflow: hidden; margin-bottom: 20px;">
				<div style="background: rgba(255,255,255,0.98); margin: 2px; border-radius: 10px; padding: 25px;">
					
					<div class="wpwevo-bulk-form">
						<form method="post" id="wpwevo-bulk-form" enctype="multipart/form-data">
							<?php wp_nonce_field('wpwevo_bulk_send', 'wpwevo_bulk_send_nonce'); ?>
							
							<!-- Abas com CSS original -->
							<nav class="wpwevo-tabs">
								<a href="#tab-customers" class="wpwevo-tab-button active" data-tab="customers">
									🛒 <?php echo esc_html($this->i18n['tabs']['customers']); ?>
								</a>
								<a href="#tab-csv" class="wpwevo-tab-button" data-tab="csv">
									📄 <?php echo esc_html($this->i18n['tabs']['csv']); ?>
								</a>
								<a href="#tab-manual" class="wpwevo-tab-button" data-tab="manual">
									✍️ <?php echo esc_html($this->i18n['tabs']['manual']); ?>
								</a>
							</nav>

					<div class="wpwevo-tab-content active" id="tab-customers">
						<table class="form-table">
							<tr>
								<th scope="row"><?php echo esc_html($this->i18n['form']['order_status']); ?></th>
								<td>
									<div class="wpwevo-status-checkboxes">
										<?php
										$statuses = wc_get_order_statuses();
										foreach ($statuses as $status => $label) {
											$status_value = str_replace('wc-', '', $status);
											echo sprintf(
												'<label class="wpwevo-status-checkbox">
													<input type="checkbox" 
														   name="status[]" 
														   value="%s"
														   class="wpwevo-status-input"
														   id="wpwevo-status-%s">
													<span>%s</span>
												</label>',
												esc_attr($status_value),
												esc_attr($status_value),
												esc_html($label)
											);
										}
										?>
									</div>
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
									<div class="wpwevo-currency-input">
										<span class="wpwevo-currency-symbol">R$</span>
										<input type="text" 
											   name="wpwevo_min_total" 
											   class="regular-text wpwevo-currency-field" 
											   placeholder="0,00"
											   pattern="^\d*[0-9](|,\d{0,2}|,\d{2}|\d*[0-9]|,\d{0,2}\d*[0-9])$"
											   maxlength="15">
									</div>
									<style>
										.wpwevo-currency-input {
											position: relative;
											display: inline-block;
										}
										.wpwevo-currency-symbol {
											position: absolute;
											left: 8px;
											top: 50%;
											transform: translateY(-50%);
											color: #666;
										}
										.wpwevo-currency-field {
											padding-left: 25px !important;
										}
									</style>
									<script>
										jQuery(document).ready(function($) {
											function formatCurrency(value) {
												// Remove tudo exceto números e vírgula
												value = value.replace(/[^\d,]/g, '');
												
												// Garante apenas uma vírgula
												let commaCount = (value.match(/,/g) || []).length;
												if (commaCount > 1) {
													value = value.replace(/,/g, function(match, index, original) {
														return index === original.lastIndexOf(',') ? match : '';
													});
												}
												
												// Se não tem vírgula, adiciona ,00
												if (!value.includes(',')) {
													value = value + ',00';
												} else {
													// Se tem vírgula, garante 2 casas decimais
													let parts = value.split(',');
													if (parts[1]) {
														// Se tem decimais, completa ou limita a 2 casas
														if (parts[1].length === 1) {
															parts[1] = parts[1] + '0';
														} else if (parts[1].length > 2) {
															parts[1] = parts[1].substring(0, 2);
														}
													} else {
														// Se tem vírgula mas não tem número depois
														parts[1] = '00';
													}
													value = parts.join(',');
												}
												
												return value;
											}

											$('.wpwevo-currency-field').on('input', function(e) {
												let value = $(this).val();
												
												// Não formata se estiver vazio
												if (!value) return;
												
												// Remove formatação ao colar
												value = value.replace(/[^\d,]/g, '');
												
												$(this).val(value);
											});

											$('.wpwevo-currency-field').on('blur', function(e) {
												let value = $(this).val();
												
												// Não formata se estiver vazio
												if (!value) return;
												
												$(this).val(formatCurrency(value));
											});

											// Formata valor inicial se existir
											let initialValue = $('.wpwevo-currency-field').val();
											if (initialValue) {
												$('.wpwevo-currency-field').val(formatCurrency(initialValue));
											}
										});
									</script>
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
								<th scope="row">Arquivo CSV</th>
								<td>
									<input type="file" name="wpwevo_csv_file" accept=".csv">
									<div class="wpwevo-csv-instructions" style="margin-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">
										<details style="margin-bottom: 15px;">
											<summary style="cursor: pointer; font-weight: 600; font-size: 15px; color: #2d3748;">📋 Como importar sua lista (clique para ver instruções)</summary>
											<div style="margin-top: 12px; font-size: 14px; color: #495057;">
												<p><strong>1. Prepare seu arquivo CSV:</strong></p>
												<ul style="margin: 8px 0; padding-left: 20px;">
													<li>Primeira linha deve conter os nomes das colunas</li>
													<li>Inclua uma coluna com números de telefone (ex: "Telefone", "Celular", "Phone")</li>
													<li>Opcional: inclua uma coluna com nomes (ex: "Nome", "Name", "Cliente")</li>
													<li>Use vírgula (,) ou ponto e vírgula (;) como separador</li>
												</ul>
												
												<p><strong>2. Faça o upload:</strong></p>
												<ul style="margin: 8px 0; padding-left: 20px;">
													<li>Selecione seu arquivo CSV</li>
													<li>O sistema detectará automaticamente as colunas</li>
													<li>Escolha qual coluna contém os telefones</li>
												</ul>
												
												<p><strong>3. Envie as mensagens:</strong></p>
												<ul style="margin: 8px 0; padding-left: 20px;">
													<li>Digite sua mensagem (use {nome} para incluir o nome)</li>
													<li>Clique em "Iniciar Envio"</li>
												</ul>
												
												<div style="background: #e8f5e8; padding: 10px; border-radius: 6px; margin-top: 10px; border-left: 4px solid #48bb78;">
													<p style="margin: 0; font-size: 13px; color: #2f855a;">
														<strong>💡 Dica:</strong> O sistema é inteligente e detecta automaticamente colunas de telefone!
													</p>
												</div>
												
												<div style="margin-top: 15px; text-align: center;">
													<a href="data:text/csv;charset=utf-8,Nome,Telefone,Email%0D%0AJoão Silva,+55 11 99999-9999,joao@email.com%0D%0AMaria Santos,+55 21 88888-8888,maria@email.com%0D%0APedro Costa,+55 31 77777-7777,pedro@email.com" 
													   download="exemplo-contatos.csv" 
													   class="button button-secondary" 
													   style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
														📥 Baixar Arquivo de Exemplo
													</a>
												</div>
											</div>
										</details>
									</div>
									<div id="wpwevo-csv-column-mapping" style="margin-top: 20px; display: none;"></div>
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

							<!-- Seção de Mensagem e Configurações -->
							<div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #4facfe;">
								<h3 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px; display: flex; align-items: center;">
									<span style="margin-right: 10px;">💬</span> Mensagem
								</h3>
								<textarea name="wpwevo_bulk_message" id="wpwevo-bulk-message" 
										  rows="4" required
										  style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: monospace; font-size: 13px; line-height: 1.4; resize: vertical;"
										  placeholder="Digite sua mensagem aqui..."></textarea>
							</div>

						<!-- Configurações de Envio -->
						<div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #a8edea;">
							<h3 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px; display: flex; align-items: center;">
								<span style="margin-right: 10px;">⏱️</span> <?php echo esc_html($this->i18n['form']['interval']); ?>
							</h3>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="number" name="wpwevo_interval" value="5" min="1" max="60" 
									   style="width: 80px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
								<span style="color: #4a5568; font-size: 14px;"><?php echo esc_html($this->i18n['form']['interval_help']); ?></span>
							</div>
						</div>

						<!-- Botão de Envio -->
						<div class="wpwevo-submit-wrapper">
							<button type="submit" class="button button-primary button-hero">
								<span class="dashicons dashicons-send" style="vertical-align: middle; margin-top: -2px;"></span>
								<?php echo esc_html($this->i18n['form']['send_button']); ?>
							</button>
						</div>

						<!-- Container para a barra de progresso e resultados -->
						<div id="wpwevo-bulk-status" style="margin-top: 20px;"></div>

					</form>
				</div>
			</div>
		</div>

		<!-- Seção do Histórico de Envios -->
		<div id="wpwevo-history-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); overflow: hidden; margin-top: 30px;">
			<div style="background: rgba(255,255,255,0.98); margin: 2px; border-radius: 10px; padding: 25px;">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
					<div style="display: flex; align-items: center;">
						<div style="background: rgba(102, 126, 234, 0.1); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; margin-right: 15px;">
							📊
						</div>
						<div>
							<h3 style="margin: 0; color: #2d3748; font-size: 20px; font-weight: 600;"><?php echo esc_html($this->i18n['history']['title']); ?></h3>
							<p style="margin: 5px 0 0 0; color: #718096; font-size: 14px;">Acompanhe todos os envios em massa realizados</p>
						</div>
					</div>
					<button id="wpwevo-clear-history" class="button button-secondary" style="background: #e53e3e; border-color: #e53e3e; color: white;">
						<span class="dashicons dashicons-trash" style="vertical-align: middle; margin-top: -2px;"></span>
						<?php echo esc_html($this->i18n['history']['clear']); ?>
					</button>
				</div>
				
				<div id="wpwevo-history-container">
					<!-- O histórico será carregado aqui via AJAX -->
					<div style="text-align: center; padding: 40px; color: #718096;">
						<div style="font-size: 48px; margin-bottom: 15px;">📊</div>
						<p><?php echo esc_html($this->i18n['history']['no_history']); ?></p>
					</div>
				</div>
			</div>
		</div>

		<style>
		/* Estilos gerais */
		.wpwevo-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin-bottom: 20px;
		}
		.wpwevo-tab-button {
			padding: 10px 20px;
			text-decoration: none;
			color: #555;
			border: 1px solid transparent;
			border-bottom: 0;
			margin-bottom: -1px;
		}
		.wpwevo-tab-button.active {
			border-color: #ddd;
			border-bottom-color: white;
			background: white;
			border-radius: 5px 5px 0 0;
			color: #0073aa;
			font-weight: 600;
		}
		.wpwevo-tab-content {
			display: none;
		}
		.wpwevo-tab-content.active {
			display: block;
		}
		.wpwevo-submit-wrapper {
			margin-top: 20px;
		}
		.button-hero {
			padding: 10px 25px;
			font-size: 16px;
			height: auto;
		}
		
		/* Estilos para o histórico */
		#wpwevo-history-container table {
			width: 100%;
			border-collapse: collapse;
			margin-top: 15px;
			background: white;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		
		#wpwevo-history-container th {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 12px 15px;
			text-align: left;
			font-weight: 600;
			font-size: 14px;
		}
		
		#wpwevo-history-container td {
			padding: 12px 15px;
			border-bottom: 1px solid #f1f5f9;
			font-size: 14px;
		}
		
		#wpwevo-history-container tr:hover {
			background-color: #f8fafc;
		}
		
		#wpwevo-history-container .order-status {
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 12px;
			font-weight: 500;
			text-transform: uppercase;
		}
		
		#wpwevo-history-container .order-status-completed {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		
		#wpwevo-history-container .order-status-failed {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		
		/* Estilos para resultados de envio */
		.wpwevo-result {
			background: white;
			border-radius: 8px;
			padding: 20px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		
		.wpwevo-result h3 {
			margin: 0 0 20px 0;
			color: #2d3748;
			font-size: 18px;
			text-align: center;
		}
		
		.wpwevo-result-stats {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
			gap: 15px;
			margin-bottom: 20px;
		}
		
		.wpwevo-stat {
			text-align: center;
			padding: 15px;
			border-radius: 8px;
			background: #f7fafc;
			border: 1px solid #e2e8f0;
		}
		
		.wpwevo-stat-number {
			display: block;
			font-size: 24px;
			font-weight: 700;
			color: #2d3748;
			margin-bottom: 5px;
		}
		
		.wpwevo-stat-label {
			display: block;
			font-size: 12px;
			color: #718096;
			text-transform: uppercase;
			letter-spacing: 0.5px;
		}
		
		.wpwevo-stat-success {
			background: #d4edda;
			border-color: #c3e6cb;
		}
		
		.wpwevo-stat-success .wpwevo-stat-number {
			color: #155724;
		}
		
		.wpwevo-stat-error {
			background: #f8d7da;
			border-color: #f5c6cb;
		}
		
		.wpwevo-stat-error .wpwevo-stat-number {
			color: #721c24;
		}
		
		.wpwevo-stat-rate {
			background: #d1ecf1;
			border-color: #bee5eb;
		}
		
		.wpwevo-stat-rate .wpwevo-stat-number {
			color: #0c5460;
		}
		
		.wpwevo-error-details {
			margin-top: 15px;
			border: 1px solid #f5c6cb;
			border-radius: 6px;
			overflow: hidden;
		}
		
		.wpwevo-error-details summary {
			background: #f8d7da;
			padding: 10px 15px;
			cursor: pointer;
			font-weight: 500;
			color: #721c24;
		}
		
		.wpwevo-error-details ul {
			margin: 0;
			padding: 15px;
			background: white;
			list-style: none;
		}
		
		.wpwevo-error-details li {
			padding: 5px 0;
			border-bottom: 1px solid #f1f5f9;
			font-size: 13px;
			color: #4a5568;
		}
		
		.wpwevo-error-details li:last-child {
			border-bottom: none;
		}
		</style>
		<?php
	}

	/**
	 * Normaliza um número de telefone para comparação
	 * Não altera o número original, apenas cria uma versão padronizada para comparação
	 */
	private function normalize_phone_for_comparison($phone) {
		// Remove tudo que não for número
		$numbers_only = preg_replace('/[^0-9]/', '', $phone);
		
		// Valida formato básico (10-13 dígitos)
		if (strlen($numbers_only) < 10 || strlen($numbers_only) > 13) {
			return false;
		}
		
		// Normaliza para formato brasileiro
		if (strlen($numbers_only) == 10 && !preg_match('/^55/', $numbers_only)) {
			// 10 dígitos: adiciona código do país (telefone fixo)
			$numbers_only = '55' . $numbers_only;
		} elseif (strlen($numbers_only) == 11 && !preg_match('/^55/', $numbers_only)) {
			// 11 dígitos: adiciona código do país (celular)
			$numbers_only = '55' . $numbers_only;
		} elseif (!preg_match('/^55/', $numbers_only)) {
			// Se não começar com 55 e não for 10 ou 11 dígitos, adiciona
			$numbers_only = '55' . $numbers_only;
		}
		
		// Valida formato final
		if (!preg_match('/^55[1-9][1-9][0-9]{7,9}$/', $numbers_only)) {
			return false;
		}
		
		return $numbers_only;
	}

	public function preview_customers() {
		try {
			// Verifica nonce
			if (!wp_verify_nonce($_POST['nonce'], 'wpwevo_bulk_send')) {
				wp_send_json_error(__('Verificação de segurança falhou.', 'wp-whatsapp-evolution'));
			}

			if (!current_user_can('manage_options')) {
				wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			// Garante que status seja um array
			$status = [];
			if (isset($_POST['status']) && is_array($_POST['status'])) {
				$status = array_values($_POST['status']); // Força reindexação do array
			} elseif (isset($_POST['status'])) {
				$status = [$_POST['status']];
			}
			
			// Remove valores vazios e sanitiza
			$status = array_filter($status, function($s) {
				return !empty(trim($s));
			});

			// Verifica se há status válidos
			if (empty($status)) {
				throw new \Exception(__('Selecione pelo menos um status.', 'wp-whatsapp-evolution'));
			}

			// Sanitiza e formata os status
			$statuses = array_map(function($s) {
				$s = sanitize_text_field($s);
				// Adiciona o prefixo 'wc-' se não existir
				return (strpos($s, 'wc-') === 0) ? $s : 'wc-' . $s;
			}, $status);

			// Verifica se os status são válidos
			$valid_statuses = array_keys(wc_get_order_statuses());
			$invalid_statuses = array_diff($statuses, $valid_statuses);
			
			if (!empty($invalid_statuses)) {
				throw new \Exception(__('Um ou mais status selecionados são inválidos.', 'wp-whatsapp-evolution'));
			}

			// Obtém os filtros adicionais
			$date_from = isset($_POST['wpwevo_date_from']) ? sanitize_text_field($_POST['wpwevo_date_from']) : '';
			$date_to = isset($_POST['wpwevo_date_to']) ? sanitize_text_field($_POST['wpwevo_date_to']) : '';
			$min_total = isset($_POST['wpwevo_min_total']) ? str_replace(['.', ','], ['', '.'], sanitize_text_field($_POST['wpwevo_min_total'])) : 0;
			$min_total = floatval($min_total);

			// Prepara os argumentos da query
			$query_args = [
				'limit' => -1,
				'status' => array_map(function($s) {
					return str_replace('wc-', '', $s);
				}, $statuses),
				'return' => 'ids'
			];

			// Adiciona filtro de data se especificado
			if (!empty($date_from) || !empty($date_to)) {
				$date_query = [];

				if (!empty($date_from)) {
					$date_query[] = [
						'after'     => $date_from . ' 00:00:00',
						'inclusive' => true
					];
				}

				if (!empty($date_to)) {
					$date_query[] = [
						'before'    => $date_to . ' 23:59:59',
						'inclusive' => true
					];
				}

				if (!empty($date_query)) {
					$query_args['date_query'] = [
						'relation' => 'AND',
						$date_query
					];
				}
			}
			
			$orders_query = new \WC_Order_Query($query_args);
			$orders = $orders_query->get_orders();

			if (empty($orders)) {
				throw new \Exception(__('Nenhum cliente encontrado com os filtros selecionados.', 'wp-whatsapp-evolution'));
			}

			// Processa os pedidos encontrados
			$customers = [];
			$processed_phones = [];

			foreach ($orders as $order_id) {
				$order = wc_get_order($order_id);
				if (!$order) continue;

				// Filtro por valor mínimo
				if ($min_total > 0 && $order->get_total() < $min_total) {
					continue;
				}

				$phone = wpwevo_get_order_phone($order);
				if (empty($phone)) continue;

				// Normaliza o número para comparação
				$normalized_phone = $this->normalize_phone_for_comparison($phone);
				if (!$normalized_phone) {
					continue;
				}

				// Usa o número normalizado como chave para evitar duplicatas
				if (!isset($processed_phones[$normalized_phone])) {
					$customer_data = [
						'phone' => $phone, // Mantém o número original para exibição
						'normalized_phone' => $normalized_phone, // Guarda a versão normalizada
						'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'total_orders' => wc_get_customer_order_count($order->get_customer_id()),
						'last_order' => date_i18n('d/m/Y', strtotime($order->get_date_created())),
						'status' => $order->get_status(),
						'order_id' => $order->get_id(),
						'order_total' => $order->get_total()
					];

					$customers[] = $customer_data;
					$processed_phones[$normalized_phone] = true;
				}
			}

			if (empty($customers)) {
				throw new \Exception(__('Nenhum cliente encontrado com os filtros selecionados.', 'wp-whatsapp-evolution'));
			}

			// Ordena os clientes pelo nome
			usort($customers, function($a, $b) {
				return strcmp($a['name'], $b['name']);
			});

			ob_start();
			?>
			<div class="wpwevo-preview-table">
				<div class="wpwevo-preview-summary">
					<h4>
						<?php 
						printf(
							__('Total de clientes únicos encontrados: %d', 'wp-whatsapp-evolution'),
							count($customers)
						); 
						?>
					</h4>
					<p class="description">
						<?php 
						$filters = [];
						
						// Status
						$status_labels = array_map(function($s) {
							return wc_get_order_status_name(str_replace('wc-', '', $s));
						}, $statuses);
						$filters[] = __('Status: ', 'wp-whatsapp-evolution') . implode(', ', $status_labels);
						
						// Período
						if (!empty($date_from) || !empty($date_to)) {
							$period = __('Período: ', 'wp-whatsapp-evolution');
							if (!empty($date_from)) {
								$period .= date_i18n('d/m/Y', strtotime($date_from));
							}
							$period .= ' - ';
							if (!empty($date_to)) {
								$period .= date_i18n('d/m/Y', strtotime($date_to));
							}
							$filters[] = $period;
						}
						
						// Valor mínimo
						if ($min_total > 0) {
							$filters[] = sprintf(
								__('Valor mínimo: R$ %s', 'wp-whatsapp-evolution'),
								number_format($min_total, 2, ',', '.')
							);
						}
						
						echo implode(' | ', $filters);
						?>
					</p>
				</div>

				<table class="widefat striped">
				<thead>
					<tr>
						<th><?php _e('Nome', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Telefone', 'wp-whatsapp-evolution'); ?></th>
						<th>
							<?php _e('Total de Pedidos', 'wp-whatsapp-evolution'); ?>
							<span class="dashicons dashicons-info-outline" title="<?php esc_attr_e('Total de pedidos do cliente em todos os status', 'wp-whatsapp-evolution'); ?>"></span>
						</th>
						<th><?php _e('Último Pedido', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Status Atual', 'wp-whatsapp-evolution'); ?></th>
						<th><?php _e('Valor', 'wp-whatsapp-evolution'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($customers as $customer) : ?>
						<tr>
							<td><?php echo esc_html($customer['name']); ?></td>
							<td>
								<?php 
								$formatted_phone = wpwevo_validate_phone($customer['phone']);
								if ($formatted_phone !== $customer['phone']) {
									echo '<strong>' . esc_html($formatted_phone) . '</strong>';
									echo '<br><small class="description">' . esc_html($customer['phone']) . '</small>';
								} else {
									echo esc_html($customer['phone']);
								}
								?>
							</td>
							<td>
								<?php 
								echo esc_html($customer['total_orders']);
								printf(
									'<br><small class="description">%s</small>',
									sprintf(
										__('Pedido atual: #%s', 'wp-whatsapp-evolution'),
										$customer['order_id']
									)
								);
								?>
							</td>
							<td><?php echo esc_html($customer['last_order']); ?></td>
							<td>
								<?php
								$status_class = sanitize_html_class('order-status-' . $customer['status']);
								printf(
									'<mark class="order-status %s"><span>%s</span></mark>',
									esc_attr($status_class),
									esc_html(wc_get_order_status_name($customer['status']))
								);
								?>
							</td>
							<td>
								<?php
								echo 'R$ ' . number_format($customer['order_total'], 2, ',', '.');
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

				<div class="wpwevo-preview-notes">
					<p class="description">
						<?php _e('Notas:', 'wp-whatsapp-evolution'); ?>
						<ul>
							<li><?php _e('* Os números de telefone foram formatados para o padrão WhatsApp internacional.', 'wp-whatsapp-evolution'); ?></li>
							<li><?php _e('* Clientes com múltiplos pedidos são mostrados apenas uma vez, com o status do pedido mais recente.', 'wp-whatsapp-evolution'); ?></li>
							<li><?php _e('* O total de pedidos inclui todos os pedidos do cliente, independente do status.', 'wp-whatsapp-evolution'); ?></li>
						</ul>
					</p>
		</div>
			</div>

			<style>
			.wpwevo-preview-table {
				margin-top: 20px;
			}
			.wpwevo-preview-summary {
				margin-bottom: 20px;
			}
			.wpwevo-preview-summary h4 {
				margin: 0 0 10px 0;
			}
			.wpwevo-preview-table table {
				border-collapse: collapse;
				width: 100%;
			}
			.wpwevo-preview-table th,
			.wpwevo-preview-table td {
				padding: 8px;
				text-align: left;
			}
			.wpwevo-preview-table mark {
				padding: 4px 8px;
				border-radius: 4px;
				background: #f0f0f1;
			}
			.wpwevo-preview-table mark.order-status-completed {
				background: #c6e1c6;
				color: #5b841b;
			}
			.wpwevo-preview-table mark.order-status-processing {
				background: #c6e1c6;
				color: #5b841b;
			}
			.wpwevo-preview-table mark.order-status-on-hold {
				background: #f8dda7;
				color: #94660c;
			}
			.wpwevo-preview-table mark.order-status-pending {
				background: #e5e5e5;
				color: #777;
			}
			.wpwevo-preview-table mark.order-status-cancelled,
			.wpwevo-preview-table mark.order-status-failed,
			.wpwevo-preview-table mark.order-status-refunded {
				background: #eba3a3;
				color: #761919;
			}
			.wpwevo-preview-notes {
				margin-top: 20px;
				padding: 10px;
				background: #f8f9fa;
				border-left: 4px solid #646970;
			}
			.wpwevo-preview-notes ul {
				margin: 5px 0 0 20px;
			}
			.dashicons-info-outline {
				font-size: 16px;
				color: #646970;
				vertical-align: middle;
				cursor: help;
			}
			</style>
		<?php
		wp_send_json_success(['html' => ob_get_clean()]);

		} catch (\Exception $e) {
			wp_send_json_error($e->getMessage());
		}
	}

	public function handle_bulk_send() {
		try {
			// Verifica nonce com o nome correto do campo
			if (!isset($_POST['wpwevo_bulk_send_nonce']) || !wp_verify_nonce($_POST['wpwevo_bulk_send_nonce'], 'wpwevo_bulk_send')) {
				wp_send_json_error(__('Verificação de segurança falhou.', 'wp-whatsapp-evolution'), 403);
			}

			if (!current_user_can('manage_options')) {
				throw new \Exception(__('Permissão negada.', 'wp-whatsapp-evolution'));
			}

			// Verifica conexão com a API de forma segura
			try {
				if (!$this->api->is_configured()) {
					throw new \Exception($this->i18n['connection_error']);
				}
			} catch (\Exception $e) {
				// Se a API falhar, retorna erro mas não quebra o site
				wp_send_json_error(__('Erro na configuração da API. Verifique as configurações.', 'wp-whatsapp-evolution'));
			}

			$active_tab = isset($_POST['active_tab']) ? sanitize_text_field($_POST['active_tab']) : '';
			$message = isset($_POST['wpwevo_bulk_message']) ? sanitize_textarea_field($_POST['wpwevo_bulk_message']) : '';
			$interval = isset($_POST['wpwevo_interval']) ? absint($_POST['wpwevo_interval']) : 5;

		if (empty($message)) {
				throw new \Exception(__('A mensagem é obrigatória.', 'wp-whatsapp-evolution'));
		}

			// Obtém a lista de números com base na aba ativa
		$numbers = [];
			switch ($active_tab) {
			case 'customers':
					// Processa os status
					$statuses = isset($_POST['status']) ? (array)$_POST['status'] : [];
					$statuses = array_unique(array_filter($statuses, 'strlen')); // Remove duplicatas e valores vazios
					
					if (empty($statuses)) {
						throw new \Exception(__('Selecione pelo menos um status.', 'wp-whatsapp-evolution'));
					}

					$date_from = isset($_POST['wpwevo_date_from']) ? sanitize_text_field($_POST['wpwevo_date_from']) : '';
					$date_to = isset($_POST['wpwevo_date_to']) ? sanitize_text_field($_POST['wpwevo_date_to']) : '';
					$min_total = isset($_POST['wpwevo_min_total']) ? floatval(str_replace(',', '.', $_POST['wpwevo_min_total'])) : 0;
					
				$numbers = $this->get_customers_numbers($statuses, $date_from, $date_to, $min_total);
				break;

			case 'csv':
					if (!isset($_FILES['wpwevo_csv_file']) || empty($_FILES['wpwevo_csv_file']['tmp_name'])) {
						throw new \Exception(__('Arquivo CSV não enviado ou está vazio.', 'wp-whatsapp-evolution'));
				}
					$numbers = $this->process_csv_file($_FILES['wpwevo_csv_file']);
				break;

			case 'manual':
					$manual_numbers = isset($_POST['wpwevo_manual_numbers']) ? sanitize_textarea_field($_POST['wpwevo_manual_numbers']) : '';
				if (empty($manual_numbers)) {
						throw new \Exception(__('Lista de números vazia.', 'wp-whatsapp-evolution'));
				}
					$numbers = array_filter(array_map('trim', explode("\n", $manual_numbers)));
				break;

			default:
					throw new \Exception(__('Origem dos números inválida.', 'wp-whatsapp-evolution'));
		}

		if (empty($numbers)) {
				throw new \Exception(__('Nenhum número encontrado para envio.', 'wp-whatsapp-evolution'));
		}

			$total = count($numbers);
			$sent = 0;
			$errors = [];
			$success = 0;

			// Envia as mensagens diretamente
			foreach ($numbers as $contact) {
				$phone_number = is_array($contact) ? $contact['phone'] : $contact;
				$contact_name = is_array($contact) ? $contact['name'] : '';

				try {
					// Valida o número de telefone antes de enviar
					$validated_phone = wpwevo_validate_phone($phone_number);
					if (!$validated_phone) {
						$errors[] = sprintf(
							__('Número com formato inválido para %s (%s)', 'wp-whatsapp-evolution'),
							$contact_name ?: 'desconhecido',
							$phone_number
						);
						continue; // Pula para o próximo número
					}

					// Prepara a mensagem com as variáveis substituídas
					$prepared_message = $this->replace_variables($message, $contact);
					
					// Envia a mensagem de forma segura
					try {
						$result = $this->api->send_message($validated_phone, $prepared_message);

						if (!$result['success']) {
							$errors[] = sprintf(
								__('Erro ao enviar para %s: %s', 'wp-whatsapp-evolution'),
								$contact_name ?: $validated_phone,
								$result['message']
							);
						} else {
							$success++;
						}
					} catch (\Exception $api_error) {
						// Se a API falhar, registra o erro mas continua
						$errors[] = sprintf(
							__('Erro na API ao enviar para %s: %s', 'wp-whatsapp-evolution'),
							$contact_name ?: $validated_phone,
							$api_error->getMessage()
						);
					}

				} catch (\Exception $e) {
					$errors[] = sprintf(
						__('Erro ao processar %s: %s', 'wp-whatsapp-evolution'),
						$contact_name ?: $phone_number,
						$e->getMessage()
					);
				} finally {
					$sent++;
					// Aguarda o intervalo configurado apenas se houver mais envios
					if ($sent < $total && $interval > 0) {
						sleep($interval);
					}
				}
			}

			// Registra no histórico
			$history = get_option('wpwevo_bulk_history', []);
			$history[] = [
				'date' => time(),
				'total' => $total,
				'sent' => $sent,
				'success' => $success,
				'errors' => count($errors),
				'source' => $active_tab,
				'status' => sprintf(
					__('%d enviados com sucesso, %d erros', 'wp-whatsapp-evolution'),
					$success,
					count($errors)
				)
			];
			
			// Mantém apenas os últimos 50 registros
			if (count($history) > 50) {
				$history = array_slice($history, -50);
			}
			
			update_option('wpwevo_bulk_history', $history);

			// Envia e-mail com relatório
			$admin_email = get_option('admin_email');
			$subject = __('Relatório de Envio em Massa - WhatsApp Evolution', 'wp-whatsapp-evolution');
			$report = sprintf(
				__("Envio em massa concluído!\n\nTotal enviado: %d\nSucesso: %d\nErros: %d\n\nDetalhes dos erros:\n%s", 'wp-whatsapp-evolution'),
				$sent,
				$success,
				count($errors),
				implode("\n", $errors)
			);
			wp_mail($admin_email, $subject, $report);

			// Retorna sucesso final com HTML atualizado do histórico
			wp_send_json_success([
				'status' => 'completed',
				'total' => $total,
				'sent' => $sent,
				'success' => $success,
				'errors' => $errors,
				'progress' => 100,
				'message' => sprintf(
					__('Envio concluído! %d mensagens enviadas com sucesso, %d erros.', 'wp-whatsapp-evolution'),
					$success,
					count($errors)
				),
				'historyHtml' => $this->get_history_html()
			]);

		} catch (\Exception $e) {
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}

	private function replace_variables($message, $contact_data) {
		if (!is_array($contact_data)) {
			return $message;
		}

		// Mapeia placeholders para chaves do array de contato
		$replacements = [
			'{customer_name}' => $contact_data['name'] ?? '',
			'{customer_phone}' => $contact_data['phone'] ?? '',
		];

		// Adiciona placeholders de WooCommerce se disponíveis
		if (isset($contact_data['order'])) {
			/** @var \WC_Order $order */
			$order = $contact_data['order'];
			$replacements['{order_id}'] = $order->get_id();
			$replacements['{order_total}'] = $order->get_formatted_order_total();
			$replacements['{billing_first_name}'] = $order->get_billing_first_name();
			$replacements['{billing_last_name}'] = $order->get_billing_last_name();
			$replacements['{shipping_method}'] = $order->get_shipping_method();
		}

		// **NOVO: Adiciona placeholders específicos para CSV**
		if (isset($contact_data['name']) && !empty($contact_data['name'])) {
			$replacements['{nome}'] = $contact_data['name'];
			$replacements['{name}'] = $contact_data['name'];
		}

		return str_replace(array_keys($replacements), array_values($replacements), $message);
	}

	private function get_customers_numbers($statuses, $date_from, $date_to, $min_total) {
		if (!class_exists('WooCommerce')) {
			throw new \Exception(__('WooCommerce não está ativo.', 'wp-whatsapp-evolution'));
		}

		// Remove duplicatas e valores vazios
		$statuses = array_unique(array_filter($statuses, 'strlen'));

		// Garante que os status estejam no formato correto
		$formatted_statuses = array_map(function($status) {
			return (strpos($status, 'wc-') === 0) ? $status : 'wc-' . $status;
		}, $statuses);

		// Prepara os argumentos da query
		$query_args = [
			'limit' => -1,
			'status' => array_map(function($s) {
				return str_replace('wc-', '', $s);
			}, $formatted_statuses),
			'return' => 'ids'
		];

		// Adiciona filtro de data se especificado
		if (!empty($date_from) || !empty($date_to)) {
			$date_query = [];

			if (!empty($date_from)) {
				$date_query[] = [
					'after'     => $date_from . ' 00:00:00',
					'inclusive' => true
				];
			}

			if (!empty($date_to)) {
				$date_query[] = [
					'before'    => $date_to . ' 23:59:59',
					'inclusive' => true
				];
			}

			if (!empty($date_query)) {
				$query_args['date_query'] = [
					'relation' => 'AND',
					$date_query
				];
			}
		}

		$orders_query = new \WC_Order_Query($query_args);
		$orders = $orders_query->get_orders();

		$customers = [];
		$processed_phones = [];

		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order) continue;
			
			// Filtro por valor mínimo
			if ($min_total > 0 && $order->get_total() < $min_total) {
				continue;
			}

			$phone = wpwevo_get_order_phone($order);
			if (empty($phone)) continue;

			// Normaliza o número para comparação para evitar duplicatas
			$normalized_phone = preg_replace('/\D/', '', $phone);
			if (isset($processed_phones[$normalized_phone])) {
				continue;
			}

			$customers[] = [
				'name'  => $order->get_billing_first_name(),
				'phone' => $phone,
				'order' => $order // Passa o objeto do pedido para mais variáveis
			];
			$processed_phones[$normalized_phone] = true;
		}

		return $customers;
	}

	private function process_csv_file($file) {
		if ($file['error'] !== UPLOAD_ERR_OK) {
			throw new \Exception(__('Erro no upload do arquivo.', 'wp-whatsapp-evolution'));
		}

		$file_path = $file['tmp_name'];
		$file_content = file_get_contents($file_path);

		// Etapa 1: Tenta detectar e converter a codificação do arquivo para UTF-8 para lidar com acentos
		$encoding = mb_detect_encoding($file_content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
		if ($encoding && $encoding !== 'UTF-8') {
			$file_content = mb_convert_encoding($file_content, 'UTF-8', $encoding);
		}

		// Etapa 2: Processa o conteúdo linha por linha para máxima robustez
		$lines = explode("\n", $file_content);
		$lines = array_filter(array_map('trim', $lines));

		if (count($lines) < 2) {
			throw new \Exception(__('Arquivo CSV precisa de um cabeçalho e pelo menos uma linha de dados.', 'wp-whatsapp-evolution'));
		}

		// Etapa 3: Extrai o cabeçalho, detecta o delimitador e encontra as colunas
		$header_line = array_shift($lines);
		$delimiter = (substr_count($header_line, ';') > substr_count($header_line, ',')) ? ';' : ',';
		$header = str_getcsv($header_line, $delimiter);
		$header_map = array_map('strtolower', array_map('trim', $header));

		// **MELHORADO: Mapeamento dinâmico da coluna de telefone**
		$phone_col_index = null;
		
		// Primeiro, verifica se o usuário selecionou uma coluna específica
		if (isset($_POST['wpwevo_phone_column']) && is_numeric($_POST['wpwevo_phone_column'])) {
			$phone_col_index = (int)$_POST['wpwevo_phone_column'];
			
			// Valida se o índice está dentro dos limites
			if ($phone_col_index < 0 || $phone_col_index >= count($header)) {
				throw new \Exception(__('Coluna selecionada não existe no arquivo CSV.', 'wp-whatsapp-evolution'));
			}
		} else {
			// Se não enviado, tenta encontrar coluna chamada telefone, celular, fone, ou usa a primeira
			$possibles = ['telefone', 'celular', 'fone', 'phone', 'mobile', 'whatsapp', 'contato'];
			foreach ($header_map as $idx => $col) {
				if (in_array($col, $possibles)) {
					$phone_col_index = $idx;
					break;
				}
			}
			if ($phone_col_index === null) {
				$phone_col_index = 0; // Usa a primeira coluna por padrão
			}
		}

		// **NOVO: Log para debug**
		error_log("WPWhatsEvolution CSV: Coluna selecionada: " . $phone_col_index . " - Nome: " . $header[$phone_col_index]);

		// Etapa 4: Processa cada linha de dados
		$contacts = [];
		$line_number = 1; // Para debug
		
		foreach ($lines as $line) {
			$line_number++;
			if (empty(trim($line))) continue;
			
			$data = str_getcsv($line, $delimiter);
			
			// Verifica se a linha tem dados suficientes
			if (count($data) <= $phone_col_index) {
				error_log("WPWhatsEvolution CSV: Linha $line_number tem menos colunas que o esperado");
				continue;
			}
			
			$phone = isset($data[$phone_col_index]) ? trim($data[$phone_col_index]) : null;
			
			if (!empty($phone)) {
				// **NOVO: Tenta extrair nome se disponível**
				$name = '';
				$name_keywords = ['nome', 'name', 'cliente', 'customer'];
				
				// Procura por coluna de nome
				foreach ($header_map as $idx => $col) {
					if (in_array($col, $name_keywords) && $idx != $phone_col_index) {
						$name = isset($data[$idx]) ? trim($data[$idx]) : '';
						break;
					}
				}
				
				// Se não encontrou nome, usa a primeira coluna que não seja telefone
				if (empty($name) && count($data) > 1) {
					foreach ($data as $idx => $value) {
						if ($idx != $phone_col_index && !empty(trim($value))) {
							$name = trim($value);
							break;
						}
					}
				}
				
				$contacts[] = [
					'phone' => $phone,
					'name' => $name
				];
			}
		}

		if (empty($contacts)) {
			throw new \Exception(__('Nenhum contato com número de telefone válido foi encontrado no arquivo CSV.', 'wp-whatsapp-evolution'));
		}
		
		// **NOVO: Log de sucesso**
		error_log("WPWhatsEvolution CSV: Processados " . count($contacts) . " contatos com sucesso");
		
		return $contacts;
	}

	public function clear_history() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');
		
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		delete_option('wpwevo_bulk_history');
		
		wp_send_json_success();
	}

	public function ajax_get_history() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Permissão negada.', 'wp-whatsapp-evolution'));
		}

		wp_send_json_success(['historyHtml' => $this->get_history_html()]);
	}

	private function get_history_html() {
		$history = get_option('wpwevo_bulk_history', []);
		
		if (empty($history)) {
			return '<p>' . esc_html($this->i18n['history']['no_history']) . '</p>';
		}

		ob_start();
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php echo esc_html($this->i18n['history']['date']); ?></th>
					<th><?php echo esc_html($this->i18n['history']['source']); ?></th>
					<th><?php echo esc_html($this->i18n['history']['total']); ?></th>
					<th><?php echo esc_html($this->i18n['history']['sent']); ?></th>
					<th><?php echo esc_html($this->i18n['history']['status']); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach (array_reverse($history) as $item) : ?>
					<tr>
						<td><?php echo esc_html(date_i18n('d/m/Y H:i', $item['date'])); ?></td>
						<td><?php echo esc_html($item['source']); ?></td>
						<td><?php echo esc_html($item['total']); ?></td>
						<td><?php echo esc_html($item['sent']); ?></td>
						<td>
							<mark class="order-status <?php echo $item['errors'] > 0 ? 'order-status-failed' : 'order-status-completed'; ?>">
								<span><?php echo esc_html($item['status']); ?></span>
							</mark>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return ob_get_clean();
	}
} 