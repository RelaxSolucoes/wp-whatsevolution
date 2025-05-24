<?php
namespace WpWhatsAppEvolution;

/**
 * Gerencia a conexão com a Evolution API
 */
class Api_Connection {
    private static $instance = null;
    private $api_url;
    private $api_key;
    private $instance_name;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'setup']);
    }

    /**
     * Configura a instância após o init
     */
    public function setup() {
        $this->api_url = get_option('wpwevo_api_url');
        $this->api_key = get_option('wpwevo_api_key');
        $this->instance_name = get_option('wpwevo_instance');
    }

    /**
     * Verifica se as configurações da API estão completas
     */
    public function is_configured() {
        // Força recarregar as configurações
        $this->api_url = get_option('wpwevo_api_url');
        $this->api_key = get_option('wpwevo_api_key');
        $this->instance_name = get_option('wpwevo_instance');

        // Log para debug
        wpwevo_log_error('Checking configuration:');
        wpwevo_log_error('API URL: ' . $this->api_url);
        wpwevo_log_error('API Key: ' . substr($this->api_key, 0, 8) . '...');
        wpwevo_log_error('Instance: ' . $this->instance_name);

        return !empty($this->api_url) && !empty($this->api_key) && !empty($this->instance_name);
    }

    /**
     * Verifica o estado da conexão da instância
     */
    public function check_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Configuração da API incompleta.', 'wp-whatsapp-evolution')
            ];
        }

        // Constrói a URL exatamente como no teste
        $url = rtrim($this->api_url, '/') . '/instance/connectionState/' . $this->instance_name;

        // Log para debug
        wpwevo_log_error('Making API request:');
        wpwevo_log_error('URL: ' . $url);
        wpwevo_log_error('API Key: ' . substr($this->api_key, 0, 8) . '...');

        $args = [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 15,
            'sslverify' => false // Temporário para debug
        ];

        // Log dos argumentos da requisição
        wpwevo_log_error('Request args: ' . print_r($args, true));

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            wpwevo_log_error('Connection check error: ' . $response->get_error_message());
            
            // Verifica se é um erro de DNS/host não encontrado
            if (strpos(strtolower($response->get_error_message()), 'could not resolve host') !== false) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Não foi possível conectar ao servidor %s. Verifique se a URL da API está correta.', 'wp-whatsapp-evolution'),
                        parse_url($this->api_url, PHP_URL_HOST)
                    )
                ];
            }
            
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao verificar conexão: %s', 'wp-whatsapp-evolution'),
                    $response->get_error_message()
                )
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log da resposta completa
        wpwevo_log_error('API Response:');
        wpwevo_log_error('Status: ' . $status_code);
        wpwevo_log_error('Headers: ' . print_r(wp_remote_retrieve_headers($response), true));
        wpwevo_log_error('Body: ' . $body);

        $data = json_decode($body, true);

        if ($status_code === 404) {
            if (isset($data['response']['message']) && is_array($data['response']['message'])) {
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('A instância "%s" não existe.', 'wp-whatsapp-evolution'),
                        $this->instance_name
                    )
                ];
            }
            return [
                'success' => false,
                'message' => sprintf(
                    __('A instância "%s" não existe.', 'wp-whatsapp-evolution'),
                    $this->instance_name
                )
            ];
        }

        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro na API (código %d)', 'wp-whatsapp-evolution'),
                    $status_code
                )
            ];
        }

        if (!is_array($data) || !isset($data['instance']) || !isset($data['instance']['state'])) {
            wpwevo_log_error('Invalid response format: ' . print_r($data, true));
            return [
                'success' => false,
                'message' => __('Resposta da API em formato inválido.', 'wp-whatsapp-evolution')
            ];
        }

        $state = strtolower($data['instance']['state']);
        $is_connected = $state === 'open';
        
        $state_messages = [
            'open' => __('Conexão estabelecida com sucesso!', 'wp-whatsapp-evolution'),
            'connecting' => __('Instância está se conectando...', 'wp-whatsapp-evolution'),
            'close' => __('Instância está desconectada.', 'wp-whatsapp-evolution'),
            'disconnecting' => __('Instância está se desconectando...', 'wp-whatsapp-evolution'),
            'default' => __('Estado desconhecido da instância.', 'wp-whatsapp-evolution')
        ];

        return [
            'success' => $is_connected,
            'message' => $state_messages[$state] ?? $state_messages['default'],
            'state' => $state
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
     * Formata e valida o número de telefone
     * @param string $number Número de telefone
     * @return array ['success' => bool, 'number' => string, 'message' => string]
     */
    private function format_phone_number($number) {
        // Remove todos os caracteres não numéricos
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Verifica se o número está vazio
        if (empty($number)) {
            return [
                'success' => false,
                'message' => __('O número de telefone é obrigatório.', 'wp-whatsapp-evolution')
            ];
        }

        $country_code = $this->get_country_code();
        
        // Se não começar com o código do país, adiciona
        if (!preg_match('/^' . $country_code . '/', $number)) {
            $number = $country_code . $number;
        }

        // Para números brasileiros
        if ($country_code === '55') {
            // Valida o comprimento total (13 ou 14 dígitos com o 55)
            if (strlen($number) !== 13 && strlen($number) !== 14) {
                return [
                    'success' => false,
                    'message' => __('Número inválido. Digite apenas o DDD e o número.', 'wp-whatsapp-evolution')
                ];
            }

            // Extrai o DDD para validação
            $ddd = substr($number, 2, 2);
            
            // Valida o DDD (códigos de área válidos do Brasil)
            if (!preg_match('/^[1-9][1-9]$/', $ddd)) {
                return [
                    'success' => false,
                    'message' => __('DDD inválido. Use um DDD válido do Brasil.', 'wp-whatsapp-evolution')
                ];
            }

            // Valida o formato final completo para números brasileiros
            if (!preg_match('/^55[1-9][1-9][0-9]{8,9}$/', $number)) {
                return [
                    'success' => false,
                    'message' => __('Formato de número inválido. Use: (DDD) + Número', 'wp-whatsapp-evolution')
                ];
            }
        }

        return [
            'success' => true,
            'number' => $number
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

        // Log para debug
        wpwevo_log_error('Replacing variables in message:');
        wpwevo_log_error('Original message: ' . $message);
        wpwevo_log_error('Variables: ' . print_r($variables, true));

        $replaced_message = str_replace(
            array_keys($variables),
            array_values($variables),
            $message
        );

        // Log da mensagem final
        wpwevo_log_error('Final message: ' . $replaced_message);

        return $replaced_message;
    }

    /**
     * Envia uma mensagem de texto
     * @param string $number Número do WhatsApp
     * @param string $message Mensagem (pode conter variáveis)
     * @return array Resultado do envio
     */
    public function send_message($number, $message) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Configuração da API incompleta.', 'wp-whatsapp-evolution')
            ];
        }

        // Substitui as variáveis da loja na mensagem
        $message = $this->replace_store_variables($message);

        // Formata o número antes de enviar
        $formatted = $this->format_phone_number($number);
        if (!$formatted['success']) {
            return $formatted;
        }
        $number = $formatted['number'];

        // Constrói a URL para envio da mensagem
        $url = rtrim($this->api_url, '/') . '/message/sendText/' . $this->instance_name;

        // Log para debug
        wpwevo_log_error('Sending message:');
        wpwevo_log_error('URL: ' . $url);
        wpwevo_log_error('Number: ' . $number);
        wpwevo_log_error('Message: ' . $message);

        $body = [
            'number' => $number,
            'text' => $message,
            'linkPreview' => true
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 15,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            wpwevo_log_error('Message sending error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => __('Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.', 'wp-whatsapp-evolution')
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log da resposta
        wpwevo_log_error('API Response:');
        wpwevo_log_error('Status: ' . $status_code);
        wpwevo_log_error('Body: ' . $body);

        // Trata os diferentes códigos de status
        if ($status_code !== 201) {
            $error_messages = [
                400 => __('Não foi possível enviar a mensagem. Verifique o número e tente novamente.', 'wp-whatsapp-evolution'),
                401 => __('Erro de autenticação. Verifique sua chave API.', 'wp-whatsapp-evolution'),
                404 => __('Instância do WhatsApp não encontrada.', 'wp-whatsapp-evolution'),
                429 => __('Muitas requisições. Aguarde um momento e tente novamente.', 'wp-whatsapp-evolution'),
                500 => __('Erro interno do servidor. Tente novamente mais tarde.', 'wp-whatsapp-evolution')
            ];

            return [
                'success' => false,
                'message' => $error_messages[$status_code] ?? __('Erro desconhecido ao enviar mensagem.', 'wp-whatsapp-evolution')
            ];
        }

        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['key']['id'])) {
            return [
                'success' => false,
                'message' => __('Resposta inválida do servidor.', 'wp-whatsapp-evolution')
            ];
        }

        return [
            'success' => true,
            'message' => __('Mensagem enviada com sucesso!', 'wp-whatsapp-evolution'),
            'data' => [
                'message_id' => $data['key']['id'],
                'status' => $data['status']
            ]
        ];
    }

    /**
     * Verifica se um número é válido
     */
    public function validate_number($number) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => __('Configuração da API incompleta.', 'wp-whatsapp-evolution')
            ];
        }

        // Formata o número antes de validar
        $formatted = $this->format_phone_number($number);
        if (!$formatted['success']) {
            return $formatted;
        }
        $number = $formatted['number'];

        // Constrói a URL para validação do número
        $url = rtrim($this->api_url, '/') . '/chat/whatsappNumbers/' . $this->instance_name;

        // Log para debug
        wpwevo_log_error('Validating number:');
        wpwevo_log_error('URL: ' . $url);
        wpwevo_log_error('Number: ' . $number);

        $response = wp_remote_post($url, [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'numbers' => [$number]
            ]),
            'timeout' => 15,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            wpwevo_log_error('Number validation error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao validar número: %s', 'wp-whatsapp-evolution'),
                    $response->get_error_message()
                )
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log da resposta
        wpwevo_log_error('API Response:');
        wpwevo_log_error('Status: ' . $status_code);
        wpwevo_log_error('Body: ' . $body);

        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Erro na API (código %d)', 'wp-whatsapp-evolution'),
                    $status_code
                )
            ];
        }

        $data = json_decode($body, true);

        // Verifica se recebemos um array com pelo menos um resultado
        if (!is_array($data) || empty($data) || !isset($data[0]['exists'])) {
            return [
                'success' => false,
                'message' => __('Resposta inválida da API ao validar número.', 'wp-whatsapp-evolution')
            ];
        }

        // Verifica se o número existe no WhatsApp
        if (!$data[0]['exists']) {
            return [
                'success' => false,
                'message' => __('O número informado não é um WhatsApp válido.', 'wp-whatsapp-evolution')
            ];
        }

        return [
            'success' => true,
            'message' => __('Número válido!', 'wp-whatsapp-evolution'),
            'data' => $data[0] // Retorna os dados completos do número para uso posterior se necessário
        ];
    }
} 