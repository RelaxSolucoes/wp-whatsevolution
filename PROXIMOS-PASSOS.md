# 🚀 PRÓXIMOS PASSOS - WP WhatsApp Evolution

## 📋 ROADMAP DE DESENVOLVIMENTO

### **🎯 VISÃO GERAL**
Implementação de funcionalidades prioritárias para melhorar UX e automação do plugin WP WhatsApp Evolution.

---

## 🏆 **PRIORIDADE 1: METABOX NO PEDIDO** *(2-3 dias)*

### 📋 **OBJETIVO**
Adicionar metabox lateral na tela de edição de pedidos do WooCommerce para envio direto de WhatsApp, similar ao WP WhatsApp Sender.

### 🎯 **FUNCIONALIDADES DESEJADAS**
- Interface lateral na tela de edição do pedido
- Seleção de templates (Status Atual, personalizada, etc.)
- Pré-visualização em tempo real da mensagem
- Envio direto com feedback visual
- Histórico de envios nas notas do pedido

---

### 🛠️ **FASE 1A: ESTRUTURA BASE** *(Dia 1)*

#### **📁 ETAPA 1A.1: Criação da Classe** *(Manhã)*
```php
// Arquivo: includes/class-order-metabox.php
<?php
namespace WpWhatsAppEvolution;

class Order_Metabox {
    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('wp_ajax_wpwevo_send_order_message', [$this, 'ajax_handler']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
}
```

#### **⚙️ ETAPA 1A.2: Integração no Plugin Loader** *(Tarde)*
```php
// Em includes/class-plugin-loader.php
public function init_modules() {
    Settings_Page::init();
    Send_Single::init();
    Send_By_Status::init();
    Cart_Abandonment::init();
    Bulk_Sender::init();
    Checkout_Validator::init();
    Order_Metabox::init(); // ← Nova linha
}
```

**📋 ENTREGÁVEL FASE 1A:**
- Estrutura básica da classe
- Integração com autoloader
- Hooks WordPress configurados

---

### 🎨 **FASE 1B: INTERFACE E TEMPLATES** *(Dia 2)*

#### **🖥️ ETAPA 1B.1: HTML do Metabox** *(Manhã)*
```php
public function add_metabox() {
    // Verifica se WooCommerce está ativo
    if (!class_exists('WooCommerce')) {
        return;
    }

    add_meta_box(
        'wpwevo_order_metabox',
        __('Enviar WhatsApp', 'wp-whatsapp-evolution'),
        [$this, 'render_metabox'],
        'shop_order',
        'side',
        'default'
    );
}

public function render_metabox($post) {
    $order = wc_get_order($post->ID);
    // HTML do metabox baseado no WP Sender
}
```

#### **📱 ETAPA 1B.2: Sistema de Templates** *(Tarde)*
```php
private function get_available_templates($order) {
    $templates = [];
    
    // Templates do Send_By_Status
    $status_templates = get_option('wpwevo_status_messages', []);
    $current_status = $order->get_status();
    
    if (isset($status_templates[$current_status])) {
        $templates['status_atual'] = $status_templates[$current_status]['message'];
    }
    
    // Templates customizados (futura expansão)
    return $templates;
}
```

**📋 ENTREGÁVEL FASE 1B:**
- Interface visual do metabox
- Sistema de templates integrado
- Seleção dropdown funcionando

---

### ⚡ **FASE 1C: FUNCIONALIDADES AVANÇADAS** *(Dia 3)*

#### **👁️ ETAPA 1C.1: Preview em Tempo Real** *(Manhã)*
```javascript
// assets/js/order-metabox.js
function updatePreview() {
    var message = $('#wpwevo_order_message').val();
    
    // Substituir variáveis do pedido
    message = message.replace(/{customer_name}/g, orderData.customerName);
    message = message.replace(/{order_id}/g, orderData.orderId);
    message = message.replace(/{order_total}/g, orderData.orderTotal);
    
    $('#wpwevo_order_preview').html(message.replace(/\n/g, '<br>'));
}
```

#### **📡 ETAPA 1C.2: Handler AJAX** *(Tarde)*
```php
public function ajax_handler() {
    check_ajax_referer('wpwevo_send_order_message', 'nonce');
    
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Permissão negada.');
    }
    
    $order_id = intval($_POST['order_id']);
    $message = sanitize_textarea_field($_POST['message']);
    
    $order = wc_get_order($order_id);
    $phone = $order->get_billing_phone();
    
    // Usar Api_Connection existente
    $api = Api_Connection::get_instance();
    $result = $api->send_message($phone, $message);
    
    if ($result['success']) {
        // Adicionar nota ao pedido
        $order->add_order_note("Mensagem WhatsApp enviada: " . $message);
        wp_send_json_success('Mensagem enviada com sucesso!');
    } else {
        wp_send_json_error($result['message']);
    }
}
```

**📋 ENTREGÁVEL FASE 1C:**
- Preview funcionando em tempo real
- Envio via AJAX operacional
- Logs nas notas do pedido

---

### ✅ **MARCOS METABOX**

#### 🚩 **MARCO 1A** - Dia 1
- [ ] ✅ Estrutura básica criada
- [ ] ✅ Integração com Plugin Loader
- [ ] ✅ Metabox aparecendo na tela

#### 🚩 **MARCO 1B** - Dia 2
- [ ] ✅ Interface completa renderizada
- [ ] ✅ Templates carregando
- [ ] ✅ Dropdown funcionando

#### 🚩 **MARCO 1C** - Dia 3
- [ ] ✅ Preview em tempo real
- [ ] ✅ Envio via AJAX funcionando
- [ ] ✅ Notas do pedido sendo criadas

---

## 🔄 **PRIORIDADE 2: SEQUÊNCIA DE E-MAILS** *(13 dias)*

### **🎯 OBJETIVO**
Interceptar o sistema de sequência de e-mails do plugin "WooCommerce Cart Abandonment Recovery" e converter para envio via WhatsApp, mantendo timing, templates e cupons.

### **📊 RESULTADO ESPERADO**
Cliente recebe sequência automática via WhatsApp:
- **30 min**: "🛒 Oi! Vi que você parou na compra... Problema?"
- **45 min**: "😊 Precisa de ajuda para finalizar?"  
- **60 min**: "🎁 Desconto exclusivo + cupom VOLTA10!"

---

### 📊 **FASE 2A: ANÁLISE E DISCOVERY** *(Dias 4-5)*

#### 🔍 **ETAPA 2A.1: Investigação do Banco** *(Dia 4 - Manhã)*

**🗄️ Tabelas Principais:**
```sql
-- Histórico de e-mails agendados
cartflows_ca_email_history
- id, template_id, ca_session_id
- scheduled_time, email_sent, coupon_code

-- Templates de e-mail configurados  
cartflows_ca_email_templates
- id, template_name, email_subject
- email_body, frequency, frequency_unit

-- Dados do carrinho abandonado
cartflows_ca_cart_abandonment
- session_id, email, cart_contents
- cart_total, other_fields, time
```

**📋 Comandos de Investigação:**
```bash
# Comandos para investigar as tabelas:
wp db query "DESCRIBE wp_cartflows_ca_email_history"
wp db query "DESCRIBE wp_cartflows_ca_email_templates" 
wp db query "DESCRIBE wp_cartflows_ca_cart_abandonment"

# Ver dados reais
wp db query "SELECT * FROM wp_cartflows_ca_email_history LIMIT 5"
wp db query "SELECT * FROM wp_cartflows_ca_email_templates"
```

#### 🕵️ **ETAPA 2A.2: Mapeamento de Hooks** *(Dia 4 - Tarde)*

**⚙️ Hooks de Interceptação:**
```php
// Hook principal
add_action('cartflows_ca_send_email_templates', 'intercept_emails', 5);

// Hook alternativo (fallback)
add_filter('wp_mail', 'convert_to_whatsapp', 999);

// Hook de agendamento
add_action('cartflows_ca_email_scheduled', 'schedule_whatsapp');
```

**🔍 Debug de Hooks:**
```php
// Arquivo: debug-hooks.php (temporário)
add_action('all', function($hook) {
    if (strpos($hook, 'cartflows') !== false) {
        error_log("Hook encontrado: " . $hook);
    }
});
```

#### 📝 **ETAPA 2A.3: Análise de Templates** *(Dia 5)*

**📊 Dados Necessários (já disponíveis):**
```
✅ Nome: RONALD MELO
✅ Telefone: 19989881838  
✅ Email: rony.campinas@hotmail.com
✅ Produtos no carrinho
✅ Valor total
✅ Link de finalização
✅ Cupons gerados
```

**🎨 Exemplos de Conversão:**

**📧 Template 1 (30min) → 📱 WhatsApp:**
```
E-MAIL: "Purchase issue? Complete your order now"

WHATSAPP: 
🛒 Oi {first_name}!

Vi que você estava finalizando uma compra mas parou... 🤔
Aconteceu algum problema?

Finalize aqui: {checkout_url}
```

**📧 Template 2 (45min) → 📱 WhatsApp:**
```
E-MAIL: "Need help? We're here to assist you"

WHATSAPP:
😊 Oi novamente!

Precisa de ajuda para finalizar?
Estou aqui para te ajudar! 💬

Link rápido: {checkout_url}
```

**📧 Template 3 (60min) → 📱 WhatsApp:**
```
E-MAIL: "Exclusive discount for you. Let's get things started!"

WHATSAPP:
🎁 OFERTA ESPECIAL!

Como você não finalizou, liberei um desconto exclusivo:

*{coupon_code}* 🎟️

⏰ Válido só hoje!
Aproveite: {checkout_url}
```

**📋 ENTREGÁVEL FASE 2A:**
- Documento com mapeamento completo do sistema
- Lista de hooks identificados
- Especificação técnica de conversão E-mail → WhatsApp
- Exemplos de templates convertidos

---

### 🛠️ **FASE 2B: PROTOTIPAGEM** *(Dias 6-8)*

#### ⚙️ **ETAPA 2B.1: Interceptação Básica** *(Dia 6)*

**🔧 Estrutura da Classe:**
```php
// Arquivo: includes/class-email-interceptor.php
namespace WpWhatsAppEvolution;

class Email_Interceptor {
    
    public function __construct() {
        add_action('cartflows_ca_send_email_templates', [$this, 'intercept'], 5);
        add_filter('wp_mail', [$this, 'intercept_wp_mail'], 999, 1);
    }
    
    /**
     * Intercepta e-mails agendados do Cart Abandonment
     * 
     * @param array $email_data Dados do e-mail a ser enviado
     * @return bool True se interceptado com sucesso
     * @since 1.2.0
     */
    public function intercept_scheduled_email($email_data) {
        // Verificar se interceptação está ativa
        if (!get_option('wpwevo_email_sequence_enabled', false)) {
            return; // Deixa e-mail passar normalmente
        }
        
        wpwevo_log('info', '🚀 WhatsApp Interceptor: Hook executado!');
        
        // Buscar dados do carrinho
        $cart_data = $this->get_cart_data($email_data);
        
        if (!$cart_data || empty($cart_data['phone'])) {
            wpwevo_log('warning', 'Dados insuficientes para WhatsApp');
            return; // Deixa e-mail passar normalmente
        }
        
        // Processar envio WhatsApp
        $this->send_whatsapp($cart_data, $email_data);
    }
    
    /**
     * Envia mensagem WhatsApp diretamente do pedido
     * 
     * @param int $order_id ID do pedido WooCommerce
     * @param string $message Mensagem a ser enviada
     * @return array Resultado do envio
     * @since 1.1.0
     */
    public function send_order_message($order_id, $message) {
        // Código aqui
    }
}
```

#### 🔄 **ETAPA 2B.2: Query de E-mails Pendentes** *(Dia 7)*

**📊 Sistema de Queries:**
```php
// Implementar função para buscar e-mails agendados
public function get_pending_emails() {
    global $wpdb;
    
    return $wpdb->get_results("
        SELECT h.*, t.template_name, t.email_body, c.other_fields, c.cart_contents
        FROM {$wpdb->prefix}cartflows_ca_email_history h
        JOIN {$wpdb->prefix}cartflows_ca_email_templates t ON h.template_id = t.id
        JOIN {$wpdb->prefix}cartflows_ca_cart_abandonment c ON h.ca_session_id = c.session_id
        WHERE h.scheduled_time <= NOW() 
        AND h.email_sent = 0
        ORDER BY h.scheduled_time ASC
    ");
}

private function get_cart_data($email_data) {
    global $wpdb;
    
    $cart_abandonment = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}cartflows_ca_cart_abandonment 
        WHERE session_id = %s
    ", $email_data['session_id']));
    
    if (!$cart_abandonment) {
        return false;
    }
    
    // Extrair telefone dos other_fields
    $other_fields = maybe_unserialize($cart_abandonment->other_fields);
    $phone = '';
    
    if (isset($other_fields['billing_phone'])) {
        $phone = $other_fields['billing_phone'];
    } elseif (isset($other_fields['billing_cellphone'])) {
        $phone = $other_fields['billing_cellphone'];
    }
    
    return [
        'phone' => $phone,
        'first_name' => $other_fields['billing_first_name'] ?? '',
        'cart_contents' => maybe_unserialize($cart_abandonment->cart_contents),
        'cart_total' => $cart_abandonment->cart_total,
        'checkout_url' => $cart_abandonment->checkout_url ?? '',
        'coupon_code' => $email_data['coupon_code'] ?? ''
    ];
}
```

#### 📱 **ETAPA 2B.3: Primeiro Envio WhatsApp** *(Dia 8)*

**🔌 Integração com API Existente:**
```php
private function send_whatsapp($cart_data, $email_data) {
    // Usar API Connection existente
    $api = Api_Connection::get_instance();
    
    if (!$api->is_configured()) {
        wpwevo_log('error', 'Evolution API não configurada');
        return false;
    }
    
    // Converter template
    $converter = new Template_Converter();
    $whatsapp_message = $converter->convert_email_to_whatsapp($email_data, $cart_data);
    
    // Enviar mensagem
    $result = $api->send_message($cart_data['phone'], $whatsapp_message);
    
    if ($result['success']) {
        wpwevo_log('success', 'WhatsApp enviado com sucesso: ' . $cart_data['phone']);
        
        // Marcar e-mail como processado (não cancelar completamente)
        $this->mark_email_processed($email_data['id']);
        
        return true;
    } else {
        wpwevo_log('error', 'Falha no envio WhatsApp: ' . $result['message']);
        return false;
    }
}
```

**📋 ENTREGÁVEL FASE 2B:**
- Protótipo funcional interceptando e-mails
- Sistema de queries do banco funcionando
- Primeiro envio WhatsApp bem-sucedido
- Validação do conceito técnico

---

### 🎨 **FASE 2C: DESENVOLVIMENTO COMPLETO** *(Dias 9-12)*

#### 📧 **ETAPA 2C.1: Sistema de Conversão Avançado** *(Dias 9-10)*

**🔄 Conversor de Templates:**
```php
// Arquivo: includes/class-template-converter.php
namespace WpWhatsAppEvolution;

class Template_Converter {
    
    /**
     * Converte template de e-mail para WhatsApp
     */
    public function convert_email_to_whatsapp($email_data, $cart_data) {
        $template_type = $this->identify_template_type($email_data);
        
        switch($template_type) {
            case 'template_1':
                return $this->template_reminder($cart_data);
            case 'template_2': 
                return $this->template_help($cart_data);
            case 'template_3':
                return $this->template_discount($cart_data);
            default:
                return $this->template_generic($email_data, $cart_data);
        }
    }
    
    private function identify_template_type($email_data) {
        $template_name = strtolower($email_data['template_name']);
        
        if (strpos($template_name, 'first') !== false || 
            strpos($template_name, 'reminder') !== false) {
            return 'template_1';
        }
        
        if (strpos($template_name, 'second') !== false || 
            strpos($template_name, 'help') !== false) {
            return 'template_2';
        }
        
        if (strpos($template_name, 'third') !== false || 
            strpos($template_name, 'discount') !== false ||
            strpos($template_name, 'coupon') !== false) {
            return 'template_3';
        }
        
        return 'generic';
    }
    
    private function template_reminder($data) {
        return "🛒 Oi {$data['first_name']}!\n\nVi que você estava finalizando uma compra mas parou... 🤔\nAconteceu algum problema?\n\nFinalize aqui: {$data['checkout_url']}";
    }
    
    private function template_help($data) {
        return "😊 Oi novamente!\n\nPrecisa de ajuda para finalizar?\nEstou aqui para te ajudar! 💬\n\nLink rápido: {$data['checkout_url']}";
    }
    
    private function template_discount($data) {
        $message = "🎁 OFERTA ESPECIAL!\n\nComo você não finalizou, liberei um desconto exclusivo:";
        
        if (!empty($data['coupon_code'])) {
            $message .= "\n\n*{$data['coupon_code']}* 🎟️";
        }
        
        $message .= "\n\n⏰ Válido só hoje!\nAproveite: {$data['checkout_url']}";
        
        return $message;
    }
    
    private function template_generic($email_data, $cart_data) {
        // Converter HTML para texto limpo
        $clean_text = $this->html_to_clean_text($email_data['email_body']);
        
        // Substituir shortcodes
        return $this->replace_shortcodes($clean_text, $cart_data);
    }
    
    private function html_to_clean_text($html) {
        // Remover HTML tags
        $text = strip_tags($html);
        
        // Converter entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Limpar espaços excessivos
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function replace_shortcodes($text, $data) {
        $replacements = [
            '{first_name}' => $data['first_name'],
            '{customer_name}' => $data['first_name'],
            '{cart_total}' => 'R$ ' . number_format($data['cart_total'], 2, ',', '.'),
            '{checkout_url}' => $data['checkout_url'],
            '{coupon_code}' => $data['coupon_code'],
            '{product_names}' => $this->get_product_names($data['cart_contents']),
            '{site_name}' => get_bloginfo('name')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    private function get_product_names($cart_contents) {
        if (empty($cart_contents)) {
            return '';
        }
        
        $products = [];
        foreach ($cart_contents as $item) {
            if (isset($item['product_name'])) {
                $products[] = $item['product_name'];
            }
        }
        
        return implode(', ', $products);
    }
}
```

#### ⏰ **ETAPA 2C.2: Sistema de Timing** *(Dia 11)*

**⏱️ Controle de Timing:**
```php
// Arquivo: includes/class-whatsapp-sequencer.php
namespace WpWhatsAppEvolution;

class WhatsApp_Sequencer {
    
    public function __construct() {
        // Hook para processar fila de WhatsApp
        add_action('wpwevo_process_whatsapp_queue', [$this, 'process_queue']);
        
        // Agendar processamento a cada 5 minutos
        if (!wp_next_scheduled('wpwevo_process_whatsapp_queue')) {
            wp_schedule_event(time(), 'every_5_minutes', 'wpwevo_process_whatsapp_queue');
        }
    }
    
    public function process_queue() {
        $interceptor = new Email_Interceptor();
        $pending_emails = $interceptor->get_pending_emails();
        
        foreach ($pending_emails as $email) {
            // Verificar se já passou do horário
            if (strtotime($email->scheduled_time) <= time()) {
                $this->process_single_email($email);
            }
        }
    }
    
    private function process_single_email($email) {
        $interceptor = new Email_Interceptor();
        
        // Tentar enviar com retry
        $max_retries = get_option('wpwevo_sequence_max_retries', 3);
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            $result = $interceptor->intercept_scheduled_email($email);
            
            if ($result) {
                wpwevo_log('success', "WhatsApp enviado na tentativa " . ($retry_count + 1));
                break;
            }
            
            $retry_count++;
            wpwevo_log('warning', "Tentativa {$retry_count} falhou, tentando novamente...");
            
            // Delay entre tentativas
            sleep(5);
        }
        
        if ($retry_count >= $max_retries) {
            wpwevo_log('error', "Falha após {$max_retries} tentativas");
            
            // Fallback para e-mail se configurado
            if (get_option('wpwevo_sequence_fallback_email', true)) {
                $this->send_fallback_email($email);
            }
        }
    }
}
```

#### 🎁 **ETAPA 2C.3: Sistema de Cupons** *(Dia 12)*

**🎟️ Gerenciamento de Cupons:**
```php
// Dentro da classe Template_Converter
private function process_coupon($cart_data, $email_data) {
    // Se já tem cupom do sistema Cart Abandonment
    if (!empty($email_data['coupon_code'])) {
        return $email_data['coupon_code'];
    }
    
    // Verificar se há cupom configurado para sequência WhatsApp
    $sequence_coupon = get_option('wpwevo_sequence_default_coupon', '');
    
    if (!empty($sequence_coupon)) {
        return $sequence_coupon;
    }
    
    // Gerar cupom dinâmico se configurado
    if (get_option('wpwevo_sequence_generate_coupon', false)) {
        return $this->generate_dynamic_coupon($cart_data);
    }
    
    return '';
}

private function generate_dynamic_coupon($cart_data) {
    $coupon_code = 'VOLTA' . substr(md5($cart_data['phone']), 0, 6);
    
    // Criar cupom WooCommerce
    $coupon = new \WC_Coupon();
    $coupon->set_code($coupon_code);
    $coupon->set_discount_type('percent');
    $coupon->set_amount(10); // 10% desconto
    $coupon->set_usage_limit(1);
    $coupon->set_usage_limit_per_user(1);
    $coupon->set_date_expires(date('Y-m-d', strtotime('+7 days')));
    $coupon->save();
    
    wpwevo_log('info', "Cupom gerado: {$coupon_code}");
    
    return $coupon_code;
}
```

**📋 ENTREGÁVEL FASE 2C:**
- Sistema completo de conversão funcionando
- Todos os 3 templates convertendo adequadamente
- Sistema de timing respeitando horários originais
- Sistema de cupons integrado e funcional
- Sistema de retry implementado

---

### 🖥️ **FASE 2D: INTERFACE E CONFIGURAÇÃO** *(Dias 13-14)*

#### 🎛️ **ETAPA 2D.1: Painel Admin** *(Dia 13)*

**🖥️ Interface Administrativa:**
```php
// Adicionar nova aba no admin
add_action('admin_menu', function() {
    add_submenu_page(
        'wpwevo-settings',
        'Sequência E-mails', 
        'Sequência E-mails',
        'manage_options',
        'wpwevo-email-sequence',
        'render_sequence_page'
    );
});

function render_sequence_page() {
    ?>
    <div class="wrap">
        <h1>Sequência de E-mails → WhatsApp</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('wpwevo_sequence_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Ativar Interceptação</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpwevo_email_sequence_enabled" value="1" 
                                   <?php checked(get_option('wpwevo_email_sequence_enabled', false)); ?>>
                            Interceptar e-mails do Cart Abandonment e enviar via WhatsApp
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Fallback para E-mail</th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpwevo_sequence_fallback_email" value="1" 
                                   <?php checked(get_option('wpwevo_sequence_fallback_email', true)); ?>>
                            Se WhatsApp falhar, manter envio do e-mail original
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Templates Personalizados</th>
                    <td>
                        <textarea name="wpwevo_sequence_template_1" rows="4" cols="50" placeholder="Template 1 (30min)..."><?php echo esc_textarea(get_option('wpwevo_sequence_template_1', '')); ?></textarea>
                        <br><small>Deixe vazio para usar template automático</small>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <!-- Preview de Conversão -->
        <div class="wpwevo-preview">
            <h3>Preview de Conversão</h3>
            <div id="email-preview" style="background: #f9f9f9; padding: 15px; margin: 10px 0;">
                <!-- Email original será mostrado aqui -->
            </div>
            
            <div id="whatsapp-preview" style="background: #25d366; color: white; padding: 15px; margin: 10px 0;">
                <!-- WhatsApp convertido será mostrado aqui -->
            </div>
        </div>
    </div>
    <?php
}
```

#### 📊 **ETAPA 2D.2: Logs e Relatórios** *(Dia 14)*

**📈 Dashboard com Estatísticas:**
```php
// Adicionar widget no dashboard principal
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'wpwevo_sequence_stats',
        'Sequência WhatsApp - Stats',
        'render_sequence_dashboard_widget'
    );
});

function render_sequence_dashboard_widget() {
    global $wpdb;
    
    // Buscar estatísticas dos últimos 30 dias
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(*) as total_intercepted,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_success,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as sent_failed
        FROM {$wpdb->prefix}wpwevo_logs 
        WHERE action = 'email_sequence' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    ?>
    <div class="wpwevo-stats">
        <h4>📊 Últimos 30 dias</h4>
        <p><strong>E-mails Interceptados:</strong> <?php echo $stats->total_intercepted ?? 0; ?></p>
        <p><strong>WhatsApp Enviados:</strong> <?php echo $stats->sent_success ?? 0; ?></p>
        <p><strong>Falhas:</strong> <?php echo $stats->sent_failed ?? 0; ?></p>
        
        <?php if ($stats->total_intercepted > 0): ?>
            <p><strong>Taxa de Sucesso:</strong> 
               <?php echo round(($stats->sent_success / $stats->total_intercepted) * 100, 1); ?>%
            </p>
        <?php endif; ?>
    </div>
    <?php
}
```

**📋 ENTREGÁVEL FASE 2D:**
- Interface administrativa completa e intuitiva
- Sistema de configurações persistentes
- Dashboard com estatísticas em tempo real
- Preview de conversão funcionando

---

### 🧪 **FASE 2E: TESTES E REFINAMENTO** *(Dias 15-16)*

#### ✅ **ETAPA 2E.1: Testes Funcionais** *(Dia 15)*

**🧪 Cenários de Teste:**
```php
// Arquivo: tests/sequence-tests.php (para desenvolvimento)
class SequenceTests {
    
    public function test_email_interception() {
        // Simular carrinho abandonado
        $cart_data = $this->create_test_cart();
        
        // Simular agendamento de e-mail
        $email_data = $this->schedule_test_email($cart_data);
        
        // Verificar se interceptação funciona
        $interceptor = new Email_Interceptor();
        $result = $interceptor->intercept_scheduled_email($email_data);
        
        $this->assertTrue($result, 'Interceptação deve funcionar');
    }
    
    public function test_template_conversion() {
        $converter = new Template_Converter();
        
        // Testar conversão template 1
        $whatsapp_message = $converter->template_reminder([
            'first_name' => 'João',
            'checkout_url' => 'https://example.com/checkout'
        ]);
        
        $this->assertContains('🛒 Oi João!', $whatsapp_message);
        $this->assertContains('https://example.com/checkout', $whatsapp_message);
    }
    
    public function test_timing_respect() {
        // Verificar se timing original é respeitado
        $scheduled_time = '2024-12-18 15:30:00';
        $email_data = ['scheduled_time' => $scheduled_time];
        
        $sequencer = new WhatsApp_Sequencer();
        $should_process = $sequencer->should_process_now($email_data);
        
        // Se ainda não chegou a hora, não deve processar
        if (strtotime($scheduled_time) > time()) {
            $this->assertFalse($should_process);
        }
    }
    
    public function test_coupon_generation() {
        $converter = new Template_Converter();
        $coupon = $converter->generate_dynamic_coupon(['phone' => '11999999999']);
        
        $this->assertNotEmpty($coupon);
        $this->assertStringStartsWith('VOLTA', $coupon);
        
        // Verificar se cupom foi criado no WooCommerce
        $wc_coupon = new WC_Coupon($coupon);
        $this->assertTrue($wc_coupon->get_id() > 0);
    }
}
```

**✅ Checklist de Testes:**
- [ ] Interceptação de e-mails funcionando
- [ ] Conversão de todos os 3 templates
- [ ] Timing sendo respeitado
- [ ] Cupons sendo gerados/aplicados
- [ ] Fallback para e-mail funcionando
- [ ] Logs sendo gravados corretamente
- [ ] Interface admin responsiva
- [ ] Compatibilidade com HPOS
- [ ] Performance aceitável

#### 🚀 **ETAPA 2E.2: Testes de Performance** *(Dia 16)*

**📊 Benchmarks de Performance:**
```php
// Teste de performance com múltiplos carrinhos
public function test_multiple_carts_performance() {
    $start_time = microtime(true);
    
    // Simular 100 carrinhos abandonados
    for ($i = 0; $i < 100; $i++) {
        $this->process_test_cart();
    }
    
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    // Performance deve ser < 30 segundos para 100 carrinhos
    $this->assertLessThan(30, $execution_time, 'Performance inadequada');
}

// Teste de memory usage
public function test_memory_usage() {
    $initial_memory = memory_get_usage();
    
    // Processar sequência
    $this->process_large_sequence();
    
    $final_memory = memory_get_usage();
    $memory_increase = $final_memory - $initial_memory;
    
    // Aumento de memória deve ser < 10MB
    $this->assertLessThan(10 * 1024 * 1024, $memory_increase);
}
```

**🎯 Métricas de Qualidade:**
- **Performance**: < 100ms por interceptação
- **Memory**: < 2MB adicional por funcionalidade
- **Taxa de Sucesso**: > 95% dos envios
- **Compatibilidade**: WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+

**📋 ENTREGÁVEL FASE 2E:**
- Sistema totalmente testado e validado
- Performance otimizada e dentro dos limites
- Documentação técnica completa
- Todos os cenários de uso funcionando

---

## 🎯 **ESPECIFICAÇÕES TÉCNICAS COMPLETAS**

### 📊 **Integração com Sistema Atual**
- **`Api_Connection`**: Reutilizar para envios da sequência
- **`Cart_Abandonment`**: Coexistir pacificamente (toggle de ativação)
- **Sistema de logs**: Expandir para incluir logs da sequência
- **Templates**: Reaproveitar estrutura de shortcodes

### 🚀 **Sinergia com Metabox**
- Metabox pode usar templates da sequência
- Logs compartilhados entre funcionalidades
- Interface administrativa consistente
- Sistema de validação unificado

### ⚠️ **Riscos e Mitigações**
1. **Plugin Cart Abandonment não ter hooks suficientes**
   - *Mitigação*: Usar interceptação de `wp_mail` como fallback
   
2. **Performance do WordPress Cron**
   - *Mitigação*: Implementar queue system próprio
   
3. **Rate limiting da Evolution API**
   - *Mitigação*: Throttling e retry logic

4. **Conflito com sistema existente de carrinho abandonado**
   - *Mitigação*: Implementar toggle para desativar carrinho atual

### 🛡️ **Pré-requisitos**
- [ ] **Metabox no Pedido** deve estar 100% concluído
- [ ] Plugin Cart Abandonment Recovery ativo
- [ ] Evolution API configurada e funcionando
- [ ] Telefones válidos nos dados dos clientes

### 📈 **ROI Esperado**
- **Conversão atual (só e-mail)**: ~15%
- **Conversão esperada (WhatsApp)**: ~35-45%
- [ ] **Aumento**: 2-3x na recuperação de carrinhos

---

*Plano atualizado em: 18/12/2024*
*Estimativa total: 16 dias úteis*
*Prioridade 1: 3 dias | Prioridade 2: 13 dias*
*Status: 📋 Pronto para execução* 