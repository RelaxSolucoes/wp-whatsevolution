# WP WhatsApp Sender Evolution

Plugin WordPress para integração com a Evolution API, permitindo envio de mensagens WhatsApp através do WooCommerce.

## Requisitos

- WordPress 5.8 ou superior
- PHP 7.4 ou superior
- WooCommerce 5.0 ou superior
- Evolution API instalada e configurada

## Instalação

1. Faça o upload da pasta `wp-whatsapp-evolution` para o diretório `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Acesse as configurações em 'WhatsApp Evolution' no menu do WordPress

## Configuração

1. Instale a Evolution API em seu servidor seguindo a [documentação oficial](https://doc.evolution-api.com/v2/pt/get-started/introduction)
2. Obtenha a URL da API, API KEY e crie uma instância do WhatsApp
3. Configure estas informações no painel do plugin
4. Teste a conexão antes de começar a usar

## Funcionalidades

### 1. Painel de Conexão
- Configure URL da API, API KEY e nome da instância
- Teste a conexão com feedback visual
- Link para documentação da Evolution API

### 2. Envio Único
- Interface simples para envio de mensagens
- Validação de número em tempo real
- Suporte a templates salvos
- Feedback de envio via AJAX

### 3. Envio por Status de Pedido
- Configure mensagens para cada status WooCommerce
- Ative/desative notificações por status
- Preview de mensagem com dados reais
- Suporte a variáveis como {customer_name}, {order_id}, etc.

### 4. Carrinho Abandonado
- Detecção automática de carrinhos abandonados
- Configuração de tempo de espera
- Mensagens personalizadas com variáveis
- Estatísticas de recuperação
- Salvamento do telefone via cookie/sessão

### 5. Envio em Massa
- Três modos: Clientes WooCommerce, CSV e Lista Manual
- Filtros por status, período e valor mínimo
- Preview de clientes selecionados
- Agendamento de envios
- Controle de intervalo entre mensagens
- Histórico de envios
- Barra de progresso

## Variáveis Disponíveis

- `{customer_name}` - Nome do cliente
- `{order_id}` - Número do pedido
- `{order_total}` - Valor total do pedido
- `{order_status}` - Status do pedido
- `{payment_method}` - Método de pagamento
- `{cart_total}` - Valor total do carrinho
- `{cart_items}` - Lista de produtos no carrinho
- `{cart_url}` - Link para recuperar o carrinho

## Suporte

Para suporte, por favor abra uma issue no repositório do plugin ou entre em contato através do fórum do WordPress.

## Licença

Este plugin é licenciado sob a GPL v2 ou posterior.

## Créditos

Desenvolvido por [Seu Nome] 