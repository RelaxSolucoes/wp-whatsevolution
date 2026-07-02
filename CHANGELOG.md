# Changelog - WP WhatsEvolution

## [1.5.0] - 2026-07-02

### 🚀 Modo Managed migrado para o WhatsEvolution V2

**O backend do teste grátis/cobrança agora é o WhatsEvolution V2 (whats-evolution-v2.vercel.app). A V1 entra em modo legado.**

#### 🔄 Migração
- **Novo backend**: signup, status, pagamento e polling agora usam o endpoint único `/api/plugin` da V2
- **Envio direto pela Evolution API**: no modo managed, mensagens e validação de número agora vão direto pela Evolution API com as credenciais da instância (corrige envio managed que falhava — as Edge Functions `send-message`/`validate-number` da V1 não existiam)
- **Renovação por PIX no wp-admin**: pagamento aprovado renova a instância automaticamente por +30 dias (webhook Mercado Pago da V2)
- **Modelo de validade unificado**: `trial_expires_at` agora reflete o `expires_at` da instância na V2 (trial e plano pago renovável)

#### 🐛 Correções
- Cálculo de dias restantes usava `current_time('timestamp')` (fuso do WP) contra epoch UTC — corrigido para `time()`
- Removido `sslverify => false` em chamada de validação de número
- Polling de pagamento não expõe mais URL/chave do Supabase no navegador (proxy via admin-ajax)
- Removido enqueue/localize duplicado do `quick-signup.js` (Settings_Page × Quick_Signup)

#### 🧹 Limpeza
- Removido código morto: `render_page_content`/`render_signup_form`/`render_status_view` (com bug fatal de PHP 8), helpers de envio duplicados apontando para a V1
- URLs do backend centralizadas em `includes/config.php` (`WHATSEVOLUTION_API_BASE`, `WHATSEVOLUTION_DASHBOARD_URL`)

## [1.4.8] - 2025-01-27

### 🎲 Sistema de Mensagens Aleatórias para Envio em Massa

**Funcionalidade que permite criar múltiplos templates de mensagem com seleção aleatória automática**

#### 🆕 Principais Funcionalidades

##### 1. Interface de Múltiplas Mensagens
- **Botão "+"**: Adiciona novas mensagens dinamicamente
- **Botão "Remover"**: Remove mensagens individuais (mínimo 1 mensagem)
- **Numeração Automática**: Mensagens numeradas sequencialmente
- **Interface Intuitiva**: Design consistente com o plugin
- **Posicionamento Dinâmico**: Botão + sempre abaixo da última mensagem

##### 2. Sistema de Seleção Aleatória
- **Algoritmo**: `array_rand()` do PHP para seleção verdadeiramente aleatória
- **Por Número**: Cada contato recebe UMA mensagem selecionada aleatoriamente
- **Distribuição**: Exemplo - número 1 → msg 3, número 2 → msg 1, número 3 → msg 5
- **Naturalidade**: Evita detecção como spam por variação de conteúdo

##### 3. Compatibilidade Total
- ✅ **Variáveis**: Todas as variáveis continuam funcionando (`{customer_name}`, `{order_id}`, etc.)
- ✅ **Processamento**: Variáveis substituídas individualmente em cada mensagem
- ✅ **Filtros**: Compatível com filtros de data, valor, inatividade
- ✅ **Intervalos**: Funciona com modo fixo e aleatório
- ✅ **Todas as Abas**: Clientes WooCommerce, Todos os Clientes, CSV, Manual

##### 4. Validações e Segurança
- **Mínimo**: Pelo menos 1 mensagem obrigatória
- **Sanitização**: Todas as mensagens sanitizadas com `sanitize_textarea_field()`
- **Fallback**: Compatibilidade com mensagem única (sistemas antigos)
- **Nonce**: Verificação de segurança mantida

#### 💻 Implementação Técnica

**Frontend (HTML/PHP):**
- **Arquivo**: `includes/class-bulk-sender.php` (linhas 572-598)
- **Container**: `#wpwevo-messages-container` com mensagens dinâmicas
- **Nome do Campo**: `wpwevo_bulk_messages[]` (array)

**JavaScript:**
- **Arquivo**: `assets/js/bulk-send.js` (linhas 582-656)
- **Função Principal**: `initMultipleMessages()`
- **Eventos**: Delegação com `$(document).on()` para elementos dinâmicos
- **Validação**: Coleta array de mensagens com `formData.getAll()`

**Backend (PHP):**
- **Arquivo**: `includes/class-bulk-sender.php` (linhas 1878-1988)
- **Coleta**: Loop sobre `$_POST['wpwevo_bulk_messages']`
- **Seleção**: `$messages[array_rand($messages)]` por número
- **Processamento**: `replace_variables()` com dados do contato

#### 🎯 Casos de Uso

**1. Múltiplas Abordagens:**
```
Mensagem 1: Olá {customer_name}! Seu pedido #{order_id} está pronto! 🎉
Mensagem 2: Oi {customer_name}, temos uma novidade sobre seu pedido #{order_id}! ✨
Mensagem 3: {customer_name}, boas notícias sobre o pedido #{order_id}! 🚀
```

**2. Variação de Tom:**
```
Mensagem 1: Formal e profissional
Mensagem 2: Casual e amigável
Mensagem 3: Entusiasta e enérgica
```

**3. Testes A/B:**
```
Mensagem 1: CTA direto
Mensagem 2: CTA com urgência
Mensagem 3: CTA com benefício
```

#### ✨ Benefícios

- 🎲 **Anti-Spam**: Variação natural evita bloqueios
- 🎯 **Personalização**: Cada contato recebe mensagem única
- ⚡ **Eficiência**: Processo automático sem intervenção manual
- 📊 **Testes**: Possibilidade de testar diferentes abordagens
- 🔄 **Flexibilidade**: Adicione quantas mensagens quiser

#### 🔧 Arquivos Modificados

1. **includes/class-bulk-sender.php**
   - Interface HTML de múltiplas mensagens
   - Backend de processamento aleatório

2. **assets/js/bulk-send.js**
   - Gerenciamento de mensagens dinâmicas
   - Validação de múltiplas mensagens

3. **assets/css/** (estilos inline)
   - Hover effects nos botões
   - Animações de transição

---

## [1.4.7] - 2025-11-07

### 📦 Variáveis de Rastreamento e Sistema de Logs

**Principais funcionalidades para rastreamento de envios e monitoramento centralizado**

#### 🆕 Novas Variáveis de Rastreamento

##### 1. Variáveis Implementadas
- **`{tracking_code}`**: Código de rastreio do pedido (ex: AB646739409BR)
- **`{tracking_url}`**: Link automático de rastreamento via Melhor Rastreio
- **`{shipping_company}`**: Nome da transportadora ou método de envio

##### 2. Compatibilidade com Plugins Brasileiros
- **Melhor Envio**: Suporte automático ao meta field `melhorenvio_tracking`
- **WooCommerce Shipment Tracking**: Compatível com plugin oficial
- **Plugins Genéricos**: Fallback para `_tracking_code` e `_tracking_number`
- **Melhor Rastreio**: Links automáticos usando melhorrastreio.com.br

##### 3. Implementação Técnica
- **Localização**: `includes/class-send-by-status.php`
- **Método**: `get_tracking_code()` - Busca código em múltiplas fontes
- **Método**: `get_tracking_url()` - Gera URL do Melhor Rastreio
- **Método**: `get_shipping_company()` - Obtém nome da transportadora
- **Regex dos Correios**: `^[A-Z]{2}[0-9]{9}[A-Z]{2}$` para validação
- **Fallback inteligente**: Retorna string vazia se não encontrar

##### 4. Fontes de Dados Suportadas
1. **Melhor Envio**: `melhorenvio_tracking`
2. **WC Shipment Tracking**: `_wc_shipment_tracking_items` (array)
3. **Generic**: `_tracking_code`
4. **Generic**: `_tracking_number`

#### 📊 Nova Aba de Logs Centralizada

##### 1. Página de Logs
- **Arquivo**: `includes/class-logs-page.php`
- **Localização menu**: WP WhatsEvolution → Logs
- **Tabela**: `wp_wpwevo_logs`
- **Colunas**: timestamp, level, message, context

##### 2. Funcionalidades da Interface
- **Filtro por Nível**: Debug, Info, Warning, Error
- **Busca Textual**: Pesquisa em message e context
- **Paginação**: 50 logs por página
- **Contexto Expansível**: Detalhes em JSON formatado
- **Cores por Nível**:
  - 🔴 Error: #dc3545
  - 🟡 Warning: #ffc107
  - 🔵 Info: #17a2b8
  - ⚫ Debug: #6c757d

##### 3. Limpeza de Logs
- **Botão**: "🗑️ Limpar Logs"
- **Confirmação**: Dialog antes de executar
- **Método**: AJAX `wpwevo_clear_logs`
- **Operação**: `TRUNCATE TABLE` (instantâneo)
- **Segurança**: Nonce e verificação de permissões

##### 4. Otimização de Logs
- **Removidos logs desnecessários**:
  - ❌ "Enqueueing quick signup assets..."
  - ❌ "Trial expires at sincronizado..."
  - ❌ "User plan sincronizado..."
  - ❌ "Trial days left sincronizado..."
  - ❌ "Corrigindo aninhamento da resposta..."
- **Mantidos logs importantes**:
  - ✅ Erros de API
  - ✅ Números inválidos
  - ✅ Carrinhos abandonados
  - ✅ Falhas de envio

#### 💡 Casos de Uso das Variáveis

**1. Notificação de Envio:**
```
📦 Seu pedido #{order_id} foi enviado!

Código de rastreio: {tracking_code}
Transportadora: {shipping_company}

Acompanhe: {tracking_url}
```

**2. Template Conciso:**
```
Pedido enviado via {shipping_company}!
Rastreio: {tracking_code}
```

**3. Link Direto:**
```
Sua encomenda está a caminho!
Clique para rastrear: {tracking_url}
```

#### 🔧 Detalhes Técnicos

**Arquivos Modificados:**
1. `wp-whatsevolution.php` - Versão 1.4.7
2. `includes/class-send-by-status.php` - Variáveis de rastreamento
3. `includes/class-logs-page.php` - Nova página de logs (NOVO)
4. `includes/class-plugin-loader.php` - Inicialização do Logs_Page
5. `includes/class-quick-signup.php` - Remoção de logs desnecessários

**Banco de Dados:**
- Tabela: `wp_wpwevo_logs` (já existente)
- Colunas: id, timestamp, level, message, context
- Índice: timestamp (para ordenação)

**Performance:**
- Busca otimizada com meta queries
- TRUNCATE para limpeza instantânea
- Paginação para grandes volumes
- Índices no banco para queries rápidas

---

## [1.4.6] - 2025-11-06

### 🔔 Sistema de Notificações Admin por Status

**Nova funcionalidade completa para notificar administradores quando pedidos mudarem de status**

#### 🆕 Funcionalidades Implementadas

##### 1. Campo WhatsApp Admin nas Configurações
- **Localização**: `includes/class-settings-page.php`
- **Novo campo**: "WhatsApp Admin" opcional nas configurações principais
- **Validação em tempo real**: Verifica se número existe no WhatsApp
- **Sanitização**: Input sanitizado com `sanitize_text_field()`
- **Persistência**: Salvo automaticamente em `wpwevo_admin_whatsapp`

##### 2. Interface de Notificação por Status
- **Localização**: `includes/class-send-by-status.php`
- **Checkbox "Notificar Admin"**: Para cada status individualmente
- **Campo de Mensagem Admin**: Textarea que aparece quando checkbox marcado
- **Show/Hide Inteligente**: JavaScript slideDown/slideUp suave
- **Labels Diferenciados**:
  - 📱 Mensagem para o Cliente
  - 🔔 Mensagem para o Admin

##### 3. JavaScript Interativo
- **Arquivo**: `assets/js/send-by-status.js`
- **Toggle dinâmico**: Mostra/oculta campo admin ao marcar checkbox
- **Auto-resize**: Textarea ajusta altura automaticamente
- **Processamento no submit**: Envia `notify_admin` e `admin_message`
- **Seletores específicos**: Evita conflitos com outros elementos

##### 4. Backend - Salvamento de Configurações
- **Método**: `handle_save_messages()`
- **Novos campos salvos**:
  - `notify_admin`: Boolean (checkbox marcado ou não)
  - `admin_message`: String (mensagem personalizada)
- **Sanitização**: `wp_kses_post()` para conteúdo
- **Retrocompatibilidade**: Fallback para configurações antigas

##### 5. Lógica de Envio Duplo Sequencial
- **Método**: `handle_status_change()`
- **Fluxo de execução**:
  1. Envia mensagem ao cliente (comportamento atual)
  2. Verifica se `notify_admin` está ativo para o status
  3. Valida se WhatsApp Admin está configurado
  4. Envia notificação ao admin
- **Mensagem padrão** (se admin_message vazio):
  ```
  🔔 *Notificação de Pedido*

  📋 Pedido: #{order_id}
  📊 Status: {order_status}
  👤 Cliente: {customer_name}
  📱 Contato: {customer_phone}
  💰 Valor: {order_total}

  🔗 Ver pedido: {order_url}
  ```
- **Substituição de variáveis**: Reutiliza `replace_variables()`
- **Notas no pedido**: Registra envio ao admin
- **Logs de erro**: Falha no admin não afeta cliente

##### 6. Validação em Tempo Real do Admin
- **Arquivo**: `assets/js/admin.js`
- **Debounce**: 800ms para evitar chamadas excessivas
- **Validação**: Usa endpoint `wpwevo_validate_number`
- **Feedback visual**:
  - ⏳ "Validando número..."
  - ✅ "Número válido do WhatsApp!"
  - ❌ "Número inválido ou não existe no WhatsApp"
- **Nonce específico**: `validate_nonce` separado do nonce de settings

#### 🎯 Casos de Uso

1. **Novos Pedidos (Processing)**
   - Cliente recebe: "Seu pedido foi aprovado!"
   - Admin recebe: "Novo pedido #1234 - R$ 149,90 - João Silva"

2. **Pedidos Concluídos (Completed)**
   - Cliente recebe: "Pedido entregue com sucesso!"
   - Admin recebe: "Pedido #1234 concluído - Cliente: João Silva"

3. **Cancelamentos (Cancelled)**
   - Cliente recebe: "Pedido cancelado"
   - Admin recebe: "Alerta: Pedido #1234 cancelado - Verificar motivo"

4. **Pedidos VIP (Alto Valor)**
   - Mensagem personalizada para admin quando valor > R$ 500

#### 🔧 Detalhes Técnicos

**Arquivos Modificados:**
1. `wp-whatsevolution.php` - Versão atualizada para 1.4.6
2. `includes/class-settings-page.php` - Campo WhatsApp Admin + validação
3. `includes/class-send-by-status.php` - Interface + lógica de envio duplo
4. `assets/js/send-by-status.js` - Toggle e processamento de formulário
5. `assets/js/admin.js` - Validação em tempo real

**Estrutura de Dados:**
```php
// Nova option
get_option('wpwevo_admin_whatsapp') // '5511999999999'

// Estrutura atualizada
$status_messages = [
    'processing' => [
        'enabled' => true,
        'message' => 'Mensagem para cliente...',
        'notify_admin' => true,           // NOVO
        'admin_message' => 'Mensagem para admin...'  // NOVO
    ]
]
```

**Segurança:**
- ✅ Nonces verificados em todos os AJAX
- ✅ Capabilities `manage_options` validadas
- ✅ Inputs sanitizados com `sanitize_text_field()` e `wp_kses_post()`
- ✅ Fallbacks para evitar erros
- ✅ Logs de erro sem expor dados sensíveis

#### 📊 Performance e Compatibilidade

- **Impacto**: Mínimo - apenas 2 campos adicionais por status
- **Envio sequencial**: Admin só recebe após cliente (evita confusão)
- **Retrocompatibilidade**: Configurações antigas funcionam normalmente
- **Fallback inteligente**: Usa mensagem padrão se admin_message vazio
- **Independência**: Falha no envio ao admin não afeta cliente

#### 🎨 UX/UI

- **Visual consistente**: Mantém design system do plugin
- **Interação suave**: Animações slideDown/slideUp
- **Feedback imediato**: Validação em tempo real
- **Tooltips informativos**: Labels claros e descritivos
- **Cores diferenciadas**: Verde claro para campo admin

#### ✅ Testes Recomendados

1. Configurar WhatsApp Admin com número válido
2. Ativar "Notificar Admin" para status "processing"
3. Criar pedido de teste
4. Mudar status para "processing"
5. Verificar:
   - Cliente recebe mensagem
   - Admin recebe notificação
   - Notas registradas no pedido
   - Logs sem erros

---

## [1.4.4] - 2025-01-27

### 🐛 Correção Crítica no Cart Abandonment
- **Problema identificado**: Erro JavaScript `Cannot read properties of undefined (reading 'saving')` ao salvar templates
- **Causa raiz**: Objeto `wpwevoCartAbandonment` não possuía propriedades de internacionalização (`i18n`)
- **Solução implementada**: 
  - Adicionadas traduções `saving` e `generating` ao objeto localizado do script
  - Implementadas verificações de segurança com fallbacks para textos padrão
  - Versão do script atualizada para forçar limpeza do cache
- **Resultado**: Sistema de salvamento de templates funcionando perfeitamente sem erros JavaScript

### 🔧 Melhorias Técnicas
- **Robustez**: Verificações de segurança com operador de coalescência nula (`?.`)
- **Fallbacks**: Textos padrão caso traduções não estejam disponíveis
- **Cache**: Versionamento do script para garantir atualizações

---

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