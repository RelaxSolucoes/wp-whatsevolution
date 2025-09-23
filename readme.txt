=== WP WhatsEvolution ===
Contributors: relaxsolucoes
Tags: whatsapp, woocommerce, evolution api, mensagens, carrinho abandonado, marketing
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.4.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integração completa com WooCommerce usando Evolution API. Envio automático para carrinho abandonado, por status de pedido, mensagens em massa e muito mais.

== Descrição ==

🚀 **WP WhatsEvolution** é o plugin mais completo para integração de mensagens com WooCommerce usando a poderosa Evolution API.

### ✨ Funcionalidades Principais

**🤖 Integração com Agentes de IA (n8n)**
* Conecte fluxos do n8n para gerar mensagens dinâmicas com IA
* Use dados do WooCommerce como contexto para respostas
* Ideal para suporte, upsell e recuperação

**🛒 Carrinho Abandonado (NOVO!)**
* Integração automática com plugin "WooCommerce Cart Abandonment Recovery"
* Envio automático de mensagens para carrinhos abandonados
* Templates personalizáveis com shortcodes dinâmicos
* Logs detalhados de todos os envios

**📊 Envio por Status de Pedido**
* Configure mensagens automáticas para qualquer status (processando, concluído, etc)
* Templates personalizados para cada status
* Suporte a shortcodes dinâmicos (nome, valor, produtos, etc)
* **NOVO**: Sistema inteligente de fallback para endereços de envio

**📱 Envio Individual**
* Envie mensagens para clientes específicos
* Interface simples e intuitiva
* Validação automática de números

**📢 Envio em Massa**
* Envie para múltiplos clientes simultaneamente
* Filtros por pedidos, produtos, datas
* **NOVO**: Sistema de variáveis dinâmicas por aba
* **NOVO**: Interface melhorada com variáveis sempre visíveis
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

### 🆕 Novidades da Versão 1.4.2

**🐛 Correções Críticas:**
* **Submenus funcionando**: Corrigido problema de submenus ausentes
* **Variáveis dinâmicas**: Sistema robusto de exibição por aba
* **Fallback inteligente**: Endereços de envio sempre funcionam

**🚀 Melhorias de Interface:**
* Variáveis WooCommerce sempre visíveis por padrão
* Sistema de fallback para sessionStorage
* Interface mais intuitiva e responsiva

**⚡ Performance:**
* Código otimizado e organizado
* Melhor compatibilidade com WordPress
* Suporte completo a HPOS (WooCommerce)

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

= 1.4.4 =
* **CORREÇÃO CRÍTICA**: Erro JavaScript "Cannot read properties of undefined (reading 'saving')" no Cart Abandonment
* **SALVAMENTO DE TEMPLATES**: Sistema de salvamento de templates totalmente funcional
* **VERIFICAÇÕES DE SEGURANÇA**: Fallbacks para textos padrão caso traduções não estejam disponíveis
* **CACHE ATUALIZADO**: Versionamento do script para garantir limpeza automática do cache
* **ROBUSTEZ**: Sistema com operador de coalescência nula (?.?) para maior estabilidade

= 1.4.3 =
* **COMPATIBILIDADE BRAZILIAN MARKET**: Integração total com Brazilian Market on WooCommerce
* **ENDEREÇOS COMPLETOS**: {shipping_address_full} e {billing_address_full} agora incluem número da casa e bairro
* **DETECÇÃO AUTOMÁTICA**: Sistema inteligente que detecta se o Brazilian Market está ativo
* **FALLBACK INTELIGENTE**: Funciona perfeitamente com ou sem o plugin Brazilian Market
* **ORDEM CORRETA**: Endereços formatados como "Rua, Número, Bairro, Cidade, Estado, CEP"

= 1.4.2 =
* **CORREÇÃO ANTI-BUG**: Plugin Cart Abandonment Recovery v2.0 - evita envio para clientes que já finalizaram pedidos
* **VERIFICAÇÃO INTELIGENTE**: Remove carrinhos de clientes com pedidos nas últimas 2 horas
* **LOGS DETALHADOS**: Rastreamento completo de carrinhos removidos por pedidos finalizados
* **PERFORMANCE**: Verificação individual antes do processamento de carrinhos
* **COMPATIBILIDADE**: Funciona com status completed, processing, on-hold, pending

= 1.4.1 =
* **CORREÇÃO CRÍTICA**: Submenus agora funcionam perfeitamente
* **NOVO**: Sistema de variáveis dinâmicas por aba no envio em massa
* **NOVO**: Fallback inteligente para endereços de envio
* **MELHORIA**: Interface mais intuitiva e responsiva
* **CORREÇÃO**: Propriedades de menu definidas corretamente
* **OTIMIZAÇÃO**: Código reorganizado e otimizado
* **COMPATIBILIDADE**: Suporte completo a HPOS WooCommerce

= 1.4.0 =
* Integração com Agentes de IA (n8n)
* Sistema de Carrinho Abandonado
* Envio por Status de Pedido
* Envio Individual e em Massa
* Validação no Checkout
* Interface moderna e responsiva

= 1.3.1 - 2025-01-27 =
*   **NOVO**: Sistema automático de adição de notas nos pedidos ao enviar mensagens de WhatsApp
*   **Carrinho Abandonado**: Notas são adicionadas automaticamente quando mensagens são enviadas para carrinhos abandonados
*   **Mudanças de Status**: Notas são criadas quando mensagens são enviadas por mudanças de status de pedido
*   **Rastreabilidade**: Todas as mensagens enviadas ficam registradas no histórico do pedido para auditoria
*   **Correção de Bugs**: Diversos bugs menores foram corrigidos para melhor estabilidade
*   **Otimização**: Melhorias de performance no sistema de envio de mensagens
*   **Compatibilidade**: Garantida compatibilidade total com WooCommerce 8.0+

1. Dashboard principal com todas as funcionalidades
2. Configuração da Evolution API
3. Sistema de Carrinho Abandonado
4. Envio por Status de Pedido
5. Interface de Envio em Massa
6. Validação no Checkout

== Upgrade Notice ==

= 1.4.3 =
Esta versão adiciona compatibilidade total com Brazilian Market on WooCommerce, garantindo que endereços completos (com número da casa e bairro) sejam exibidos nas mensagens. Funciona automaticamente com ou sem o plugin Brazilian Market.

= 1.4.0 =
Esta versão adiciona integração com agentes de IA do n8n para fluxos conversacionais e mensagens dinâmicas com contexto do WooCommerce. Recomendamos a atualização para aproveitar as novas automações de IA.

= 1.3.1 =
Esta versão adiciona o sistema automático de notas nos pedidos, garantindo rastreabilidade completa de todas as mensagens enviadas. Recomendamos a atualização para ter acesso ao novo sistema de auditoria e as correções de bugs implementadas.

= 1.3.0 =
Esta versão contém uma reconstrução completa da funcionalidade de Envio em Massa. Recomendamos fortemente a atualização para ter acesso à nova interface, importação de CSV aprimorada, personalização com variáveis e relatórios de erro detalhados. 