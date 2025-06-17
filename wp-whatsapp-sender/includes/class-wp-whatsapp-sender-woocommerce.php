<?php
/**
 * Integração com o WooCommerce.
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

class WP_WhatsApp_Sender_WooCommerce {

    /**
     * Inicializa a integração com o WooCommerce.
     */
    public static function init() {
        // Verifica se o WooCommerce está ativo
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Adiciona botão de envio de WhatsApp na página de pedidos
        add_action('woocommerce_admin_order_data_after_order_details', array(__CLASS__, 'add_whatsapp_button'));
        
        // Adiciona metabox na página de pedido
        add_action('add_meta_boxes', array(__CLASS__, 'add_order_whatsapp_metabox'));
        
        // Processa o envio de mensagem
        add_action('wp_ajax_wp_whatsapp_sender_send_to_customer', array(__CLASS__, 'ajax_send_to_customer'));

        // Adiciona o envio de WhatsApp nas ações em massa
        add_filter('bulk_actions-edit-shop_order', array(__CLASS__, 'add_bulk_actions'));
        
        // Processa as ações em massa
        add_filter('handle_bulk_actions-edit-shop_order', array(__CLASS__, 'handle_bulk_actions'), 10, 3);
        
        // Adiciona notificações após as ações em massa
        add_action('admin_notices', array(__CLASS__, 'bulk_action_admin_notice'));

        // Adiciona a meta box nos pedidos do WooCommerce
        add_action('add_meta_boxes', array(__CLASS__, 'add_order_meta_box'));
        
        // Processa o envio de mensagem para o pedido
        add_action('wp_ajax_wp_whatsapp_sender_send_order_message', array(__CLASS__, 'ajax_send_order_message'));
        
        // Adiciona ação para enviar mensagem automaticamente quando o status do pedido é alterado
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'send_status_message'), 10, 4);
    }

    /**
     * Adiciona botão de WhatsApp na página de detalhes do pedido.
     *
     * @param WC_Order $order O objeto do pedido.
     */
    public static function add_whatsapp_button($order) {
        // Verifica se a API está configurada
        if (!WP_WhatsApp_Sender_Utils::is_api_configured()) {
            return;
        }

        // Obtém o telefone do cliente
        $phone = $order->get_billing_phone();
        
        if (empty($phone)) {
            return;
        }

        echo '<p class="form-field form-field-wide">';
        echo '<a href="#" class="button button-primary send-whatsapp-message" data-order-id="' . esc_attr($order->get_id()) . '" data-phone="' . esc_attr($phone) . '">Enviar WhatsApp</a>';
        echo '</p>';
        
        // Adiciona o script inline
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.send-whatsapp-message').on('click', function(e) {
                    e.preventDefault();
                    var orderId = $(this).data('order-id');
                    var phone = $(this).data('phone');
                    
                    // Abre a modal de mensagem
                    var message = prompt('Digite a mensagem para enviar via WhatsApp:', '');
                    
                    if (message !== null && message !== '') {
                        // Envia a mensagem via AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wp_whatsapp_sender_send_to_customer',
                                order_id: orderId,
                                phone: phone,
                                message: message,
                                security: '<?php echo wp_create_nonce('wp_whatsapp_sender_send_message'); ?>'
                            },
                            beforeSend: function() {
                                $(this).text('Enviando...').prop('disabled', true);
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('Mensagem enviada com sucesso!');
                                } else {
                                    alert('Erro ao enviar mensagem: ' + response.data);
                                }
                            },
                            error: function() {
                                alert('Erro ao processar a solicitação.');
                            },
                            complete: function() {
                                $(this).text('Enviar WhatsApp').prop('disabled', false);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Adiciona metabox de WhatsApp na página de pedido.
     */
    public static function add_order_whatsapp_metabox() {
        // Verifica se a API está configurada
        if (!WP_WhatsApp_Sender_Utils::is_api_configured()) {
            return;
        }
        
        add_meta_box(
            'wp_whatsapp_sender_metabox',
            'Enviar WhatsApp',
            array(__CLASS__, 'render_order_whatsapp_metabox'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Renderiza o conteúdo do metabox de WhatsApp.
     *
     * @param WP_Post $post O objeto post atual.
     */
    public static function render_order_whatsapp_metabox($post) {
        // Obtém o pedido
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        // Obtém o telefone do cliente
        $phone = $order->get_billing_phone();
        $customer_name = $order->get_billing_first_name();
        
        if (empty($phone)) {
            echo '<p>Cliente não possui telefone cadastrado.</p>';
            return;
        }
        
        // Obtém os templates disponíveis
        $templates = array();
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $option => $value) {
            if (strpos($option, 'wp_whatsapp_sender_template_') === 0) {
                $template_name = str_replace('wp_whatsapp_sender_template_', '', $option);
                $templates[$template_name] = $value;
            }
        }
        
        ?>
        <div class="wp-whatsapp-sender-order-metabox">
            <p>
                <strong>Telefone do Cliente:</strong> <?php echo esc_html($phone); ?>
            </p>
            
            <?php if (!empty($templates)): ?>
                <p>
                    <label for="wp_whatsapp_sender_template">Template:</label>
                    <select id="wp_whatsapp_sender_template" class="widefat">
                        <option value="">Mensagem personalizada</option>
                        <?php foreach ($templates as $name => $content): ?>
                            <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            <?php endif; ?>
            
            <p>
                <label for="wp_whatsapp_sender_message">Mensagem:</label>
                <textarea id="wp_whatsapp_sender_message" class="widefat" rows="4"></textarea>
            </p>
            
            <p>
                <button type="button" class="button button-primary send-whatsapp" data-order-id="<?php echo esc_attr($order->get_id()); ?>" data-phone="<?php echo esc_attr($phone); ?>">Enviar Mensagem</button>
            </p>
            
            <div id="wp_whatsapp_sender_result"></div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Carrega template quando selecionado
                $('#wp_whatsapp_sender_template').on('change', function() {
                    var templateName = $(this).val();
                    if (templateName === '') {
                        $('#wp_whatsapp_sender_message').val('').prop('disabled', false);
                        return;
                    }
                    
                    // Carrega o conteúdo do template via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_whatsapp_sender_get_template',
                            template_name: templateName,
                            order_id: '<?php echo esc_js($order->get_id()); ?>',
                            security: '<?php echo wp_create_nonce('wp_whatsapp_sender_get_template'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#wp_whatsapp_sender_message').val(response.data);
                            }
                        }
                    });
                });
                
                // Envio da mensagem
                $('.send-whatsapp').on('click', function() {
                    var orderId = $(this).data('order-id');
                    var phone = $(this).data('phone');
                    var message = $('#wp_whatsapp_sender_message').val();
                    var templateName = $('#wp_whatsapp_sender_template').val();
                    
                    if (message === '') {
                        alert('Por favor, digite uma mensagem.');
                        return;
                    }
                    
                    // Envia a mensagem via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_whatsapp_sender_send_to_customer',
                            order_id: orderId,
                            phone: phone,
                            message: message,
                            template_name: templateName,
                            security: '<?php echo wp_create_nonce('wp_whatsapp_sender_send_message'); ?>'
                        },
                        beforeSend: function() {
                            $('.send-whatsapp').text('Enviando...').prop('disabled', true);
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#wp_whatsapp_sender_result').html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            } else {
                                $('#wp_whatsapp_sender_result').html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                            }
                        },
                        error: function() {
                            $('#wp_whatsapp_sender_result').html('<div class="notice notice-error inline"><p>Erro ao processar a solicitação.</p></div>');
                        },
                        complete: function() {
                            $('.send-whatsapp').text('Enviar Mensagem').prop('disabled', false);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Processa o envio de mensagem via AJAX.
     */
    public static function ajax_send_to_customer() {
        // Verifica o nonce
        check_ajax_referer('wp_whatsapp_sender_send_message', 'security');
        
        // Verifica as permissões
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Você não tem permissão para realizar esta ação.');
        }
        
        // Obtém os dados
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
        
        if (empty($phone) || empty($message)) {
            wp_send_json_error('Telefone ou mensagem não informados.');
        }
        
        // Processa variáveis do pedido no texto, se necessário
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $message = self::process_order_variables($message, $order);
            }
        }
        
        // Envia a mensagem
        $result = WP_WhatsApp_Sender_API::send_message($phone, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Registra o envio no pedido
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->add_order_note(
                        sprintf(
                            'Mensagem de WhatsApp enviada: %s',
                            esc_html($message)
                        )
                    );
                }
            }
            
            wp_send_json_success('Mensagem enviada com sucesso!');
        }
    }

    /**
     * Processa as variáveis do pedido na mensagem.
     *
     * @param string $message A mensagem a ser processada.
     * @param WC_Order $order O objeto do pedido.
     * @return string A mensagem processada.
     */
    public static function process_order_variables($message, $order) {
        // Dados de substituição para as variáveis
        $replacement_data = array(
            'order_id' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name(),
            'total' => wc_price($order->get_total(), array('html' => false)),
            'order_total' => wc_price($order->get_total(), array('html' => false)),
            'date' => $order->get_date_created()->date_i18n(get_option('date_format')),
            'shop_name' => get_bloginfo('name'),
            'payment_method' => $order->get_payment_method_title(),
            'shipping_method' => $order->get_shipping_method(),
            'order_items' => self::get_order_items_formatted($order)
        );
        
        // Obter o URL de pagamento se disponível
        if ($order->needs_payment()) {
            $payment_url = $order->get_checkout_payment_url();
            $replacement_data['payment_url'] = $payment_url;
        } else {
            $replacement_data['payment_url'] = '';
        }
        
        // Obter cupons utilizados
        $coupon_codes = $order->get_coupon_codes();
        if (!empty($coupon_codes)) {
            $replacement_data['coupon_used'] = implode(', ', $coupon_codes);
        } else {
            $replacement_data['coupon_used'] = 'Nenhum';
        }

        // Preparar arrays para substituição
        $variables_simple = array();  // Para formato {variable}
        $variables_double = array();  // Para formato {{variable}}
        $replacements = array();

        foreach ($replacement_data as $key => $value) {
            $variables_simple['{' . $key . '}'] = $value;
            $variables_double['{{' . $key . '}}'] = $value;
            $replacements[] = $value;
        }
        
        // Registrar o processamento no log
        WP_WhatsApp_Sender_Utils::log('Processando variáveis na mensagem para o pedido #' . $order->get_order_number());
        WP_WhatsApp_Sender_Utils::log('Mensagem original: ' . $message);
        
        // Substituir as variáveis (primeiro formato {{var}})
        $message = str_replace(array_keys($variables_double), array_values($variables_double), $message);
        
        // Substituir as variáveis (segundo formato {var})
        $message = str_replace(array_keys($variables_simple), array_values($variables_simple), $message);
        
        // Remover qualquer HTML remanescente dos valores formatados
        $message = strip_tags($message);

        // Substituir entidades HTML por seus caracteres correspondentes
        $message = html_entity_decode($message);
        
        WP_WhatsApp_Sender_Utils::log('Mensagem processada: ' . $message);
        
        return $message;
    }
    
    /**
     * Formata os itens do pedido como texto.
     *
     * @param WC_Order $order O objeto do pedido.
     * @return string Texto formatado com os itens do pedido.
     */
    private static function get_order_items_formatted($order) {
        $items_text = '';
        
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $total = wc_price($order->get_line_subtotal($item), array('html' => false));
            
            $items_text .= "- {$quantity}x {$product_name}: {$total}\n";
        }
        
        return trim($items_text);
    }

    /**
     * Adiciona opções de envio de WhatsApp nas ações em massa.
     *
     * @param array $actions As ações existentes.
     * @return array As ações atualizadas.
     */
    public static function add_bulk_actions($actions) {
        // Verifica se a API está configurada
        if (!WP_WhatsApp_Sender_Utils::is_api_configured()) {
            return $actions;
        }
        
        // Obtém os templates disponíveis
        $templates = array();
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $option => $value) {
            if (strpos($option, 'wp_whatsapp_sender_template_') === 0) {
                $template_name = str_replace('wp_whatsapp_sender_template_', '', $option);
                $templates[$template_name] = $value;
            }
        }
        
        // Adiciona ação para cada template disponível
        if (!empty($templates)) {
            foreach ($templates as $name => $content) {
                $actions['wp_whatsapp_' . $name] = 'Enviar WhatsApp: ' . $name;
            }
        }
        
        // Adiciona ação personalizada
        $actions['wp_whatsapp_custom'] = 'Enviar WhatsApp personalizado';
        
        return $actions;
    }

    /**
     * Processa as ações em massa.
     *
     * @param string $redirect_to URL de redirecionamento.
     * @param string $action A ação atual.
     * @param array $post_ids Os IDs dos posts selecionados.
     * @return string URL de redirecionamento atualizada.
     */
    public static function handle_bulk_actions($redirect_to, $action, $post_ids) {
        // Verifica se é uma ação de WhatsApp
        if (strpos($action, 'wp_whatsapp_') !== 0) {
            return $redirect_to;
        }
        
        // Extrai o nome do template da ação
        $template_name = str_replace('wp_whatsapp_', '', $action);
        
        // Ação personalizada
        if ($template_name === 'custom') {
            // Neste caso, o admin precisa digitar a mensagem manualmente
            // Adicionamos parâmetros para mostrar uma notificação
            return add_query_arg(array(
                'wp_whatsapp_bulk_action' => 'custom',
                'wp_whatsapp_processed' => count($post_ids),
                'wp_whatsapp_template' => 'custom'
            ), $redirect_to);
        }
        
        // Obtém o conteúdo do template
        $template_content = get_option('wp_whatsapp_sender_template_' . $template_name, '');
        
        if (empty($template_content)) {
            return $redirect_to;
        }
        
        // Contador de mensagens enviadas com sucesso
        $success_count = 0;
        
        // Processa cada pedido
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if (!$order) {
                continue;
            }
            
            // Obtém o telefone do cliente
            $phone = $order->get_billing_phone();
            if (empty($phone)) {
                continue;
            }
            
            // Processa as variáveis do pedido no texto
            $message = self::process_order_variables($template_content, $order);
            
            // Envia a mensagem
            $result = WP_WhatsApp_Sender_API::send_message($phone, $message);
            
            if (!is_wp_error($result)) {
                $success_count++;
                
                // Registra o envio no pedido
                $order->add_order_note(
                    sprintf(
                        'Mensagem de WhatsApp enviada (ação em massa - template: %s): %s',
                        esc_html($template_name),
                        esc_html($message)
                    )
                );
            }
        }
        
        // Adiciona parâmetros para mostrar uma notificação
        return add_query_arg(array(
            'wp_whatsapp_bulk_action' => 'processed',
            'wp_whatsapp_processed' => $success_count,
            'wp_whatsapp_template' => $template_name
        ), $redirect_to);
    }

    /**
     * Exibe notificação após o processamento das ações em massa.
     */
    public static function bulk_action_admin_notice() {
        if (empty($_REQUEST['wp_whatsapp_bulk_action'])) {
            return;
        }
        
        $action = $_REQUEST['wp_whatsapp_bulk_action'];
        
        // Notificação para ação personalizada
        if ($action === 'custom') {
            $processed = intval($_REQUEST['wp_whatsapp_processed']);
            ?>
            <div class="notice notice-info is-dismissible">
                <p>Para enviar mensagens personalizadas via WhatsApp para <?php echo esc_html($processed); ?> pedidos, use a página de envio em massa.</p>
                <p><a href="<?php echo admin_url('admin.php?page=wp-whatsapp-sender-send'); ?>" class="button button-primary">Ir para página de envio</a></p>
            </div>
            <?php
            return;
        }
        
        // Notificação para ações processadas
        $processed = intval($_REQUEST['wp_whatsapp_processed']);
        $template = sanitize_text_field($_REQUEST['wp_whatsapp_template']);
        
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php 
                printf(
                    esc_html__('Foram enviadas %d mensagens de WhatsApp com sucesso (template: %s).', 'wp-whatsapp-sender'),
                    $processed,
                    $template
                ); 
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Adiciona a meta box na página de edição de pedido.
     */
    public static function add_order_meta_box() {
        add_meta_box(
            'wp_whatsapp_sender_order_meta_box',
            'Enviar WhatsApp',
            array(__CLASS__, 'render_order_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }
    
    /**
     * Renderiza o conteúdo da meta box.
     *
     * @param WP_Post $post O post atual.
     */
    public static function render_order_meta_box($post) {
        // Obtém o objeto do pedido
        $order = wc_get_order($post->ID);
        if (!$order) {
            echo 'Pedido não encontrado.';
            return;
        }
        
        // Obtém o número de telefone do cliente
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            echo '<p>O cliente não forneceu um número de telefone.</p>';
            return;
        }
        
        // Formata o telefone para exibição
        $formatted_phone = WP_WhatsApp_Sender_Utils::format_phone_number($phone);
        
        // Obtém os templates disponíveis
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
        $current_status = $order->get_status();
        $status_template = isset($order_status_templates[$current_status]) ? $order_status_templates[$current_status] : '';
        
        // Cria nonce para a requisição AJAX
        $nonce = wp_create_nonce('wp_whatsapp_sender_send_order_message');
        
        // Exibe o formulário
        ?>
        <div class="wp-whatsapp-sender-order-form">
            <p>
                <strong>Telefone:</strong> <?php echo esc_html($formatted_phone); ?>
            </p>
            
            <p>
                <label for="wp_whatsapp_sender_order_message_type">Tipo de Mensagem:</label>
                <select id="wp_whatsapp_sender_order_message_type" class="widefat">
                    <option value="custom">Personalizada</option>
                    <option value="template">Template</option>
                    <option value="status" <?php selected(!empty($status_template)); ?>>Template do Status Atual</option>
                </select>
            </p>
            
            <div id="wp_whatsapp_sender_order_template_row" style="display: none;">
                <p>
                    <label for="wp_whatsapp_sender_order_template">Template:</label>
                    <select id="wp_whatsapp_sender_order_template" class="widefat">
                        <?php foreach ($templates as $name => $content): ?>
                            <option value="<?php echo esc_attr($name); ?>"><?php echo esc_html($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </div>
            
            <p>
                <label for="wp_whatsapp_sender_order_message">Mensagem:</label>
                <textarea id="wp_whatsapp_sender_order_message" class="widefat" rows="5"><?php echo esc_textarea($status_template); ?></textarea>
            </p>
            
            <div class="wp-whatsapp-sender-message-preview">
                <h4>Pré-Visualização</h4>
                <div id="wp_whatsapp_sender_order_preview"></div>
            </div>
            
            <p class="submit">
                <button id="wp_whatsapp_sender_send_order_button" class="button button-primary" data-order-id="<?php echo esc_attr($post->ID); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">Enviar Mensagem</button>
                <span class="spinner" style="float: none; margin-top: 4px;"></span>
            </p>
            
            <div id="wp_whatsapp_sender_order_result"></div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Função para pré-visualizar a mensagem
                function updatePreview() {
                    var message = $('#wp_whatsapp_sender_order_message').val();
                    
                    // Substitui as variáveis com dados do pedido
                    message = message.replace(/\{\{order_id\}\}/g, '<?php echo esc_js($order->get_order_number()); ?>');
                    message = message.replace(/\{\{customer_name\}\}/g, '<?php echo esc_js($order->get_billing_first_name()); ?>');
                    message = message.replace(/\{\{total\}\}/g, '<?php echo esc_js(wc_price($order->get_total(), array('html' => false))); ?>');
                    message = message.replace(/\{\{order_total\}\}/g, '<?php echo esc_js(wc_price($order->get_total(), array('html' => false))); ?>');
                    message = message.replace(/\{\{date\}\}/g, '<?php echo esc_js($order->get_date_created()->date_i18n(get_option('date_format'))); ?>');
                    message = message.replace(/\{\{shop_name\}\}/g, '<?php echo esc_js(get_bloginfo('name')); ?>');
                    
                    // Atualiza a pré-visualização
                    $('#wp_whatsapp_sender_order_preview').html(message.replace(/\n/g, '<br>'));
                }
                
                // Mostra/esconde o campo de template com base no tipo de mensagem
                $('#wp_whatsapp_sender_order_message_type').on('change', function() {
                    var messageType = $(this).val();
                    
                    $('#wp_whatsapp_sender_order_template_row').hide();
                    
                    if (messageType === 'template') {
                        $('#wp_whatsapp_sender_order_template_row').show();
                        // Carrega o conteúdo do template selecionado
                        loadTemplateContent();
                    } else if (messageType === 'status') {
                        // Carrega o template do status atual
                        $('#wp_whatsapp_sender_order_message').val('<?php echo esc_js($status_template); ?>');
                        updatePreview();
                    } else {
                        // Custom message
                        $('#wp_whatsapp_sender_order_message').val('');
                        updatePreview();
                    }
                });
                
                // Carrega o conteúdo do template quando o template é alterado
                $('#wp_whatsapp_sender_order_template').on('change', function() {
                    loadTemplateContent();
                });
                
                // Função para carregar o conteúdo do template via AJAX
                function loadTemplateContent() {
                    var templateName = $('#wp_whatsapp_sender_order_template').val();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_whatsapp_sender_get_template',
                            template_name: templateName,
                            order_id: <?php echo esc_js($post->ID); ?>,
                            security: '<?php echo wp_create_nonce('wp_whatsapp_sender_get_template'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#wp_whatsapp_sender_order_message').val(response.data);
                                updatePreview();
                            }
                        }
                    });
                }
                
                // Evento para enviar a mensagem
                $('#wp_whatsapp_sender_send_order_button').on('click', function(e) {
                    e.preventDefault();
                    
                    var button = $(this);
                    var spinner = button.next('.spinner');
                    var resultContainer = $('#wp_whatsapp_sender_order_result');
                    
                    // Desabilita o botão e mostra o spinner
                    button.prop('disabled', true);
                    spinner.addClass('is-active');
                    resultContainer.html('');
                    
                    // Envia a mensagem via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wp_whatsapp_sender_send_order_message',
                            order_id: button.data('order-id'),
                            message: $('#wp_whatsapp_sender_order_message').val(),
                            security: button.data('nonce')
                        },
                        success: function(response) {
                            // Reabilita o botão e esconde o spinner
                            button.prop('disabled', false);
                            spinner.removeClass('is-active');
                            
                            if (response.success) {
                                resultContainer.html('<div class="notice notice-success inline"><p>' + response.data + '</p></div>');
                            } else {
                                resultContainer.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                            }
                        },
                        error: function() {
                            // Reabilita o botão e esconde o spinner
                            button.prop('disabled', false);
                            spinner.removeClass('is-active');
                            
                            resultContainer.html('<div class="notice notice-error inline"><p>Erro ao enviar a requisição.</p></div>');
                        }
                    });
                });
                
                // Atualiza a pré-visualização quando a mensagem é alterada
                $('#wp_whatsapp_sender_order_message').on('input', updatePreview);
                
                // Inicializa a pré-visualização
                updatePreview();
            });
        </script>
        <?php
    }
    
    /**
     * Processa o envio de mensagem para o pedido via AJAX.
     */
    public static function ajax_send_order_message() {
        // Verifica o nonce
        check_ajax_referer('wp_whatsapp_sender_send_order_message', 'security');
        
        // Verifica as permissões
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Você não tem permissão para realizar esta ação.');
        }
        
        // Obtém os dados
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($order_id) || empty($message)) {
            wp_send_json_error('Por favor, preencha todos os campos obrigatórios.');
        }
        
        // Obtém o pedido
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Pedido não encontrado.');
        }
        
        // Obtém o número de telefone do cliente
        $phone = $order->get_billing_phone();
        if (empty($phone)) {
            wp_send_json_error('O cliente não forneceu um número de telefone.');
        }
        
        // Processa as variáveis do pedido
        $message = self::process_order_variables($message, $order);
        
        // Envia a mensagem
        $result = WP_WhatsApp_Sender_API::send_message($phone, $message);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            // Adiciona uma nota ao pedido
            $order->add_order_note(
                sprintf(
                    'Mensagem de WhatsApp enviada para %s: %s',
                    $phone,
                    $message
                ),
                false
            );
            
            wp_send_json_success('Mensagem enviada com sucesso!');
        }
    }
    
    /**
     * Envia mensagem quando o status do pedido é alterado.
     *
     * @param int $order_id ID do pedido.
     * @param string $old_status Status antigo.
     * @param string $new_status Novo status.
     * @param WC_Order $order Objeto do pedido.
     */
    public static function send_status_message($order_id, $old_status, $new_status, $order) {
        // Obtém os templates de status de pedido
        $order_status_templates = get_option('wp_whatsapp_sender_order_status_templates', array());
        $auto_send_status = get_option('wp_whatsapp_sender_auto_send_status', array());
        
        // Verifica se o novo status tem template configurado e se o auto envio está ativado
        if (isset($order_status_templates[$new_status]) && !empty($order_status_templates[$new_status]) && in_array($new_status, $auto_send_status)) {
            // Obtém o número de telefone do cliente
            $phone = $order->get_billing_phone();
            if (empty($phone)) {
                return; // O cliente não forneceu um número de telefone
            }
            
            // Obtém o template da mensagem
            $message = $order_status_templates[$new_status];
            
            // Processa as variáveis do pedido
            $message = self::process_order_variables($message, $order);
            
            // Envia a mensagem
            $result = WP_WhatsApp_Sender_API::send_message($phone, $message);
            
            if (!is_wp_error($result)) {
                // Adiciona uma nota ao pedido
                $order->add_order_note(
                    sprintf(
                        'Mensagem de WhatsApp enviada automaticamente para %s devido à mudança de status para %s: %s',
                        $phone,
                        wc_get_order_status_name($new_status),
                        $message
                    ),
                    false
                );
            } else {
                // Adiciona uma nota sobre a falha
                $order->add_order_note(
                    sprintf(
                        'Falha ao enviar mensagem de WhatsApp para %s: %s',
                        $phone,
                        $result->get_error_message()
                    ),
                    false
                );
            }
        }
    }
}

// Inicializa a integração com o WooCommerce
add_action('plugins_loaded', array('WP_WhatsApp_Sender_WooCommerce', 'init')); 