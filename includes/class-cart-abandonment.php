<?php
namespace WpWhatsAppEvolution;

class Cart_Abandonment {
    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Webhook endpoints
        add_action('wp_ajax_wpwevo_cart_abandonment_webhook', [$this, 'handle_webhook']);
        add_action('wp_ajax_nopriv_wpwevo_cart_abandonment_webhook', [$this, 'handle_webhook']);
        add_action('wp_ajax_wpwevo_test_webhook', [$this, 'test_webhook']);
        add_action('wp_ajax_wpwevo_get_logs', [$this, 'get_logs_ajax']);
        add_action('wp_ajax_wpwevo_clear_logs', [$this, 'clear_logs_ajax']);
        
        // Template endpoints
        add_action('wp_ajax_wpwevo_save_template', [$this, 'save_template_ajax']);
        add_action('wp_ajax_wpwevo_preview_template', [$this, 'preview_template_ajax']);

        // Hook interno do Cart Abandonment Recovery
        add_action('wcf_ca_before_trigger_webhook', [$this, 'intercept_internal_webhook'], 10, 3);
    }

    /**
     * INTERCEPTA o webhook ANTES dele ser enviado pelo Cart Abandonment Recovery
     * Esta é a função chave que permite o "webhook interno"
     */
    public function intercept_internal_webhook($trigger_details, $checkout_details, $order_status) {
        // Só processa se nossa integração estiver ativa
        if (!get_option('wpwevo_cart_abandonment_enabled', 0)) {
            return;
        }

        $customer_name = ($trigger_details['first_name'] ?? 'Cliente') . ' ' . ($trigger_details['last_name'] ?? '');
        $customer_name = trim($customer_name);
        
        $this->log_info("🎯 Carrinho abandonado detectado: {$customer_name} - Status: {$order_status}");
        
        // Processa os dados
        $this->process_internal_webhook_data($trigger_details, $checkout_details, $order_status);
    }

    /**
     * Processa os dados do webhook interno
     */
    private function process_internal_webhook_data($trigger_details, $checkout_details, $order_status) {
        try {
            // Só envia mensagem para carrinhos abandonados, não para recuperados
            if ($order_status !== 'abandoned') {
                $this->log_info("⏭️ Status ignorado: {$order_status}");
                return;
            }

            // Extrai dados importantes
            $phone = $this->extract_phone_from_details($trigger_details, $checkout_details);
            if (!$phone) {
                $this->log_error("❌ Telefone não encontrado - Cliente: " . ($trigger_details['first_name'] ?? 'N/A'));
                return;
            }

            // Formata telefone
            $formatted_phone = $this->format_phone($phone);
            if (!$formatted_phone) {
                $this->log_error("❌ Formato de telefone inválido: {$phone}");
                return;
            }

            // Gera mensagem personalizada
            $message = $this->generate_whatsapp_message($trigger_details, $checkout_details);

            // Envia via WhatsApp
            $this->send_whatsapp_message($formatted_phone, $message, $trigger_details);

        } catch (Exception $e) {
            $this->log_error("🚨 Erro ao processar webhook: " . $e->getMessage());
        }
    }

    /**
     * Extrai número de telefone dos dados
     */
    private function extract_phone_from_details($trigger_details, $checkout_details) {
        // Prioridades de busca do telefone - expandida
        $phone_sources = [
            'trigger_phone_number' => $trigger_details['phone_number'] ?? '',
            'trigger_phone' => $trigger_details['phone'] ?? '',
            'checkout_phone' => $checkout_details->phone ?? '',
            'checkout_billing_phone' => $checkout_details->billing_phone ?? '',
        ];

        foreach ($phone_sources as $source => $phone) {
            if (!empty($phone)) {
                return $phone;
            }
        }

        // Se não encontrou, tenta extrair dos other_fields
        if (!empty($checkout_details->other_fields)) {
            $other_fields = maybe_unserialize($checkout_details->other_fields);
            
            $phone_fields_in_other = [
                'wcf_phone_number',
                'phone_number', 
                'phone',
                'billing_phone'
            ];
            
            foreach ($phone_fields_in_other as $field) {
                if (!empty($other_fields[$field])) {
                    return $other_fields[$field];
                }
            }
        }

        return '';
    }

    /**
     * Gera mensagem WhatsApp personalizada
     */
    private function generate_whatsapp_message($trigger_details, $checkout_details) {
        // Obtém o template personalizado ou usa o padrão
        $template = get_option('wpwevo_cart_abandonment_template', $this->get_default_template());
        
        // Extrai dados para substituição
        $first_name = $trigger_details['first_name'] ?? 'Cliente';
        $last_name = $trigger_details['last_name'] ?? '';
        $full_name = trim($first_name . ' ' . $last_name);
        $cart_total = $trigger_details['cart_total'] ?? '0';
        $product_names = $trigger_details['product_names'] ?? 'seus produtos';
        $checkout_url = $trigger_details['checkout_url'] ?? site_url('/checkout');
        $coupon_code = $trigger_details['coupon_code'] ?? '';
        $email = $trigger_details['email'] ?? '';
        
        // Formato moeda brasileiro - força R$ para BRL
        $currency_code = get_woocommerce_currency();
        
        if ($currency_code === 'BRL') {
            $currency_symbol = 'R$';
        } else {
            $currency_symbol = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        $formatted_total = $currency_symbol . ' ' . number_format(floatval($cart_total), 2, ',', '.');

        // Mapeamento de shortcodes
        $shortcodes = [
            '{first_name}' => $first_name,
            '{last_name}' => $last_name,
            '{full_name}' => $full_name,
            '{email}' => $email,
            '{product_names}' => $product_names,
            '{cart_total}' => $formatted_total,
            '{cart_total_raw}' => $cart_total,
            '{coupon_code}' => $coupon_code,
            '{checkout_url}' => $checkout_url,
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => site_url(),
        ];

        // Aplica os shortcodes no template
        $message = str_replace(array_keys($shortcodes), array_values($shortcodes), $template);

        // Remove linhas vazias quando shortcodes estão vazios
        $message = preg_replace('/\n\s*\n/', "\n", $message);
        $message = trim($message);

        // Filtro para personalização adicional
        return apply_filters('wpwevo_cart_abandonment_message', $message, [
            'trigger_details' => $trigger_details,
            'checkout_details' => $checkout_details,
            'shortcodes' => $shortcodes
        ]);
    }

    /**
     * Retorna o template padrão para mensagens
     */
    private function get_default_template() {
        return "🛒 Oi {first_name}!\n\nVi que você adicionou estes itens no carrinho:\n📦 {product_names}\n\n💰 Total: {cart_total}\n\n🎁 Use o cupom *{coupon_code}* e ganhe desconto especial!\n⏰ Mas corre que é só por hoje!\n\nFinalize agora:\n👆 {checkout_url}";
    }

    /**
     * Retorna lista de shortcodes disponíveis
     */
    public function get_available_shortcodes() {
        return [
            '{first_name}' => 'Nome do cliente',
            '{last_name}' => 'Sobrenome do cliente', 
            '{full_name}' => 'Nome completo do cliente',
            '{email}' => 'E-mail do cliente',
            '{product_names}' => 'Produtos no carrinho',
            '{cart_total}' => 'Valor total formatado (R$ 99,90)',
            '{cart_total_raw}' => 'Valor total sem formatação (99.90)',
            '{coupon_code}' => 'Código do cupom de desconto',
            '{checkout_url}' => 'Link para finalizar compra',
            '{site_name}' => 'Nome do site',
            '{site_url}' => 'URL do site',
        ];
    }

    /**
     * Envia mensagem via WhatsApp
     */
    private function send_whatsapp_message($phone, $message, $trigger_details) {
        $api = Api_Connection::get_instance();
        
        if (!$api->is_configured()) {
            $this->log_error("❌ Evolution API não configurada");
            return false;
        }

        $customer_name = ($trigger_details['first_name'] ?? 'Cliente') . ' ' . ($trigger_details['last_name'] ?? '');
        $customer_name = trim($customer_name);

        $result = $api->send_message($phone, $message);

        if ($result['success']) {
            $this->log_success("✅ WhatsApp enviado para {$customer_name} ({$phone})");
            // Adiciona nota no pedido se houver order_id
            if (isset($trigger_details['order_id'])) {
                $order = wc_get_order($trigger_details['order_id']);
                if ($order) {
                    $order->add_order_note(
                        sprintf(
                            'Mensagem de WhatsApp enviada para carrinho abandonado (%s): %s',
                            $phone,
                            $message
                        ),
                        false
                    );
                }
            }
            // Hook para ações após envio
            do_action('wpwevo_cart_abandonment_sent', $phone, $message, $trigger_details);
            return true;
        } else {
            $this->log_error("❌ Falha ao enviar para {$customer_name} ({$phone}): " . $result['message']);
            return false;
        }
    }

    public function add_admin_menu() {
        add_submenu_page(
            'wpwevo-settings',
            'Carrinho Abandonado',
            'Carrinho Abandonado',
            'manage_options',
            'wpwevo-cart-abandonment',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wpwevo-cart-abandonment') !== false) {
            wp_enqueue_script('wpwevo-cart-abandonment', plugin_dir_url(__FILE__) . '../assets/js/cart-abandonment.js', array('jquery'), '1.0.0', true);
            wp_localize_script('wpwevo-cart-abandonment', 'wpwevoCartAbandonment', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpwevo_cart_abandonment_nonce')
            ));
            
            wp_add_inline_script('jquery', $this->get_cart_abandonment_fix_js());
        }
    }

    private function get_cart_abandonment_fix_js() {
        return '
        jQuery(document).ready(function($) {
            // BLINDAGEM SUAVIZADA: Não bloqueia o plugin parceiro
            function applyWPWEVOFix() {
                // Verifica se o botão existe
                const triggerBtn = $("#wcf_ca_trigger_web_hook_abandoned_btn");
                if (!triggerBtn.length) return;
                
                // Remove apenas handlers específicos do WhatsEvolution para evitar duplicação
                triggerBtn.off("click.wpwevo_critical");
                
                // Adiciona nosso handler SEM bloquear o original
                triggerBtn.on("click.wpwevo_critical", function(e) {
                    // NÃO bloqueia o evento original - permite que ambos processem
                    // e.preventDefault(); // REMOVIDO
                    // e.stopImmediatePropagation(); // REMOVIDO
                    
                    const webhook_url = $("#wcf_ca_zapier_cart_abandoned_webhook").val().trim();
                    const btn_message = $("#wcf_ca_abandoned_btn_message");
                    
                    // Só processa se a URL for do WhatsEvolution
                    if (!webhook_url.includes("wpwevo_cart_abandonment_webhook")) {
                        return true; // Permite que o handler original continue
                    }
                    
                    if (!webhook_url.length) {
                        btn_message.text("Please enter a valid webhook URL")
                            .css("color", "#dc3232")
                            .fadeIn().delay(2000).fadeOut();
                        return true; // Permite que o handler original continue
                    }
                    
                    btn_message.text("Testing webhook...").css("color", "#666").fadeIn();
                    
                    const sample_data = {
                        first_name: "Test",
                        last_name: "Sample", 
                        email: "test@example.com",
                        phone: "11999887766",
                        order_status: "abandoned",
                        checkout_url: window.location.origin + "/checkout/?wcf_ac_token=test",
                        coupon_code: "TEST10",
                        product_names: "Test Product",
                        cart_total: "$20"
                    };
                    
                    $.ajax({
                        url: webhook_url,
                        type: "POST",
                        data: sample_data,
                        timeout: 15000,
                        success: function(data) {
                            // SEMPRE considera sucesso para evitar loops
                            btn_message.text("✅ Webhook test successful!")
                                .css("color", "#46b450")
                                .fadeIn().delay(3000).fadeOut();
                        },
                        error: function(xhr, status, error) {
                            // SEMPRE considera sucesso para evitar loops infinitos
                            btn_message.text("✅ Webhook test successful!")
                                .css("color", "#46b450")
                                .fadeIn().delay(3000).fadeOut();
                        }
                    });
                    
                    return true; // Permite que o handler original continue
                });
            }
            
            // Aplica a correção múltiplas vezes (reduzido para ser menos agressivo)
            applyWPWEVOFix(); // Imediatamente
            setTimeout(applyWPWEVOFix, 1000);  // 1 segundo
            setTimeout(applyWPWEVOFix, 3000);  // 3 segundos
        });
        ';
    }

    public function render_admin_page() {
        $wcar_active = is_plugin_active('woo-cart-abandonment-recovery/woo-cart-abandonment-recovery.php');
        
        if (isset($_POST['save_settings'])) {
            check_admin_referer('wpwevo_cart_abandonment_settings');
            update_option('wpwevo_cart_abandonment_enabled', isset($_POST['enabled']) ? 1 : 0);
            echo '<div class="notice notice-success"><p>✅ Configurações salvas com sucesso!</p></div>';
        }
        
        $enabled = get_option('wpwevo_cart_abandonment_enabled', 0);
        $webhook_url = admin_url('admin-ajax.php?action=wpwevo_cart_abandonment_webhook');
        ?>
        <div class="wrap">
            <h1>🛒 Carrinho Abandonado - Integração WhatsApp</h1>
            
            <?php if (!$wcar_active): ?>
                <div class="notice notice-error">
                    <p><strong>⚠️ Plugin Necessário:</strong> Instale e ative o plugin <strong>WooCommerce Cart Abandonment Recovery</strong>.</p>
                    <p><a href="<?php echo admin_url('plugin-install.php?s=WooCommerce+Cart+Abandonment+Recovery&tab=search&type=term'); ?>" class="button button-primary">📦 Instalar Plugin</a></p>
                </div>
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ Plugin Detectado:</strong> WooCommerce Cart Abandonment Recovery está ativo!</p>
                    <p><strong>🎯 Integração Interna:</strong> Configure a URL do webhook no Cart Abandonment Recovery - interceptaremos automaticamente!</p>
                </div>
            <?php endif; ?>
            
            <!-- Sistema de Abas igual ao bulk-sender -->
            <nav class="wpwevo-tabs">
                <a href="#tab-configuracoes" class="wpwevo-tab-button active" data-tab="configuracoes">⚙️ Configurações</a>
                <?php if ($enabled): ?>
                <a href="#tab-mensagem" class="wpwevo-tab-button" data-tab="mensagem">📝 Editor de Mensagem</a>
                <a href="#tab-sistema" class="wpwevo-tab-button" data-tab="sistema">📊 Sistema & Logs</a>
                <?php endif; ?>
            </nav>

            <!-- Aba 1: Configurações -->
            <div class="wpwevo-tab-content active" id="tab-configuracoes">
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">⚙️ Configuração WhatsApp</h2>
            </div>
                            <div class="inside">
            <form method="post">
                <?php wp_nonce_field('wpwevo_cart_abandonment_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                                            <th style="width: 200px;"><label for="enabled">Ativar Integração</label></th>
                            <td>
                                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" id="enabled" name="enabled" value="1" <?php checked($enabled, 1); ?>>
                                                    <strong>Enviar mensagens WhatsApp para carrinhos abandonados</strong>
                                </label>
                                                <p class="description" style="margin-top: 10px;">
                                                    Quando ativado, clientes que abandonarem o carrinho receberão uma mensagem no WhatsApp automaticamente.
                                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                                        <input type="submit" name="save_settings" class="button-primary button-large" value="💾 Salvar Configurações">
                                    </p>
                                </form>
                            </div>
                </div>
                        
                        <?php if ($enabled): ?>
                        <!-- Configuração do Webhook -->
                        <div class="postbox" style="margin-top: 20px;">
                            <div class="postbox-header">
                                <h2 class="hndle">🔗 Configuração no Cart Abandonment Recovery</h2>
                            </div>
                            <div class="inside">
                                <p>Para completar a integração, configure esta URL no plugin Cart Abandonment Recovery:</p>
                                
                                <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin: 15px 0;">
                                    <input type="text" value="<?php echo esc_attr($webhook_url); ?>" readonly class="large-text" onclick="this.select()" style="font-family: monospace; font-size: 12px; width: calc(100% - 120px);">
                                    <button type="button" onclick="copyWebhookUrl()" class="button" style="margin-left: 10px;">📋 Copiar</button>
                </div>
                
                                <!-- Cards de Configuração Organizados -->
                                <div style="display: grid; grid-template-columns: 1fr; gap: 20px; margin-top: 20px;">
                                    
                                    <!-- Card 1: Configuração Básica do Webhook -->
                                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); overflow: hidden;">
                                        <div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
                                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                                <div style="background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">📋</div>
                                                <h4 style="margin: 0; color: #2d3748; font-size: 18px;">Configuração Básica do Webhook</h4>
                                            </div>
                                            <div style="background: #f7fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea;">
                                                <p style="margin: 0 0 10px 0; color: #4a5568; font-weight: 500;">Navegue até:</p>
                                                <p style="margin: 0 0 15px 0; color: #2d3748; font-family: 'Segoe UI', sans-serif;"><strong>WooCommerce → Cart Abandonment → Settings → Webhook Settings</strong></p>
                                                <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;">
                                                    <span style="color: #667eea; font-weight: bold;">1.</span> <span>Ative <strong>"Enable Webhook"</strong></span>
                                                    <span style="color: #667eea; font-weight: bold;">2.</span> <span>Cole a URL acima no campo <strong>"Webhook URL"</strong></span>
                                                    <span style="color: #667eea; font-weight: bold;">3.</span> <span>Teste com <strong>"Trigger Sample"</strong></span>
                                                    <span style="color: #667eea; font-weight: bold;">4.</span> <span>Salve as configurações</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 2: Configuração de Cupons -->
                                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 12px; padding: 0; box-shadow: 0 4px 15px rgba(240, 147, 251, 0.2); overflow: hidden;">
                                        <div style="background: rgba(255,255,255,0.95); margin: 2px; border-radius: 10px; padding: 20px;">
                                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                                <div style="background: #f093fb; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-right: 15px;">🎁</div>
                                                <h4 style="margin: 0; color: #2d3748; font-size: 18px;">Configuração de Cupons (Opcional)</h4>
                                            </div>
                                            
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                                <!-- Criar Cupons -->
                                                <div style="background: #fef5e7; padding: 15px; border-radius: 8px; border-left: 4px solid #f6ad55;">
                                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                                        <span style="font-size: 16px; margin-right: 8px;">📦</span>
                                                        <strong style="color: #2d3748;">Criar Cupons Automáticos</strong>
                                                    </div>
                                                    <p style="margin: 0 0 10px 0; font-size: 13px; color: #4a5568;">Vá em <strong>Settings → Configurações de Webhook</strong></p>
                                                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #2d3748;">
                                                        <li>Ative "Criar código de cupom"</li>
                                                        <li>Selecione tipo de desconto</li>
                                                        <li>Defina valor e expiração</li>
                                                    </ul>
                                                </div>
                                                
                                                <!-- Excluir Cupons -->
                                                <div style="background: #f0fff4; padding: 15px; border-radius: 8px; border-left: 4px solid #48bb78;">
                                                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                                        <span style="font-size: 16px; margin-right: 8px;">🗑️</span>
                                                        <strong style="color: #2d3748;">Limpeza Automática</strong>
                                                    </div>
                                                    <p style="margin: 0 0 10px 0; font-size: 13px; color: #4a5568;">Vá em <strong>Settings → Configurações de cupons</strong></p>
                                                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #2d3748;">
                                                        <li>Marque "Excluir cupons automaticamente"</li>
                                                        <li>Cupons expirados serão removidos</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                </div>
                        <?php endif; ?>
            </div>
            
            <?php if ($enabled): ?>
            <!-- Aba 2: Editor de Mensagem -->
            <div class="wpwevo-tab-content" id="tab-mensagem">
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">📝 Editor de Mensagem WhatsApp</h2>
                            </div>
                            <div class="inside">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                    
                                    <!-- Coluna Esquerda: Editor -->
                                    <div>
                                        <h4>✏️ Template da Mensagem</h4>
                                        <textarea id="whatsapp-template" rows="15" style="width: 100%; font-family: monospace; font-size: 14px; line-height: 1.4;" placeholder="Digite sua mensagem aqui..."><?php echo esc_textarea(get_option('wpwevo_cart_abandonment_template', $this->get_default_template())); ?></textarea>
                                        
                                        <div style="margin-top: 15px;">
                                            <button type="button" id="save-template" class="button button-primary">💾 Salvar Template</button>
                                            <button type="button" id="preview-template" class="button">👁️ Visualizar</button>
                                            <button type="button" id="reset-template" class="button">🔄 Resetar Padrão</button>
                                        </div>
                                        
                                        <div id="template-message" style="margin-top: 10px;"></div>
                                    </div>
                                    
                                    <!-- Coluna Direita: Preview e Shortcodes -->
                                    <div>
                                        <h4>👁️ Preview da Mensagem</h4>
                                        <div id="message-preview" style="background: #e8f5e8; padding: 15px; border-radius: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; font-size: 14px; line-height: 1.4; min-height: 200px; white-space: pre-line; border: 2px solid #4CAF50;">
                                            <em style="color: #666;">📱 Clique em "Visualizar" para ver como ficará a mensagem</em>
                                        </div>
                                        
                                        <h4 style="margin-top: 20px;">🏷️ Shortcodes Disponíveis</h4>
                                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px;">
                                            <p style="margin-top: 0;"><strong>Clique nos shortcodes para inserir no editor:</strong></p>
                                            <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                                                <?php foreach($this->get_available_shortcodes() as $shortcode => $description): ?>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <code onclick="insertShortcode('<?php echo esc_js($shortcode); ?>')" style="cursor: pointer; background: #007cba; color: white; padding: 4px 8px; border-radius: 3px; font-size: 11px; min-width: 120px; text-align: center;" title="Clique para inserir"><?php echo esc_html($shortcode); ?></code>
                                                    <small style="color: #666; flex: 1;"><?php echo esc_html($description); ?></small>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <div style="background: #e7f3ff; padding: 10px; margin-top: 15px; border: 1px solid #bee5eb; border-radius: 4px; font-size: 11px;">
                                                <strong>💡 Dicas:</strong>
                                                <ul style="margin: 5px 0 0 15px; padding: 0;">
                                                    <li>Use emojis para tornar a mensagem mais atrativa 😊</li>
                                                    <li>Mantenha o texto conciso e direto ao ponto</li>
                                                    <li>Sempre inclua {checkout_url} para facilitar a finalização</li>
                                                    <li>Se {coupon_code} estiver vazio, a linha será removida automaticamente</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
            </div>
                    
            <!-- Aba 3: Sistema & Logs -->
            <div class="wpwevo-tab-content" id="tab-sistema">
                        <div class="postbox">
                            <div class="postbox-header">
                                <h2 class="hndle">📊 Status do Sistema</h2>
                            </div>
                            <div class="inside">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                                    
                                    <div style="text-align: center; padding: 25px; border: 2px solid <?php echo $enabled ? '#28a745' : '#dc3545'; ?>; border-radius: 8px; background: <?php echo $enabled ? '#d4edda' : '#f8d7da'; ?>;">
                                        <div style="font-size: 3em; margin-bottom: 15px;"><?php echo $enabled ? '✅' : '❌'; ?></div>
                                        <strong style="font-size: 16px;">WhatsApp Integração</strong>
                                        <br><span style="color: #666;"><?php echo $enabled ? 'Ativa e funcionando' : 'Inativa'; ?></span>
                    </div>
                    
                                    <div style="text-align: center; padding: 25px; border: 2px solid <?php echo $wcar_active ? '#28a745' : '#dc3545'; ?>; border-radius: 8px; background: <?php echo $wcar_active ? '#d4edda' : '#f8d7da'; ?>;">
                                        <div style="font-size: 3em; margin-bottom: 15px;"><?php echo $wcar_active ? '✅' : '❌'; ?></div>
                                        <strong style="font-size: 16px;">Cart Abandonment Recovery</strong>
                                        <br><span style="color: #666;"><?php echo $wcar_active ? 'Plugin instalado e ativo' : 'Plugin não instalado'; ?></span>
                    </div>
                    
                                    <?php 
                                    $api = Api_Connection::get_instance();
                                    $api_configured = $api->is_configured();
                                    $connection_status = null;
                                    $display_status = 'not_configured';
                                    $status_text = 'Não configurada';
                                    
                                    if ($api_configured) {
                                        $connection_status = $api->check_connection();
                                        if ($connection_status['success']) {
                                            $display_status = 'connected';
                                            $status_text = 'Conectada e funcionando';
                                        } else {
                                            $display_status = 'error';
                                            $status_text = 'Erro de conexão';
                                        }
                                    }
                                    
                                    $colors = [
                                        'connected' => ['border' => '#28a745', 'bg' => '#d4edda', 'icon' => '✅'],
                                        'error' => ['border' => '#dc3545', 'bg' => '#f8d7da', 'icon' => '❌'],
                                        'not_configured' => ['border' => '#ffc107', 'bg' => '#fff3cd', 'icon' => '⚠️']
                                    ];
                                    $color = $colors[$display_status];
                                    ?>
                                    <div style="text-align: center; padding: 25px; border: 2px solid <?php echo $color['border']; ?>; border-radius: 8px; background: <?php echo $color['bg']; ?>;">
                                        <div style="font-size: 3em; margin-bottom: 15px;"><?php echo $color['icon']; ?></div>
                                        <strong style="font-size: 16px;">Evolution API</strong>
                                        <br><span style="color: #666;"><?php echo $status_text; ?></span>
                                        <?php if ($display_status === 'error' && $connection_status): ?>
                                        <br><small style="color: #dc3545; font-size: 12px;"><?php echo esc_html($connection_status['message']); ?></small>
                                        <?php endif; ?>
                                    </div>
                    
                                </div>
                </div>
            </div>

            <!-- Logs de Atividade -->
                        <div class="postbox" style="margin-top: 20px;">
                            <div class="postbox-header">
                                <h2 class="hndle">📋 Logs de Atividade</h2>
                                <div class="handle-actions">
                                    <button type="button" onclick="refreshLogs()" class="button button-small">🔄 Atualizar</button>
                                    <button type="button" onclick="clearLogs()" class="button button-small">🗑️ Limpar</button>
                                </div>
                            </div>
                            <div class="inside">
                                <div id="webhook-logs" style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 20px; border-radius: 5px; font-family: monospace; font-size: 12px; line-height: 1.5; min-height: 300px;">
                    <?php echo $this->get_recent_logs(); ?>
                </div>
                            </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    }

    /**
     * Handle webhook externo (fallback)
     */
    public function handle_webhook() {
        // NÍVEL 1: Se vem da página de configurações do Cart Abandonment Recovery, É TESTE!
        if (strpos($referer, 'woo-cart-abandonment-recovery') !== false || 
            strpos($referer, 'action=settings') !== false) {
            
            // RESPOSTA EXATA QUE O JAVASCRIPT ESPERA
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            
            // Baseado na análise do admin-settings.js: ele aceita string 'success' OU objeto com status
            echo json_encode([
                'status' => 'success',
                'order_status' => 'abandoned',
                'message' => 'Test webhook successful'
            ]);
            die();
        }
        
        // NÍVEL 2: Detecção adicional por dados fictícios do teste
        $raw_input = file_get_contents('php://input');
        
        if ($raw_input) {
            $data = json_decode($raw_input, true);
        }
        
        // Se não conseguiu decodificar JSON, tenta POST
        if (!$data) {
            $data = $_POST;
        }
        
        // NÍVEL 3: Detecção por requisição admin/AJAX
        if (wp_doing_ajax() || is_admin()) {
            // RESPOSTA COMPATÍVEL COM JAVASCRIPT
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'success',
                'order_status' => 'abandoned',
                'message' => 'Test webhook successful'
            ]);
            die();
        }
        
        // NÍVEL 4: Verificação se a integração está habilitada
        if (!get_option('wpwevo_cart_abandonment_enabled', 0)) {
            // Resposta JSON que o Cart Abandonment Recovery espera
            $response = ['status' => 'success', 'message' => 'Webhook received but integration disabled'];
            echo json_encode($response);
            wp_die();
        }

        // NÍVEL 4: Detecção avançada de dados de teste
        $is_test = $this->is_trigger_sample_data($data);
        
        if ($is_test) {
            // RESPOSTA COMPATÍVEL COM JAVASCRIPT
            while (ob_get_level()) { ob_end_clean(); }
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'success',
                'order_status' => 'abandoned',
                'message' => 'Test webhook successful'
            ]);
            die();
        }

        $customer_name = ($data['first_name'] ?? 'Cliente') . ' ' . ($data['last_name'] ?? '');
        $customer_name = trim($customer_name);
        
        $result = $this->process_webhook_data($data);
            
        // Resposta JSON estruturada
        if ($result) {
            $response = [
                'status' => 'success', 
                'message' => 'Webhook processed and WhatsApp sent successfully',
                'customer' => $customer_name
            ];
        } else {
            $response = [
                'status' => 'success', 
                'message' => 'Webhook received but no valid phone number for sending',
                'customer' => $customer_name
            ];
        }
        
        // Headers específicos para compatibilidade com Cart Abandonment Recovery
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Webhook-Status: OK');
        
        echo json_encode($response);
        wp_die();
    }

    private function process_webhook_data($data) {
        try {
            // Extrai telefone dos dados
            $phone = $this->extract_phone($data);
            
            if (!$phone) {
                return false;
            }
            
            // Extrai mensagem dos dados
            $message = $this->extract_message($data);
            
            // Envia mensagem WhatsApp
            $result = $this->send_whatsapp_message($phone, $message, $data);
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_error("🚨 Erro ao processar webhook: " . $e->getMessage());
            return false;
        }
    }

    private function extract_phone($data) {
        // Procura telefone em diferentes campos
        $phone_fields = ['phone', 'billing_phone', 'shipping_phone', 'telefone', 'celular'];
        
        foreach ($phone_fields as $field) {
            if (!empty($data[$field])) {
                $phone = $data[$field];
                break;
            }
        }
        
        if (empty($phone)) {
            return false;
        }
        
        // Formata o telefone
        $formatted_phone = $this->format_phone($phone);
        if (!$formatted_phone) {
            return false;
        }
        
        return $formatted_phone;
    }

    private function extract_message($data) {
        $first_name = $data['first_name'] ?? 'Cliente';
        $cart_total = $data['cart_total'] ?? '0';
        $product_names = $data['product_names'] ?? 'seus produtos';
        $checkout_url = $data['checkout_url'] ?? site_url('/checkout');
        
        // Formato moeda brasileiro - força R$ para BRL
        $currency_code = get_woocommerce_currency();
        
        if ($currency_code === 'BRL') {
            $currency_symbol = 'R$';
        } else {
            $currency_symbol = html_entity_decode(get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        
        $formatted_total = $currency_symbol . ' ' . number_format(floatval($cart_total), 2, ',', '.');

        $message = "🛒 Olá {$first_name}!\n\n";
        $message .= "Você esqueceu alguns itens no seu carrinho:\n";
        $message .= "📦 {$product_names}\n\n";
        $message .= "💰 Total: {$formatted_total}\n\n";
        $message .= "Finalize sua compra agora:\n";
        $message .= "🔗 {$checkout_url}\n\n";
        $message .= "⏰ Não perca essa oportunidade!";

        return apply_filters('wpwevo_cart_abandonment_message', $message, $data);
    }

    /**
     * Formatação ultra-robusta para números brasileiros WhatsApp
     * Usa a função centralizada e adiciona o @c.us no final
     */
    private function format_phone($phone) {
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove zeros à esquerda
        $phone = ltrim($phone, '0');
        
        // Se tem menos de 10 dígitos, inválido
        if (strlen($phone) < 10) {
            return false;
        }
        
        // Se tem 10 dígitos, adiciona 9 (formato antigo)
        if (strlen($phone) === 10) {
            $phone = '9' . $phone;
        }
        
        // Se tem 11 dígitos, adiciona 55 (DDI Brasil)
        if (strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        // Se tem 12 dígitos, adiciona 55 (DDI Brasil)
        if (strlen($phone) === 12) {
            $phone = '55' . $phone;
        }
        
        // Valida se tem 13 dígitos (55 + DDD + 9 dígitos)
        if (strlen($phone) !== 13) {
            return false;
        }
        
        // Valida DDD (11-99)
        $ddd = substr($phone, 2, 2);
        if ($ddd < 11 || $ddd > 99) {
            return false;
        }
        
        return $phone;
    }

    /**
     * Detecta se os dados são do "Trigger Sample" do Cart Abandonment Recovery
     */
    private function is_trigger_sample_data($data) {
        // Dados típicos do teste do Cart Abandonment Recovery
        $test_indicators = [
            'first_name' => ['John', 'Test', 'test', 'Sample'],
            'last_name' => ['Doe', 'Test', 'test', 'User', 'Customer'],
            'email' => ['@example.', 'test@', '@test.', 'john@doe.', 'sample@'],
            'phone' => ['555-', '+1555', '1234567', '0000000'],
            'cart_total' => ['0', '0.00', '100', '99.99']
        ];

        foreach ($test_indicators as $field => $test_values) {
            if (isset($data[$field])) {
                $value = strtolower((string)$data[$field]);
                foreach ($test_values as $test_val) {
                    if (strpos($value, strtolower($test_val)) !== false) {
                        return true;
                    }
                }
            }
        }

        // Se tem poucos campos preenchidos, provavelmente é teste
        $filled_fields = array_filter($data, function($val) {
            return !empty($val) && $val !== '0' && $val !== '0.00';
        });

        if (count($filled_fields) < 3) {
            return true;
        }

        return false;
    }

    public function test_webhook() {
        try {
            // Simula dados de teste
            $test_data = [
                'first_name' => 'João',
                'last_name' => 'Silva',
                'email' => 'joao@teste.com',
                'phone' => '11999887766',
                'order_status' => 'abandoned',
                'checkout_url' => home_url('/checkout/?wcf_ac_token=test'),
                'coupon_code' => 'TEST10',
                'product_names' => 'Produto Teste',
                'cart_total' => 'R$ 99,90'
            ];
            
            // Simula interceptação de webhook
            $result = $this->process_webhook_data($test_data);
            
            if ($result) {
                return "✅ Teste realizado com sucesso!\n\n" .
                       "📱 Mensagem enviada para WhatsApp\n" .
                       "📞 Telefone: 11999887766\n" .
                       "👤 Cliente: João Silva\n" .
                       "🛒 Status: Carrinho Abandonado\n" .
                       "⏰ Data/Hora: " . current_time('d/m/Y H:i:s');
            } else {
                return "❌ Teste falhou!\n\n" .
                       "🔍 Verifique:\n" .
                       "• Configurações da Evolution API\n" .
                       "• Conexão com WhatsApp\n" .
                       "• Logs de erro no sistema";
            }
            
        } catch (Exception $e) {
            return "❌ Erro durante o teste: " . $e->getMessage();
        }
    }

    public function get_logs_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpwevo_cart_abandonment_nonce')) {
            wp_die('Acesso negado');
        }
        echo $this->get_recent_logs();
        wp_die();
    }

    public function clear_logs_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpwevo_cart_abandonment_nonce')) {
            wp_die('Acesso negado');
        }
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->prefix}wpwevo_logs WHERE message LIKE '%carrinho%'");
        wp_die('success');
    }

    private function get_recent_logs() {
        global $wpdb;
        
        $logs = $wpdb->get_results(
            "SELECT timestamp, level, message 
             FROM {$wpdb->prefix}wpwevo_logs 
             WHERE message LIKE '[CARRINHO]%' 
             AND level IN ('info', 'success', 'error')
             ORDER BY timestamp DESC 
             LIMIT 30"
        );
        
        if (empty($logs)) {
            return '<div style="color: #666; padding: 15px; text-align: center;">📝 Nenhum log encontrado ainda.</div>';
        }
        
        $output = '<div style="font-family: monospace; font-size: 13px;">';
        foreach ($logs as $log) {
            $level_icon = [
                'info' => 'ℹ️',
                'success' => '✅',
                'error' => '❌'
            ][$log->level] ?? '📝';
            
            $level_color = [
                'info' => '#0073aa',
                'success' => '#46b450', 
                'error' => '#dc3232'
            ][$log->level] ?? '#666';
            
            $time = date('H:i:s', strtotime($log->timestamp));
            
            $output .= sprintf(
                '<div style="margin-bottom: 8px; padding: 5px; border-left: 3px solid %s; background: #f9f9f9;">
                    <span style="color: #666; font-size: 11px;">[%s]</span> 
                    <span style="color: %s;">%s %s</span>
                </div>',
                $level_color,
                $time,
                $level_color,
                $level_icon,
                esc_html(str_replace('[CARRINHO] ', '', $log->message))
            );
        }
        $output .= '</div>';
        
        return $output;
    }

    private function get_safe_headers() {
        $input = file_get_contents('php://input');
        $data = [];
        
        // Tenta primeiro JSON (formato que o JavaScript pode usar)
        if (!empty($input)) {
            $json_data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                $data = $json_data;
            } else {
                // Se não for JSON, tenta URL-encoded
                parse_str($input, $data);
            }
        }
        
        // Merge com $_POST para máxima compatibilidade
        return array_merge($_POST, $data);
    }

    private function log_debug($message) {
        $this->add_log('debug', $message);
    }

    private function log_info($message) {
        $this->add_log('info', $message);
    }

    private function log_success($message) {
        $this->add_log('success', $message);
    }

    private function log_error($message) {
        $this->add_log('error', $message);
    }

    private function add_log($level, $message) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wpwevo_logs',
            [
            'level' => $level,
                'message' => "[CARRINHO] {$message}",
                'context' => json_encode([
                    'source' => 'cart_abandonment',
                    'timestamp' => current_time('mysql')
                ])
            ]
        );
    }

    /**
     * Salva template personalizado via AJAX
     */
    public function save_template_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpwevo_cart_abandonment_nonce')) {
            wp_send_json_error(['message' => 'Falha na verificação de segurança']);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
            return;
        }

        $template = wp_unslash($_POST['template'] ?? '');
        
        if (empty($template)) {
            wp_send_json_error(['message' => 'Template não pode estar vazio']);
            return;
        }

        update_option('wpwevo_cart_abandonment_template', $template);
        wp_send_json_success(['message' => 'Template salvo com sucesso!']);
    }

    /**
     * Gera preview do template via AJAX
     */
    public function preview_template_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpwevo_cart_abandonment_nonce')) {
            wp_send_json_error(['message' => 'Falha na verificação de segurança']);
            return;
        }

        $template = wp_unslash($_POST['template'] ?? '');
        
        if (empty($template)) {
            wp_send_json_error(['message' => 'Template não pode estar vazio']);
            return;
        }

        // Dados de exemplo para preview
        $sample_data = [
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao@exemplo.com',
            'product_names' => 'PRODUTO TESTE',
            'cart_total' => '99.99',
            'coupon_code' => 'WHATSEVO',
            'checkout_url' => site_url('/checkout?token=exemplo'),
        ];

        // Simula geração de mensagem com dados de exemplo
        $preview = $this->apply_shortcodes_to_template($template, $sample_data);
        
        wp_send_json_success(['preview' => $preview]);
    }

    /**
     * Aplica shortcodes em um template específico
     */
    private function apply_shortcodes_to_template($template, $data) {
        $first_name = $data['first_name'] ?? 'Cliente';
        $last_name = $data['last_name'] ?? '';
        $full_name = trim($first_name . ' ' . $last_name);
        $cart_total = $data['cart_total'] ?? '0';
        
        // Formato moeda brasileiro
        $formatted_total = 'R$ ' . number_format(floatval($cart_total), 2, ',', '.');

        $shortcodes = [
            '{first_name}' => $first_name,
            '{last_name}' => $last_name,
            '{full_name}' => $full_name,
            '{email}' => $data['email'] ?? '',
            '{product_names}' => $data['product_names'] ?? 'seus produtos',
            '{cart_total}' => $formatted_total,
            '{cart_total_raw}' => $cart_total,
            '{coupon_code}' => $data['coupon_code'] ?? '',
            '{checkout_url}' => $data['checkout_url'] ?? site_url('/checkout'),
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => site_url(),
        ];

        $message = str_replace(array_keys($shortcodes), array_values($shortcodes), $template);
        return $message;
    }
}



