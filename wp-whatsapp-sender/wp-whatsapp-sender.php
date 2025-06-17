<?php
/**
 * Plugin Name: WP WhatsApp Sender
 * Plugin URI: https://seusite.com/wp-whatsapp-sender
 * Description: Plugin para enviar mensagens de WhatsApp para clientes diretamente do WordPress.
 * Version: 1.0.0
 * Author: WordPress Developer
 * Author URI: https://seusite.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-whatsapp-sender
 * Domain Path: /languages
 */

// Se este arquivo for chamado diretamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes para o plugin
define('WP_WHATSAPP_SENDER_VERSION', '1.0.0');
define('WP_WHATSAPP_SENDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_WHATSAPP_SENDER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * A classe principal do plugin.
 */
class WP_WhatsApp_Sender {

    /**
     * Instância da classe.
     *
     * @var WP_WhatsApp_Sender
     */
    protected static $instance = null;

    /**
     * Inicializa o plugin.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        
        // Limpa templates existentes de qualquer HTML
        self::clean_existing_templates();
    }

    /**
     * Retorna a instância da classe.
     *
     * @return WP_WhatsApp_Sender
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carrega as dependências do plugin.
     */
    private function load_dependencies() {
        // Carrega o arquivo com funções de utilidade
        require_once WP_WHATSAPP_SENDER_PLUGIN_DIR . 'includes/class-wp-whatsapp-sender-utils.php';

        // Carrega o arquivo para o envio de mensagens via WhatsApp
        require_once WP_WHATSAPP_SENDER_PLUGIN_DIR . 'includes/class-wp-whatsapp-sender-api.php';

        // Carrega o arquivo para a administração
        require_once WP_WHATSAPP_SENDER_PLUGIN_DIR . 'admin/class-wp-whatsapp-sender-admin.php';
        
        // Carrega o arquivo para integração com o WooCommerce
        require_once WP_WHATSAPP_SENDER_PLUGIN_DIR . 'includes/class-wp-whatsapp-sender-woocommerce.php';
        
        // Carrega o arquivo para envio em massa
        require_once WP_WHATSAPP_SENDER_PLUGIN_DIR . 'admin/class-wp-whatsapp-sender-bulk.php';
    }

    /**
     * Define os ganchos administrativos.
     */
    private function define_admin_hooks() {
        $plugin_admin = new WP_WhatsApp_Sender_Admin();
        
        // Adiciona o menu de administração
        add_action('admin_menu', array($plugin_admin, 'add_admin_menu'));
        
        // Registra as configurações
        add_action('admin_init', array($plugin_admin, 'register_settings'));
        
        // Inicializa o módulo de envio em massa
        new WP_WhatsApp_Sender_Bulk();
        
        // Adiciona o handler para obter templates via AJAX
        add_action('wp_ajax_wp_whatsapp_sender_get_template', array($this, 'ajax_get_template'));
    }
    
    /**
     * Processa a requisição AJAX para obter template.
     */
    public function ajax_get_template() {
        // Verifica o nonce
        check_ajax_referer('wp_whatsapp_sender_get_template', 'security');
        
        // Verifica as permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Você não tem permissão para realizar esta ação.');
        }
        
        // Obtém os dados
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($template_name)) {
            wp_send_json_error('Nome do template não informado.');
        }
        
        // Obtém o conteúdo do template
        $template_content = get_option('wp_whatsapp_sender_template_' . $template_name, '');
        
        if (empty($template_content)) {
            wp_send_json_error('Template não encontrado.');
        }
        
        // Processa variáveis do pedido, se aplicável
        if ($order_id && class_exists('WooCommerce')) {
            $order = wc_get_order($order_id);
            if ($order) {
                // Obtém os dados do pedido para substituir no template
                $variables = array(
                    '{{order_id}}' => $order->get_order_number(),
                    '{{customer_name}}' => $order->get_billing_first_name(),
                    '{{customer_full_name}}' => $order->get_formatted_billing_full_name(),
                    '{{order_total}}' => wc_price($order->get_total(), array('html' => false)),
                    '{{order_status}}' => wc_get_order_status_name($order->get_status()),
                    '{{order_date}}' => $order->get_date_created()->date_i18n(get_option('date_format')),
                    '{{shop_name}}' => get_bloginfo('name')
                );
                
                $template_content = str_replace(array_keys($variables), array_values($variables), $template_content);
            }
        }
        
        wp_send_json_success($template_content);
    }

    /**
     * Código executado na ativação do plugin.
     */
    public static function activate() {
        // Verifica requisitos do plugin
        if (version_compare(PHP_VERSION, '7.2', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Este plugin requer PHP 7.2 ou superior.');
        }
        
        // Configurações padrão
        add_option('wp_whatsapp_sender_api_key', '');
        add_option('wp_whatsapp_sender_api_url', '');
        add_option('wp_whatsapp_sender_api_phone', '');
        add_option('wp_whatsapp_sender_instance_name', '');
        
        // Templates de exemplo
        add_option('wp_whatsapp_sender_template_boas_vindas', 'Olá {{customer_name}}, seja bem-vindo(a) à {{shop_name}}! Estamos à disposição para ajudar.');
        add_option('wp_whatsapp_sender_template_cupom', 'Olá {{customer_name}}, como agradecimento pela sua fidelidade, estamos enviando um cupom de desconto para sua próxima compra: {{coupon_code}}. Este cupom oferece {{coupon_value}} de desconto e é válido até {{coupon_expiry}}. Aproveite!');
        add_option('wp_whatsapp_sender_template_aniversario', 'Feliz aniversário, {{customer_name}}! Para comemorar esta data especial, preparamos um presente para você: use o cupom {{coupon_code}} e ganhe {{coupon_value}} de desconto em sua próxima compra. Válido até {{coupon_expiry}}. Desejamos muitas felicidades!');
        
        // Templates padrão para status de pedido
        $default_order_status_templates = array(
            'pending' => 'Olá {{customer_name}}, recebemos seu pedido #{{order_id}} no valor de {{order_total}}. Estamos aguardando a confirmação do pagamento para dar continuidade. Obrigado por comprar conosco!',
            'processing' => 'Olá {{customer_name}}, o pagamento do seu pedido #{{order_id}} foi aprovado e já estamos preparando tudo para o envio. Em breve você receberá atualizações sobre o envio. Obrigado pela confiança!',
            'on-hold' => 'Olá {{customer_name}}, seu pedido #{{order_id}} está temporariamente em espera. Entraremos em contato para resolver qualquer pendência. Para mais informações, entre em contato conosco.',
            'completed' => 'Olá {{customer_name}}, seu pedido #{{order_id}} foi concluído com sucesso! Esperamos que tenha gostado dos produtos. Agradecemos a preferência e estamos à disposição para o que precisar.',
            'cancelled' => 'Olá {{customer_name}}, informamos que seu pedido #{{order_id}} foi cancelado conforme solicitado. Esperamos ter a oportunidade de atendê-lo em breve!',
            'refunded' => 'Olá {{customer_name}}, confirmamos o reembolso do seu pedido #{{order_id}} no valor de {{order_total}}. O valor será creditado de acordo com a forma de pagamento utilizada. Estamos à disposição!',
            'failed' => 'Olá {{customer_name}}, identificamos um problema com o pagamento do seu pedido #{{order_id}}. Por favor, entre em contato conosco para resolvermos esta questão.'
        );
        
        add_option('wp_whatsapp_sender_order_status_templates', $default_order_status_templates);
        
        // Por padrão, habilitar o envio automático para todos os status que têm template
        add_option('wp_whatsapp_sender_auto_send_status', array_keys($default_order_status_templates));
        
        // Limpa templates existentes de qualquer HTML
        self::clean_existing_templates();
        
        // Garante que as regras de reescrita sejam atualizadas
        global $wp_rewrite;
        $wp_rewrite->flush_rules(true);
        
        // Limpa o cache de reescrita
        flush_rewrite_rules();
    }

    /**
     * Limpa templates existentes de qualquer HTML.
     */
    private static function clean_existing_templates() {
        // Obtém todos os templates de status de pedido
        $order_status_templates = get_option('wp_whatsapp_sender_order_status_templates', array());
        $updated = false;
        
        // Processa cada template para remover HTML
        foreach ($order_status_templates as $status => $template) {
            $clean_template = strip_tags($template);
            $clean_template = html_entity_decode($clean_template);
            
            if ($clean_template !== $template) {
                $order_status_templates[$status] = $clean_template;
                $updated = true;
            }
        }
        
        // Salva os templates limpos se houver alterações
        if ($updated) {
            update_option('wp_whatsapp_sender_order_status_templates', $order_status_templates);
        }
        
        // Obtém todos os templates gerais
        $all_options = wp_load_alloptions();
        
        foreach ($all_options as $option => $value) {
            if (strpos($option, 'wp_whatsapp_sender_template_') === 0) {
                $clean_value = strip_tags($value);
                $clean_value = html_entity_decode($clean_value);
                
                if ($clean_value !== $value) {
                    update_option($option, $clean_value);
                }
            }
        }
    }

    /**
     * Código executado na desativação do plugin.
     */
    public static function deactivate() {
        // Limpa o cache de reescrita
        flush_rewrite_rules();
    }
}

// Ganchos de ativação e desativação
register_activation_hook(__FILE__, array('WP_WhatsApp_Sender', 'activate'));
register_deactivation_hook(__FILE__, array('WP_WhatsApp_Sender', 'deactivate'));

// Inicializa o plugin
function run_wp_whatsapp_sender() {
    return WP_WhatsApp_Sender::get_instance();
}

// Executa o plugin
run_wp_whatsapp_sender(); 