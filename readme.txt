=== WP WhatsEvolution ===
Contributors: relaxsolucoes
Tags: whatsapp, woocommerce, evolution api, mensagens, carrinho abandonado, marketing
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.3.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integração completa com WooCommerce usando Evolution API. Envio automático para carrinho abandonado, por status de pedido, mensagens em massa e muito mais.

== Descrição ==

🚀 **WP WhatsEvolution** é o plugin mais completo para integração de mensagens com WooCommerce usando a poderosa Evolution API.

### ✨ Funcionalidades Principais

**🛒 Carrinho Abandonado (NOVO!)**
* Integração automática com plugin "WooCommerce Cart Abandonment Recovery"
* Envio automático de mensagens para carrinhos abandonados
* Templates personalizáveis com shortcodes dinâmicos
* Logs detalhados de todos os envios

**📊 Envio por Status de Pedido**
* Configure mensagens automáticas para qualquer status (processando, concluído, etc)
* Templates personalizados para cada status
* Suporte a shortcodes dinâmicos (nome, valor, produtos, etc)

**📱 Envio Individual**
* Envie mensagens para clientes específicos
* Interface simples e intuitiva
* Validação automática de números

**📢 Envio em Massa**
* Envie para múltiplos clientes simultaneamente
* Filtros por pedidos, produtos, datas
* Controle de velocidade e logs completos

**✅ Validação no Checkout**
* Torna o campo telefone obrigatório
* Validação em tempo real do formato
* Compatível com checkout padrão e plugins

### 🔧 Configuração Fácil

1. **Instale o plugin**
2. **Configure sua Evolution API** (URL, API Key, Instância)
3. **Teste a conexão**
4. **Configure as funcionalidades desejadas**
5. **Pronto!** O sistema funcionará automaticamente

### 📋 Requisitos

* WordPress 5.8+
* WooCommerce 5.0+
* PHP 7.4+
* Evolution API configurada

### 🎯 Como Usar Carrinho Abandonado

1. **Instale o plugin** "WooCommerce Cart Abandonment Recovery"
2. **Ative a integração** em "WhatsEvolution > Carrinho Abandonado"
3. **Personalize a mensagem** com shortcodes disponíveis
4. **Monitore os envios** através dos logs em tempo real

O sistema intercepta automaticamente os carrinhos abandonados e envia mensagens personalizadas!

### 🔗 Shortcodes Disponíveis

* `{first_name}` - Nome do cliente
* `{full_name}` - Nome completo
* `{product_names}` - Produtos no carrinho
* `{cart_total}` - Valor total formatado (R$ 99,90)
* `{checkout_url}` - Link para finalizar compra
* `{coupon_code}` - Código do cupom
* E muito mais!

== Instalação ==

1. Faça upload do plugin para `/wp-content/plugins/`
2. Ative o plugin no painel do WordPress
3. Vá em "WhatsEvolution" para configurar
4. Configure sua Evolution API
5. Teste a conexão
6. Configure as funcionalidades desejadas

== Frequently Asked Questions ==

= Preciso de qual Evolution API? =
Qualquer instância da Evolution API v2.0+ funcionará perfeitamente.

= O carrinho abandonado funciona com qualquer tema? =
Sim! A integração é feita via hooks internos do WooCommerce.

= Posso personalizar as mensagens? =
Totalmente! Use os shortcodes disponíveis para criar mensagens dinâmicas.

= O plugin é gratuito? =
Sim, 100% gratuito e open source.

== Screenshots ==

1. Painel principal de configuração
2. Configuração da Evolution API
3. Envio por status de pedido
4. Carrinho abandonado com templates
5. Envio em massa
6. Logs em tempo real

== Changelog ==

= 1.3.1 - 2025-01-27 =
*   **NOVO**: Sistema automático de adição de notas nos pedidos ao enviar mensagens de WhatsApp
*   **Carrinho Abandonado**: Notas são adicionadas automaticamente quando mensagens são enviadas para carrinhos abandonados
*   **Mudanças de Status**: Notas são criadas quando mensagens são enviadas por mudanças de status de pedido
*   **Rastreabilidade**: Todas as mensagens enviadas ficam registradas no histórico do pedido para auditoria
*   **Correção de Bugs**: Diversos bugs menores foram corrigidos para melhor estabilidade
*   **Otimização**: Melhorias de performance no sistema de envio de mensagens
*   **Compatibilidade**: Garantida compatibilidade total com WooCommerce 8.0+

= 1.3.0 - 2024-06-21 =
*   **REVOLUÇÃO NO ENVIO EM MASSA**: A funcionalidade de Envio em Massa foi completamente reconstruída do zero para ser mais poderosa, intuitiva e à prova de falhas.
*   **Melhoria - Interface de Importação CSV Inteligente:** A tela de importação de CSV agora é visualmente clara, com uma tabela de exemplo que elimina a confusão entre colunas e vírgulas.
*   **Melhoria - Robustez do CSV:** O sistema agora detecta automaticamente se o separador é vírgula (,) ou ponto e vírgula (;), garantindo compatibilidade com Excel de diferentes regiões. Também corrige problemas de codificação de caracteres (acentos).
*   **Melhoria - Personalização com Variáveis:** Agora é possível usar `{customer_name}` e `{customer_phone}` em mensagens para contatos importados via CSV. Para clientes WooCommerce, a lista de variáveis foi expandida.
*   **Melhoria - UI Dinâmica:** A seção "Variáveis Disponíveis" agora é inteligente e mostra apenas as variáveis que se aplicam à aba selecionada (WooCommerce, CSV ou Manual).
*   **Melhoria - Relatórios de Erro Detalhados:** As mensagens de erro agora são específicas, informando exatamente qual número falhou e por quê (ex: "Formato inválido").
*   **Correção:** Inúmeros bugs de lógica e validação foram corrigidos, garantindo que cada aba (WooCommerce, CSV, Manual) funcione de forma independente e correta.
*   **Correção:** Resolvido o problema no download do arquivo de exemplo, que agora é gerado em um formato 100% compatível com Excel (incluindo o BOM para UTF-8).

= 1.2.8 =
*   Fix: Corrigido o problema do seletor de mensagem no envio em massa.

= 1.2.7 =
*   Fix: Removidos arquivos de teste e logs desnecessários.

= 1.2.6 =
*   Fix: Corrigido o problema dos submenus que não apareciam.

= 1.2.5 =
*   Fix: Corrigido o problema de fallback de endereço de entrega.

= 1.2.4 =
*   Fix: Corrigido o problema da barra de progresso no envio em massa.

= 1.2.3 =
*   Fix: Melhorias na interface do envio em massa.

= 1.2.2 =
*   Fix: Correção na validação de números de telefone.

= 1.2.1 =
*   Fix: Correção no trigger de amostra do abandono de carrinho.

= 1.2.0 =
*   Feature: Adicionado o sistema de signup rápido.
*   Feature: Adicionado o sistema de status do plugin.
*   Feature: Adicionado o sistema de checagem de atualizações.
*   Fix: Melhorias gerais de performance e usabilidade.
*   Fix: Correção de bugs menores.
*   I18n: Adicionada a tradução para Português do Brasil.

== Upgrade Notice ==

= 1.3.1 =
Esta versão adiciona o sistema automático de notas nos pedidos, garantindo rastreabilidade completa de todas as mensagens enviadas. Recomendamos a atualização para ter acesso ao novo sistema de auditoria e as correções de bugs implementadas.

= 1.3.0 =
Esta versão contém uma reconstrução completa da funcionalidade de Envio em Massa. Recomendamos fortemente a atualização para ter acesso à nova interface, importação de CSV aprimorada, personalização com variáveis e relatórios de erro detalhados. 