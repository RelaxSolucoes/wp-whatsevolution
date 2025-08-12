# Changelog - WP WhatsEvolution

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