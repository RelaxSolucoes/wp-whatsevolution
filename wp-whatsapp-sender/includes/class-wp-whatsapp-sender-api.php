<?php
/**
 * Classe para comunicação com a API do WhatsApp.
 */

class WP_WhatsApp_Sender_API {

    /**
     * Envia uma mensagem via WhatsApp.
     *
     * @param string $to Número de telefone do destinatário.
     * @param string $message Mensagem a ser enviada.
     * @return array|WP_Error Resposta da API ou objeto de erro.
     */
    public static function send_message($to, $message) {
        // Verifica se a API está configurada
        if (!WP_WhatsApp_Sender_Utils::is_api_configured()) {
            return new WP_Error('api_not_configured', 'A API do WhatsApp não está configurada corretamente.');
        }

        // Formata o número do telefone
        $to = WP_WhatsApp_Sender_Utils::format_phone_number($to);
        
        // Valida o número do telefone
        if (!WP_WhatsApp_Sender_Utils::validate_phone_number($to)) {
            return new WP_Error('invalid_phone', 'O número de telefone fornecido é inválido.');
        }

        // Obtém as configurações da API
        $settings = WP_WhatsApp_Sender_Utils::get_api_settings();
        
        // Prepara os dados para a requisição
        $api_url = $settings['api_url'];
        $api_key = $settings['api_key'];
        $api_phone = $settings['api_phone'] ?: '';
        $instance_name = $settings['instance_name'] ?: 'RelaxSite';

        // Detecta qual API está sendo usada com base na URL
        $is_evolution_api = (strpos($api_url, 'evolution') !== false || strpos($api_url, 'relax') !== false);
        
        if ($is_evolution_api) {
            // Primeiro vamos verificar se a instância está conectada
            WP_WhatsApp_Sender_Utils::log('Verificando status da instância ' . $instance_name);
            
            $check_endpoint = trailingslashit($api_url) . 'instance/connectionState/' . $instance_name;
            
            $check_response = wp_remote_get($check_endpoint, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'apikey' => $api_key
                ),
                'timeout' => 20
            ));
            
            if (!is_wp_error($check_response)) {
                $check_body = wp_remote_retrieve_body($check_response);
                $check_data = json_decode($check_body, true);
                
                WP_WhatsApp_Sender_Utils::log('Status da instância: ' . $check_body);
                
                // Verifica se a instância está conectada
                if (isset($check_data['instance']['state']) && $check_data['instance']['state'] !== 'open') {
                    return new WP_Error('instance_not_connected', 'A instância ' . $instance_name . ' não está conectada. Estado atual: ' . $check_data['instance']['state']);
                }
            } else {
                WP_WhatsApp_Sender_Utils::log('Erro ao verificar status da instância: ' . $check_response->get_error_message(), 'error');
            }
            
            // Evolution API - tentando diferentes formatos de endpoint
            
            // Primeira tentativa: diretamente /message/sendText/{{instanceName}}
            $endpoint = trailingslashit($api_url) . 'message/sendText/' . $instance_name;
            
            // Segunda tentativa (alternativa)
            // $endpoint = trailingslashit($api_url) . 'message/sendText';
            
            WP_WhatsApp_Sender_Utils::log('Usando endpoint: ' . $endpoint);
            
            $body = array(
                'number' => $to,
                'text' => $message
            );
            
            // Configuração dos headers (pode precisar de autenticação adicional)
            $headers = array(
                'Content-Type' => 'application/json',
                'apikey' => $api_key,
                'Accept' => 'application/json'
            );
            
            // Log dos cabeçalhos (exceto informações sensíveis)
            WP_WhatsApp_Sender_Utils::log('Headers: Content-Type=application/json, Accept=application/json');
            WP_WhatsApp_Sender_Utils::log('API Key está configurada: ' . (!empty($api_key) ? 'Sim' : 'Não'));
        } else {
            // Chat API (padrão original)
            $endpoint = trailingslashit($api_url) . 'sendMessage?token=' . $api_key;
            
            $body = array(
                'phone' => $to,
                'body' => $message
            );
            
            $headers = array(
                'Content-Type' => 'application/json'
            );
        }

        // Registra a tentativa de envio para log
        WP_WhatsApp_Sender_Utils::log('Tentando enviar mensagem para: ' . $to);
        WP_WhatsApp_Sender_Utils::log('Usando API: ' . ($is_evolution_api ? 'Evolution API' : 'Chat API'));
        WP_WhatsApp_Sender_Utils::log('Endpoint: ' . $endpoint);
        WP_WhatsApp_Sender_Utils::log('Dados enviados: ' . json_encode($body));
        
        // Faz a requisição para a API
        $response = wp_remote_post($endpoint, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        // Verifica se houve erro na requisição
        if (is_wp_error($response)) {
            WP_WhatsApp_Sender_Utils::log('Erro ao enviar mensagem: ' . $response->get_error_message(), 'error');
            return $response;
        }
        
        // Obtém o corpo da resposta
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        // Registra a resposta da API
        WP_WhatsApp_Sender_Utils::log('Resposta da API: ' . $response_body);
        
        // Verifica se a API retornou sucesso (adapta para o formato de resposta da Evolution API)
        if ($is_evolution_api) {
            if ($response_code >= 200 && $response_code < 300) {
                WP_WhatsApp_Sender_Utils::log('Mensagem enviada com sucesso para: ' . $to);
                return $response_data;
            } else {
                // Se receber 404, tenta um endpoint alternativo
                if ($response_code == 404 && strpos($endpoint, 'message/sendText') !== false) {
                    WP_WhatsApp_Sender_Utils::log('Tentando endpoint alternativo após 404...');
                    
                    // Tente estes endpoints alternadamente descomentando apenas UM por vez:
                    
                    // Alternativa 1: Inverte instance e message na ordem
                    $alt_endpoint = trailingslashit($api_url) . 'instance/' . $instance_name . '/sendText';
                    
                    // Alternativa 2: Usando api/messages/send/text
                    // $alt_endpoint = trailingslashit($api_url) . 'api/messages/send/text';
                    
                    // Alternativa 3: Usando api
                    // $alt_endpoint = trailingslashit($api_url) . 'api/' . $instance_name . '/sendMessage';
                    
                    WP_WhatsApp_Sender_Utils::log('Novo endpoint: ' . $alt_endpoint);
                    
                    $alt_response = wp_remote_post($alt_endpoint, array(
                        'headers' => $headers,
                        'body' => json_encode($body),
                        'timeout' => 30
                    ));
                    
                    if (!is_wp_error($alt_response)) {
                        $alt_response_code = wp_remote_retrieve_response_code($alt_response);
                        $alt_response_body = wp_remote_retrieve_body($alt_response);
                        $alt_response_data = json_decode($alt_response_body, true);
                        
                        WP_WhatsApp_Sender_Utils::log('Resposta alternativa: ' . $alt_response_body);
                        
                        if ($alt_response_code >= 200 && $alt_response_code < 300) {
                            WP_WhatsApp_Sender_Utils::log('Mensagem enviada com sucesso pelo endpoint alternativo!');
                            return $alt_response_data;
                        }
                    }
                }
                
                // Tenta obter uma mensagem mais descritiva
                $error_message = 'Erro ao enviar mensagem via Evolution API.';
                
                if (isset($response_data['error'])) {
                    $error_message = $response_data['error'];
                } elseif (isset($response_data['message'])) {
                    $error_message = $response_data['message'];
                }
                
                // Adiciona o código de resposta para ajudar na depuração
                $error_message .= ' (Código: ' . $response_code . ')';
                
                WP_WhatsApp_Sender_Utils::log('Falha ao enviar mensagem: ' . $error_message, 'error');
                WP_WhatsApp_Sender_Utils::log('Resposta completa: ' . json_encode($response_data), 'error');
                
                return new WP_Error('api_error', $error_message);
            }
        } else {
            // Resposta da Chat API
            if ($response_code >= 200 && $response_code < 300 && isset($response_data['sent']) && $response_data['sent']) {
                WP_WhatsApp_Sender_Utils::log('Mensagem enviada com sucesso para: ' . $to);
                return $response_data;
            } else {
                $error_message = isset($response_data['message']) ? $response_data['message'] : 'Erro desconhecido ao enviar mensagem.';
                WP_WhatsApp_Sender_Utils::log('Falha ao enviar mensagem: ' . $error_message, 'error');
                return new WP_Error('api_error', $error_message);
            }
        }
    }

    /**
     * Envia uma mensagem com template.
     *
     * @param string $to Número de telefone do destinatário.
     * @param string $template_name Nome do template.
     * @param array $template_params Parâmetros do template.
     * @return array|WP_Error Resposta da API ou objeto de erro.
     */
    public static function send_template_message($to, $template_name, $template_params = array()) {
        // Obtém o texto do template
        $message = get_option('wp_whatsapp_sender_template_' . $template_name, '');
        
        if (empty($message)) {
            return new WP_Error('template_not_found', 'O template especificado não foi encontrado.');
        }
        
        // Substitui os parâmetros no template
        foreach ($template_params as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
        }
        
        // Envia a mensagem processada
        return self::send_message($to, $message);
    }
} 