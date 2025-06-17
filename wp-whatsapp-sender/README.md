# WP WhatsApp Sender - Instruções de Instalação

Plugin para WordPress que permite enviar mensagens via WhatsApp para clientes diretamente do painel administrativo.

## Estrutura do Plugin

```
wp-whatsapp-sender/
├── admin/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   ├── class-wp-whatsapp-sender-admin.php
│   └── class-wp-whatsapp-sender-bulk.php
├── includes/
│   ├── class-wp-whatsapp-sender-api.php
│   ├── class-wp-whatsapp-sender-utils.php
│   └── class-wp-whatsapp-sender-woocommerce.php
├── wp-whatsapp-sender.php
├── uninstall.php
└── README.md
```

## Preparando o Plugin para Instalação

Para instalar o plugin no WordPress, siga os passos abaixo:

1. Compacte a pasta `wp-whatsapp-sender` criando um arquivo ZIP.
2. No painel administrativo do WordPress, vá em "Plugins > Adicionar Novo".
3. Clique em "Enviar Plugin" e selecione o arquivo ZIP criado.
4. Após o upload, clique em "Ativar Plugin".

## Como Criar o Arquivo ZIP no Windows

1. Clique com o botão direito do mouse na pasta `wp-whatsapp-sender`.
2. Selecione "Enviar para > Pasta compactada (zipada)".
3. Renomeie o arquivo para `wp-whatsapp-sender.zip`.

## Configuração Após a Instalação

1. Após a instalação, acesse o menu "WhatsApp Sender > Configurações" no painel administrativo.
2. Insira sua API Key e URL da API fornecidos pelo serviço de API do WhatsApp que você está utilizando.
3. Salve as configurações.

## APIs Compatíveis

Este plugin foi desenvolvido para ser compatível com:

- [Chat API](https://chat-api.com)
- WhatsApp Business API
- Outras APIs que oferecem endpoints HTTP para envio de mensagens

## Funcionalidades Principais

- Envio individual de mensagens
- Criação e uso de templates
- Envio em massa para usuários e clientes
- Integração com WooCommerce
- Envio de mensagens a partir da página de pedidos do WooCommerce

## Como Testar o Plugin

### Configuração da API

Para testar o plugin, você precisará de uma conta em um serviço de API do WhatsApp. Algumas opções:

1. **Chat API**: Oferece um período de teste gratuito. Registre-se em https://chat-api.com
2. **WhatsApp Business API**: Requer aprovação da Meta. Saiba mais em https://developers.facebook.com/docs/whatsapp/api/overview

### Testando o Envio de Mensagens

1. Configure a API nas configurações do plugin
2. Acesse "WhatsApp Sender > Enviar Mensagem"
3. Digite um número de telefone no formato internacional (por exemplo: 5511999999999)
4. Digite uma mensagem e clique em "Enviar Mensagem"

### Criando Templates

1. Acesse "WhatsApp Sender > Templates"
2. Crie templates com variáveis como `{{customer_name}}`, `{{order_id}}`, etc.
3. Estes templates podem ser usados tanto no envio individual quanto em massa

## Integração com WooCommerce

Se você tiver o WooCommerce instalado, o plugin adiciona:

1. Um botão de envio de WhatsApp na página de detalhes do pedido
2. Um metabox para envio de mensagens na lateral da tela de pedido
3. Opções de envio em massa para clientes que realizaram compras

## Suporte

Para dúvidas ou suporte, entre em contato através de: [seu-email@dominio.com]

## Licença

Este plugin é licenciado sob a licença GPL v2 ou posterior.

## Changelog

### 1.0.0
- Versão inicial do plugin
- Suporte para envio de mensagens individuais
- Sistema de templates de mensagens
- Integração com Chat API 