<?php
/**
 * Classe para administração do plugin.
 */

class WP_WhatsApp_Sender_Admin {

    /**
     * Inicializa a classe.
     */
    public function __construct() {
        // Carrega os scripts e estilos do admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Adiciona o menu de administração.
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            'WP WhatsApp Sender',
            'WhatsApp Sender',
            'manage_options',
            'wp-whatsapp-sender',
            array($this, 'display_settings_page'),
            'dashicons-format-chat',
            30
        );
        
        // Submenus
        add_submenu_page(
            'wp-whatsapp-sender',
            'Configurações',
            'Configurações',
            'manage_options',
            'wp-whatsapp-sender',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'wp-whatsapp-sender',
            'Enviar Mensagem',
            'Enviar Mensagem',
            'manage_options',
            'wp-whatsapp-sender-send',
            array($this, 'display_send_page')
        );
        
        add_submenu_page(
            'wp-whatsapp-sender',
            'Templates',
            'Templates',
            'manage_options',
            'wp-whatsapp-sender-templates',
            array($this, 'display_templates_page')
        );
        
        // Registra uma função para verificar se as URLs estão corretas
        add_action('admin_init', array($this, 'check_admin_urls'));
    }

    /**
     * Verifica se as URLs administrativas estão corretas.
     */
    public function check_admin_urls() {
        // Registra as variáveis GET para as páginas do plugin
        global $pagenow;
        if ($pagenow == 'admin.php' && isset($_GET['page'])) {
            $page = sanitize_text_field($_GET['page']);
            if (in_array($page, array('wp-whatsapp-sender', 'wp-whatsapp-sender-send', 'wp-whatsapp-sender-templates', 'wp-whatsapp-sender-bulk'))) {
                // Garante que a página seja registrada adequadamente
                if ($page === 'wp-whatsapp-sender-bulk') {
                    flush_rewrite_rules();
                }
            }
        }
    }

    /**
     * Registra as configurações do plugin.
     */
    public function register_settings() {
        // Registra a seção de configurações
        add_settings_section(
            'wp_whatsapp_sender_api_section',
            'Configurações da API do WhatsApp',
            array($this, 'api_section_callback'),
            'wp-whatsapp-sender'
        );
        
        // Registra os campos de configuração
        register_setting('wp_whatsapp_sender_settings', 'wp_whatsapp_sender_api_key');
        register_setting('wp_whatsapp_sender_settings', 'wp_whatsapp_sender_api_url');
        register_setting('wp_whatsapp_sender_settings', 'wp_whatsapp_sender_api_phone');
        register_setting('wp_whatsapp_sender_settings', 'wp_whatsapp_sender_instance_name');
        
        // Campo da API Key
        add_settings_field(
            'wp_whatsapp_sender_api_key',
            'API Key',
            array($this, 'api_key_field_callback'),
            'wp-whatsapp-sender',
            'wp_whatsapp_sender_api_section'
        );
        
        // Campo da URL da API
        add_settings_field(
            'wp_whatsapp_sender_api_url',
            'URL da API',
            array($this, 'api_url_field_callback'),
            'wp-whatsapp-sender',
            'wp_whatsapp_sender_api_section'
        );
        
        // Campo do número de telefone para envio
        add_settings_field(
            'wp_whatsapp_sender_api_phone',
            'Número de Telefone (opcional)',
            array($this, 'api_phone_field_callback'),
            'wp-whatsapp-sender',
            'wp_whatsapp_sender_api_section'
        );
        
        // Campo do nome da instância
        add_settings_field(
            'wp_whatsapp_sender_instance_name',
            'Nome da Instância',
            array($this, 'instance_name_field_callback'),
            'wp-whatsapp-sender',
            'wp_whatsapp_sender_api_section'
        );
    }

    /**
     * Callback para a seção de configurações da API.
     */
    public function api_section_callback() {
        echo '<p>Configure a API do WhatsApp para enviar mensagens.</p>';
        echo '<p>Este plugin utiliza a <strong>Evolution API</strong> como base: <a href="https://doc.evolution-api.com/" target="_blank">evolution-api</a></p>';
    }

    /**
     * Callback para o campo de API Key.
     */
    public function api_key_field_callback() {
        $api_key = get_option('wp_whatsapp_sender_api_key', '');
        echo '<input type="text" id="wp_whatsapp_sender_api_key" name="wp_whatsapp_sender_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">Use a chave de API configurada no seu servidor Evolution API.</p>';
    }

    /**
     * Callback para o campo de URL da API.
     */
    public function api_url_field_callback() {
        $api_url = get_option('wp_whatsapp_sender_api_url', '');
        echo '<input type="url" id="wp_whatsapp_sender_api_url" name="wp_whatsapp_sender_api_url" value="' . esc_attr($api_url) . '" class="regular-text">';
        echo '<p class="description">';
        echo 'URL do seu servidor Evolution API<br>';
        echo '<small>Exemplo: https://api.seuservidor.com (sem o nome da instância no final)</small>';
        echo '</p>';
    }

    /**
     * Callback para o campo de número de telefone.
     */
    public function api_phone_field_callback() {
        $api_phone = get_option('wp_whatsapp_sender_api_phone', '');
        echo '<input type="text" id="wp_whatsapp_sender_api_phone" name="wp_whatsapp_sender_api_phone" value="' . esc_attr($api_phone) . '" class="regular-text">';
        echo '<p class="description">Formato: 5511999999999 (opcional)</p>';
    }

    /**
     * Callback para o campo de nome da instância.
     */
    public function instance_name_field_callback() {
        $instance_name = get_option('wp_whatsapp_sender_instance_name', '');
        echo '<input type="text" id="wp_whatsapp_sender_instance_name" name="wp_whatsapp_sender_instance_name" value="' . esc_attr($instance_name) . '" class="regular-text">';
        echo '<p class="description">Informe o identificador da instância criada no painel da Evolution API. Este é o nome exato que você utilizou ao criar sua instância no servidor.</p>';
    }

    /**
     * Exibe a página de configurações.
     */
    public function display_settings_page() {
        $api_url = get_option('wp_whatsapp_sender_api_url', '');
        $instance_name = get_option('wp_whatsapp_sender_instance_name', '');
        $api_key = get_option('wp_whatsapp_sender_api_key', '');
        
        // Verifica se está usando Evolution API
        $is_evolution_api = (strpos($api_url, 'evolution') !== false);
        
        // Processa a verificação de conexão se solicitado
        $connection_status = '';
        $connection_message = '';
        
        if ($is_evolution_api && isset($_GET['check_connection']) && !empty($api_url) && !empty($instance_name) && !empty($api_key)) {
            $check_endpoint = trailingslashit($api_url) . 'instance/connectionState/' . $instance_name;
            
            $response = wp_remote_get($check_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'apikey' => $api_key
                ),
                'timeout' => 20
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                
                if ($response_code >= 200 && $response_code < 300 && isset($response_data['instance']['state'])) {
                    if ($response_data['instance']['state'] === 'open') {
                        $connection_status = 'success';
                        $connection_message = 'Conectado com sucesso! A instância ' . $instance_name . ' está ativa.';
                    } else {
                        $connection_status = 'error';
                        $connection_message = 'Instância encontrada, mas não está conectada. Estado atual: ' . $response_data['instance']['state'];
                    }
                } else {
                    $connection_status = 'error';
                    
                    // Melhor tratamento para erro 404 - instância não encontrada
                    if ($response_code == 404 && isset($response_data['response']['message']) && is_array($response_data['response']['message'])) {
                        foreach ($response_data['response']['message'] as $msg) {
                            if (strpos($msg, 'instance does not exist') !== false) {
                                $connection_message = 'Erro: A instância "' . $instance_name . '" não existe no servidor. Verifique se o nome da instância está correto.';
                                break;
                            }
                        }
                    }
                    
                    if (empty($connection_message)) {
                        $connection_message = 'Erro ao verificar a conexão. Resposta: ' . $response_body;
                    }
                }
            } else {
                $connection_status = 'error';
                $connection_message = 'Erro ao conectar com a API: ' . $response->get_error_message();
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($connection_status === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($connection_message); ?></p>
                </div>
            <?php elseif ($connection_status === 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($connection_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('wp_whatsapp_sender_settings');
                do_settings_sections('wp-whatsapp-sender');
                submit_button('Salvar Configurações');
                ?>
            </form>
            
            <?php if ($is_evolution_api && !empty($api_url) && !empty($instance_name) && !empty($api_key)): ?>
                <div class="card" style="max-width: 600px; padding: 15px; margin-top: 20px;">
                    <h2>Verificar Conexão da Evolution API</h2>
                    <p>Clique no botão abaixo para verificar se a instância <strong><?php echo esc_html($instance_name); ?></strong> está conectada:</p>
                    <a href="<?php echo esc_url(add_query_arg('check_connection', '1')); ?>" class="button button-primary">Verificar Conexão</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Exibe a página de envio de mensagens.
     */
    public function display_send_page() {
        // Processa o envio de mensagem se o formulário foi enviado
        $message_sent = false;
        $error_message = '';
        
        if (isset($_POST['wp_whatsapp_sender_send']) && wp_verify_nonce($_POST['wp_whatsapp_sender_nonce'], 'wp_whatsapp_sender_send_message')) {
            $to = isset($_POST['wp_whatsapp_sender_to']) ? sanitize_text_field($_POST['wp_whatsapp_sender_to']) : '';
            $message = isset($_POST['wp_whatsapp_sender_message']) ? sanitize_textarea_field($_POST['wp_whatsapp_sender_message']) : '';
            
            if (empty($to) || empty($message)) {
                $error_message = 'Por favor, preencha todos os campos obrigatórios.';
            } else {
                // Envia a mensagem
                $result = WP_WhatsApp_Sender_API::send_message($to, $message);
                
                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                } else {
                    $message_sent = true;
                }
            }
        }
        
        // Verifica se a API está configurada
        $api_configured = WP_WhatsApp_Sender_Utils::is_api_configured();
        
        // Verifica se está usando Evolution API
        $settings = WP_WhatsApp_Sender_Utils::get_api_settings();
        $api_url = $settings['api_url'];
        $instance_name = $settings['instance_name'];
        $api_key = $settings['api_key'];
        
        $is_evolution_api = (strpos($api_url, 'evolution') !== false);
        
        // Verifica status da conexão para Evolution API
        $connection_status = '';
        $connection_message = '';
        
        if ($is_evolution_api && $api_configured) {
            $check_endpoint = trailingslashit($api_url) . 'instance/connectionState/' . $instance_name;
            
            $response = wp_remote_get($check_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'apikey' => $api_key
                ),
                'timeout' => 20
            ));
            
            if (!is_wp_error($response)) {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                
                if ($response_code >= 200 && $response_code < 300 && isset($response_data['instance']['state'])) {
                    if ($response_data['instance']['state'] === 'open') {
                        $connection_status = 'success';
                        $connection_message = 'Conectado com sucesso! A instância ' . $instance_name . ' está ativa.';
                    } else {
                        $connection_status = 'error';
                        $connection_message = 'Instância encontrada, mas não está conectada. Estado atual: ' . $response_data['instance']['state'];
                        $api_configured = false; // Desabilita envio se não estiver conectado
                    }
                } else {
                    $connection_status = 'error';
                    $connection_message = 'Erro ao verificar a conexão.';
                    $api_configured = false; // Desabilita envio se não conseguir verificar
                }
            } else {
                $connection_status = 'error';
                $connection_message = 'Erro ao conectar com a API: ' . $response->get_error_message();
                $api_configured = false; // Desabilita envio se não conseguir conectar
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!$api_configured): ?>
                <div class="notice notice-error">
                    <p>A API do WhatsApp não está configurada corretamente. <a href="<?php echo admin_url('admin.php?page=wp-whatsapp-sender'); ?>">Configure a API</a> antes de enviar mensagens.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($connection_status === 'success'): ?>
                <div class="notice notice-success">
                    <p><?php echo esc_html($connection_message); ?></p>
                </div>
            <?php elseif ($connection_status === 'error'): ?>
                <div class="notice notice-error">
                    <p><?php echo esc_html($connection_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($message_sent): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Mensagem enviada com sucesso!</p>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('wp_whatsapp_sender_send_message', 'wp_whatsapp_sender_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp_whatsapp_sender_to">Número do Destinatário:</label>
                        </th>
                        <td>
                            <input type="text" id="wp_whatsapp_sender_to" name="wp_whatsapp_sender_to" class="regular-text" required>
                            <p class="description">Formato: (99) 99999-9999 ou 5599999999999</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wp_whatsapp_sender_message">Mensagem:</label>
                        </th>
                        <td>
                            <textarea id="wp_whatsapp_sender_message" name="wp_whatsapp_sender_message" class="large-text" rows="5" required></textarea>
                            <p class="description"><span id="char-count">0</span> caracteres (limite do WhatsApp: 4096 caracteres)</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="wp_whatsapp_sender_send" class="button button-primary" value="Enviar Mensagem" <?php echo $api_configured ? '' : 'disabled'; ?>>
                </p>
            </form>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Contador de caracteres
                    $('#wp_whatsapp_sender_message').on('input', function() {
                        var charCount = $(this).val().length;
                        $('#char-count').text(charCount);
                        
                        // Alerta visual se exceder o limite
                        if (charCount > 4096) {
                            $('#char-count').css('color', 'red');
                        } else {
                            $('#char-count').css('color', '');
                        }
                    });
                });
            </script>
        </div>
        <?php
    }

    /**
     * Exibe a página de templates.
     */
    public function display_templates_page() {
        // Processa o formulário se enviado
        $template_saved = false;
        $template_deleted = false;
        $order_status_saved = false;
        $error_message = '';
        
        // Determina qual guia está ativa
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
        // Salvar novo template
        if (isset($_POST['wp_whatsapp_sender_save_template']) && wp_verify_nonce($_POST['wp_whatsapp_sender_template_nonce'], 'wp_whatsapp_sender_save_template')) {
            $template_name = isset($_POST['wp_whatsapp_sender_template_name']) ? sanitize_text_field($_POST['wp_whatsapp_sender_template_name']) : '';
            $template_content = isset($_POST['wp_whatsapp_sender_template_content']) ? sanitize_textarea_field($_POST['wp_whatsapp_sender_template_content']) : '';
            
            if (empty($template_name) || empty($template_content)) {
                $error_message = 'Por favor, preencha todos os campos obrigatórios.';
            } else {
                // Limpa qualquer HTML do template
                $template_content = strip_tags($template_content);
                $template_content = html_entity_decode($template_content);
                
                // Salva o template
                update_option('wp_whatsapp_sender_template_' . $template_name, $template_content);
                $template_saved = true;
            }
        }
        
        // Salvar configurações de status de pedido
        if (isset($_POST['wp_whatsapp_sender_save_order_status']) && wp_verify_nonce($_POST['wp_whatsapp_sender_order_status_nonce'], 'wp_whatsapp_sender_save_order_status_templates')) {
            // Inicializar array para armazenar os templates
            $order_statuses = array();
            
            // Obter todos os templates de status de pedido enviados
            if (isset($_POST) && is_array($_POST)) {
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'wp_whatsapp_sender_template_') === 0) {
                        $status_key = str_replace('wp_whatsapp_sender_template_', '', $key);
                        
                        // Limpa qualquer HTML do template
                        $clean_value = strip_tags(sanitize_textarea_field($value));
                        $clean_value = html_entity_decode($clean_value);
                        
                        $order_statuses[$status_key] = $clean_value;
                    }
                }
            }
            
            // Salvar os templates de status de pedido
            update_option('wp_whatsapp_sender_order_status_templates', $order_statuses);
            
            // Salvar as configurações de envio automático
            $auto_send = isset($_POST['wp_whatsapp_sender_auto_send_status']) ? (array) $_POST['wp_whatsapp_sender_auto_send_status'] : array();
            update_option('wp_whatsapp_sender_auto_send_status', $auto_send);
            
            $order_status_saved = true;
        }
        
        // Excluir template
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['template']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_template_' . $_GET['template'])) {
            $template_name = sanitize_text_field($_GET['template']);
            delete_option('wp_whatsapp_sender_template_' . $template_name);
            $template_deleted = true;
        }
        
        // Obtém a lista de templates
        $templates = array();
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $option => $value) {
            if (strpos($option, 'wp_whatsapp_sender_template_') === 0) {
                $template_name = str_replace('wp_whatsapp_sender_template_', '', $option);
                $templates[$template_name] = $value;
            }
        }
        
        // Obtém os templates de status de pedido
        $order_status_templates = get_option('wp_whatsapp_sender_order_status_templates', array());
        $auto_send_status = get_option('wp_whatsapp_sender_auto_send_status', array());
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo admin_url('admin.php?page=wp-whatsapp-sender-templates&tab=general'); ?>" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Templates Gerais</a>
                <?php if (class_exists('WooCommerce')): ?>
                <a href="<?php echo admin_url('admin.php?page=wp-whatsapp-sender-templates&tab=order_status'); ?>" class="nav-tab <?php echo $active_tab == 'order_status' ? 'nav-tab-active' : ''; ?>">Templates de Status de Pedido</a>
                <?php endif; ?>
            </h2>
            
            <?php if ($template_saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Template salvo com sucesso!</p>
                </div>
            <?php endif; ?>
            
            <?php if ($template_deleted): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Template excluído com sucesso!</p>
                </div>
            <?php endif; ?>
            
            <?php if ($order_status_saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Templates de status de pedido salvos com sucesso!</p>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($active_tab == 'general'): ?>
                <!-- Templates Gerais -->
                <div class="card">
                    <h2>Criar Novo Template</h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('wp_whatsapp_sender_save_template', 'wp_whatsapp_sender_template_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="wp_whatsapp_sender_template_name">Nome do Template:</label>
                                </th>
                                <td>
                                    <input type="text" id="wp_whatsapp_sender_template_name" name="wp_whatsapp_sender_template_name" class="regular-text" required>
                                    <p class="description">Use apenas letras, números e underscores.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wp_whatsapp_sender_template_content">Conteúdo do Template:</label>
                                </th>
                                <td>
                                    <textarea id="wp_whatsapp_sender_template_content" name="wp_whatsapp_sender_template_content" class="large-text" rows="5" required></textarea>
                                    <p class="description">Use {{variavel}} para incluir variáveis no template.</p>
                                    <p class="description">Variáveis disponíveis: <code>{{customer_name}}</code>, <code>{{order_id}}</code>, <code>{{order_total}}</code>, <code>{{order_items}}</code>, <code>{{payment_method}}</code>, <code>{{payment_url}}</code>, <code>{{shipping_method}}</code>, <code>{{coupon_code}}</code>, <code>{{coupon_value}}</code>, <code>{{coupon_expiry}}</code></p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="wp_whatsapp_sender_save_template" class="button button-primary" value="Salvar Template">
                        </p>
                    </form>
                </div>
                
                <div class="card">
                    <h2>Templates Existentes</h2>
                    
                    <?php if (empty($templates)): ?>
                        <p>Nenhum template cadastrado.</p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Conteúdo</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $name => $content): ?>
                                    <tr>
                                        <td><?php echo esc_html($name); ?></td>
                                        <td><?php echo esc_html($content); ?></td>
                                        <td>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=wp-whatsapp-sender-templates&action=delete&template=' . urlencode($name)), 'delete_template_' . $name); ?>" class="button button-small" onclick="return confirm('Tem certeza que deseja excluir este template?');">Excluir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            
            <?php elseif ($active_tab == 'order_status' && class_exists('WooCommerce')): ?>
                <!-- Templates de Status de Pedido -->
                <div class="card">
                    <h2>Templates de Status de Pedido</h2>
                    
                    <div class="wp-whatsapp-actions">
                        <button id="apply-all-templates" class="button">Aplicar Todas as Sugestões</button>
                        <span class="description">Use templates pré-definidos para todos os status</span>
                    </div>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('wp_whatsapp_sender_save_order_status_templates', 'wp_whatsapp_sender_order_status_nonce'); ?>
                        
                        <table class="status-templates widefat">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Mensagem</th>
                                    <th>Envio <br>Automático</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                            $templates = $this->get_order_status_templates();
                            $auto_send_status = get_option('wp_whatsapp_sender_auto_send_status', array());
                            
                            // Obter todos os status de pedido WooCommerce
                            $woo_statuses = wc_get_order_statuses();
                            
                            foreach ($woo_statuses as $status_key => $status_label):
                                // Remover o prefixo 'wc-' do status
                                $status_key = substr($status_key, 0, 3) === 'wc-' ? substr($status_key, 3) : $status_key;
                                $template = isset($templates[$status_key]) ? $templates[$status_key] : '';
                                $auto_send_checked = in_array($status_key, $auto_send_status) ? 'checked="checked"' : '';
                                
                                echo '<tr>';
                                echo '<td>' . esc_html($status_label) . '</td>';
                                echo '<td>';
                                echo '<textarea name="wp_whatsapp_sender_template_' . esc_attr($status_key) . '" class="large-text">' . esc_textarea($template) . '</textarea>';
                                echo '<p class="description">Você pode usar as seguintes variáveis: <code>{customer_name}</code>, <code>{order_id}</code>, <code>{order_total}</code>, <code>{order_items}</code>, <code>{payment_method}</code>, <code>{payment_url}</code>, <code>{shipping_method}</code>, <code>{coupon_used}</code></p>';
                                echo '<p class="description">Também suporta formato de chaves duplas: <code>{{customer_name}}</code>, <code>{{order_id}}</code>, etc.</p>';
                                echo '<a href="#" class="use-suggested-template" data-status="' . esc_attr($status_key) . '">Usar mensagem sugerida</a>';
                                echo '</td>';
                                echo '<td>';
                                echo '<label>';
                                echo '<input type="checkbox" name="wp_whatsapp_sender_auto_send_status[]" value="' . esc_attr($status_key) . '" ' . $auto_send_checked . '>';
                                echo '</label>';
                                echo '</td>';
                                echo '</tr>';
                            endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="wp_whatsapp_sender_save_order_status" class="button button-primary" value="Salvar Templates de Status">
                        </p>
                    </form>
                    
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        // Definir os templates padrão sugeridos
                        var templates = {
                            'pending': 'Olá {{customer_name}}, recebemos seu pedido #{{order_id}} no valor de {{order_total}}. Estamos aguardando a confirmação do pagamento para dar continuidade. Obrigado por comprar conosco!',
                            'processing': 'Olá {{customer_name}}, o pagamento do seu pedido #{{order_id}} foi aprovado e já estamos preparando tudo para o envio. Em breve você receberá atualizações sobre o envio. Obrigado pela confiança!',
                            'on-hold': 'Olá {{customer_name}}, seu pedido #{{order_id}} está temporariamente em espera. Entraremos em contato para resolver qualquer pendência. Para mais informações, entre em contato conosco.',
                            'completed': 'Olá {{customer_name}}, seu pedido #{{order_id}} foi concluído com sucesso! Esperamos que tenha gostado dos produtos. Agradecemos a preferência e estamos à disposição para o que precisar.',
                            'cancelled': 'Olá {{customer_name}}, informamos que seu pedido #{{order_id}} foi cancelado conforme solicitado. Esperamos ter a oportunidade de atendê-lo em breve!',
                            'refunded': 'Olá {{customer_name}}, confirmamos o reembolso do seu pedido #{{order_id}} no valor de {{order_total}}. O valor será creditado de acordo com a forma de pagamento utilizada. Estamos à disposição!',
                            'failed': 'Olá {{customer_name}}, identificamos um problema com o pagamento do seu pedido #{{order_id}}. Por favor, entre em contato conosco para resolvermos esta questão.',
                            'coupon-sent': 'Olá {{customer_name}}, como agradecimento pela sua fidelidade, estamos enviando um cupom de desconto para sua próxima compra: {{coupon_code}}. Este cupom oferece {{coupon_value}} de desconto e é válido até {{coupon_expiry}}. Aproveite!'
                        };
                        
                        // Manipula os links "Usar sugestão"
                        $('.use-suggested-template').on('click', function(e) {
                            e.preventDefault();
                            var status = $(this).data('status');
                            var textareaSelector = 'textarea[name="wp_whatsapp_sender_template_' + status + '"]';
                            
                            // Aplica o template sugerido ao textarea
                            if (templates[status]) {
                                $(textareaSelector).val(templates[status]);
                            } else {
                                // Template padrão genérico para status sem sugestão específica
                                $(textareaSelector).val('Olá {{customer_name}}, houve uma atualização no seu pedido #{{order_id}}: o status mudou para "' + status + '". Para mais informações, entre em contato conosco.');
                            }
                            
                            // Mostrar feedback visual
                            $(this).text('Sugestão aplicada');
                            setTimeout(function() {
                                $('.use-suggested-template').text('Usar mensagem sugerida');
                            }, 1500);
                        });
                        
                        // Manipula o botão "Aplicar todas as sugestões"
                        $('#apply-all-templates').on('click', function(e) {
                            e.preventDefault();
                            
                            // Para cada link de sugestão, aplica o template
                            $('.use-suggested-template').each(function() {
                                var status = $(this).data('status');
                                var textareaSelector = 'textarea[name="wp_whatsapp_sender_template_' + status + '"]';
                                
                                // Aplica o template sugerido ao textarea
                                if (templates[status]) {
                                    $(textareaSelector).val(templates[status]);
                                } else {
                                    // Template padrão genérico para status sem sugestão específica
                                    $(textareaSelector).val('Olá {{customer_name}}, houve uma atualização no seu pedido #{{order_id}}: o status mudou para "' + status + '". Para mais informações, entre em contato conosco.');
                                }
                                
                                // Atualiza o texto do link
                                $(this).text('Sugestão aplicada');
                            });
                            
                            // Restaura os textos dos links após um tempo
                            setTimeout(function() {
                                $('.use-suggested-template').text('Usar mensagem sugerida');
                            }, 1500);
                            
                            // Mostra mensagem de sucesso
                            $('<div class="notice notice-success is-dismissible"><p>Todas as sugestões foram aplicadas! Clique em "Salvar Templates de Status" para confirmar.</p></div>').insertAfter($(this).closest('.wp-whatsapp-actions')).hide().fadeIn(300);
                        });
                    });
                    </script>
                    
                    <style type="text/css">
                    /* Estilos removidos daqui, agora estão no arquivo admin.css */
                    </style>
                </div>
            <?php endif; ?>
            
        </div>
        <?php
    }

    /**
     * Carrega os scripts e estilos do admin.
     *
     * @param string $hook_suffix O hook atual da página de administração.
     */
    public function enqueue_scripts($hook_suffix) {
        // Verifica se estamos em uma página do nosso plugin
        if (strpos($hook_suffix, 'wp-whatsapp-sender') !== false) {
            // Registra e enfileira os estilos
            wp_register_style(
                'wp-whatsapp-sender-admin-css',
                WP_WHATSAPP_SENDER_PLUGIN_URL . 'admin/css/admin.css',
                array(),
                WP_WHATSAPP_SENDER_VERSION
            );
            wp_enqueue_style('wp-whatsapp-sender-admin-css');
            
            // Registra e enfileira os scripts
            wp_register_script(
                'wp-whatsapp-sender-admin-js',
                WP_WHATSAPP_SENDER_PLUGIN_URL . 'admin/js/admin.js',
                array('jquery'),
                WP_WHATSAPP_SENDER_VERSION,
                true
            );
            wp_enqueue_script('wp-whatsapp-sender-admin-js');
            
            // Adiciona dados para o script
            wp_localize_script(
                'wp-whatsapp-sender-admin-js',
                'wpWhatsAppSender',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('wp_whatsapp_sender_nonce')
                )
            );
        }
    }
    
    /**
     * Obtém os templates de status de pedido.
     *
     * @return array Array contendo os templates de status de pedido.
     */
    public function get_order_status_templates() {
        $templates = get_option('wp_whatsapp_sender_order_status_templates', array());
        
        // Inicializa os templates com valores vazios para status que não têm template
        if (class_exists('WooCommerce')) {
            $statuses = wc_get_order_statuses();
            foreach ($statuses as $status => $label) {
                $status_key = substr($status, 0, 3) === 'wc-' ? substr($status, 3) : $status;
                if (!isset($templates[$status_key])) {
                    $templates[$status_key] = '';
                }
            }
        }
        
        return $templates;
    }
} 