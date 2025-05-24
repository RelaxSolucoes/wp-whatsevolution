# WP WhatsApp Evolution

Integração avançada do WhatsApp com WooCommerce usando Evolution API.

## Descrição

O WP WhatsApp Evolution é um plugin WordPress que oferece uma integração completa entre o WooCommerce e o WhatsApp através da Evolution API. Permite envio automático de mensagens, notificações por status de pedido, envio em massa e muito mais.

## Requisitos

- PHP 7.4 ou superior
- WordPress 5.8 ou superior
- WooCommerce 5.0 ou superior
- Evolution API configurada e em execução

## Instalação

1. Faça upload dos arquivos do plugin para a pasta `/wp-content/plugins/wp-whatsapp-evolution`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse as configurações do plugin e configure a URL da API, chave e instância

## Configuração

### Evolution API

1. Configure sua instância da Evolution API
2. Obtenha a URL da API e a chave de acesso
3. No WordPress, acesse WP WhatsApp Evolution > Configurações
4. Preencha os dados de conexão e teste a conexão

### Notificações por Status

1. Acesse WP WhatsApp Evolution > Status
2. Configure as mensagens para cada status de pedido
3. Use as variáveis disponíveis para personalizar as mensagens

## Funcionalidades

### Validação de WhatsApp no Checkout

- Validação em tempo real do número de WhatsApp durante o checkout
- Garante que apenas números válidos sejam aceitos
- Reduz erros de digitação e números inválidos
- Melhora a qualidade da base de contatos
- Personalização da mensagem de erro
- Compatível com máscaras de input

### Envio em Massa

- Envio para clientes WooCommerce filtrados por:
  - Status de pedido
  - Período de compra
  - Valor mínimo
- Importação via CSV
- Lista manual de números
- Histórico de envios
- Intervalo configurável entre envios

### Notificações Automáticas

- Mensagens automáticas por status de pedido
- Variáveis dinâmicas nas mensagens
- Personalização por status

### Carrinho Abandonado

- Recuperação automática de carrinhos abandonados
- Mensagens personalizáveis
- Intervalo configurável

## Hooks e Filtros

### Filtros

```php
// Modifica a mensagem antes do envio
add_filter('wpwevo_before_send_message', function($message, $context) {
    return $message;
}, 10, 2);

// Modifica os dados do pedido nas variáveis
add_filter('wpwevo_order_data', function($data, $order) {
    return $data;
}, 10, 2);

// Modifica o intervalo entre envios em massa
add_filter('wpwevo_bulk_send_interval', function($interval) {
    return $interval;
});

// Personaliza a validação do WhatsApp no checkout
add_filter('wpwevo_validate_whatsapp', function($is_valid, $number) {
    return $is_valid;
}, 10, 2);
```

### Actions

```php
// Executado antes do envio em massa
add_action('wpwevo_before_bulk_send', function($numbers, $message) {
    // Seu código aqui
}, 10, 2);

// Executado após envio bem sucedido
add_action('wpwevo_after_message_sent', function($number, $message, $response) {
    // Seu código aqui
}, 10, 3);

// Executado quando ocorre erro no envio
add_action('wpwevo_message_send_error', function($number, $message, $error) {
    // Seu código aqui
}, 10, 3);

// Executado após validação do WhatsApp no checkout
add_action('wpwevo_after_whatsapp_validation', function($number, $is_valid) {
    // Seu código aqui
}, 10, 2);
```

## Segurança

- Todas as requisições são validadas com nonce
- Sanitização de inputs
- Validação de números de telefone
- Rate limiting para envios em massa
- Logs de erros e atividades

## Suporte

Para suporte, acesse:
- [Documentação](https://relaxsolucoes.online/)
- [Fórum de Suporte](https://relaxsolucoes.online/)
- [GitHub](https://github.com/RelaxSolucoes/wp-whatsapp-evolution)

## Changelog

### 1.0.0 - 2024-03-20
- Lançamento inicial
- Integração com Evolution API
- Envio em massa
- Notificações por status
- Recuperação de carrinho abandonado
- Validação de WhatsApp no checkout

## Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## Créditos

Desenvolvido por Relax Soluções
Site: [relaxsolucoes.online](https://relaxsolucoes.online/) 