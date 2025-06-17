<?php
require_once('../../../wp-config.php');

echo "=== TESTANDO EVOLUTION API DIRETAMENTE ===\n\n";

// Pegar configurações da Evolution API
$api_url = get_option('wpwevo_api_url');
$api_key = get_option('wpwevo_api_key');
$instance = get_option('wpwevo_instance');

echo "📋 CONFIGURAÇÕES DA API:\n";
echo "  🔗 URL: $api_url\n";
echo "  🔑 Key: " . (!empty($api_key) ? substr($api_key, 0, 10) . '...' : 'NÃO CONFIGURADA') . "\n";
echo "  📱 Instância: $instance\n\n";

if (empty($api_url) || empty($api_key) || empty($instance)) {
    echo "❌ CONFIGURAÇÕES INCOMPLETAS!\n";
    echo "💡 Configure a Evolution API no admin do WordPress\n";
    echo "\n=== FIM DO TESTE ===\n";
    exit;
}

// Testar envio direto
$phone = '5511999888777@c.us';
$message = '🧪 Teste direto da Evolution API - ' . date('H:i:s');

echo "🧪 TESTANDO ENVIO DIRETO:\n";
echo "  📱 Para: $phone\n";
echo "  📝 Mensagem: $message\n\n";

// Montar payload
$payload = [
    'number' => $phone,
    'textMessage' => [
        'text' => $message
    ]
];

$json_payload = json_encode($payload);

echo "📦 Payload JSON:\n";
echo "$json_payload\n\n";

// Fazer requisição
$url = rtrim($api_url, '/') . '/message/sendText/' . $instance;

echo "📤 URL completa: $url\n";

$response = wp_remote_post($url, [
    'headers' => [
        'Content-Type' => 'application/json',
        'apikey' => $api_key
    ],
    'body' => $json_payload,
    'timeout' => 30
]);

if (is_wp_error($response)) {
    echo "❌ ERRO na requisição: " . $response->get_error_message() . "\n";
} else {
    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    echo "📊 Status HTTP: $status_code\n";
    echo "📝 Resposta:\n";
    echo "$response_body\n\n";
    
    if ($status_code == 200 || $status_code == 201) {
        $response_data = json_decode($response_body, true);
        
        if (isset($response_data['key'])) {
            echo "🎉 SUCESSO! WhatsApp enviado!\n";
            echo "✅ Message Key: {$response_data['key']['id']}\n";
        } else {
            echo "⚠️ Resposta 200 mas sem 'key' - verificar formato\n";
        }
    } else {
        echo "❌ FALHA! Status $status_code\n";
        
        // Tentar decodificar erro
        $error_data = json_decode($response_body, true);
        if (isset($error_data['message'])) {
            echo "💬 Erro: {$error_data['message']}\n";
        }
        
        if ($status_code == 500) {
            echo "🔍 Erro 500 = problema no servidor Evolution API\n";
            echo "💡 Verificar se Evolution API está online\n";
        } elseif ($status_code == 401) {
            echo "🔍 Erro 401 = problema de autenticação\n";
            echo "💡 Verificar API Key\n";
        } elseif ($status_code == 404) {
            echo "🔍 Erro 404 = instância não encontrada\n";
            echo "💡 Verificar nome da instância\n";
        }
    }
}

echo "\n=== FIM DO TESTE ===\n"; 