<?php
/**
 * Classe para envio em massa de mensagens WhatsApp.
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

class WP_WhatsApp_Sender_Bulk {

    /**
     * Inicializa a classe.
     */
    public function __construct() {
        // Adiciona a página de submenu
        add_action('admin_menu', array($this, 'add_bulk_menu'));
        
        // Processa o envio em massa
        add_action('admin_post_wp_whatsapp_bulk_send', array($this, 'process_bulk_send'));
    }

    /**
     * Adiciona o menu de envio em massa.
     */
    public function add_bulk_menu() {
        // Adiciona a página de submenu
        add_submenu_page(
            'wp-whatsapp-sender',  // Slug do menu pai
            'Envio em Massa',      // Título da página
            'Envio em Massa',      // Texto do menu
            'manage_options',      // Capacidade necessária
            'wp-whatsapp-sender-bulk', // Slug do menu
            array($this, 'display_bulk_page') // Callback para renderizar a página
        );
        
        // Adiciona regra de reescrita para a página de envio em massa
        add_action('admin_init', array($this, 'add_rewrite_rules'));
        
        // Corrige URLs incorretas
        add_action('admin_init', array($this, 'redirect_bulk_page'));
    }

    /**
     * Adiciona regras de reescrita para a página de envio em massa.
     */
    public function add_rewrite_rules() {
        global $wp_rewrite;
        flush_rewrite_rules();
    }

    /**
     * Redireciona URLs incorretas para a página de envio em massa.
     */
    public function redirect_bulk_page() {
        global $pagenow;
        
        // Verifica se estamos na URL incorreta que está causando o erro 404
        if ($pagenow === 'wp-whatsapp-sender-bulk' || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-admin/wp-whatsapp-sender-bulk') !== false)) {
            // Redireciona para a URL correta
            wp_redirect(admin_url('admin.php?page=wp-whatsapp-sender-bulk'));
            exit;
        }
    }

    /**
     * Exibe a página de envio em massa.
     */
    public function display_bulk_page() {
        // Verifica se a API está configurada
        $api_configured = WP_WhatsApp_Sender_Utils::is_api_configured();
        
        // Obtém os templates disponíveis
        $templates = array();
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $option => $value) {
            if (strpos($option, 'wp_whatsapp_sender_template_') === 0) {
                $template_name = str_replace('wp_whatsapp_sender_template_', '', $option);
                $templates[$template_name] = $value;
            }
        }
        
        // Obtém os grupos de usuários disponíveis
        $user_roles = array();
        $editable_roles = get_editable_roles();
        foreach ($editable_roles as $role => $details) {
            $user_roles[$role] = translate_user_role($details['name']);
        }
        
        // Mensagem de resultado (após o envio)
        $success_message = isset($_GET['success']) ? intval($_GET['success']) : 0;
        $error_message = isset($_GET['error']) ? intval($_GET['error']) : 0;
        $total_sent = isset($_GET['total']) ? intval($_GET['total']) : 0;
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!$api_configured): ?>
                <div class="notice notice-error">
                    <p>A API do WhatsApp não está configurada corretamente. <a href="<?php echo admin_url('admin.php?page=wp-whatsapp-sender'); ?>">Configure a API</a> antes de enviar mensagens.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message > 0 || $error_message > 0): ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        Processamento concluído. 
                        <?php echo $success_message; ?> mensagens enviadas com sucesso.
                        <?php if ($error_message > 0): ?>
                            <?php echo $error_message; ?> mensagens não puderam ser enviadas.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Enviar Mensagem em Massa</h2>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="wp-whatsapp-sender-bulk-form">
                    <input type="hidden" name="action" value="wp_whatsapp_bulk_send">
                    <?php wp_nonce_field('wp_whatsapp_bulk_send', 'wp_whatsapp_bulk_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wp_whatsapp_target_type">Enviar para:</label>
                            </th>
                            <td>
                                <select id="wp_whatsapp_target_type" name="wp_whatsapp_target_type" class="regular-text">
                                    <option value="all_users">Todos os usuários</option>
                                    <option value="user_role">Grupo específico de usuários</option>
                                    <?php if (class_exists('WooCommerce')): ?>
                                        <option value="wc_customers">Clientes do WooCommerce</option>
                                        <option value="wc_orders">Pedidos recentes</option>
                                    <?php endif; ?>
                                    <option value="custom_list">Lista personalizada</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr id="wp_whatsapp_role_row" style="display: none;">
                            <th scope="row">
                                <label for="wp_whatsapp_user_role">Grupo de usuários:</label>
                            </th>
                            <td>
                                <select id="wp_whatsapp_user_role" name="wp_whatsapp_user_role" class="regular-text">
                                    <?php foreach ($user_roles as $role => $name): ?>
                                        <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <?php if (class_exists('WooCommerce')): ?>
                            <tr id="wp_whatsapp_orders_row" style="display: none;">
                                <th scope="row">
                                    <label for="wp_whatsapp_orders_days">Pedidos dos últimos dias:</label>
                                </th>
                                <td>
                                    <input type="number" id="wp_whatsapp_orders_days" name="wp_whatsapp_orders_days" class="small-text" value="30" min="1" max="365">
                                    <p class="description">Enviar para clientes que fizeram pedidos nos últimos X dias.</p>
                                </td>
                            </tr>
                            
                            <tr id="wp_whatsapp_orders_status_row" style="display: none;">
                                <th scope="row">
                                    <label>Status do pedido:</label>
                                </th>
                                <td>
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    foreach ($order_statuses as $status => $label):
                                        $status = str_replace('wc-', '', $status);
                                    ?>
                                        <label style="display: inline-block; margin-right: 15px;">
                                            <input type="checkbox" name="wp_whatsapp_order_status[]" value="<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr id="wp_whatsapp_custom_list_row" style="display: none;">
                            <th scope="row">
                                <label for="wp_whatsapp_custom_list">Lista de números:</label>
                            </th>
                            <td>
                                <textarea id="wp_whatsapp_custom_list" name="wp_whatsapp_custom_list" class="large-text" rows="5" placeholder="Um número por linha. Ex: 5511999999999"></textarea>
                                <p class="description">Insira um número de telefone por linha, no formato internacional (ex: 5511999999999).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="wp_whatsapp_message_type">Tipo de mensagem:</label>
                            </th>
                            <td>
                                <select id="wp_whatsapp_message_type" name="wp_whatsapp_message_type" class="regular-text">
                                    <option value="custom">Personalizada</option>
                                    <?php if (!empty($templates)): ?>
                                        <option value="template">Template</option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <?php if (!empty($templates)): ?>
                            <tr id="wp_whatsapp_template_row" style="display: none;">
                                <th scope="row">
                                    <label for="wp_whatsapp_template">Template:</label>
                                </th>
                                <td>
                                    <select id="wp_whatsapp_template" name="wp_whatsapp_template" class="regular-text">
                                        <?php foreach ($templates as $name => $content): ?>
                                            <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <tr id="wp_whatsapp_message_row">
                            <th scope="row">
                                <label for="wp_whatsapp_message">Mensagem:</label>
                            </th>
                            <td>
                                <textarea id="wp_whatsapp_message" name="wp_whatsapp_message" class="large-text" rows="5" required></textarea>
                                <p class="description">
                                    Você pode usar as seguintes variáveis: {{first_name}}, {{last_name}}, {{email}}, {{website}}, {{coupon_code}}, {{coupon_value}}, {{coupon_expiry}}
                                    <?php if (class_exists('WooCommerce')): ?>
                                        , {{order_count}}, {{total_spent}}
                                    <?php endif; ?>
                                </p>
                                <p class="description"><span id="bulk-char-count">0</span> caracteres (limite do WhatsApp: 4096 caracteres)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="wp_whatsapp_delay">Intervalo entre envios:</label>
                            </th>
                            <td>
                                <input type="number" id="wp_whatsapp_delay" name="wp_whatsapp_delay" class="small-text" value="5" min="1" max="60">
                                <p class="description">Intervalo em segundos entre o envio de mensagens para evitar bloqueios. Recomendamos 5 segundos para maior segurança.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="wp-whatsapp-sender-preview">
                        <h3>Pré-visualização</h3>
                        <div id="wp_whatsapp_preview" class="wp-whatsapp-sender-card">
                            <p class="description">A mensagem será exibida aqui.</p>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="wp_whatsapp_bulk_send" class="button button-primary" value="Enviar Mensagens" <?php echo $api_configured ? '' : 'disabled'; ?>>
                    </p>
                </form>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Mostra/esconde campos com base no tipo de destino
                $('#wp_whatsapp_target_type').on('change', function() {
                    var targetType = $(this).val();
                    
                    // Esconde todos os campos específicos
                    $('#wp_whatsapp_role_row, #wp_whatsapp_orders_row, #wp_whatsapp_orders_status_row, #wp_whatsapp_custom_list_row').hide();
                    
                    // Mostra os campos específicos para o tipo selecionado
                    if (targetType === 'user_role') {
                        $('#wp_whatsapp_role_row').show();
                    } else if (targetType === 'wc_orders') {
                        $('#wp_whatsapp_orders_row, #wp_whatsapp_orders_status_row').show();
                    } else if (targetType === 'custom_list') {
                        $('#wp_whatsapp_custom_list_row').show();
                    }
                });
                
                // Mostra/esconde campos com base no tipo de mensagem
                $('#wp_whatsapp_message_type').on('change', function() {
                    var messageType = $(this).val();
                    
                    if (messageType === 'template') {
                        $('#wp_whatsapp_template_row').show();
                        $('#wp_whatsapp_message_row').hide();
                        
                        // Carrega o conteúdo do template selecionado
                        var templateName = $('#wp_whatsapp_template').val();
                        loadTemplateContent(templateName);
                    } else {
                        $('#wp_whatsapp_template_row').hide();
                        $('#wp_whatsapp_message_row').show();
                    }
                    
                    updatePreview();
                });
                
                // Carrega o conteúdo do template quando o template é alterado
                $('#wp_whatsapp_template').on('change', function() {
                    var templateName = $(this).val();
                    loadTemplateContent(templateName);
                });
                
                // Atualiza a pré-visualização quando a mensagem é alterada
                $('#wp_whatsapp_message').on('input', function() {
                    updatePreview();
                    
                    // Contador de caracteres
                    var charCount = $(this).val().length;
                    $('#bulk-char-count').text(charCount);
                    
                    // Alerta visual se exceder o limite
                    if (charCount > 4096) {
                        $('#bulk-char-count').css('color', 'red');
                    } else {
                        $('#bulk-char-count').css('color', '');
                    }
                });
                
                // Função para carregar o conteúdo do template via AJAX
                function loadTemplateContent(templateName) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_whatsapp_sender_get_template',
                            template_name: templateName,
                            security: '<?php echo wp_create_nonce('wp_whatsapp_sender_get_template'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#wp_whatsapp_message').val(response.data);
                                updatePreview();
                            }
                        }
                    });
                }
                
                // Função para atualizar a pré-visualização
                function updatePreview() {
                    var message = $('#wp_whatsapp_message').val();
                    
                    // Substitui variáveis de exemplo
                    message = message.replace(/\{\{first_name\}\}/g, 'João');
                    message = message.replace(/\{\{last_name\}\}/g, 'Silva');
                    message = message.replace(/\{\{email\}\}/g, 'joao.silva@email.com');
                    message = message.replace(/\{\{website\}\}/g, 'www.exemplo.com');
                    message = message.replace(/\{\{order_count\}\}/g, '3');
                    message = message.replace(/\{\{total_spent\}\}/g, 'R$ 450,00');
                    message = message.replace(/\{\{coupon_code\}\}/g, 'DESC20');
                    message = message.replace(/\{\{coupon_value\}\}/g, '20%');
                    message = message.replace(/\{\{coupon_expiry\}\}/g, '31/12/2023');
                    
                    // Atualiza a pré-visualização
                    $('#wp_whatsapp_preview').html(message.replace(/\n/g, '<br>'));
                }
            });
        </script>
        <?php
    }

    /**
     * Processa o envio em massa.
     */
    public function process_bulk_send() {
        // Verifica o nonce
        check_admin_referer('wp_whatsapp_bulk_send', 'wp_whatsapp_bulk_nonce');
        
        // Verifica as permissões
        if (!current_user_can('manage_options')) {
            wp_die('Você não tem permissão para realizar esta ação.');
        }
        
        // Verifica se a API está configurada
        if (!WP_WhatsApp_Sender_Utils::is_api_configured()) {
            wp_redirect(add_query_arg(array('page' => 'wp-whatsapp-sender-bulk'), admin_url('admin.php')));
            exit;
        }
        
        // Obtém os dados do formulário
        $target_type = isset($_POST['wp_whatsapp_target_type']) ? sanitize_text_field($_POST['wp_whatsapp_target_type']) : '';
        $user_role = isset($_POST['wp_whatsapp_user_role']) ? sanitize_text_field($_POST['wp_whatsapp_user_role']) : '';
        $orders_days = isset($_POST['wp_whatsapp_orders_days']) ? intval($_POST['wp_whatsapp_orders_days']) : 30;
        $order_statuses = isset($_POST['wp_whatsapp_order_status']) ? array_map('sanitize_text_field', $_POST['wp_whatsapp_order_status']) : array();
        $custom_list = isset($_POST['wp_whatsapp_custom_list']) ? sanitize_textarea_field($_POST['wp_whatsapp_custom_list']) : '';
        $message_type = isset($_POST['wp_whatsapp_message_type']) ? sanitize_text_field($_POST['wp_whatsapp_message_type']) : '';
        $template = isset($_POST['wp_whatsapp_template']) ? sanitize_text_field($_POST['wp_whatsapp_template']) : '';
        $message = isset($_POST['wp_whatsapp_message']) ? sanitize_textarea_field($_POST['wp_whatsapp_message']) : '';
        $delay = isset($_POST['wp_whatsapp_delay']) ? intval($_POST['wp_whatsapp_delay']) : 5;
        
        // Obtém a lista de destinatários com base no tipo selecionado
        $recipients = $this->get_recipients($target_type, $user_role, $orders_days, $order_statuses, $custom_list);
        
        // Se for um template, obtém o conteúdo do template
        if ($message_type === 'template' && !empty($template)) {
            $template_content = get_option('wp_whatsapp_sender_template_' . $template, '');
            if (!empty($template_content)) {
                $message = $template_content;
            }
        }
        
        // Validações
        if (empty($message)) {
            wp_redirect(add_query_arg(array(
                'page' => 'wp-whatsapp-sender-bulk',
                'error' => 'empty_message'
            ), admin_url('admin.php')));
            exit;
        }
        
        if (empty($recipients)) {
            wp_redirect(add_query_arg(array(
                'page' => 'wp-whatsapp-sender-bulk',
                'error' => 'no_recipients'
            ), admin_url('admin.php')));
            exit;
        }
        
        // Envia as mensagens
        $success_count = 0;
        $error_count = 0;
        
        foreach ($recipients as $recipient) {
            // Processa as variáveis na mensagem
            $personalized_message = $this->personalize_message($message, $recipient);
            
            // Envia a mensagem
            $result = WP_WhatsApp_Sender_API::send_message($recipient['phone'], $personalized_message);
            
            if (is_wp_error($result)) {
                $error_count++;
            } else {
                $success_count++;
            }
            
            // Adiciona um atraso para evitar bloqueios
            if ($delay > 0) {
                sleep($delay);
            }
        }
        
        // Redireciona de volta com os resultados
        wp_redirect(add_query_arg(array(
            'page' => 'wp-whatsapp-sender-bulk',
            'success' => $success_count,
            'error' => $error_count,
            'total' => count($recipients)
        ), admin_url('admin.php')));
        exit;
    }

    /**
     * Obtém a lista de destinatários com base no tipo selecionado.
     *
     * @param string $target_type Tipo de destino.
     * @param string $user_role Grupo de usuários.
     * @param int $orders_days Dias para filtrar pedidos.
     * @param array $order_statuses Status dos pedidos.
     * @param string $custom_list Lista personalizada.
     * @return array Lista de destinatários.
     */
    private function get_recipients($target_type, $user_role, $orders_days, $order_statuses, $custom_list) {
        $recipients = array();
        
        switch ($target_type) {
            case 'all_users':
                // Todos os usuários com número de telefone
                $users = get_users(array('fields' => array('ID', 'user_email', 'display_name')));
                foreach ($users as $user) {
                    $phone = get_user_meta($user->ID, 'phone', true);
                    if (empty($phone)) {
                        $phone = get_user_meta($user->ID, 'billing_phone', true);
                    }
                    
                    if (!empty($phone)) {
                        $recipients[] = array(
                            'phone' => $phone,
                            'first_name' => get_user_meta($user->ID, 'first_name', true),
                            'last_name' => get_user_meta($user->ID, 'last_name', true),
                            'email' => $user->user_email,
                            'display_name' => $user->display_name,
                            'user_id' => $user->ID
                        );
                    }
                }
                break;
                
            case 'user_role':
                // Usuários de um grupo específico
                $users = get_users(array('role' => $user_role, 'fields' => array('ID', 'user_email', 'display_name')));
                foreach ($users as $user) {
                    $phone = get_user_meta($user->ID, 'phone', true);
                    if (empty($phone)) {
                        $phone = get_user_meta($user->ID, 'billing_phone', true);
                    }
                    
                    if (!empty($phone)) {
                        $recipients[] = array(
                            'phone' => $phone,
                            'first_name' => get_user_meta($user->ID, 'first_name', true),
                            'last_name' => get_user_meta($user->ID, 'last_name', true),
                            'email' => $user->user_email,
                            'display_name' => $user->display_name,
                            'user_id' => $user->ID
                        );
                    }
                }
                break;
                
            case 'wc_customers':
                // Clientes do WooCommerce
                if (class_exists('WooCommerce')) {
                    $customers = get_users(array('role' => 'customer', 'fields' => array('ID', 'user_email', 'display_name')));
                    foreach ($customers as $customer) {
                        $phone = get_user_meta($customer->ID, 'billing_phone', true);
                        
                        if (!empty($phone)) {
                            $recipients[] = array(
                                'phone' => $phone,
                                'first_name' => get_user_meta($customer->ID, 'billing_first_name', true),
                                'last_name' => get_user_meta($customer->ID, 'billing_last_name', true),
                                'email' => $customer->user_email,
                                'display_name' => $customer->display_name,
                                'user_id' => $customer->ID,
                                'order_count' => wc_get_customer_order_count($customer->ID),
                                'total_spent' => wc_price(wc_get_customer_total_spent($customer->ID), array('html' => false))
                            );
                        }
                    }
                }
                break;
                
            case 'wc_orders':
                // Pedidos recentes do WooCommerce
                if (class_exists('WooCommerce')) {
                    // Define data limite para filtrar pedidos
                    $date_limit = date('Y-m-d', strtotime('-' . $orders_days . ' days'));
                    
                    // Prepara os argumentos para consulta de pedidos
                    $args = array(
                        'date_created' => '>' . $date_limit,
                        'limit' => -1,
                        'return' => 'ids'
                    );
                    
                    // Adiciona filtro por status se especificado
                    if (!empty($order_statuses)) {
                        $args['status'] = $order_statuses;
                    }
                    
                    // Obtém os pedidos
                    $order_ids = wc_get_orders($args);
                    
                    // Processa cada pedido
                    $processed_phones = array(); // Para evitar duplicatas
                    
                    foreach ($order_ids as $order_id) {
                        $order = wc_get_order($order_id);
                        if (!$order) {
                            continue;
                        }
                        
                        $phone = $order->get_billing_phone();
                        
                        // Evita duplicatas
                        if (!empty($phone) && !in_array($phone, $processed_phones)) {
                            $processed_phones[] = $phone;
                            
                            $customer_id = $order->get_customer_id();
                            $order_count = 1;
                            $total_spent = $order->get_total();
                            
                            // Se tiver ID de cliente, obtém dados adicionais
                            if ($customer_id) {
                                $order_count = wc_get_customer_order_count($customer_id);
                                $total_spent = wc_get_customer_total_spent($customer_id);
                            }
                            
                            $recipients[] = array(
                                'phone' => $phone,
                                'first_name' => $order->get_billing_first_name(),
                                'last_name' => $order->get_billing_last_name(),
                                'email' => $order->get_billing_email(),
                                'display_name' => $order->get_formatted_billing_full_name(),
                                'user_id' => $customer_id,
                                'order_id' => $order_id,
                                'order_count' => $order_count,
                                'total_spent' => wc_price($total_spent, array('html' => false))
                            );
                        }
                    }
                }
                break;
                
            case 'custom_list':
                // Lista personalizada de números
                if (!empty($custom_list)) {
                    $phone_numbers = explode("\n", str_replace("\r", "", $custom_list));
                    foreach ($phone_numbers as $phone) {
                        $phone = trim($phone);
                        if (!empty($phone)) {
                            $recipients[] = array(
                                'phone' => $phone,
                                'first_name' => '',
                                'last_name' => '',
                                'email' => '',
                                'display_name' => ''
                            );
                        }
                    }
                }
                break;
        }
        
        return $recipients;
    }

    /**
     * Personaliza a mensagem com base nos dados do destinatário.
     *
     * @param string $message A mensagem original.
     * @param array $recipient Os dados do destinatário.
     * @return string A mensagem personalizada.
     */
    private function personalize_message($message, $recipient) {
        $variables = array(
            '{{first_name}}' => isset($recipient['first_name']) ? $recipient['first_name'] : '',
            '{{last_name}}' => isset($recipient['last_name']) ? $recipient['last_name'] : '',
            '{{email}}' => isset($recipient['email']) ? $recipient['email'] : '',
            '{{display_name}}' => isset($recipient['display_name']) ? $recipient['display_name'] : '',
            '{{website}}' => get_bloginfo('name')
        );
        
        // Adiciona variáveis específicas para WooCommerce
        if (isset($recipient['order_count'])) {
            $variables['{{order_count}}'] = $recipient['order_count'];
        }
        
        if (isset($recipient['total_spent'])) {
            $variables['{{total_spent}}'] = $recipient['total_spent'];
        }
        
        // Substitui as variáveis na mensagem
        return str_replace(array_keys($variables), array_values($variables), $message);
    }
} 