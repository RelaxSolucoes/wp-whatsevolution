# Changelog - WP WhatsEvolution

## [1.4.3] - 2025-01-27

### 🇧🇷 Compatibilidade Total com Brazilian Market on WooCommerce
- **Integração completa**: Suporte total aos campos customizados do Brazilian Market
- **Endereços completos**: `{shipping_address_full}` e `{billing_address_full}` agora incluem número da casa e bairro
- **Detecção automática**: Sistema inteligente que detecta se o Brazilian Market está ativo
- **Fallback inteligente**: Funciona perfeitamente com ou sem o plugin Brazilian Market
- **Ordem correta**: Endereços formatados como "Rua, Número, Bairro, Cidade, Estado, CEP"

### 🔧 Melhorias Técnicas
- **Captura de meta fields**: Acesso aos campos `_shipping_number`, `_shipping_neighborhood`, `_billing_number`, `_billing_neighborhood`
- **Função auxiliar**: Código reutilizável `build_address_full()` para montagem de endereços
- **Compatibilidade**: Zero impacto para usuários que não usam Brazilian Market
- **Precisão**: Endereços sempre completos e formatados corretamente

### 🎯 Benefícios da Integração
- **Endereços precisos**: Número da casa e bairro sempre incluídos nas mensagens
- **Mercado brasileiro**: Otimizado para o padrão de endereços do Brasil
- **Plug & Play**: Funciona automaticamente sem configuração adicional
- **Mensagens profissionais**: Endereços completos para melhor experiência do cliente

---

## [1.4.2] - 2025-01-27

### 🐛 Correção Anti-Bug para Cart Abandonment Recovery v2.0
- **Problema identificado**: Plugin Cart Abandonment Recovery v2.0 marca pedidos finalizados como abandonados
- **Solução implementada**: Verificação automática que remove carrinhos de clientes que já finalizaram pedidos
- **Critérios**: Remove carrinhos se cliente finalizou pedido nas últimas 2 horas
- **Status considerados**: completed, processing, on-hold, pending
- **Logs detalhados**: Rastreamento completo de carrinhos removidos por pedidos finalizados

### 🔧 Melhorias Técnicas
- **Busca otimizada**: Uso correto de meta_query para _billing_phone no WooCommerce
- **Performance**: Verificação individual antes do processamento de carrinhos
- **Auditoria**: Sistema de logs para monitoramento e debug

### 📚 Documentação
- **Arquivo**: ANTI-BUG-IMPLEMENTATION.md com detalhes técnicos completos
- **Guia**: Instruções de teste e validação da funcionalidade

### 🎯 Benefícios
- **Sem spam**: Clientes não recebem mensagens desnecessárias
- **Taxa de conversão**: Mensagens apenas para carrinhos realmente abandonados
- **Experiência do cliente**: Evita confusão sobre status do pedido
- **Eficiência**: Reduz custos de envio desnecessário

---

## [1.4.1] - 2025-01-27

### ✨ Novas Funcionalidades
- **Detecção Automática de Versão da Evolution API**: Sistema agora verifica automaticamente a versão da Evolution API configurada
- **Validação de Compatibilidade**: Detecta se a API é V2.x.x (compatível) ou V1.x.x (pode ter problemas)
- **Avisos Inteligentes**: Mostra avisos apropriados baseados na versão detectada da API

### 🔧 Melhorias
- **Verificação de Versão**: Antes de testar conexão da instância, verifica a versão da API via endpoint raiz
- **Interface Informativa**: Exibe versão da API e status de compatibilidade na página de configurações
- **Avisos Visuais**: Cards de aviso sobre compatibilidade de versões da Evolution API

### 🐛 Correções
- **Compatibilidade V1**: Sistema continua funcionando com V1.x.x mas mostra avisos apropriados
- **Validação Robusta**: Verificação de versão apenas quando conexão da instância é bem-sucedida
- **Modo Managed**: Funcionalidade não afeta o modo de configuração automática

### 📚 Documentação
- **Guia de Compatibilidade**: Documentação sobre versões suportadas da Evolution API
- **Avisos de Incompatibilidade**: Explicação sobre mensagens de aviso para versões V1

### 🐛 Correções Críticas e Melhorias Significativas

**🔧 Correções Críticas**
* **CORREÇÃO CRÍTICA**: Submenus agora funcionam perfeitamente em todas as funcionalidades
* **CORREÇÃO**: Propriedades de menu definidas corretamente no timing dos hooks WordPress
* **CORREÇÃO**: Sistema de variáveis dinâmicas funcionando por aba no envio em massa

**🚀 Novas Funcionalidades**
* **NOVO**: Sistema inteligente de fallback para endereços de envio
* **NOVO**: Variáveis WooCommerce sempre visíveis por padrão
* **NOVO**: Sistema robusto de fallbacks para sessionStorage e compatibilidade

**⚡ Melhorias de Performance**
* **OTIMIZAÇÃO**: Código reorganizado seguindo boas práticas WordPress
* **OTIMIZAÇÃO**: Interface mais intuitiva e responsiva
* **COMPATIBILIDADE**: Suporte completo a HPOS (WooCommerce Custom Order Tables)

**📱 Melhorias de Interface**
* Interface de envio em massa com variáveis dinâmicas por aba
* Sistema de fallback para garantir que variáveis sejam sempre exibidas
* Melhor organização visual e responsividade

**🏗️ Arquitetura**
* Propriedades de menu movidas para `__construct()` ANTES dos hooks
* Sistema de hooks WordPress corrigido e otimizado
* Código seguindo padrões modernos de desenvolvimento

---

## [1.4.0] - 2025-08-12
### 🤖 Integração com Agentes de IA do n8n

**Novidades**
* Integração com agentes de IA do n8n para automações conversacionais
* Mensagens dinâmicas com contexto do WooCommerce (pedidos e clientes)

**Outros**
* Documentação atualizada (`README.md`, `readme.txt`)
* Pequenas melhorias e ajustes de estabilidade

---

## [1.3.2] - 2025-08-12
### 🔧 Padronização, Compatibilidade e Estabilidade

**I18n & Slug**
* Padronizado o text domain para `wp-whatsevolution` em todo o plugin
* Renomeado o arquivo principal para `wp-whatsevolution.php`

**Execução & UX**
* Removido o agendamento via CRON (envio ocorre apenas com a tela aberta)
* Checkout: valida apenas campos de telefone (ignora CPF/CNPJ)
* Compatibilidade com Cart Abandonment Recovery mantida sem exibir aviso ao usuário
* Fallback para telas do Quick Signup quando templates não existirem

**Traduções**
* Geração automática do `.mo` a partir do `.po` quando ausente

---

## [1.3.1] - 2025-01-27
### 🚀 Novas Funcionalidades e Correções

**📝 Adição de Notas nos Pedidos**
* **NOVO**: Sistema automático de adição de notas nos pedidos ao enviar mensagens de WhatsApp
* **Carrinho Abandonado**: Notas são adicionadas automaticamente quando mensagens são enviadas para carrinhos abandonados
* **Mudanças de Status**: Notas são criadas quando mensagens são enviadas por mudanças de status de pedido
* **Rastreabilidade**: Todas as mensagens enviadas ficam registradas no histórico do pedido para auditoria

**🔧 Melhorias e Correções**
* **Correção de Bugs**: Diversos bugs menores foram corrigidos para melhor estabilidade
* **Otimização**: Melhorias de performance no sistema de envio de mensagens
* **Compatibilidade**: Garantida compatibilidade total com WooCommerce 8.0+

**📋 Detalhes Técnicos**
* As notas incluem: tipo de mensagem, data/hora, número de telefone e status do envio
* Sistema funciona automaticamente sem necessidade de configuração adicional
* Compatível com todos os tipos de envio: individual, em massa, por status e carrinho abandonado

---

## [1.3.0] - 2025-06-21
### 🚀 Lançamento Oficial

Esta é a primeira versão pública e estável do WP WhatsEvolution, resultado de várias iterações, correções e melhorias.

Principais destaques:
- Envio em massa reescrito do zero, robusto e intuitivo
- Carrinho abandonado com integração automática
- Envio por status de pedido automatizado
- Envio individual e validação no checkout
- Templates personalizáveis e sistema de variáveis dinâmicas
- Compatibilidade total com WooCommerce e Evolution API

> Versões anteriores (1.0.x, 1.1.x, 1.2.x) foram usadas apenas para desenvolvimento e testes internos.

---

## Histórico resumido

- [1.2.x] e anteriores: versões internas, não recomendadas para uso público. 