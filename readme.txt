=== WP WhatsEvolution ===
Contributors: relaxsolucoes
Tags: whatsapp, woocommerce, evolution api, mensagens, carrinho abandonado, marketing
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.2.6
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

= 1.2.6 =
* 🎯 CORREÇÃO CRÍTICA: Submenus principais agora aparecem corretamente
* 🔧 HOOKS OTIMIZADOS: Ordem de inicialização corrigida (menu_title antes de admin_menu)
* 🎨 CSS FUNCIONANDO: Estilos carregam corretamente em todas as páginas
* ✅ INTERFACE COMPLETA: Envio Individual, Envio por Status e Envio em Massa 100% funcionais
* ⚡ PERFORMANCE: Inicialização de propriedades movida para __construct()
* 🐛 BUGFIX: Condição invertida strpos() corrigida em 6 arquivos

= 1.2.1 =
* 🤖 NOVO: Auto-update via GitHub Releases
* 🔄 Sistema de atualização automática implementado
* 📦 Plugin Update Checker integrado
* ✨ Atualizações automáticas sem intervenção manual
* 🛠️ Compatibilidade com GitHub Releases API

= 1.2.0 =
* 🚀 NOVO: Sistema de Onboarding 1-Click completo!
* 🆕 Integração automática com sistema principal via Edge Functions
* ✨ Criação de conta automática e configuração instantânea
* 📱 QR Code dinâmico e detecção automática de conexão WhatsApp
* ⚡ Polling otimizado (3 segundos) para detecção rápida
* 🎯 Interface moderna e responsiva para teste grátis
* 🔧 Sistema de status em tempo real sincronizado
* 🛠️ Reset automático para facilitar testes
* 🔌 Arquitetura cross-project otimizada
* ✅ Compatibilidade total com Supabase Edge Functions

= 1.0.4 =
* 🆕 NOVO: Integração completa com carrinho abandonado
* 🆕 Suporte ao plugin "WooCommerce Cart Abandonment Recovery"
* 🆕 Templates personalizáveis para carrinho abandonado
* 🆕 Shortcodes dinâmicos para mensagens
* ✨ Logs otimizados e mais limpos
* 🔧 Formatação automática de moeda brasileira (R$)
* 🐛 Correções de compatibilidade
* 🏷️ Renomeado para WP WhatsEvolution (questões legais)

= 1.0.3 =
* ✨ Envio por status de pedido
* ✨ Envio em massa melhorado
* 🔧 Validação de checkout
* 🐛 Correções gerais

= 1.0.2 =
* ✨ Envio em massa
* 🔧 Melhorias na interface
* 🐛 Correções de bugs

= 1.0.1 =
* ✨ Envio individual
* 🔧 Melhorias na conexão
* 🐛 Correções iniciais

= 1.0.0 =
* 🚀 Versão inicial
* ✨ Conexão com Evolution API
* ✨ Configurações básicas

== Upgrade Notice ==

= 1.0.4 =
REBRANDING: Agora é WP WhatsEvolution! Nova funcionalidade: Carrinho Abandonado com integração automática e templates personalizáveis. Atualize agora! 