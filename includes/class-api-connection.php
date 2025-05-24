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
        return !empty($this->api_url) && !empty($this->api_key) && !empty($this->instance_name);
    }

    /**
     * Verifica o estado da conexão da instância
     */
    public function check_connection() {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => 'API configuration incomplete.' // Mensagem não traduzida
            ];
        }

        $url = trailingslashit($this->api_url) . 'instance/connectionState/' . $this->instance_name;
        $response = wp_remote_get($url, [
            'headers' => [
                'apikey' => $this->api_key
            ],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            wpwevo_log_error('Connection check error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!isset($data->state)) {
            wpwevo_log_error('Invalid API response: ' . $body);
            return [
                'success' => false,
                'message' => 'Invalid API response.' // Mensagem não traduzida
            ];
        }

        $is_connected = $data->state === 'CONNECTED';
        
        // Retorna mensagens não traduzidas
        return [
            'success' => $is_connected,
            'message' => $is_connected ? 'Connection established successfully!' : 'Instance is not connected.',
            'state' => $data->state
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