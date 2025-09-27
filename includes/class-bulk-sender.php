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
        $this->menu_title = __('Envio em Massa', 'wp-whatsevolution');
        $this->page_title = __('Envio em Massa', 'wp-whatsevolution');
		
		$this->i18n = [
            'connection_error' => __('A conexão com o WhatsApp não está ativa. Verifique as configurações.', 'wp-whatsevolution'),
            'sending' => __('Enviando...', 'wp-whatsevolution'),
            'preview' => __('Visualizar', 'wp-whatsevolution'),
			'variables' => [
                'title' => __('Variáveis Disponíveis', 'wp-whatsevolution'),
                'customer_name' => __('Nome do cliente', 'wp-whatsevolution'),
                'customer_email' => __('Email do cliente', 'wp-whatsevolution'),
                'total_orders' => __('Total de pedidos do cliente', 'wp-whatsevolution'),
                'last_order_date' => __('Data do último pedido', 'wp-whatsevolution'),
                'for_all' => __('Comum a todas as fontes', 'wp-whatsevolution'),
                'for_woo' => __('Específicas para WooCommerce', 'wp-whatsevolution'),
                'for_csv' => __('Para importação CSV', 'wp-whatsevolution'),
                'for_manual' => __('Variáveis não se aplicam à Lista Manual.', 'wp-whatsevolution')
			],
			'tabs' => [
                'customers' => __('Clientes WooCommerce', 'wp-whatsevolution'),
                'csv' => __('Importar CSV', 'wp-whatsevolution'),
                'manual' => __('Lista Manual', 'wp-whatsevolution')
			],
			'form' => [
                'order_status' => __('Filtrar por Status', 'wp-whatsevolution'),
                'status_help' => __('Selecione os status dos pedidos para filtrar os clientes.', 'wp-whatsevolution'),
                'period' => __('Período', 'wp-whatsevolution'),
                'to' => __('até', 'wp-whatsevolution'),
                'min_value' => __('Valor Mínimo', 'wp-whatsevolution'),
                'order_value' => __('Valor do Pedido', 'wp-whatsevolution'),
                'preview_customers' => __('Visualizar Clientes', 'wp-whatsevolution'),
                'csv_file' => __('Arquivo CSV', 'wp-whatsevolution'),
                'csv_help' => __('Para melhores resultados, use um arquivo CSV com as colunas "nome" e "telefone". O sistema aceita separação por ponto e vírgula (;) ou vírgula (,).', 'wp-whatsevolution'),
                'csv_example_title' => __('Exemplo Visual da Estrutura:', 'wp-whatsevolution'),
                'csv_download' => __('Baixar Arquivo de Exemplo', 'wp-whatsevolution'),
                'number_list' => __('Lista de Números', 'wp-whatsevolution'),
                'number_placeholder' => __('Um número por linha, com DDD e país', 'wp-whatsevolution'),
                'message' => __('Mensagem', 'wp-whatsevolution'),
                'message_placeholder' => __('Digite sua mensagem aqui...', 'wp-whatsevolution'),
                'schedule' => __('Agendamento', 'wp-whatsevolution'),
                'schedule_enable' => __('Agendar envio', 'wp-whatsevolution'),
                'schedule_help' => __('Data e hora para iniciar o envio das mensagens.', 'wp-whatsevolution'),
                'interval' => __('Intervalo', 'wp-whatsevolution'),
                'interval_help' => __('segundos entre cada envio', 'wp-whatsevolution'),
                'start_sending' => __('Iniciar Envio', 'wp-whatsevolution'),
                'send_button' => __('Iniciar Envio', 'wp-whatsevolution')
			],
			'history' => [
                'title' => __('Histórico de Envios', 'wp-whatsevolution'),
                'clear' => __('Limpar Histórico', 'wp-whatsevolution'),
                'confirm_clear' => __('Tem certeza que deseja limpar todo o histórico de envios?', 'wp-whatsevolution'),
                'no_history' => __('Nenhum envio em massa realizado ainda.', 'wp-whatsevolution'),
                'date' => __('Data', 'wp-whatsevolution'),
                'source' => __('Origem', 'wp-whatsevolution'),
                'total' => __('Total', 'wp-whatsevolution'),
                'sent' => __('Enviados', 'wp-whatsevolution'),
                'status' => __('Status', 'wp-whatsevolution'),
				'sources' => [
                    'customers' => __('Clientes WooCommerce', 'wp-whatsevolution'),
                    'all-customers' => __('Todos os Clientes', 'wp-whatsevolution'),
                    'csv' => __('Importação CSV', 'wp-whatsevolution'),
                    'manual' => __('Lista Manual', 'wp-whatsevolution')
				]
			]
		];

		add_action('admin_menu', [$this, 'add_submenu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_wpwevo_bulk_send', [$this, 'handle_bulk_send']);
		add_action('wp_ajax_wpwevo_preview_customers', [$this, 'preview_customers']);
		add_action('wp_ajax_wpwevo_preview_all_customers', [$this, 'preview_all_customers']);
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
                'loading' => __('Carregando...', 'wp-whatsevolution'),
                'error' => __('Erro ao carregar dados.', 'wp-whatsevolution'),
                'success' => __('Enviado com sucesso!', 'wp-whatsevolution'),
                'confirm' => __('Tem certeza que deseja enviar mensagens para todos os clientes selecionados?', 'wp-whatsevolution'),
                'sending' => __('Enviando...', 'wp-whatsevolution'),
                'send' => __('Iniciar Envio', 'wp-whatsevolution'),
                'statusRequired' => __('Selecione pelo menos um status de pedido.', 'wp-whatsevolution'),
                'confirmClearHistory' => __('Tem certeza que deseja limpar todo o histórico de envios?', 'wp-whatsevolution')
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
								<a href="#tab-all-customers" class="wpwevo-tab-button" data-tab="all-customers">
									👥 <?php _e('Todos os Clientes', 'wp-whatsevolution'); ?>
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
								<th scope="row"><?php echo esc_html($this->i18n['form']['order_value']); ?></th>
								<td>
									<div style="display: flex; gap: 20px; align-items: center;">
										<div class="wpwevo-currency-input">
											<span class="wpwevo-currency-symbol">R$</span>
											<input type="text" 
												   name="wpwevo_min_total" 
												   class="regular-text wpwevo-currency-field" 
												   placeholder="0,00"
												   pattern="^\d*[0-9](|,\d{0,2}|,\d{2}|\d*[0-9]|,\d{0,2}\d*[0-9])$"
												   maxlength="15">
											<span style="margin-left: 8px; color: #666; font-size: 13px;">Mínimo</span>
										</div>
										
										<div class="wpwevo-currency-input">
											<span class="wpwevo-currency-symbol">R$</span>
											<input type="text" 
												   name="wpwevo_max_total" 
												   class="regular-text wpwevo-currency-field" 
												   placeholder="0,00"
												   pattern="^\d*[0-9](|,\d{0,2}|,\d{2}|\d*[0-9]|,\d{0,2}\d*[0-9])$"
												   maxlength="15">
											<span style="margin-left: 8px; color: #666; font-size: 13px;">Máximo</span>
										</div>
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
											line-height: 1;
											font-size: 14px;
											height: 14px;
											display: flex;
											align-items: center;
										}
										.wpwevo-currency-field {
											padding-left: 25px !important;
											line-height: 1.4;
										}
									</style>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e('Clientes Inativos', 'wp-whatsevolution'); ?></th>
								<td>
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
											<input type="checkbox" 
												   name="wpwevo_filter_inactive" 
												   value="1" 
												   id="wpwevo-filter-inactive"
												   style="transform: scale(1.1);">
											<span style="font-weight: 500;">
												<?php _e('Filtrar clientes inativos há mais de', 'wp-whatsevolution'); ?>
											</span>
										</label>
										
										<div id="wpwevo-inactive-days-filter" style="display: none;">
											<input type="number" 
												   name="wpwevo_inactive_days" 
												   id="wpwevo-inactive-days"
												   min="1" 
												   max="365" 
												   value="30"
												   style="width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
											<span style="color: #666; font-size: 14px;">
												<?php _e('dias', 'wp-whatsevolution'); ?>
											</span>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php _e('Filtro por Total de Pedidos', 'wp-whatsevolution'); ?></th>
								<td>
									<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
										<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
											<input type="checkbox" 
												   name="wpwevo_filter_min_orders" 
												   value="1" 
												   id="wpwevo-filter-min-orders"
												   style="transform: scale(1.1);">
											<span style="font-weight: 500;">
												<?php _e('Mostrar apenas clientes com', 'wp-whatsevolution'); ?>
											</span>
										</label>
										
										<div id="wpwevo-min-orders-filter" style="display: none;">
											<input type="number" 
												   name="wpwevo_min_orders" 
												   id="wpwevo-min-orders"
												   min="1" 
												   max="1000" 
												   value="1"
												   style="width: 80px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
											<span style="color: #666; font-size: 14px;">
												<?php _e('ou mais pedidos', 'wp-whatsevolution'); ?>
											</span>
										</div>
									</div>
									<script>
										jQuery(document).ready(function($) {
											// Controle do filtro de inatividade
											$('#wpwevo-filter-inactive').on('change', function() {
												if ($(this).is(':checked')) {
													$('#wpwevo-inactive-days-filter').show();
												} else {
													$('#wpwevo-inactive-days-filter').hide();
												}
											});
											
											// Controle do filtro de total de pedidos
											$('#wpwevo-filter-min-orders').on('change', function() {
												if ($(this).is(':checked')) {
													$('#wpwevo-min-orders-filter').show();
												} else {
													$('#wpwevo-min-orders-filter').hide();
												}
											});
											
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

							<div class="wpwevo-tab-content" id="tab-all-customers">
						<div style="background: #f0f8ff; padding: 20px; border-radius: 8px; border: 2px solid #4facfe; margin-bottom: 20px;">
							<div style="display: flex; align-items: center; margin-bottom: 15px;">
								<div style="background: #4facfe; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px; color: white;">
									👥
								</div>
								<div>
									<h3 style="margin: 0; color: #2d3748; font-size: 18px; font-weight: 600;">
										<?php _e('Envio para Todos os Clientes', 'wp-whatsevolution'); ?>
									</h3>
									<p style="margin: 5px 0 0 0; color: #4a5568; font-size: 14px;">
										<?php _e('Envie mensagens para todos os usuários cadastrados no WordPress que possuem telefone.', 'wp-whatsevolution'); ?>
									</p>
								</div>
							</div>
							
							<div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #48bb78;">
								<p style="margin: 0; color: #2f855a; font-size: 14px;">
									<strong>✅ Inclui:</strong> Todos os usuários com telefone cadastrado (billing_phone ou phone)<br>
									<strong>📱 Fonte:</strong> Dados do perfil do usuário no WordPress<br>
									<strong>🔄 Atualização:</strong> Busca em tempo real, sempre atualizado
								</p>
							</div>
						</div>

						<!-- Filtro de Aniversariantes -->
						<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e9ecef;">
							<label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
								<input type="checkbox" 
									   name="wpwevo_filter_birthday" 
									   value="1" 
									   id="wpwevo-filter-birthday"
									   style="transform: scale(1.1);">
								<span style="font-weight: 600; color: #2d3748;">
									🎂 <?php _e('Filtrar aniversariantes do mês', 'wp-whatsevolution'); ?>
								</span>
							</label>
							<div id="wpwevo-birthday-month-filter" style="display: none;">
								<select name="wpwevo_birthday_month" id="wpwevo-birthday-month" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 150px;">
									<option value="01"><?php _e('Janeiro', 'wp-whatsevolution'); ?></option>
									<option value="02"><?php _e('Fevereiro', 'wp-whatsevolution'); ?></option>
									<option value="03"><?php _e('Março', 'wp-whatsevolution'); ?></option>
									<option value="04"><?php _e('Abril', 'wp-whatsevolution'); ?></option>
									<option value="05"><?php _e('Maio', 'wp-whatsevolution'); ?></option>
									<option value="06"><?php _e('Junho', 'wp-whatsevolution'); ?></option>
									<option value="07"><?php _e('Julho', 'wp-whatsevolution'); ?></option>
									<option value="08"><?php _e('Agosto', 'wp-whatsevolution'); ?></option>
									<option value="09"><?php _e('Setembro', 'wp-whatsevolution'); ?></option>
									<option value="10"><?php _e('Outubro', 'wp-whatsevolution'); ?></option>
									<option value="11"><?php _e('Novembro', 'wp-whatsevolution'); ?></option>
									<option value="12"><?php _e('Dezembro', 'wp-whatsevolution'); ?></option>
								</select>
								<p style="margin: 8px 0 0 0; color: #6c757d; font-size: 13px;">
									<?php _e('Mostra apenas clientes que fazem aniversário no mês selecionado.', 'wp-whatsevolution'); ?>
								</p>
							</div>
						</div>

						<button type="button" class="button" id="wpwevo-preview-all-customers">
							<?php _e('Visualizar Todos os Clientes', 'wp-whatsevolution'); ?>
						</button>

						<div id="wpwevo-all-customers-preview"></div>
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
								
								<!-- Variáveis Disponíveis -->
								<div style="margin-top: 15px; padding: 15px; background: #f0fff4; border-radius: 8px; border-left: 4px solid #48bb78;">
									<h4 style="margin: 0 0 10px 0; color: #2d3748; font-size: 14px; display: flex; align-items: center;">
										<span style="margin-right: 8px;">🏷️</span> Variáveis Disponíveis
									</h4>
									<p style="margin: 0 0 12px 0; color: #4a5568; font-size: 12px;">Copie e cole as variáveis nas mensagens:</p>
									
									<!-- Aba Clientes WooCommerce -->
									<div id="wpwevo-variables-woo" class="wpwevo-variables-section" style="display: block;">
										<div style="margin-bottom: 8px; padding: 6px 10px; background: #e6fffa; border-radius: 4px; border-left: 3px solid #319795;">
											<small style="color: #2c7a7b; font-weight: 600;">🛒 Clientes WooCommerce</small>
										</div>
										<div style="display: grid; gap: 6px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
											<?php 
											$woo_variables = [
												'{customer_name}' => 'Nome do cliente',
												'{customer_phone}' => 'Telefone do cliente',
												'{order_id}' => 'ID do pedido',
												'{order_total}' => 'Total do pedido',
												'{billing_first_name}' => 'Nome de cobrança',
												'{billing_last_name}' => 'Sobrenome de cobrança',
												'{shipping_method}' => 'Método de envio',
												'{last_order_date}' => 'Data do último pedido'
											];
											
											foreach ($woo_variables as $var => $desc) : ?>
												<div style="background: #e6fffa; padding: 8px; border-radius: 6px; border: 1px solid #b2f5ea;">
													<div style="margin-bottom: 4px;">
														<code style="background: #319795; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; user-select: all; cursor: text;"><?php echo esc_html($var); ?></code>
													</div>
													<div style="color: #2d3748; font-size: 11px; line-height: 1.3; user-select: none;"><?php echo esc_html($desc); ?></div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
									
									<!-- Aba CSV -->
									<div id="wpwevo-variables-csv" class="wpwevo-variables-section" style="display: none;">
										<div style="margin-bottom: 8px; padding: 6px 10px; background: #e6fffa; border-radius: 4px; border-left: 3px solid #319795;">
											<small style="color: #2c7a7b; font-weight: 600;">📄 Importação CSV</small>
										</div>
										<div style="display: grid; gap: 6px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
											<?php 
											$csv_variables = [
												'{customer_name}' => 'Nome do contato',
												'{customer_phone}' => 'Telefone do contato',
												'{customer_email}' => 'Email do contato'
											];
											
											foreach ($csv_variables as $var => $desc) : ?>
												<div style="background: #e6fffa; padding: 8px; border-radius: 6px; border: 1px solid #b2f5ea;">
													<div style="margin-bottom: 4px;">
														<code style="background: #319795; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; user-select: all; cursor: text;"><?php echo esc_html($var); ?></code>
													</div>
													<div style="color: #2d3748; font-size: 11px; line-height: 1.3; user-select: none;"><?php echo esc_html($desc); ?></div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
									
									<!-- Aba Todos os Clientes -->
									<div id="wpwevo-variables-all-customers" class="wpwevo-variables-section" style="display: none;">
										<div style="margin-bottom: 8px; padding: 6px 10px; background: #e6fffa; border-radius: 4px; border-left: 3px solid #319795;">
											<small style="color: #2c7a7b; font-weight: 600;">👥 Todos os Clientes</small>
										</div>
										<div style="display: grid; gap: 6px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
											<?php 
											$all_customers_variables = [
												'{customer_name}' => 'Nome do cliente',
												'{customer_phone}' => 'Telefone do cliente',
												'{customer_email}' => 'Email do cliente',
												'{birthdate}' => 'Data de aniversário (DD/MM)',
												'{user_id}' => 'ID do usuário',
												'{display_name}' => 'Nome de exibição'
											];
											
											foreach ($all_customers_variables as $var => $desc) : ?>
												<div style="background: #e6fffa; padding: 8px; border-radius: 6px; border: 1px solid #b2f5ea;">
													<div style="margin-bottom: 4px;">
														<code style="background: #319795; color: white; padding: 2px 5px; border-radius: 3px; font-size: 10px; user-select: all; cursor: text;"><?php echo esc_html($var); ?></code>
													</div>
													<div style="color: #2d3748; font-size: 11px; line-height: 1.3; user-select: none;"><?php echo esc_html($desc); ?></div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
									
									<!-- Aba Manual -->
									<div id="wpwevo-variables-manual" class="wpwevo-variables-section" style="display: none;">
										<div style="background: #fff5f5; padding: 10px; border-radius: 6px; border-left: 4px solid #f56565;">
											<p style="margin: 0; color: #c53030; font-size: 12px; text-align: center;">
												⚠️ <strong>Lista Manual:</strong> Variáveis não se aplicam. Use apenas texto fixo.
											</p>
										</div>
									</div>
									
									<!-- Dicas de Uso -->
									<div style="margin-top: 12px; padding: 10px; background: #fef5e7; border-radius: 6px; border-left: 4px solid #ed8936;">
										<h5 style="margin: 0 0 6px 0; color: #2d3748; font-size: 12px;">💡 Dicas:</h5>
										<ul style="margin: 0; padding-left: 15px; color: #744210; font-size: 11px; line-height: 1.3;">
											<li>Use emojis para tornar as mensagens mais atrativas</li>
											<li>Personalize conforme seu tipo de negócio</li>
											<li>Teste as mensagens antes de enviar em massa</li>
										</ul>
									</div>
								</div>
							</div>

						<!-- Configurações de Envio -->
						<div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #a8edea;">
							<h3 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px; display: flex; align-items: center;">
								<span style="margin-right: 10px;">⏱️</span> <?php echo esc_html($this->i18n['form']['interval']); ?>
							</h3>
							
							<!-- Modo de Intervalo -->
							<div style="margin-bottom: 15px;">
								<div style="display: flex; gap: 20px; margin-bottom: 10px;">
									<label style="display: flex; align-items: center; cursor: pointer;">
										<input type="radio" name="wpwevo_interval_mode" value="fixed" checked style="margin-right: 8px;">
										<span style="font-weight: 500;">Fixo</span>
									</label>
									<label style="display: flex; align-items: center; cursor: pointer;">
										<input type="radio" name="wpwevo_interval_mode" value="random" style="margin-right: 8px;">
										<span style="font-weight: 500;">Aleatório</span>
									</label>
								</div>
							</div>
							
							<!-- Campo de Intervalo Fixo -->
							<div id="wpwevo-interval-fixed" style="display: flex; align-items: center; gap: 10px;">
								<input type="number" name="wpwevo_interval" value="5" min="1" max="60" 
									   style="width: 80px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px;">
								<span style="color: #4a5568; font-size: 14px;"><?php echo esc_html($this->i18n['form']['interval_help']); ?></span>
							</div>
							
							<!-- Informação do Modo Aleatório -->
							<div id="wpwevo-interval-random" style="display: none; padding: 10px; background: #e6fffa; border-radius: 6px; border-left: 4px solid #319795;">
								<p style="margin: 0; color: #2c7a7b; font-size: 14px;">
									<strong>Modo Aleatório:</strong> Intervalos variam entre 2 e 9 segundos automaticamente
								</p>
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
		
		<script>
		jQuery(document).ready(function($) {
			// Alterna entre modos de intervalo
			$('input[name="wpwevo_interval_mode"]').on('change', function() {
				if ($(this).val() === 'random') {
					$('#wpwevo-interval-fixed').hide();
					$('#wpwevo-interval-random').show();
				} else {
					$('#wpwevo-interval-fixed').show();
					$('#wpwevo-interval-random').hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Filtra pedidos baseado no total de pedidos do cliente usando abordagem otimizada
	 * 
	 * @param array $order_ids Array de IDs dos pedidos
	 * @param int $min_orders Número mínimo de pedidos
	 * @return array Array filtrado de IDs dos pedidos
	 */
	private function filter_orders_by_customer_order_count($order_ids, $min_orders) {
		if (empty($order_ids) || $min_orders <= 0) {
			return $order_ids;
		}

		// Abordagem mais simples e eficiente: processa cada pedido individualmente
		$filtered_orders = [];
		$customer_orders_count = []; // Cache para evitar recálculos
		
		// Log para debug
		error_log("WPWhatsEvolution: Iniciando filtro de pedidos. Total de pedidos: " . count($order_ids) . ", Mínimo: " . $min_orders);
		
		// Limita o processamento para evitar timeout
		$max_orders_to_process = 500; // Limite de segurança
		$orders_to_process = array_slice($order_ids, 0, $max_orders_to_process);
		
		if (count($order_ids) > $max_orders_to_process) {
			error_log("WPWhatsEvolution: Limitando processamento a " . $max_orders_to_process . " pedidos de " . count($order_ids) . " para evitar timeout");
		}
		
		foreach ($orders_to_process as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order) continue;
			
			$customer_id = $order->get_customer_id();
			$customer_email = $order->get_billing_email();
			$cache_key = '';
			
			// Determina a chave de cache
			if ($customer_id > 0) {
				$cache_key = 'customer_' . $customer_id;
			} elseif (!empty($customer_email)) {
				$cache_key = 'email_' . md5($customer_email);
			}
			
			// Se não tem chave válida, pula
			if (empty($cache_key)) {
				continue;
			}
			
			// Verifica se já calculou para este cliente/email
			if (!isset($customer_orders_count[$cache_key])) {
				$total_orders = 0;
				
				if ($customer_id > 0) {
					// Cliente logado: conta por customer_id usando função nativa do WooCommerce
					$total_orders = wc_get_customer_order_count($customer_id);
					
					// Se a função nativa retornar 0, tenta método alternativo
					if ($total_orders == 0) {
						$customer_orders_query = new \WC_Order_Query([
							'customer' => $customer_id,
							'limit' => -1,
							'return' => 'ids'
						]);
						$customer_orders = $customer_orders_query->get_orders();
						$total_orders = count($customer_orders);
					}
				} else {
					// Cliente convidado: conta por email
					$email_orders_query = new \WC_Order_Query([
						'billing_email' => $customer_email,
						'limit' => -1,
						'return' => 'ids'
					]);
					$email_orders = $email_orders_query->get_orders();
					$total_orders = count($email_orders);
				}
				
				$customer_orders_count[$cache_key] = $total_orders;
			}
			
			// Se o cliente tem X+ pedidos, inclui este pedido
			if ($customer_orders_count[$cache_key] >= $min_orders) {
				$filtered_orders[] = $order_id;
			}
		}
		
		// Log final para debug
		error_log("WPWhatsEvolution: Filtro concluído. Pedidos filtrados: " . count($filtered_orders) . " de " . count($orders_to_process) . " processados");
		
		return $filtered_orders;
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
            wp_send_json_error(__('Verificação de segurança falhou.', 'wp-whatsevolution'));
			}

            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
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
                throw new \Exception(__('Selecione pelo menos um status.', 'wp-whatsevolution'));
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
                throw new \Exception(__('Um ou mais status selecionados são inválidos.', 'wp-whatsevolution'));
			}

			// Obtém os filtros adicionais
			$date_from = isset($_POST['wpwevo_date_from']) ? sanitize_text_field($_POST['wpwevo_date_from']) : '';
			$date_to = isset($_POST['wpwevo_date_to']) ? sanitize_text_field($_POST['wpwevo_date_to']) : '';
			$min_total = isset($_POST['wpwevo_min_total']) ? str_replace(['.', ','], ['', '.'], sanitize_text_field($_POST['wpwevo_min_total'])) : 0;
			$min_total = floatval($min_total);
			$max_total = isset($_POST['wpwevo_max_total']) ? str_replace(['.', ','], ['', '.'], sanitize_text_field($_POST['wpwevo_max_total'])) : 0;
			$max_total = floatval($max_total);
			
			// Filtro de inatividade
			$filter_inactive = isset($_POST['wpwevo_filter_inactive']) && $_POST['wpwevo_filter_inactive'] === '1';
			$inactive_days = isset($_POST['wpwevo_inactive_days']) ? max(1, min(365, intval($_POST['wpwevo_inactive_days']))) : 30;
			
			// Filtro de total de pedidos
			$filter_min_orders = isset($_POST['wpwevo_filter_min_orders']) && $_POST['wpwevo_filter_min_orders'] === '1';
			$min_orders = isset($_POST['wpwevo_min_orders']) ? max(1, min(1000, intval($_POST['wpwevo_min_orders']))) : 1;

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
                throw new \Exception(__('Nenhum cliente encontrado com os filtros selecionados.', 'wp-whatsevolution'));
			}

			// Se filtro de total de pedidos está ativo, aplica filtro antes de processar
			if ($filter_min_orders) {
				$orders = $this->filter_orders_by_customer_order_count($orders, $min_orders);
			}

			// Processa os pedidos encontrados
			$customers = [];
			$processed_phones = [];
			$customer_orders_cache = []; // Cache para evitar queries repetidas

			foreach ($orders as $order_id) {
				$order = wc_get_order($order_id);
				if (!$order) continue;

				// Filtro por valor mínimo e máximo
				$order_total = $order->get_total();
				if ($min_total > 0 && $order_total < $min_total) {
					continue;
				}
				if ($max_total > 0 && $order_total > $max_total) {
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
					$last_order_date = $order->get_date_created();
					$customer_id = $order->get_customer_id();
					
					// Filtro de inatividade: verifica se o cliente não fez pedidos recentes
					if ($filter_inactive && $customer_id > 0) {
						$inactive_cutoff = date('Y-m-d H:i:s', strtotime("-{$inactive_days} days"));
						
						// Busca o último pedido do cliente (independente do status)
						$last_order_query = new \WC_Order_Query([
							'customer' => $customer_id,
							'limit' => 1,
							'orderby' => 'date',
							'order' => 'DESC',
							'return' => 'ids'
						]);
						
						$last_orders = $last_order_query->get_orders();
						
						// Se o cliente tem pedidos recentes, pula
						if (!empty($last_orders)) {
							$last_order_obj = wc_get_order($last_orders[0]);
							if ($last_order_obj && $last_order_obj->get_date_created() > $inactive_cutoff) {
								continue; // Cliente ativo, pula
							}
						}
					}

					// Calcula o total de pedidos do cliente de forma robusta e otimizada
					$customer_id = $order->get_customer_id();
					$total_orders = 0;
					$cache_key = '';
					
					if ($customer_id > 0) {
						$cache_key = 'customer_' . $customer_id;
					} else {
						// Se não tem customer_id, usa email como chave de cache
						$customer_email = $order->get_billing_email();
						if (!empty($customer_email)) {
							$cache_key = 'email_' . md5($customer_email);
						}
					}
					
					// Verifica se já calculou para este cliente/email
					if (!empty($cache_key) && isset($customer_orders_cache[$cache_key])) {
						$total_orders = $customer_orders_cache[$cache_key];
					} else if (!empty($cache_key)) {
						// Calcula o total de pedidos
						if ($customer_id > 0) {
							// Usa WC_Order_Query para contar pedidos do cliente
							$customer_orders_query = new \WC_Order_Query([
								'customer' => $customer_id,
								'limit' => -1,
								'return' => 'ids'
							]);
							$customer_orders = $customer_orders_query->get_orders();
							$total_orders = count($customer_orders);
						} else {
							// Se não tem customer_id, conta pedidos pelo email
							$customer_email = $order->get_billing_email();
							if (!empty($customer_email)) {
								$email_orders_query = new \WC_Order_Query([
									'billing_email' => $customer_email,
									'limit' => -1,
									'return' => 'ids'
								]);
								$email_orders = $email_orders_query->get_orders();
								$total_orders = count($email_orders);
							}
						}
						
						// Armazena no cache para evitar recálculos
						$customer_orders_cache[$cache_key] = $total_orders;
					}

					$customer_data = [
						'phone' => $phone, // Mantém o número original para exibição
						'normalized_phone' => $normalized_phone, // Guarda a versão normalizada
						'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
						'total_orders' => $total_orders,
						'last_order' => date_i18n('d/m/Y', strtotime($last_order_date)),
						'status' => $order->get_status(),
						'order_id' => $order->get_id(),
						'order_total' => $order->get_total()
					];

					$customers[] = $customer_data;
					$processed_phones[$normalized_phone] = true;
				}
			}

            if (empty($customers)) {
                throw new \Exception(__('Nenhum cliente encontrado com os filtros selecionados.', 'wp-whatsevolution'));
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
                            __('Total de clientes únicos encontrados: %d', 'wp-whatsevolution'),
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
                        $filters[] = __('Status: ', 'wp-whatsevolution') . implode(', ', $status_labels);
						
						// Período
						if (!empty($date_from) || !empty($date_to)) {
                            $period = __('Período: ', 'wp-whatsevolution');
							if (!empty($date_from)) {
								$period .= date_i18n('d/m/Y', strtotime($date_from));
							}
							$period .= ' - ';
							if (!empty($date_to)) {
								$period .= date_i18n('d/m/Y', strtotime($date_to));
							}
							$filters[] = $period;
						}
						
						// Valor mínimo e máximo
						if ($min_total > 0 || $max_total > 0) {
							$value_filter = '';
							if ($min_total > 0 && $max_total > 0) {
								$value_filter = sprintf(
									__('Valor: R$ %s a R$ %s', 'wp-whatsevolution'),
									number_format($min_total, 2, ',', '.'),
									number_format($max_total, 2, ',', '.')
								);
							} elseif ($min_total > 0) {
								$value_filter = sprintf(
									__('Valor mínimo: R$ %s', 'wp-whatsevolution'),
									number_format($min_total, 2, ',', '.')
								);
							} elseif ($max_total > 0) {
								$value_filter = sprintf(
									__('Valor máximo: R$ %s', 'wp-whatsevolution'),
									number_format($max_total, 2, ',', '.')
								);
							}
							$filters[] = $value_filter;
						}
						
						// Filtro de inatividade
						if ($filter_inactive) {
							$filters[] = sprintf(
								__('Inativos há mais de %d dias', 'wp-whatsevolution'),
								$inactive_days
							);
						}
						
						// Filtro de total de pedidos
						if ($filter_min_orders) {
							$filters[] = sprintf(
								__('Clientes com %d+ pedidos', 'wp-whatsevolution'),
								$min_orders
							);
						}
						
						echo implode(' | ', $filters);
						?>
					</p>
				</div>

				<table class="widefat striped">
				<thead>
					<tr>
                        <th><?php _e('Nome', 'wp-whatsevolution'); ?></th>
                        <th><?php _e('Telefone', 'wp-whatsevolution'); ?></th>
						<th>
                            <?php _e('Total de Pedidos', 'wp-whatsevolution'); ?>
                            <span class="dashicons dashicons-info-outline" title="<?php esc_attr_e('Total de pedidos do cliente em todos os status', 'wp-whatsevolution'); ?>"></span>
						</th>
                        <th><?php _e('Último Pedido', 'wp-whatsevolution'); ?></th>
                        <th><?php _e('Status Atual', 'wp-whatsevolution'); ?></th>
                        <th><?php _e('Valor', 'wp-whatsevolution'); ?></th>
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
                                        __('Pedido atual: #%s', 'wp-whatsevolution'),
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

	/**
	 * Preview de todos os clientes (WP_User_Query)
	 */
	public function preview_all_customers() {
		try {
			// Verifica nonce
			if (!wp_verify_nonce($_POST['nonce'], 'wpwevo_bulk_send')) {
				wp_send_json_error(__('Verificação de segurança falhou.', 'wp-whatsevolution'));
			}

			if (!current_user_can('manage_options')) {
				wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
			}

			// Obtém filtros de aniversário e paginação
			$filter_birthday = isset($_POST['wpwevo_filter_birthday']) && $_POST['wpwevo_filter_birthday'] === '1';
			$birthday_month = isset($_POST['wpwevo_birthday_month']) ? sanitize_text_field($_POST['wpwevo_birthday_month']) : null;
			$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
			$per_page = isset($_POST['per_page']) ? max(1, min(100, intval($_POST['per_page']))) : 25;

			// Obtém todos os clientes usando WP_User_Query com paginação
			$result = $this->get_all_customers_numbers($filter_birthday, $birthday_month, $page, $per_page);
			$customers = $result['customers'];

			if (empty($customers)) {
				if ($filter_birthday && !empty($birthday_month)) {
					$month_names = [
						'01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
						'05' => 'Maio', '06' => 'Junho', '07' => 'Julho', '08' => 'Agosto',
						'09' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
					];
					$month_name = $month_names[$birthday_month] ?? $birthday_month;
					throw new \Exception(sprintf(__('Nenhum cliente com aniversário em %s encontrado.', 'wp-whatsevolution'), $month_name));
				} else {
					throw new \Exception(__('Nenhum cliente com telefone cadastrado encontrado.', 'wp-whatsevolution'));
				}
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
							__('Total de clientes encontrados: %d', 'wp-whatsevolution'),
							$result['total']
						); 
						?>
					</h4>
					<p class="description">
						<?php _e('Todos os usuários cadastrados no WordPress que possuem telefone.', 'wp-whatsevolution'); ?>
					</p>
					
					<!-- Controles de Paginação como WooCommerce -->
					<div class="wpwevo-pagination-controls" style="margin: 15px 0; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
						<div class="wpwevo-pagination-info">
							<?php 
							printf(
								__('Página %d de %d', 'wp-whatsevolution'),
								$result['page'],
								$result['total_pages']
							);
							?>
						</div>
						
						<div class="wpwevo-pagination-nav" style="display: flex; align-items: center; gap: 10px;">
							<?php if ($result['page'] > 1): ?>
								<button type="button" class="button" onclick="wpwevoLoadAllCustomersPage(<?php echo $result['page'] - 1; ?>)">
									&larr; <?php _e('Anterior', 'wp-whatsevolution'); ?>
								</button>
							<?php endif; ?>
							
							<?php if ($result['page'] < $result['total_pages']): ?>
								<button type="button" class="button" onclick="wpwevoLoadAllCustomersPage(<?php echo $result['page'] + 1; ?>)">
									<?php _e('Próxima', 'wp-whatsevolution'); ?> &rarr;
								</button>
							<?php endif; ?>
						</div>
						
						<div class="wpwevo-pagination-jump" style="display: flex; align-items: center; gap: 5px;">
							<label><?php _e('Ir para a página', 'wp-whatsevolution'); ?>:</label>
							<input type="number" 
								   id="wpwevo-jump-to-page" 
								   min="1" 
								   max="<?php echo $result['total_pages']; ?>" 
								   value="<?php echo $result['page']; ?>"
								   style="width: 60px; padding: 4px;">
							<button type="button" class="button" onclick="wpwevoJumpToPage()">
								<?php _e('Ir', 'wp-whatsevolution'); ?>
							</button>
						</div>
						
						<div class="wpwevo-per-page" style="display: flex; align-items: center; gap: 5px;">
							<label><?php _e('Linhas por página', 'wp-whatsevolution'); ?>:</label>
							<select id="wpwevo-per-page-select" onchange="wpwevoChangePerPage()" style="padding: 4px; min-width: 80px;">
								<option value="10" <?php selected($result['per_page'], 10); ?>>10</option>
								<option value="25" <?php selected($result['per_page'], 25); ?>>25</option>
								<option value="50" <?php selected($result['per_page'], 50); ?>>50</option>
								<option value="100" <?php selected($result['per_page'], 100); ?>>100</option>
							</select>
						</div>
					</div>
				</div>

				<table class="widefat striped">
				<thead>
					<tr>
						<th><?php _e('Nome', 'wp-whatsevolution'); ?></th>
						<th><?php _e('Telefone', 'wp-whatsevolution'); ?></th>
						<th><?php _e('Email', 'wp-whatsevolution'); ?></th>
						<th>
							<?php _e('Aniversário', 'wp-whatsevolution'); ?>
							<span class="dashicons dashicons-info-outline" 
								  title="<?php esc_attr_e('Necessita plugin Brazilian Market on WooCommerce', 'wp-whatsevolution'); ?>"
								  style="font-size: 16px; color: #646970; vertical-align: middle; cursor: help; margin-left: 5px;"></span>
						</th>
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
							<td><?php echo esc_html($customer['email']); ?></td>
							<td>
								<?php 
								if (!empty($customer['birthdate'])) {
									// Converte data para formato DD/MM
									$birthdate = $customer['birthdate'];
									if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthdate, $matches)) {
										// Já está no formato DD/MM/YYYY, extrai DD/MM
										$birthdate_formatted = $matches[1] . '/' . $matches[2];
									} else {
										// Fallback: tenta converter com strtotime
										$birthdate_formatted = date('d/m', strtotime($birthdate));
									}
									echo esc_html($birthdate_formatted);
								} else {
									echo '<span class="description">-</span>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

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
			.wpwevo-preview-notes {
				margin-top: 20px;
				padding: 10px;
				background: #f8f9fa;
				border-left: 4px solid #646970;
			}
			.wpwevo-preview-notes ul {
				margin: 5px 0 0 20px;
			}
			</style>
			
			<script>
			// Funções de paginação para "Todos os Clientes"
			function wpwevoLoadAllCustomersPage(page) {
				const formData = new FormData();
				formData.append('action', 'wpwevo_preview_all_customers');
				formData.append('nonce', wpwevoBulkSend.nonce);
				formData.append('page', page);
				formData.append('per_page', document.getElementById('wpwevo-per-page-select').value);
				
				// Filtros de aniversário
				const filterBirthday = document.getElementById('wpwevo-filter-birthday');
				if (filterBirthday && filterBirthday.checked) {
					formData.append('wpwevo_filter_birthday', '1');
					const birthdayMonth = document.getElementById('wpwevo-birthday-month');
					if (birthdayMonth) {
						formData.append('wpwevo_birthday_month', birthdayMonth.value);
					}
				}
				
				
				// Mostra loading
				const previewContainer = document.getElementById('wpwevo-all-customers-preview');
				previewContainer.innerHTML = '<div style="text-align: center; padding: 40px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p>Carregando clientes...</p></div>';
				
				fetch(wpwevoBulkSend.ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						previewContainer.innerHTML = data.data.html;
					} else {
						previewContainer.innerHTML = '<div class="notice notice-error"><p>Erro: ' + data.data + '</p></div>';
					}
				})
				.catch(error => {
					previewContainer.innerHTML = '<div class="notice notice-error"><p>Erro na requisição: ' + error.message + '</p></div>';
				});
			}
			
			function wpwevoJumpToPage() {
				const pageInput = document.getElementById('wpwevo-jump-to-page');
				const page = parseInt(pageInput.value);
				if (page > 0) {
					wpwevoLoadAllCustomersPage(page);
				}
			}
			
			function wpwevoChangePerPage() {
				// Volta para página 1 quando muda o per_page
				wpwevoLoadAllCustomersPage(1);
			}
			
			
			// Enter no campo de página
			document.addEventListener('DOMContentLoaded', function() {
				const pageInput = document.getElementById('wpwevo-jump-to-page');
				if (pageInput) {
					pageInput.addEventListener('keypress', function(e) {
						if (e.key === 'Enter') {
							wpwevoJumpToPage();
						}
					});
				}
			});
			</script>
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
                wp_send_json_error(__('Verificação de segurança falhou.', 'wp-whatsevolution'), 403);
			}

            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Permissão negada.', 'wp-whatsevolution'));
			}

			// Verifica conexão com a API de forma segura
			try {
				if (!$this->api->is_configured()) {
					throw new \Exception($this->i18n['connection_error']);
				}
			} catch (\Exception $e) {
				// Se a API falhar, retorna erro mas não quebra o site
                wp_send_json_error(__('Erro na configuração da API. Verifique as configurações.', 'wp-whatsevolution'));
			}

			$active_tab = isset($_POST['active_tab']) ? sanitize_text_field($_POST['active_tab']) : '';
			$message = isset($_POST['wpwevo_bulk_message']) ? sanitize_textarea_field($_POST['wpwevo_bulk_message']) : '';
			$interval_mode = isset($_POST['wpwevo_interval_mode']) ? sanitize_text_field($_POST['wpwevo_interval_mode']) : 'fixed';
			$interval = isset($_POST['wpwevo_interval']) ? absint($_POST['wpwevo_interval']) : 5;

        if (empty($message)) {
                throw new \Exception(__('A mensagem é obrigatória.', 'wp-whatsevolution'));
        }

			// Obtém a lista de números com base na aba ativa
		$numbers = [];
			switch ($active_tab) {
			case 'customers':
					// Processa os status
					$statuses = isset($_POST['status']) ? (array)$_POST['status'] : [];
					$statuses = array_unique(array_filter($statuses, 'strlen')); // Remove duplicatas e valores vazios
					
                    if (empty($statuses)) {
                        throw new \Exception(__('Selecione pelo menos um status.', 'wp-whatsevolution'));
					}

					$date_from = isset($_POST['wpwevo_date_from']) ? sanitize_text_field($_POST['wpwevo_date_from']) : '';
					$date_to = isset($_POST['wpwevo_date_to']) ? sanitize_text_field($_POST['wpwevo_date_to']) : '';
					$min_total = isset($_POST['wpwevo_min_total']) ? floatval(str_replace(',', '.', $_POST['wpwevo_min_total'])) : 0;
					$max_total = isset($_POST['wpwevo_max_total']) ? floatval(str_replace(',', '.', $_POST['wpwevo_max_total'])) : 0;
					
				// Filtro de inatividade para envio
				$filter_inactive = isset($_POST['wpwevo_filter_inactive']) && $_POST['wpwevo_filter_inactive'] === '1';
				$inactive_days = isset($_POST['wpwevo_inactive_days']) ? max(1, min(365, intval($_POST['wpwevo_inactive_days']))) : 30;
				
				// Filtro de total de pedidos para envio
				$filter_min_orders = isset($_POST['wpwevo_filter_min_orders']) && $_POST['wpwevo_filter_min_orders'] === '1';
				$min_orders = isset($_POST['wpwevo_min_orders']) ? max(1, min(1000, intval($_POST['wpwevo_min_orders']))) : 1;
				
				$numbers = $this->get_customers_numbers($statuses, $date_from, $date_to, $min_total, $max_total, $filter_inactive, $inactive_days, $filter_min_orders, $min_orders);
				break;

			case 'all-customers':
				$filter_birthday = isset($_POST['wpwevo_filter_birthday']) && $_POST['wpwevo_filter_birthday'] === '1';
				$birthday_month = isset($_POST['wpwevo_birthday_month']) ? sanitize_text_field($_POST['wpwevo_birthday_month']) : null;
				$result = $this->get_all_customers_numbers($filter_birthday, $birthday_month, 1, 1000); // Busca até 1000 para envio
				$numbers = $result['customers'];
				break;

			case 'csv':
                    if (!isset($_FILES['wpwevo_csv_file']) || empty($_FILES['wpwevo_csv_file']['tmp_name'])) {
                        throw new \Exception(__('Arquivo CSV não enviado ou está vazio.', 'wp-whatsevolution'));
				}
					$numbers = $this->process_csv_file($_FILES['wpwevo_csv_file']);
				break;

			case 'manual':
					$manual_numbers = isset($_POST['wpwevo_manual_numbers']) ? sanitize_textarea_field($_POST['wpwevo_manual_numbers']) : '';
                if (empty($manual_numbers)) {
                        throw new \Exception(__('Lista de números vazia.', 'wp-whatsevolution'));
				}
					$numbers = array_filter(array_map('trim', explode("\n", $manual_numbers)));
				break;

			default:
                    throw new \Exception(__('Origem dos números inválida.', 'wp-whatsevolution'));
		}

        if (empty($numbers)) {
                throw new \Exception(__('Nenhum número encontrado para envio.', 'wp-whatsevolution'));
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
                            __('Número com formato inválido para %s (%s)', 'wp-whatsevolution'),
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
                                __('Erro ao enviar para %s: %s', 'wp-whatsevolution'),
								$contact_name ?: $validated_phone,
								$result['message']
							);
						} else {
							$success++;
						}
					} catch (\Exception $api_error) {
						// Se a API falhar, registra o erro mas continua
                        $errors[] = sprintf(
                            __('Erro na API ao enviar para %s: %s', 'wp-whatsevolution'),
							$contact_name ?: $validated_phone,
							$api_error->getMessage()
						);
					}

				} catch (\Exception $e) {
                    $errors[] = sprintf(
                        __('Erro ao processar %s: %s', 'wp-whatsevolution'),
						$contact_name ?: $phone_number,
						$e->getMessage()
					);
				} finally {
					$sent++;
					// Aguarda o intervalo configurado apenas se houver mais envios
					if ($sent < $total) {
						if ($interval_mode === 'random') {
							// Modo aleatório: intervalo entre 2 e 9 segundos
							sleep(rand(2, 9));
						} else {
							// Modo fixo: intervalo configurado pelo usuário
							if ($interval > 0) {
								sleep($interval);
							}
						}
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
                    __('%d enviados com sucesso, %d erros', 'wp-whatsevolution'),
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
            $subject = __('Relatório de Envio em Massa - WhatsApp Evolution', 'wp-whatsevolution');
			$report = sprintf(
                __("Envio em massa concluído!\n\nTotal enviado: %d\nSucesso: %d\nErros: %d\n\nDetalhes dos erros:\n%s", 'wp-whatsevolution'),
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
                    __('Envio concluído! %d mensagens enviadas com sucesso, %d erros.', 'wp-whatsevolution'),
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
			$replacements['{order_total}'] = html_entity_decode(strip_tags(wc_price($order->get_total())));
			$replacements['{order_status}'] = wc_get_order_status_name($order->get_status());
			$replacements['{billing_first_name}'] = $order->get_billing_first_name();
			$replacements['{billing_last_name}'] = $order->get_billing_last_name();
			$replacements['{shipping_method}'] = $order->get_shipping_method();
			$replacements['{last_order_date}'] = date_i18n('d/m/Y', strtotime($order->get_date_created()));
		}

		// **NOVO: Adiciona placeholders específicos para CSV e Todos os Clientes**
		// Sempre substitui todas as variáveis - se vazio, fica em branco
		$replacements['{customer_email}'] = isset($contact_data['email']) && !empty($contact_data['email']) ? $contact_data['email'] : '';
		$replacements['{user_id}'] = isset($contact_data['user_id']) && !empty($contact_data['user_id']) ? $contact_data['user_id'] : '';
		$replacements['{display_name}'] = isset($contact_data['display_name']) && !empty($contact_data['display_name']) ? $contact_data['display_name'] : '';
		
		if (isset($contact_data['birthdate']) && !empty($contact_data['birthdate'])) {
			// Formato DD/MM para aniversário
			$birthdate = $contact_data['birthdate'];
			if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthdate, $matches)) {
				// Já está no formato DD/MM/YYYY, extrai DD/MM
				$replacements['{birthdate}'] = $matches[1] . '/' . $matches[2];
			} else {
				// Fallback: tenta converter com strtotime
				$replacements['{birthdate}'] = date('d/m', strtotime($birthdate));
			}
		} else {
			$replacements['{birthdate}'] = '';
		}

		return str_replace(array_keys($replacements), array_values($replacements), $message);
	}

	private function get_customers_numbers($statuses, $date_from, $date_to, $min_total, $max_total = 0, $filter_inactive = false, $inactive_days = 30, $filter_min_orders = false, $min_orders = 1) {
		if (!class_exists('WooCommerce')) {
            throw new \Exception(__('WooCommerce não está ativo.', 'wp-whatsevolution'));
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

		// Se filtro de total de pedidos está ativo, aplica filtro
		if ($filter_min_orders) {
			$orders = $this->filter_orders_by_customer_order_count($orders, $min_orders);
		}

		$customers = [];
		$processed_phones = [];

		foreach ($orders as $order_id) {
			$order = wc_get_order($order_id);
			if (!$order) continue;
			
			// Filtro por valor mínimo e máximo
			$order_total = $order->get_total();
			if ($min_total > 0 && $order_total < $min_total) {
				continue;
			}
			if ($max_total > 0 && $order_total > $max_total) {
				continue;
			}

			$phone = wpwevo_get_order_phone($order);
			if (empty($phone)) continue;

			// Normaliza o número para comparação para evitar duplicatas
			$normalized_phone = preg_replace('/\D/', '', $phone);
			if (isset($processed_phones[$normalized_phone])) {
				continue;
			}

			// Filtro de inatividade: verifica se o cliente não fez pedidos recentes
			if ($filter_inactive) {
				$customer_id = $order->get_customer_id();
				if ($customer_id > 0) {
					$inactive_cutoff = date('Y-m-d H:i:s', strtotime("-{$inactive_days} days"));
					
					// Busca o último pedido do cliente (independente do status)
					$last_order_query = new \WC_Order_Query([
						'customer' => $customer_id,
						'limit' => 1,
						'orderby' => 'date',
						'order' => 'DESC',
						'return' => 'ids'
					]);
					
					$last_orders = $last_order_query->get_orders();
					
					// Se o cliente tem pedidos recentes, pula
					if (!empty($last_orders)) {
						$last_order_obj = wc_get_order($last_orders[0]);
						if ($last_order_obj && $last_order_obj->get_date_created() > $inactive_cutoff) {
							continue; // Cliente ativo, pula
						}
					}
				}
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

	/**
	 * Obtém todos os clientes usando WP_User_Query
	 * Busca todos os usuários cadastrados no WordPress, independente de pedidos
	 * Implementa paginação real como o WooCommerce
	 * OTIMIZADO para sites com 78k+ usuários
	 */
	private function get_all_customers_numbers($filter_birthday = false, $birthday_month = null, $page = 1, $per_page = 25) {
		// Paginação real como o WooCommerce
		$offset = ($page - 1) * $per_page;
		
		// **OTIMIZAÇÃO CRÍTICA**: Query direta no banco para performance máxima
		global $wpdb;
		
		// Prepara filtro de aniversário se ativo
		$birthday_condition = '';
		if ($filter_birthday && !empty($birthday_month)) {
			$birthday_condition = $wpdb->prepare("
				AND EXISTS (
					SELECT 1 FROM {$wpdb->usermeta} um_birth 
					WHERE um_birth.user_id = u.ID 
					AND um_birth.meta_key = 'billing_birthdate' 
					AND um_birth.meta_value != ''
					AND MONTH(STR_TO_DATE(um_birth.meta_value, '%%d/%%m/%%Y')) = %d
				)
			", $birthday_month);
		}
		
		// Conta total de usuários com telefone (considerando filtro de aniversário)
		$total_query = "
			SELECT COUNT(DISTINCT u.ID) 
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
			WHERE um.meta_key IN ('billing_phone', 'billing_cellphone', 'phone') 
			AND um.meta_value != ''
			{$birthday_condition}
		";
		
		$total_users = (int) $wpdb->get_var($total_query);
		
		// Query otimizada para buscar usuários com telefone (considerando filtro de aniversário)
		$users_query = "
			SELECT DISTINCT u.ID, u.user_email, u.display_name, u.user_registered
			FROM {$wpdb->users} u
			INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id 
			WHERE um.meta_key IN ('billing_phone', 'billing_cellphone', 'phone') 
			AND um.meta_value != ''
			{$birthday_condition}
			ORDER BY u.user_registered DESC
			LIMIT %d OFFSET %d
		";
		
		$users = $wpdb->get_results($wpdb->prepare($users_query, $per_page, $offset));
		$customers = [];
		$processed_phones = [];

		// **OTIMIZAÇÃO DE PERFORMANCE**: Busca todos os meta dados de uma vez
		$user_ids = wp_list_pluck($users, 'ID');
		$all_meta = [];
		
		if (!empty($user_ids)) {
			$meta_keys = ['billing_phone', 'billing_cellphone', 'phone', 'first_name', 'last_name', 'billing_birthdate'];
			$placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
			$user_placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
			
			$meta_query = $wpdb->prepare("
				SELECT user_id, meta_key, meta_value 
				FROM {$wpdb->usermeta} 
				WHERE user_id IN ($user_placeholders) 
				AND meta_key IN ($placeholders)
			", array_merge($user_ids, $meta_keys));
			
			$results = $wpdb->get_results($meta_query);
			
			// Organiza os meta dados por user_id
			foreach ($results as $meta) {
				$all_meta[$meta->user_id][$meta->meta_key] = $meta->meta_value;
			}
		}

		foreach ($users as $user) {
			// **OTIMIZADO**: Usa dados já carregados em vez de queries individuais
			$user_meta = $all_meta[$user->ID] ?? [];
			
			// Tenta obter o telefone do usuário (prioridade: billing_phone > billing_cellphone > phone)
			$phone = $user_meta['billing_phone'] ?? '';
			if (empty($phone)) {
				$phone = $user_meta['billing_cellphone'] ?? '';
			}
			if (empty($phone)) {
				$phone = $user_meta['phone'] ?? '';
			}
			
			// Se ainda não encontrou, pula este usuário
			if (empty($phone)) {
				continue;
			}

			// **OTIMIZADO**: Usa dados já carregados
			$birthdate = $user_meta['billing_birthdate'] ?? '';
			
			// Se filtro de aniversário está ativo, verifica o mês
			if ($filter_birthday && !empty($birthday_month)) {
				if (empty($birthdate)) {
					continue; // Pula usuários sem data de nascimento
				}
				
				// Converte data DD/MM/YYYY para extrair o mês
				$birth_month = '';
				if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birthdate, $matches)) {
					$birth_month = $matches[2]; // Mês está na posição 2
				} else {
					// Fallback: tenta strtotime se não for formato DD/MM/YYYY
					$birth_month = date('m', strtotime($birthdate));
				}
				
				if ($birth_month !== $birthday_month) {
					continue; // Pula usuários que não fazem aniversário no mês selecionado
				}
			}

			// Normaliza o número para comparação para evitar duplicatas
			$normalized_phone = preg_replace('/\D/', '', $phone);
			if (isset($processed_phones[$normalized_phone])) {
				continue;
			}

			// **OTIMIZADO**: Usa dados já carregados
			$first_name = $user_meta['first_name'] ?? '';
			$last_name = $user_meta['last_name'] ?? '';
			$display_name = $user->display_name;
			
			$full_name = trim($first_name . ' ' . $last_name);
			if (empty($full_name)) {
				$full_name = $display_name;
			}

			$customers[] = [
				'name' => $full_name,
				'phone' => $phone,
				'email' => $user->user_email,
				'user_id' => $user->ID,
				'display_name' => $display_name,
				'birthdate' => $birthdate
			];
			$processed_phones[$normalized_phone] = true;
		}

		// Retorna dados com informações de paginação
		return [
			'customers' => $customers,
			'total' => $total_users,
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => ceil($total_users / $per_page)
		];
	}

	private function process_csv_file($file) {
		if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception(__('Erro no upload do arquivo.', 'wp-whatsevolution'));
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
            throw new \Exception(__('Arquivo CSV precisa de um cabeçalho e pelo menos uma linha de dados.', 'wp-whatsevolution'));
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
                throw new \Exception(__('Coluna selecionada não existe no arquivo CSV.', 'wp-whatsevolution'));
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
				// **NOVO: Tenta extrair nome e email se disponível**
				$name = '';
				$email = '';
				$name_keywords = ['nome', 'name', 'cliente', 'customer'];
				$email_keywords = ['email', 'e-mail', 'mail'];
				
				// Procura por coluna de nome
				foreach ($header_map as $idx => $col) {
					if (in_array($col, $name_keywords) && $idx != $phone_col_index) {
						$name = isset($data[$idx]) ? trim($data[$idx]) : '';
						break;
					}
				}
				
				// Procura por coluna de email
				foreach ($header_map as $idx => $col) {
					if (in_array($col, $email_keywords) && $idx != $phone_col_index) {
						$email = isset($data[$idx]) ? trim($data[$idx]) : '';
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
					'name' => $name,
					'email' => $email
				];
			}
		}

		if (empty($contacts)) {
            throw new \Exception(__('Nenhum contato com número de telefone válido foi encontrado no arquivo CSV.', 'wp-whatsevolution'));
		}
		
		// **NOVO: Log de sucesso**
		error_log("WPWhatsEvolution CSV: Processados " . count($contacts) . " contatos com sucesso");
		
		return $contacts;
	}

	public function clear_history() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');
		
		if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
		}

		delete_option('wpwevo_bulk_history');
		
		wp_send_json_success();
	}

	public function ajax_get_history() {
		check_ajax_referer('wpwevo_bulk_send', 'nonce');
		if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada.', 'wp-whatsevolution'));
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
						<td><?php echo esc_html($this->i18n['history']['sources'][$item['source']] ?? $item['source']); ?></td>
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