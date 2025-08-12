<?php
namespace WpWhatsAppEvolution;

if (!defined('ABSPATH')) {
    exit;
}

class AI_Agent {
    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_menu']);
        // Remove configuração legada de rodapé custom para não exibir "Desenvolvido por ..."
        add_action('init', [$this, 'cleanup_legacy_footer_option'], 20);

        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'conditionally_enqueue_front_assets']);

        // AJAX proxy (logged and guest)
        add_action('wp_ajax_wpwevo_ai_proxy', [$this, 'handle_ai_proxy']);
        add_action('wp_ajax_nopriv_wpwevo_ai_proxy', [$this, 'handle_ai_proxy']);

        // Shortcodes
        add_shortcode('wpwevo_ai_chat', [$this, 'shortcode_chat']);
        add_shortcode('wpwevo_ai_form', [$this, 'shortcode_form']);
    }

    public function cleanup_legacy_footer_option() {
        // Garantir que não enviamos mais rodapé custom
        if (get_option('wpwevo_ai_footer_text', '') !== '') {
            delete_option('wpwevo_ai_footer_text');
        }
    }

    public function register_settings() {
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_webhook_url', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ]);

        register_setting('wpwevo_ai_agent', 'wpwevo_ai_auto_inject_chat', [
            'type' => 'boolean',
            'sanitize_callback' => function ($val) { return (bool)$val; },
            'default' => false
        ]);

        register_setting('wpwevo_ai_agent', 'wpwevo_ai_welcome_message', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __('Olá! 👋 Como posso ajudar hoje?', 'wp-whatsevolution')
        ]);

        // UI texts (widget i18n)
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_title', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Olá! 👋'
        ]);
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_subtitle', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Inicie um bate-papo. Estamos aqui para ajudar você 24 horas por dia, 7 dias por semana.'
        ]);
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_input_placeholder', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Digite sua pergunta...'
        ]);
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_get_started', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'Nova conversa'
        ]);

        // Color scheme
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_color_mode', [
            'type' => 'string',
            'sanitize_callback' => function($v){ $v = in_array($v, ['default','custom'], true) ? $v : 'default'; return $v; },
            'default' => 'default'
        ]);
        register_setting('wpwevo_ai_agent', 'wpwevo_ai_primary_color', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => ''
        ]);
    }

    public function add_menu() {
        add_submenu_page(
            'wpwevo-settings',
            __('Agente de IA', 'wp-whatsevolution'),
            __('Agente de IA', 'wp-whatsevolution'),
            'manage_options',
            'wpwevo-ai-agent',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $webhook = get_option('wpwevo_ai_webhook_url', '');
        $auto_inject = (bool)get_option('wpwevo_ai_auto_inject_chat', false);
        $welcome = get_option('wpwevo_ai_welcome_message', __('Olá! 👋 Como posso ajudar hoje?', 'wp-whatsevolution'));
        $title = get_option('wpwevo_ai_title', 'Olá! 👋');
        $subtitle = get_option('wpwevo_ai_subtitle', 'Inicie um bate-papo. Estamos aqui para ajudar você 24 horas por dia, 7 dias por semana.');
        $input_ph = get_option('wpwevo_ai_input_placeholder', 'Digite sua pergunta...');
        $get_started = get_option('wpwevo_ai_get_started', 'Nova conversa');
        // Mantido para compatibilidade, mas não é mais usado: modo de cor
        $color_mode = get_option('wpwevo_ai_color_mode', 'default');
        $primary_color = get_option('wpwevo_ai_primary_color', '');
        $footer_text = get_option('wpwevo_ai_footer_text', '');
        ?>
        <div class="wrap wpwevo-panel" style="max-width: none; width: 100%;">
            <style>
            /* Webhook textarea auto-grow */
            #wpwevo-ai-webhook-url {
                width: 100% !important;
                min-height: 40px !important;
                resize: none !important;
                overflow: hidden !important;
                padding: 8px 12px !important;
                border: 1px solid #d2d6dc !important;
                border-radius: 6px !important;
                font-family: inherit !important; /* igual aos demais */
                font-size: 14px !important;
                line-height: 1.4 !important;
                box-sizing: border-box !important;
            }
            
            </style>
            <h1>🤖 <?php echo esc_html(__('Agente de IA', 'wp-whatsevolution')); ?></h1>

            <div style="margin:16px 0 24px 0; padding:16px 20px; border-left:6px solid #f59e0b; background:#fffbeb; border-radius:8px;">
                <div style="font-size:16px; color:#92400e; font-weight:700; margin-bottom:6px;">⚠️ <?php echo esc_html(__('Importante', 'wp-whatsevolution')); ?></div>
                <div style="font-size:15px; color:#78350f; line-height:1.5;">
                    <?php echo esc_html(__('Para usar respostas do Agente de IA, você precisa de um fluxo n8n para receber o webhook.', 'wp-whatsevolution')); ?>
                    <a href="https://github.com/RelaxSolucoes/Fluxo-Wordpress-IA" target="_blank" rel="noopener noreferrer" style="font-weight:700; color:#b45309; text-decoration: underline;">
                        <?php echo esc_html(__('Veja o exemplo de fluxo de trabalho neste link', 'wp-whatsevolution')); ?>
                    </a>.
                </div>
            </div>
            <form method="post" action="options.php" style="margin-top: 15px;">
                <?php settings_fields('wpwevo_ai_agent'); ?>
                <div style="display: grid; gap: 16px;">
                    <div style="background: #f7fafc; padding: 16px; border-left: 4px solid #667eea; border-radius: 8px;">
                        <label style="display:block; font-weight: 600; margin-bottom: 6px;">
                            🌐 <?php echo esc_html(__('Webhook do n8n', 'wp-whatsevolution')); ?>
                        </label>
                        <textarea id="wpwevo-ai-webhook-url" name="wpwevo_ai_webhook_url" rows="1" placeholder="https://seu-n8n/webhook/ID" required><?php echo esc_textarea($webhook); ?></textarea>
                        <script>
                        document.addEventListener('DOMContentLoaded', function(){
                            const ta = document.getElementById('wpwevo-ai-webhook-url');
                            if(!ta) return;
                            const adjust = ()=>{ ta.style.height='auto'; ta.style.height=Math.max(40, ta.scrollHeight)+'px'; };
                            adjust();
                            ta.addEventListener('input', adjust);
                            ta.addEventListener('paste', ()=> setTimeout(adjust, 10));
                        });
                        </script>
                        <p style="margin:8px 0 0 0; color:#4a5568; font-size:12px;">
                            <?php echo esc_html(__('URL pública do webhook que recebe as mensagens do widget/formulário.', 'wp-whatsevolution')); ?>
                        </p>
                    </div>

                    <div style="background: #f7fafc; padding: 16px; border-left: 4px solid #667eea; border-radius: 8px;">
                        <label style="display:block; font-weight: 600; margin-bottom: 6px;">
                            💬 <?php echo esc_html(__('Mensagem de boas-vindas', 'wp-whatsevolution')); ?>
                        </label>
                        <textarea name="wpwevo_ai_welcome_message" rows="2" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; resize: vertical;" placeholder="<?php echo esc_attr(__('Olá! 👋 Como posso ajudar hoje?', 'wp-whatsevolution')); ?>"><?php echo esc_textarea($welcome); ?></textarea>
                    </div>

                    <div style="background: #f7fafc; padding: 16px; border-left: 4px solid #667eea; border-radius: 8px; display:grid; gap:10px;">
                        <div>
                            <label style="display:block; font-weight: 600; margin-bottom: 6px;">🧩 <?php echo esc_html(__('Textos do Widget', 'wp-whatsevolution')); ?></label>
                        </div>
                        <div style="display:grid; gap:10px; grid-template-columns:1fr 1fr;">
                            <div>
                                <small style="display:block; color:#4a5568; margin-bottom:4px;">Título</small>
                                <input type="text" name="wpwevo_ai_title" value="<?php echo esc_attr($title); ?>" style="width:100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                            <div>
                                <small style="display:block; color:#4a5568; margin-bottom:4px;">Botão “New conversation”</small>
                                <input type="text" name="wpwevo_ai_get_started" value="<?php echo esc_attr($get_started); ?>" style="width:100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            </div>
                        </div>
                        <div>
                            <small style="display:block; color:#4a5568; margin-bottom:4px;">Subtítulo</small>
                            <textarea name="wpwevo_ai_subtitle" rows="2" style="width:100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; resize: vertical;"><?php echo esc_textarea($subtitle); ?></textarea>
                        </div>
                        <div>
                            <small style="display:block; color:#4a5568; margin-bottom:4px;">Placeholder do input</small>
                            <input type="text" name="wpwevo_ai_input_placeholder" value="<?php echo esc_attr($input_ph); ?>" style="width:100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        </div>
                        <!-- Rodapé opcional removido a pedido do usuário -->
                    </div>

                    <div style="background: #f7fafc; padding: 16px; border-left: 4px solid #667eea; border-radius: 8px; display:flex; gap: 10px; align-items:center;">
                        <input type="checkbox" id="wpwevo_ai_auto_inject_chat" name="wpwevo_ai_auto_inject_chat" value="1" <?php checked($auto_inject, true); ?> />
                        <label for="wpwevo_ai_auto_inject_chat" style="margin:0;"><?php echo esc_html(__('Injetar o widget de chat automaticamente no site (footer)', 'wp-whatsevolution')); ?></label>
                    </div>

                    <div style="background: #f7fafc; padding: 16px; border-left: 4px solid #667eea; border-radius: 8px; display:grid; gap:10px;">
                        <label style="display:block; font-weight:600;">🎨 <?php echo esc_html(__('Cor do Widget', 'wp-whatsevolution')); ?> <span style="font-weight:400; color:#64748b;">(<?php echo esc_html(__('opcional', 'wp-whatsevolution')); ?>)</span></label>
                        <?php $custom_color = get_option('wpwevo_ai_primary_color',''); ?>
                        <input type="color" name="wpwevo_ai_primary_color" value="<?php echo esc_attr($custom_color); ?>" style="width:56px; height:36px; padding:0; border: none; background: transparent;" />
                        <small style="color:#4a5568;"><?php echo esc_html(__('Deixe em branco para usar o padrão do n8n.', 'wp-whatsevolution')); ?></small>
                    </div>
                </div>

                <p style="margin-top: 12px;">
                    <button type="submit" class="button button-primary">💾 <?php echo esc_html(__('Salvar', 'wp-whatsevolution')); ?></button>
                </p>
            </form>

            <div style="margin-top: 24px; background:#f0f5ff; padding: 16px; border-radius:8px;">
                <strong><?php echo esc_html(__('Shortcodes disponíveis:', 'wp-whatsevolution')); ?></strong>
                <div style="margin-top:8px; display:grid; gap:6px;">
                    <code>[wpwevo_ai_chat]</code>
                    <code>[wpwevo_ai_form]</code>
                </div>
                <p style="margin-top:8px; color:#4a5568; font-size:12px;">
                    <?php echo esc_html(__('Para usar o formulário, insira o shortcode [wpwevo_ai_form] na página desejada.', 'wp-whatsevolution')); ?>
                </p>
            </div>
        </div>
        <?php
    }

    public function conditionally_enqueue_front_assets() {
        $auto_inject = (bool)get_option('wpwevo_ai_auto_inject_chat', false);
        $webhook = get_option('wpwevo_ai_webhook_url', '');
        if (!$auto_inject || empty($webhook)) {
            return;
        }

        // CSS do widget oficial
        wp_enqueue_style(
            'n8n-chat-style',
            'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css',
            [],
            '0.50.0'
        );

        // Adiciona container + script module no footer
        add_action('wp_footer', function () use ($webhook) {
            $welcome = get_option('wpwevo_ai_welcome_message', __('Olá! 👋 Como posso ajudar hoje?', 'wp-whatsevolution'));
            $title = get_option('wpwevo_ai_title', 'Olá! 👋');
            $subtitle = get_option('wpwevo_ai_subtitle', 'Inicie um bate-papo. Estamos aqui para ajudar você 24 horas por dia, 7 dias por semana.');
            $input_ph = get_option('wpwevo_ai_input_placeholder', 'Digite sua pergunta...');
            $get_started = get_option('wpwevo_ai_get_started', 'Nova conversa');
            $primary_color = get_option('wpwevo_ai_primary_color', '');
            $footer_text = get_option('wpwevo_ai_footer_text', '');
            $ajax_url = admin_url('admin-ajax.php');
            echo '<div id="n8n-chat"></div>';
            ?>
            <script type="module">
                import { createChat } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/chat.bundle.es.js';

                // Usamos proxy AJAX para não expor o webhook
                const webhookUrl = '<?php echo esc_js($ajax_url); ?>?action=wpwevo_ai_proxy';

                // Define cor primária apenas se o usuário escolheu uma cor
                let primary = '';
                <?php if (!empty($primary_color)) : ?>
                primary = <?php echo wp_json_encode($primary_color); ?>;
                <?php endif; ?>

                // Aplica fallback de cor via CSS variables (cobre múltiplos nomes usados por temas/libs)
                if (primary) {
                    const css = `:root{--chat--color-primary:${primary};--chat--toggle--background:${primary};--chat--toggle--background-hover:${primary};--chat--button--background:${primary};--chat--button--background-hover:${primary};--n8n-chat--color-primary:${primary};}`;
                    const st = document.createElement('style');
                    st.setAttribute('data-wpwevo-ai-theme','1');
                    st.textContent = css;
                    document.head.appendChild(st);
                }

                const widget = createChat({
                    webhookUrl,
                    mode: 'window',
                    target: '#n8n-chat',
                    showWelcomeScreen: true,
                    initialMessages: [<?php echo wp_json_encode($welcome); ?>],
                    enableStreaming: false,
                    loadPreviousSession: true,
                    theme: primary ? { tokens: { colorPrimary: primary } } : undefined,
                    metadata: {
                        source: 'n8n_chat_widget',
                        sourceType: 'chat_widget',
                        page_url: window.location.href,
                        page_title: document.title,
                        user_agent: navigator.userAgent,
                        timestamp: new Date().toISOString(),
                        responseConfig: { shouldRespond: true, responseTarget: 'chat_widget' }
                    },
                    webhookConfig: {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    },
                    chatInputKey: 'chatInput',
                    chatSessionKey: 'sessionId',
                    i18n: {
                        en: {
                            title: <?php echo wp_json_encode((string)$title); ?>,
                            subtitle: <?php echo wp_json_encode((string)$subtitle); ?>,
                            getStarted: <?php echo wp_json_encode((string)$get_started); ?>,
                            inputPlaceholder: <?php echo wp_json_encode((string)$input_ph); ?>
                        }
                    }
                });

                window.n8nChat = widget;
            </script>
            <?php
        }, 100);
    }

    public function handle_ai_proxy() {
        // Lê JSON bruto
        $raw = file_get_contents('php://input');
        $payload = [];
        if (!empty($raw) && wpwevo_is_json($raw)) {
            $payload = json_decode($raw, true);
        } else {
            // Fallback para POST comum
            $payload = $_POST;
        }

        $webhook = get_option('wpwevo_ai_webhook_url', '');
        if (empty($webhook)) {
            wp_send_json_error(['message' => __('Webhook do n8n não configurado.', 'wp-whatsevolution')], 400);
        }

        // Anexa metadados adicionais
        $payload = is_array($payload) ? $payload : [];
        $payload['metadata'] = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];
        $payload['metadata'] = array_merge($payload['metadata'], [
            'wordpress' => true,
            'site_url' => home_url(),
            'ajax_proxy' => true,
        ]);

        // Caso seja formulário, injeta provider e formata chatInput para a IA
        if (isset($payload['channel']) && $payload['channel'] === 'web_form') {
            $name = isset($payload['pushName']) ? sanitize_text_field($payload['pushName']) : '';
            if (empty($name) && isset($payload['contact']['nome'])) {
                $name = sanitize_text_field($payload['contact']['nome']);
            }
            $email = isset($payload['contact']['email']) ? sanitize_email($payload['contact']['email']) : '';
            $phoneRaw = isset($payload['contact']['telefone']) ? $payload['contact']['telefone'] : '';
            $phoneE164 = function_exists('WpWhatsAppEvolution\\wpwevo_validate_phone') ? \WpWhatsAppEvolution\wpwevo_validate_phone($phoneRaw) : preg_replace('/[^0-9]/', '', (string)$phoneRaw);
            if (!empty($phoneE164)) {
                $jid = $phoneE164 . '@s.whatsapp.net';
                if (empty($payload['sessionId'])) { $payload['sessionId'] = $jid; }
                if (empty($payload['remoteJid'])) { $payload['remoteJid'] = $jid; }
            }

            $page = isset($payload['metadata']['page_url']) ? $payload['metadata']['page_url'] : home_url();
            $date = date_i18n('d/m/Y H:i');
            $originalMsg = isset($payload['chatInput']) ? (string)$payload['chatInput'] : '';

            $formatted = "**Nova solicitação via formulário:**\n\n" .
                "**Nome:** " . ($name ?: '-') . "\n" .
                "**E-mail:** " . ($email ?: '-') . "\n" .
                "**Telefone:** " . ($phoneE164 ?: '-') . "\n" .
                "**Assunto:** \n\n" .
                "**Mensagem:**\n" . $originalMsg . "\n\n" .
                "**Página:** " . $page . "\n" .
                "**Data:** " . $date;
            $payload['chatInput'] = $formatted;

            // Injeta provider seguro do servidor (não expõe no front)
            $instance = get_option('wpwevo_instance', '');
            $server = get_option('wpwevo_api_url', '');
            $apiKey = \WpWhatsAppEvolution\Api_Connection::get_active_api_key();
            if (!empty($instance) && !empty($server) && !empty($apiKey)) {
                $payload['provider'] = [
                    'instanceName' => $instance,
                    'serverUrl' => rtrim($server, '/'),
                    'apiKey' => $apiKey,
                ];
            }
        }

        $response = wp_remote_post($webhook, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => wp_json_encode($payload),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()], 500);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Encaminha a resposta do n8n
        status_header($code);
        header('Content-Type: application/json; charset=utf-8');
        echo $body ? $body : wp_json_encode(['success' => ($code >= 200 && $code < 300)]);
        wp_die();
    }

    public function shortcode_chat($atts) {
        $atts = shortcode_atts([
            'mode' => 'window'
        ], $atts);

        // Garante que CSS esteja carregado para o shortcode
        wp_enqueue_style('n8n-chat-style', 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css', [], '0.50.0');

        $ajax_url = admin_url('admin-ajax.php');
        $welcome = get_option('wpwevo_ai_welcome_message', __('Olá! 👋 Como posso ajudar hoje?', 'wp-whatsevolution'));
        $title = get_option('wpwevo_ai_title', 'Olá! 👋');
        $subtitle = get_option('wpwevo_ai_subtitle', 'Inicie um bate-papo. Estamos aqui para ajudar você 24 horas por dia, 7 dias por semana.');
        $input_ph = get_option('wpwevo_ai_input_placeholder', 'Digite sua pergunta...');
        $get_started = get_option('wpwevo_ai_get_started', 'Nova conversa');
        $color_mode = get_option('wpwevo_ai_color_mode', 'auto');
        $primary_color = get_option('wpwevo_ai_primary_color', '');

        ob_start();
        ?>
        <div id="n8n-chat-shortcode"></div>
        <script type="module">
            import { createChat } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/chat.bundle.es.js';
            const webhookUrl = '<?php echo esc_js($ajax_url); ?>?action=wpwevo_ai_proxy';
            // Define cor primária somente no modo "Personalizada"
            let primary = '';
            <?php if ($color_mode === 'custom' && !empty($primary_color)) : ?>
            primary = <?php echo wp_json_encode($primary_color); ?>;
            <?php endif; ?>

            // Fallback CSS variables se personalizada
            if (primary) {
                const css = `:root{--chat--color-primary:${primary};--chat--toggle--background:${primary};--chat--toggle--background-hover:${primary};--chat--button--background:${primary};--chat--button--background-hover:${primary};--n8n-chat--color-primary:${primary};}`;
                const st = document.createElement('style');
                st.setAttribute('data-wpwevo-ai-theme','1');
                st.textContent = css;
                document.head.appendChild(st);
            }

            const widget = createChat({
                webhookUrl,
                mode: <?php echo wp_json_encode($atts['mode']); ?>,
                target: '#n8n-chat-shortcode',
                showWelcomeScreen: true,
                initialMessages: [<?php echo wp_json_encode($welcome); ?>],
                enableStreaming: false,
                loadPreviousSession: true,
                theme: primary ? { tokens: { colorPrimary: primary } } : undefined,
                metadata: {
                    source: 'n8n_chat_widget',
                    sourceType: 'chat_widget',
                    page_url: window.location.href,
                    page_title: document.title,
                    user_agent: navigator.userAgent,
                    timestamp: new Date().toISOString(),
                },
                webhookConfig: {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }
                },
                chatInputKey: 'chatInput',
                chatSessionKey: 'sessionId',
                i18n: {
                    en: {
                        title: <?php echo wp_json_encode((string)$title); ?>,
                        subtitle: <?php echo wp_json_encode((string)$subtitle); ?>,
                        getStarted: <?php echo wp_json_encode((string)$get_started); ?>,
                        inputPlaceholder: <?php echo wp_json_encode((string)$input_ph); ?>
                    }
                }
            });
            window.n8nChat = widget;
        </script>
        <?php
        return ob_get_clean();
    }

    public function shortcode_form($atts) {
        // Formulário com opções de personalização simples
        $atts = shortcode_atts([
            'title' => __('Fale com nosso Assistente', 'wp-whatsevolution'),
            'button' => __('Enviar', 'wp-whatsevolution'),
            'show_phone' => 'true'
        ], $atts);

        $ajax_url = admin_url('admin-ajax.php');
        $show_phone = strtolower($atts['show_phone']) !== 'false';
        $validation_nonce = wp_create_nonce('wpwevo_validate_checkout');
        // Campo Assunto removido para simplificar uso no editor. Mantemos valor vazio no payload.

        ob_start();
        ?>
        <div class="wpwevo-ai-form" style="max-width:700px;margin:30px auto;padding:24px;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 18px rgba(0,0,0,0.04)">
            <h3 style="margin:0 0 16px 0;">🤖 <?php echo esc_html($atts['title']); ?></h3>
            <form id="wpwevo-ai-form-el">
                <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr;">
                    <div>
                        <label><?php echo esc_html(__('Nome *', 'wp-whatsevolution')); ?></label>
                        <input required name="nome" style="width:100%;padding:10px;border:1px solid #cbd5e0;border-radius:8px" />
                    </div>
                    <div>
                        <label><?php echo esc_html(__('E-mail *', 'wp-whatsevolution')); ?></label>
                        <input required type="email" name="email" style="width:100%;padding:10px;border:1px solid #cbd5e0;border-radius:8px" />
                    </div>
                    <?php if ($show_phone): ?>
                    <div>
                        <label><?php echo esc_html(__('Telefone *', 'wp-whatsevolution')); ?></label>
                        <input required name="telefone" id="wpwevo-ai-phone" style="width:100%;padding:10px;border:1px solid #cbd5e0;border-radius:8px" />
                        <small id="wpwevo-ai-phone-ok" style="color:#16a34a;display:none"><?php echo esc_html(__('✓ Número de WhatsApp válido', 'wp-whatsevolution')); ?></small>
                        <small id="wpwevo-ai-phone-error" style="color:#e53e3e;display:none"></small>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" name="assunto" value="" />
                </div>
                <div style="margin-top:12px;">
                    <label><?php echo esc_html(__('Mensagem *', 'wp-whatsevolution')); ?></label>
                    <textarea required name="mensagem" rows="4" style="width:100%;padding:10px;border:1px solid #cbd5e0;border-radius:8px"></textarea>
                </div>
                <button type="submit" id="wpwevo-ai-form-btn" style="margin-top:12px;background:#667eea;color:#fff;border:none;padding:12px 16px;border-radius:8px;cursor:pointer"><?php echo esc_html($atts['button']); ?></button>
            </form>
            <div id="wpwevo-ai-form-resp" style="display:none;margin-top:12px;padding:12px;border-radius:8px"></div>
        </div>
        <script>
        (function(){
            const form = document.getElementById('wpwevo-ai-form-el');
            const btn = document.getElementById('wpwevo-ai-form-btn');
            const resp = document.getElementById('wpwevo-ai-form-resp');
            const proxyUrl = '<?php echo esc_js($ajax_url); ?>?action=wpwevo_ai_proxy';
            const validateUrl = '<?php echo esc_js($ajax_url); ?>';
            const validateNonce = '<?php echo esc_js($validation_nonce); ?>';

            // Validação em tempo real do telefone
            const phoneInput = document.getElementById('wpwevo-ai-phone');
            const phoneOk = document.getElementById('wpwevo-ai-phone-ok');
            const phoneErr = document.getElementById('wpwevo-ai-phone-error');
            let phoneValid = false;

            function debounce(fn, wait){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,args), wait); }; }
            async function validatePhoneRealtime(){
                if (!phoneInput) return;
                const number = (phoneInput.value||'').trim();
                phoneOk.style.display = 'none';
                phoneErr.style.display = 'none';
                phoneValid = false;
                if (number.length < 8) { btn.disabled = true; return; }
                try{
                    const body = new URLSearchParams({ action:'wpwevo_validate_checkout_number', nonce: validateNonce, number });
                    const r = await fetch(validateUrl, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() });
                    const j = await r.json();
                    if (j && j.success){
                        phoneValid = true;
                        phoneOk.style.display = 'inline';
                        btn.disabled = false;
                    } else {
                        phoneErr.textContent = (j && j.message) ? j.message : '<?php echo esc_js(__('⚠ Este número não possui WhatsApp', 'wp-whatsevolution')); ?>';
                        phoneErr.style.display = 'inline';
                        btn.disabled = true;
                    }
                }catch(e){ btn.disabled = true; }
            }
            if (phoneInput){
                const run = debounce(validatePhoneRealtime, 400);
                phoneInput.addEventListener('input', run);
                phoneInput.addEventListener('blur', run);
                btn.disabled = true;
            }

            function jidFromPhone(phone){
                if(!phone) return 'form_'+Date.now();
                let p = (''+phone).replace(/[^0-9]/g,'');
                if(!p.startsWith('55')) p = '55'+p;
                return p + '@s.whatsapp.net';
            }

            form.addEventListener('submit', async (e)=>{
                e.preventDefault();
                btn.disabled = true;
                resp.style.display = 'none';
                const fd = new FormData(form);
                const data = Object.fromEntries(fd.entries());

                // Validação obrigatória do telefone via AJAX do Checkout Validator
                const phoneErrorEl = document.getElementById('wpwevo-ai-phone-error');
                const phoneOkEl = document.getElementById('wpwevo-ai-phone-ok');
                if (phoneErrorEl) { phoneErrorEl.style.display='none'; phoneErrorEl.textContent=''; }
                if (phoneOkEl) { phoneOkEl.style.display='none'; }
                if (!data.telefone || !data.telefone.trim()) {
                    if (phoneErrorEl) { phoneErrorEl.textContent = '<?php echo esc_js(__('Telefone é obrigatório.', 'wp-whatsevolution')); ?>'; phoneErrorEl.style.display='block'; }
                    btn.disabled = false;
                    return;
                }
                try {
                    const body = new URLSearchParams({ action: 'wpwevo_validate_checkout_number', nonce: validateNonce, number: data.telefone });
                    const vr = await fetch(validateUrl, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
                    const vj = await vr.json();
                    if (!vj || !vj.success) {
                        if (phoneErrorEl) { phoneErrorEl.textContent = (vj && vj.message) ? vj.message : '<?php echo esc_js(__('Número inválido ou sem WhatsApp.', 'wp-whatsevolution')); ?>'; phoneErrorEl.style.display='block'; }
                        btn.disabled = false;
                        return; // bloqueia envio
                    }
                    if (phoneOkEl) { phoneOkEl.style.display='inline'; }
                } catch (err) {
                    if (phoneErrorEl) { phoneErrorEl.textContent = '<?php echo esc_js(__('Falha ao validar o telefone. Tente novamente.', 'wp-whatsevolution')); ?>'; phoneErrorEl.style.display='block'; }
                    btn.disabled = false;
                    return; // bloqueia envio
                }

                const payload = {
                    channel: 'web_form',
                    chatInput: data.mensagem,
                    sessionId: jidFromPhone(data.telefone),
                    remoteJid: jidFromPhone(data.telefone),
                    pushName: data.nome,
                    fromMe: false,
                    contact: { nome: data.nome, email: data.email, telefone: data.telefone||null },
                    metadata: { page_url: window.location.href, page_title: document.title, timestamp: new Date().toISOString(), user_agent: navigator.userAgent }
                };

                try{
                    const r = await fetch(proxyUrl, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
                    const ok = r.ok;
                    const json = await r.json().catch(()=>({}));
                    resp.style.display = 'block';
                    if(ok){
                        const hasPhone = !!(data.telefone && data.telefone.trim());
                        resp.style.background = hasPhone? 'rgba(76,175,80,0.1)':'rgba(255,193,7,0.1)';
                        resp.style.border = '1px solid '+(hasPhone? '#4CAF50':'#FFC107');
                        resp.innerHTML = hasPhone
                          ? `✅ Mensagem enviada! A resposta chegará no WhatsApp ${data.telefone}.`
                          : `🤖 Resposta do Assistente:<br><div style="margin-top:8px;padding:8px;background:#fff;border:1px solid #eee;border-radius:6px;">${(json.output||json.response||json.message||'Processado!').replace(/\n/g,'<br>')}</div>`;
                        form.reset();
                    } else {
                        resp.style.background = 'rgba(244,67,54,0.1)';
                        resp.style.border = '1px solid #f44336';
                        resp.textContent = '❌ Erro ao enviar. Tente novamente.';
                    }
                } catch(err){
                    resp.style.display = 'block';
                    resp.style.background = 'rgba(244,67,54,0.1)';
                    resp.style.border = '1px solid #f44336';
                    resp.textContent = '❌ Erro de conexão.';
                } finally {
                    btn.disabled = false;
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}


