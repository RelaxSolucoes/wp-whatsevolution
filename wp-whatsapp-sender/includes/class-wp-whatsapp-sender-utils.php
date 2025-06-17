<?php
/**
 * Funções de utilidade para o plugin WP WhatsApp Sender.
 */

class WP_WhatsApp_Sender_Utils {

    /**
     * Formata um número de telefone para o formato padrão do WhatsApp.
     *
     * @param string $phone_number O número de telefone a ser formatado.
     * @return string O número de telefone formatado.
     */
    public static function format_phone_number($phone_number) {
        // Remove todos os caracteres não numéricos
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Obtém as configurações da API
        $settings = self::get_api_settings();
        $api_url = $settings['api_url'];
        
        // Detecta se é Evolution API com base na URL
        $is_evolution_api = (strpos($api_url, 'evolution') !== false);
        
        // Verifica se o número já começa com o código do país
        if (substr($phone_number, 0, 2) !== '55') {
            // Adiciona o código do Brasil por padrão (isso pode ser configurável nas opções)
            $phone_number = '55' . $phone_number;
        }
        
        // Para Evolution API, retorna o formato apropriado
        if ($is_evolution_api) {
            // Certifique-se de que não há o '@c.us' no final, pois a API fará isso automaticamente
            if (substr($phone_number, -5) === '@c.us') {
                $phone_number = substr($phone_number, 0, -5);
            }
            return $phone_number;
        }
        
        // Para outras APIs, mantém o formato padrão
        return $phone_number;
    }

    /**
     * Valida um número de telefone.
     *
     * @param string $phone_number O número de telefone a ser validado.
     * @return bool True se o número for válido, false caso contrário.
     */
    public static function validate_phone_number($phone_number) {
        // Remove todos os caracteres não numéricos
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Verifica se o número tem entre 10 e 13 dígitos (incluindo código do país)
        return (strlen($phone_number) >= 10 && strlen($phone_number) <= 13);
    }

    /**
     * Registra mensagens de log para depuração.
     *
     * @param string $message A mensagem a ser registrada.
     * @param string $level O nível de log (info, error, etc).
     */
    public static function log($message, $level = 'info') {
        if (WP_DEBUG === true) {
            $log_file = WP_CONTENT_DIR . '/wp-whatsapp-sender-debug.log';
            $timestamp = current_time('mysql');
            
            $log_message = "[{$timestamp}] [{$level}] {$message}\n";
            error_log($log_message, 3, $log_file);
        }
    }

    /**
     * Obtém a configuração da API do WhatsApp.
     *
     * @return array As configurações da API.
     */
    public static function get_api_settings() {
        $api_key = get_option('wp_whatsapp_sender_api_key', '');
        $api_url = get_option('wp_whatsapp_sender_api_url', '');
        $api_phone = get_option('wp_whatsapp_sender_api_phone', '');
        $instance_name = get_option('wp_whatsapp_sender_instance_name', 'RelaxSite');
        
        return array(
            'api_key' => $api_key,
            'api_url' => $api_url,
            'api_phone' => $api_phone,
            'instance_name' => $instance_name
        );
    }

    /**
     * Verifica se as configurações da API estão completas.
     *
     * @return bool True se as configurações estiverem completas, false caso contrário.
     */
    public static function is_api_configured() {
        $settings = self::get_api_settings();
        
        return (!empty($settings['api_key']) && !empty($settings['api_url']));
    }
} 