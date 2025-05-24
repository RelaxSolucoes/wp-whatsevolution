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
     * Envia uma mensagem de texto
     */
    public function send_message($number, $message) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API configuration incomplete.' // Mensagem não traduzida
            ];
        }

        // Remove caracteres não numéricos do número
        $number = preg_replace('/[^0-9]/', '', $number);

        $url = trailingslashit($this->api_url) . 'message/text/' . $this->instance_name;
        $response = wp_remote_post($url, [
            'headers' => [
                'apikey' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'number' => $number,
                'message' => $message
            ]),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            wpwevo_log_error('Message sending error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!isset($data->key)) {
            wpwevo_log_error('Invalid API response when sending message: ' . $body);
            return [
                'success' => false,
                'message' => isset($data->error) ? $data->error : 'Error sending message.' // Mensagem não traduzida
            ];
        }

        return [
            'success' => true,
            'message' => 'Message sent successfully!', // Mensagem não traduzida
            'message_id' => $data->key
        ];
    }

    /**
     * Verifica se um número é válido
     */
    public function validate_number($number) {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API configuration incomplete.' // Mensagem não traduzida
            ];
        }

        // Remove caracteres não numéricos
        $number = preg_replace('/[^0-9]/', '', $number);

        $url = trailingslashit($this->api_url) . 'chat/whatsappNumbers/' . $this->instance_name;
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
            wpwevo_log_error('Number validation error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!isset($data->valid) || !is_array($data->valid) || empty($data->valid)) {
            return [
                'success' => false,
                'message' => 'The provided number is not a valid WhatsApp number.' // Mensagem não traduzida
            ];
        }

        return [
            'success' => true,
            'message' => 'Valid number!' // Mensagem não traduzida
        ];
    }
} 