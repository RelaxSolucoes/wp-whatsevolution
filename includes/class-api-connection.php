<?php
namespace WpWhatsAppEvolution;

/**
 * Gerencia a conexão com a Evolution API
 */
class Api_Connection {
    private static $instance = null;
    private static $last_config_check = 0;
    private static $is_configured_cache = null;
    private $api_url;
    private $api_key;
    private $instance_name;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->api_url = get_option('wpwevo_api_url', '');
            self::$instance->api_key = self::get_active_api_key(); // Usa a chave ativa
            self::$instance->instance_name = get_option('wpwevo_instance', '');
        }
        return self::$instance;
    }

    public static function get_active_api_key() {
        $mode = get_option('wpwevo_connection_mode', 'manual');
        if ($mode === 'managed') {
            return get_option('wpwevo_managed_api_key', '');
        }
        return get_option('wpwevo_manual_api_key', '');
    }

    private function __construct() {
        add_action('init', [$this, 'setup']);
    }

    /**
     * Configura a instância após o init
     */
    public function setup() {
        $this->api_url = get_option('wpwevo_api_url');
        $this->api_key = self::get_active_api_key(); // ✅ CORREÇÃO: Usar função que busca a chave correta
        $this->instance_name = get_option('wpwevo_instance');
    }

    /**
     * Verifica se as configurações da API estão completas
     */
    public function is_configured() {
        $current_time = time();
        
        // Cache por 30 segundos para evitar múltiplas verificações
        if (self::$is_configured_cache !== null && ($current_time - self::$last_config_check) < 30) {
            return self::$is_configured_cache;
        }
        
        // Recarrega as configurações usando a lógica correta
        $this->api_url = get_option('wpwevo_api_url', '');
        $this->api_key = self::get_active_api_key(); // CORRIGIDO: Usa a função que busca a chave correta
        $this->instance_name = get_option('wpwevo_instance', '');

        // SMS mode is configured independently from Evolution API credentials
        $connection_mode = get_option('wpwevo_connection_mode', 'manual');
        if ($connection_mode === 'sms') {
            $is_configured = wpwevo_is_smsgate_configured();
            self::$last_config_check = $current_time;
            self::$is_configured_cache = $is_configured;
            return $is_configured;
        }

        $is_configured = !empty($this->api_url) && !empty($this->api_key) && !empty($this->instance_name);
        
        self::$last_config_check = $current_time;
        self::$is_configured_cache = $is_configured;
        
        return $is_configured;
    }

    /**
     * Verifica a versão da Evolution API (apenas modo manual)
     */
    public function check_api_version() {
        if (empty($this->api_url)) {
            return null;
        }

        // Constrói a URL base da API
        $url = rtrim($this->api_url, '/') . '/';

        $args = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['version'])) {
            return null;
        }

        $version = $data['version'];
        $is_v2 = version_compare($version, '2.0.0', '>=');

        return [
            'version' => $version,
            'is_v2' => $is_v2
        ];
    }

    /**
     * Verifica o estado da conexão da instância
     */
    public function check_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Configuração da API incompleta.', 'wp-whatsevolution')
            ];
        }

        // Modo managed usa as mesmas credenciais Evolution salvas no signup
        // (api_url + api_key da instância), então a verificação direta funciona
        // para os dois modos.

        // Constrói a URL exatamente como no teste
        $url = rtrim($this->api_url, '/') . '/instance/connectionState/' . $this->instance_name;

        $args = [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            // Verifica se é um erro de DNS/host não encontrado
            if (strpos(strtolower($response->get_error_message()), 'could not resolve host') !== false) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Não foi possível conectar ao servidor %s. Verifique se a URL da API está correta.', 'wp-whatsevolution'),
                        parse_url($this->api_url, PHP_URL_HOST)
                    )
                ];
            }
            
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao verificar conexão: %s', 'wp-whatsevolution'),
                    $response->get_error_message()
                )
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);

        if ($status_code === 404) {
            if (isset($data['response']['message']) && is_array($data['response']['message'])) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('A instância "%s" não existe.', 'wp-whatsevolution'),
                        $this->instance_name
                    )
                ];
            }
            return [
                'success' => false,
                'message' => sprintf(
                    __('A instância "%s" não existe.', 'wp-whatsevolution'),
                    $this->instance_name
                )
            ];
        }

        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro na API (código %d)', 'wp-whatsevolution'),
                    $status_code
                )
            ];
        }

        if (!is_array($data) || !isset($data['instance']) || !isset($data['instance']['state'])) {
            return [
                'success' => false,
                'message' => __('Resposta da API em formato inválido.', 'wp-whatsevolution')
            ];
        }

        $state = strtolower($data['instance']['state']);
        $is_connected = $state === 'open';
        
        $state_messages = [
            'open' => __('Conexão estabelecida com sucesso!', 'wp-whatsevolution'),
            'connecting' => __('Instância está se conectando...', 'wp-whatsevolution'),
            'close' => __('Instância está desconectada.', 'wp-whatsevolution'),
            'disconnecting' => __('Instância está se desconectando...', 'wp-whatsevolution'),
            'default' => __('Estado desconhecido da instância.', 'wp-whatsevolution')
        ];

        // Verifica a versão da API apenas se a conexão foi bem-sucedida
        $api_version = null;
        if ($is_connected) {
            $api_version = $this->check_api_version();
        }

        return [
            'success' => $is_connected,
            'message' => $state_messages[$state] ?? $state_messages['default'],
            'state' => $state,
            'api_version' => $api_version
        ];
    }

    /**
     * Obtém o código do país com base nas configurações do WooCommerce
     * @return string Código do país (default: 55 para Brasil)
     */
    private function get_country_code() {
        // Verifica se o WooCommerce está ativo
        if (function_exists('WC')) {
            // Tenta obter o país base da loja
            $base_country = WC()->countries->get_base_country();
            $country_codes = [
                'BR' => '55', // Brasil
                'PT' => '351', // Portugal
                'AO' => '244', // Angola
                'MZ' => '258', // Moçambique
                // Adicione outros países conforme necessário
            ];
            
            return isset($country_codes[$base_country]) ? $country_codes[$base_country] : '55';
        }
        
        // Se o WooCommerce não estiver ativo, usa o locale do WordPress
        $locale = get_locale();
        if (strpos($locale, 'pt_BR') !== false) {
            return '55';
        } elseif (strpos($locale, 'pt_PT') !== false) {
            return '351';
        }
        
        // Padrão para Brasil
        return '55';
    }

    /**
     * Formata e valida o número de telefone usando a função ultra-robusta
     * @param string $number Número de telefone
     * @return array ['success' => bool, 'number' => string, 'message' => string]
     */
    private function format_phone_number($number) {
        // Usa a função centralizada ultra-robusta
        $formatted_phone = wpwevo_validate_phone($number);
        
        if ($formatted_phone === false) {
            return [
                'success' => false,
                'message' => __('Número de telefone inválido. Use formato: DDD + número (ex: 11999999999)', 'wp-whatsevolution')
            ];
        }
        
        // Adiciona o @c.us para WhatsApp
        $whatsapp_number = $formatted_phone . '@c.us';
        
        return [
            'success' => true,
            'number' => $whatsapp_number
        ];
    }

    /**
     * Substitui as variáveis da loja na mensagem
     * @param string $message Mensagem original
     * @return string Mensagem com variáveis substituídas
     */
    private function replace_store_variables($message) {
        $variables = [
            '{store_name}' => get_bloginfo('name'),
            '{store_url}' => home_url(),
            '{store_email}' => get_option('admin_email')
        ];

        $replaced_message = str_replace(
            array_keys($variables),
            array_values($variables),
            $message
        );

        return $replaced_message;
    }

    /**
     * Envia uma mensagem via WhatsApp
     * @param string $number Número do telefone
     * @param string $message Mensagem a ser enviada
     * @return array ['success' => bool, 'message' => string]
     */
    public function send_message($number, $message) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('API não configurada.', 'wp-whatsevolution')
            ];
        }

        // Verificar modo de conexão
        $connection_mode = get_option('wpwevo_connection_mode', 'manual');

        // Modo SMS exclusivo
        if ($connection_mode === 'sms') {
            $sms_result = wpwevo_send_via_smsgate($number, $message);
            $success = !empty($sms_result['success']);
            wpwevo_log(
                $success ? 'info' : 'error',
                'Envio SMS (modo sms): ' . ($success ? 'sucesso' : $sms_result['error']),
                ['phone' => $number, 'channel' => 'sms']
            );
            return [
                'success' => $success,
                'message' => $success ? 'SMS enviado com sucesso!' : ($sms_result['error'] ?? 'Erro desconhecido'),
                'data'    => $sms_result['data'] ?? null,
            ];
        }

        // Modos managed / manual → envio direto pela Evolution API.
        // No managed, api_url/api_key/instance vêm do signup e funcionam igual.
        $result = $this->send_message_manual($number, $message);

        // Fallback automático para SMS se WhatsApp falhou e fallback está habilitado
        if (!$result['success'] && get_option('wpwevo_smsgate_fallback', 'no') === 'yes' && wpwevo_is_smsgate_configured()) {
            $sms_result = wpwevo_send_via_smsgate($number, $message);
            if (!empty($sms_result['success'])) {
                wpwevo_log('info', 'WhatsApp falhou, SMS enviado como fallback', ['phone' => $number, 'channel' => 'sms_fallback', 'whatsapp_error' => $result['message']]);
                return [
                    'success' => true,
                    'message' => 'SMS enviado como fallback (WhatsApp falhou).',
                    'data'    => $sms_result['data'] ?? null,
                ];
            }
            wpwevo_log('error', 'Fallback SMS também falhou', ['phone' => $number, 'sms_error' => $sms_result['error'] ?? 'Erro desconhecido']);
        }

        return $result;
    }

    /**
     * Envia mensagem via Evolution API (modos manual e managed)
     */
    private function send_message_manual($number, $message) {
        // Formata e valida o número
        $phone_validation = $this->format_phone_number($number);
        if (!$phone_validation['success']) {
            return $phone_validation;
        }
        $number = $phone_validation['number'];

        // Substitui variáveis da loja na mensagem
        $message = $this->replace_store_variables($message);

        // Constrói a URL do endpoint
        $url = rtrim($this->api_url, '/') . '/message/sendText/' . $this->instance_name;

        // Prepara o corpo da requisição no formato correto
        $body = [
            'number' => $number,
            'text' => $message,
            'linkPreview' => true,
            'mentionsEveryOne' => false,
            'delay' => 0
        ];

        $args = [
            'method' => 'POST',
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 15
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao enviar mensagem: %s', 'wp-whatsevolution'),
                    $response->get_error_message()
                )
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $data = json_decode($body, true);

        // Verifica se o status é 201 (Created) que é o esperado para esta API
        if ($status_code !== 201) {
            // Melhora as mensagens de erro baseadas no código HTTP
            $user_friendly_message = $this->get_user_friendly_error_message($status_code, $data);
            
            return [
                'success' => false,
                'message' => $user_friendly_message
            ];
        }

        // Verifica se a resposta contém a chave de mensagem esperada
        if (!isset($data['key']) || !isset($data['status'])) {
            return [
                'success' => false,
                'message' => __('Resposta da API em formato inválido.', 'wp-whatsevolution')
            ];
        }

        return [
            'success' => true,
            'message' => __('Mensagem enviada com sucesso!', 'wp-whatsevolution'),
            'data' => $data
        ];
    }

    /**
     * Cria mensagens de erro mais amigáveis baseadas no código HTTP
     */
    private function get_user_friendly_error_message($status_code, $data = null) {
        $error_details = isset($data['error']) ? $data['error'] : '';
        
        switch ($status_code) {
            case 400:
                // Bad Request - geralmente número inválido ou não WhatsApp
                if (strpos(strtolower($error_details), 'not found') !== false || 
                    strpos(strtolower($error_details), 'not registered') !== false) {
                    return '📱 Este número não possui WhatsApp ativo ou não foi encontrado';
                }
                return '❌ Número de telefone inválido ou não possui WhatsApp';
                
            case 401:
                return '🔐 Falha na autenticação - Verifique sua API Key';
                
            case 403:
                return '🚫 Acesso negado - Permissões insuficientes';
                
            case 404:
                return '🔍 Instância não encontrada - Verifique o nome da instância';
                
            case 429:
                return '⏰ Muitas requisições - Aguarde alguns segundos e tente novamente';
                
            case 500:
                return '🔧 Erro interno do servidor - Tente novamente em alguns minutos';
                
            case 503:
                return '⚠️ Serviço temporariamente indisponível';
                
            default:
                if (!empty($error_details)) {
                    return sprintf('❌ Erro na API: %s', $error_details);
                }
                return sprintf('❌ Erro na comunicação (código %d)', $status_code);
        }
    }

    /**
     * Verifica se um número é válido
     */
    public function validate_number($number) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Configuração da API incompleta.', 'wp-whatsevolution')
            ];
        }

        // Validação direta pela Evolution API nos dois modos (managed/manual)
        return $this->validate_number_manual($number);
    }

    /**
     * Valida número via Evolution API (modos manual e managed)
     */
    private function validate_number_manual($number) {
        // Formata o número antes de validar
        $formatted = $this->format_phone_number($number);
        if (!$formatted['success']) {
            return $formatted;
        }
        $number = $formatted['number'];

        // Constrói a URL para validação do número
        $url = rtrim($this->api_url, '/') . '/chat/whatsappNumbers/' . $this->instance_name;

        $response = wp_remote_post($url, [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'numbers' => [$number]
            ]),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao validar número: %s', 'wp-whatsevolution'),
                    $response->get_error_message()
                )
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro na API (código %d)', 'wp-whatsevolution'),
                    $status_code
                )
            ];
        }

        $data = json_decode($body, true);

        // Verifica se recebemos um array com pelo menos um resultado
        if (!is_array($data) || empty($data) || !isset($data[0]['exists'])) {
            return [
                'success' => false,
                'message' => __('Resposta inválida da API ao validar número.', 'wp-whatsevolution')
            ];
        }

        // Verifica se o número existe no WhatsApp
        if (!$data[0]['exists']) {
            return [
                'success' => false,
                'data' => [
                    'is_whatsapp' => false,
                    'exists' => false
                ],
                'message' => __('O número informado não é um WhatsApp válido.', 'wp-whatsevolution')
            ];
        }

        return [
            'success' => true,
            'data' => [
                'is_whatsapp' => true,
                'exists' => true,
                'name' => isset($data[0]['name']) ? $data[0]['name'] : null
            ],
            'message' => __('Número válido!', 'wp-whatsevolution')
        ];
    }

    public function force_reload() {
        self::$instance->api_url = get_option('wpwevo_api_url', '');
        self::$instance->api_key = self::get_active_api_key();
        self::$instance->instance_name = get_option('wpwevo_instance', '');
        // Limpa o cache de configuração para forçar a revalidação
        self::$is_configured_cache = null; 
    }
} 