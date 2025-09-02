# WP WhatsEvolution v1.4.1

🚀 **A Revolução do Envio em Massa + Sistema Completo de Automação WhatsApp**

## 🆕 **NOVO na v1.4.1: Correções Críticas e Melhorias Significativas**

### 🐛 **Correções Críticas Implementadas**
- **✅ Submenus Funcionando**: Corrigido problema de submenus ausentes que afetava toda a navegação
- **✅ Variáveis Dinâmicas**: Sistema robusto de exibição por aba no envio em massa
- **✅ Fallback Inteligente**: Endereços de envio sempre funcionam, mesmo com configurações WooCommerce complexas

### 🚀 **Melhorias de Interface e Performance**
- **🎨 Interface Intuitiva**: Variáveis WooCommerce sempre visíveis por padrão
- **⚡ Sistema Robusto**: Fallbacks múltiplos para sessionStorage e compatibilidade
- **🔧 Código Otimizado**: Reorganização completa seguindo boas práticas WordPress
- **📱 Responsividade**: Interface moderna e adaptável a todos os dispositivos

### 🏗️ **Arquitetura WordPress Corrigida**
- **⏰ Timing dos Hooks**: Propriedades de menu definidas ANTES dos hooks WordPress
- **🔗 Compatibilidade HPOS**: Suporte completo ao novo sistema de pedidos WooCommerce
- **📚 Boas Práticas**: Código seguindo padrões WordPress e PHP modernos

---

## 🆕 **NOVO na v1.4.0: Integração com Agentes de IA do n8n**

### 🤖 **Sistema Completo de Agente de IA**

#### **🎯 Modalidades Disponíveis**
1. **Agente de IA**: Conecta com n8n para respostas personalizadas via IA
2. **Chat Simples**: Respostas locais baseadas em palavras-chave
3. **Fallback Inteligente**: Automaticamente usa Chat Simples quando webhook falha

#### **🔧 Funcionalidades Avançadas**
- **Webhook Proxy**: Seguro, não expõe URLs externas
- **Metadados Ricos**: Inclui contexto da página, usuário e sessão
- **Formatação Automática**: Adapta mensagens para diferentes canais
- **Validação Inteligente**: Testa webhook e ativa fallback automaticamente

#### **💬 Chat Simples (Sistema Local)**
- **Respostas baseadas em keywords**: Sistema de palavras-chave → respostas
- **Configurável**: Interface amigável para adicionar/editar respostas
- **Fallback inteligente**: Mensagem padrão quando não encontra keywords
- **Sem dependências externas**: Funciona offline

#### **📱 Widget Inteligente**
- **Chat integrado ao site**: Shortcode `[wpwevo_ai_chat]`
- **Personalização completa**: Cores, textos e comportamento
- **Injeção automática**: Opção de injetar no footer do site
- **Responsivo**: Adapta-se a todos os dispositivos

#### **🔄 Sistema de Fallback Automático**
- **Transparente para o usuário**: Chat continua funcionando sem interrupções
- **Detecção inteligente**: Identifica falhas de webhook automaticamente
- **Recuperação automática**: Volta ao Agente de IA quando possível
- **Aviso discreto**: Informa quando está usando Chat Simples

---

## 🆕 **NOVO na v1.3.2: Padronização e Estabilidade**

### 🔧 Padronizações e Melhorias
- **I18n**: Text domain padronizado para `wp-whatsevolution`
- **Arquivo Principal**: Renomeado para `wp-whatsevolution.php`
- **Checkout**: Validação apenas de telefone (CPF/CNPJ ignorados)
- **Compatibilidade**: Cart Abandonment Recovery silenciosa (sem aviso na UI)
- **Templates**: Fallback automático quando ausentes (Quick Signup)
- **Traduções**: Geração automática de `.mo` quando faltar

---

## 🆕 **Destaques da v1.3.0: Envio em Massa Reescrito do Zero!**

### ✨ **Revolução do Envio em Massa**
- **📊 Interface Intuitiva** - Abas organizadas (WooCommerce, CSV, Manual)
- **🔍 Detecção Inteligente** - CSV com separadores automáticos (vírgula/ponto e vírgula)
- **📱 Variáveis Contextuais** - Diferentes variáveis por fonte de dados
- **⚡ Performance Otimizada** - Sistema robusto e à prova de falhas
- **🎯 Histórico Completo** - Logs detalhados com limpeza automática

### 🔧 **Sistema de Fallback Inteligente**
- **📍 Endereços de Envio** - Detecta automaticamente quando endereço está vazio e usa dados de cobrança
- **📱 Validação Ultra-Robusta** - Aceita 8, 9, 10, 11, 12, 13 dígitos brasileiros
- **🔄 Compatibilidade Total** - Funciona com qualquer configuração do WooCommerce

---

## 📋 **Descrição**

O **WP WhatsEvolution** é o plugin mais avançado para integração de mensagens + WooCommerce, oferecendo:

- 🚀 **Quick Signup** com teste grátis de 7 dias
- 🛒 **Carrinho Abandonado** com interceptação interna revolucionária
- 📊 **Envio por Status** de pedido automatizado  
- 📱 **Envio Individual** para clientes específicos
- 📢 **Envio em Massa** com filtros avançados (REESCRITO v1.3.0)
- ✅ **Validação no Checkout** em tempo real
- 🎨 **Templates Personalizáveis** com shortcodes dinâmicos
- 🧠 **Sistema Inteligente** de fallback e validação

---

## 🔧 **Dois Modos de Operação**

### 🚀 **Modo Managed (Free Trial)**
- **Backend Supabase**: Usado apenas para onboarding e verificação de status
- **Configuração**: Automática em 30 segundos
- **Custo**: 7 dias grátis, depois pago
- **Ideal para**: Quem quer testar sem complicações técnicas

### ⚙️ **Modo Manual (Credenciais Próprias)**
- **Backend**: 100% local, sem calls externos
- **Configuração**: URL, API Key, Instância próprias
- **Custo**: Zero (usa sua Evolution API)
- **Ideal para**: Quem já tem Evolution API configurada

## 🔍 **Compatibilidade de Versões da Evolution API**

### ✅ **Versões Suportadas**
- **Evolution API V2.x.x**: ✅ **Totalmente compatível** - Todas as funcionalidades funcionam perfeitamente
- **Evolution API V1.x.x**: ⚠️ **Compatibilidade limitada** - Sistema funciona mas com avisos de incompatibilidade

### 🚨 **Avisos Automáticos**
- **Detecção Inteligente**: Sistema verifica automaticamente a versão da sua API
- **Avisos Visuais**: Interface mostra claramente quando há incompatibilidade
- **Recomendações**: Orientações para atualização quando necessário

### 📱 **Como Funciona**
1. **Configuração**: Insira URL, API Key e Nome da Instância
2. **Verificação**: Sistema testa conexão e verifica versão automaticamente
3. **Feedback**: Interface mostra status e avisos de compatibilidade
4. **Ação**: Atualize para V2 se necessário ou continue usando V1 com limitações

---

## 🚀 **Quick Signup - Comece em 30 Segundos**

### 🎯 **Para quem não tem Evolution API**

1. **Acesse** "WhatsEvolution > 🚀 Teste Grátis"
2. **Preencha** seus dados (nome, email, WhatsApp)
3. **Clique** em "Criar Conta e Testar Agora"
4. **Aguarde** a configuração automática (30 segundos)
5. **Conecte** seu WhatsApp via QR Code
6. **Pronto!** Teste todas as funcionalidades por 7 dias

### ✨ **Vantagens do Quick Signup**
- 🆓 **7 dias grátis** sem cartão de crédito
- ⚡ **Configuração automática** de toda a Evolution API  
- 🛠️ **Sem complicações técnicas** (VPS, Docker, etc.)
- 📞 **Suporte técnico incluído** no período de teste
- 🔄 **Fácil upgrade** quando decidir continuar

---

## 🛒 **Carrinho Abandonado - Funcionalidades Detalhadas**

### 🔧 **Configuração da Integração**

A configuração é feita em duas partes: ativar em nosso plugin e configurar o plugin parceiro.

#### **Passo 1: Instale o Plugin Parceiro**
1.  **Instale e ative** o plugin "WooCommerce Cart Abandonment Recovery".

#### **Passo 2: Configure a Integração**
Siga as instruções que aparecem na nossa página de configurações:

1.  Navegue até: **WooCommerce → Cart Abandonment → Settings → Webhook Settings**.
2.  **Ative** a opção `"Enable Webhook"`.
3.  **Cole a URL** fornecida pelo nosso plugin no campo `"Webhook URL"`.
4.  **Teste** a integração clicando em `"Trigger Sample"`.
5.  **Salve as configurações**.

### 🎯 **Interceptação Interna Revolucionária**
- **⚡ Integração Simplificada** - Apenas ative o webhook no plugin parceiro.
- **🔒 100% Seguro** - Dados nunca saem do servidor WordPress para o webhook.
- **⚡ Zero Latência** - Processamento instantâneo via hooks internos.
- **🎯 Interceptação Inteligente** - Captura carrinhos ANTES do webhook externo ser enviado.

### 🎨 **Shortcodes Disponíveis**

| Shortcode | Descrição | Exemplo |
|-----------|-----------|---------|
| `{first_name}` | Nome do cliente | João |
| `{full_name}` | Nome completo | João Silva |
| `{email}` | Email do cliente | joao@email.com |
| `{product_names}` | Produtos no carrinho | Produto A, Produto B |
| `{cart_total}` | Valor formatado | R$ 149,90 |
| `{cart_total_raw}` | Valor sem formatação | 149.90 |
| `{checkout_url}` | Link finalizar compra | https://loja.com/checkout?token=abc |
| `{coupon_code}` | Código do cupom | DESCONTO10 |
| `{site_name}` | Nome da loja | Minha Loja |
| `{site_url}` | URL da loja | https://loja.com |

### 📱 **Template Padrão Brasileiro**

```
🛒 Oi {first_name}!

Vi que você adicionou estes itens no carrinho:
📦 {product_names}

💰 Total: {cart_total}

🎁 Use o cupom *{coupon_code}* e ganhe desconto especial!
⏰ Mas corre que é só por hoje!

Finalize agora:
👆 {checkout_url}
```

---

## 📢 **Envio em Massa - Revolução v1.3.0**

### 🆕 **Nova Interface Intuitiva**

#### **🛒 Aba WooCommerce**
- **Filtros Avançados**: Status de pedido, período, valor mínimo
- **Variáveis Dinâmicas**: Dados completos do cliente e pedidos
- **Preview Inteligente**: Visualize clientes antes do envio

#### **📄 Aba Importação CSV**
- **Detecção Automática**: Separadores vírgula (,) ou ponto e vírgula (;)
- **Codificação UTF-8**: Suporte completo a acentos brasileiros
- **Template de Exemplo**: Download de arquivo modelo
- **Variáveis**: `{customer_name}`, `{customer_phone}`

#### **✍️ Aba Lista Manual**
- **Interface Simples**: Um número por linha
- **Validação Automática**: Formato brasileiro
- **Sem Variáveis**: Mensagem fixa para todos

### ⚡ **Controle de Envio**
- **Velocidade Configurável**: Segundos entre cada envio
- **Agendamento**: Data e hora para iniciar
- **Progresso em Tempo Real**: Barra de progresso com status
- **Histórico Completo**: Logs detalhados com limpeza

---

## 📊 **Envio por Status - Automação Inteligente**

### 🎯 **Status Suportados**
- **pending/on-hold**: Pedido recebido, aguardando pagamento
- **processing**: Pedido aprovado, preparando envio
- **completed**: Pedido concluído com sucesso
- **cancelled**: Pedido cancelado
- **refunded**: Reembolso processado
- **failed**: Problema no pedido

### 🧠 **Sistema de Fallback Inteligente**
- **Detecção Automática**: Quando endereço de entrega está vazio
- **Fallback Inteligente**: Usa dados de cobrança automaticamente
- **Compatibilidade Total**: Funciona com qualquer configuração do WooCommerce

### 📱 **Templates por Status**
Cada status tem template personalizável com variáveis específicas do pedido.

---

## 🏷️ **Variáveis Disponíveis (Expandidas)**

### 🛒 **Dados do Pedido**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{order_id}` | ID do pedido | #1234 |
| `{order_total}` | Valor total | R$ 149,90 |
| `{order_url}` | Link do pedido | https://loja.com/pedido/1234 |
| `{payment_method}` | Método de pagamento | Cartão de Crédito |
| `{shipping_method}` | Método de envio | Correios |

### 👤 **Dados do Cliente**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{customer_name}` | Nome completo | João Silva |
| `{customer_email}` | Email | joao@email.com |
| `{customer_phone}` | Telefone | +55 11 99999-9999 |
| `{total_orders}` | Total de pedidos | 5 |
| `{last_order_date}` | Último pedido | 15/01/2025 |

### 📍 **Endereços (Com Fallback)**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{shipping_address_full}` | Endereço completo de entrega | Rua A, 123 - São Paulo, SP |
| `{billing_address_full}` | Endereço de cobrança | Rua B, 456 - Rio de Janeiro, RJ |

### 🏪 **Dados da Loja**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{store_name}` | Nome da loja | Minha Loja |
| `{store_url}` | URL da loja | https://loja.com |
| `{store_email}` | Email da loja | contato@loja.com |

---

## ✅ **Validação no Checkout - Ultra-Robusta**

### 📱 **Validação Brasileira Avançada**
- **Múltiplos Formatos**: Aceita 8, 9, 10, 11, 12, 13 dígitos
- **DDDs Válidos**: Validação de códigos 11-99
- **Formato Antigo**: Detecta e corrige celulares sem o 9
- **Zero Erros**: Nunca mais "Formato de telefone inválido"

### ⚡ **Funcionalidades**
- **Tempo Real**: Validação enquanto digita
- **Modal Inteligente**: Confirmação quando número inválido
- **Compatibilidade**: Não interfere com máscaras existentes
- **Customização**: Títulos e mensagens personalizáveis

---

## 🧠 **Sistema Inteligente de Fallback**

### 📍 **Endereços de Envio**
- **Detecção Automática**: Quando endereço de entrega está vazio
- **Fallback Inteligente**: Usa dados de cobrança automaticamente
- **Compatibilidade**: Funciona com qualquer configuração do WooCommerce
- **Zero Configuração**: Funciona automaticamente

### 📱 **Validação Ultra-Robusta**
- **Números Brasileiros**: Aceita 8, 9, 10, 11, 12, 13 dígitos
- **DDDs Válidos**: Validação de códigos 11-99
- **Formato Antigo**: Detecta e corrige celulares sem o 9
- **Zero Erros**: Nunca mais "Formato de telefone inválido"

---

## 🤖 **Sistema de Agente de IA - Documentação Completa**

### 🎯 **Configuração e Uso**

#### **1. Configuração do Agente de IA**
```
1. Acesse: WP WhatsApp Evolution > Agente de IA
2. Selecione "Agente de IA" como modalidade
3. Configure o webhook do n8n
4. Personalize textos e cores do widget
5. Salve as configurações
```

#### **2. Configuração do Chat Simples**
```
1. Selecione "Chat Simples" como modalidade
2. Adicione respostas personalizadas:
   - Palavras-chave: "oi, olá, hello"
   - Resposta: "Olá! Como posso ajudar?"
3. Configure mensagem de fallback
4. Salve as configurações
```

### 📱 **Shortcodes Disponíveis**

#### **Widget de Chat**
```php
[wpwevo_ai_chat mode="window"]
```
**Parâmetros:**
- `mode`: "window" (padrão) ou "inline"

#### **Formulário de Contato**
```php
[wpwevo_ai_form title="Fale Conosco" button="Enviar" show_phone="true"]
```
**Parâmetros:**
- `title`: Título do formulário
- `button`: Texto do botão
- `show_phone`: "true" ou "false"

### 🔄 **Sistema de Fallback Automático**

#### **Como Funciona**
1. **Agente de IA ativo** → Tenta enviar para webhook n8n
2. **Se webhook falhar** → Automaticamente ativa Chat Simples
3. **Chat Simples ativo** → Responde com keywords locais
4. **Quando webhook volta** → Retorna automaticamente ao Agente de IA

#### **Detecção de Falhas**
- ❌ **Erro de conexão**: `wp_remote_post` falha
- ❌ **Erro HTTP 4xx/5xx**: Webhook retorna erro
- ❌ **Timeout**: Webhook não responde em 20 segundos

#### **Transparência**
- ✅ **Usuário não percebe**: Chat continua funcionando
- ✅ **Aviso discreto**: "💡 Chat Simples ativo - Agente de IA temporariamente indisponível"
- ✅ **Recuperação automática**: Volta ao Agente de IA quando possível

### 🌐 **Integração com n8n**

#### **Payload Enviado**
```json
{
  "chatInput": "mensagem do usuário",
  "sessionId": "chat_123456",
  "metadata": {
    "source": "n8n_chat_widget",
    "sourceType": "chat_widget",
    "page_url": "https://seusite.com",
    "page_title": "Título da Página",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2025-01-25T15:45:47+00:00",
    "responseConfig": {
      "shouldRespond": true,
      "responseTarget": "chat_widget"
    },
    "wordpress": true,
    "site_url": "https://seusite.com",
    "ajax_proxy": true
  }
}
```

#### **Formulário Web (Canal Especial)**
```json
{
  "channel": "web_form",
  "chatInput": "**Nova solicitação via formulário:**\n\n**Nome:** João\n**E-mail:** joao@email.com\n**Telefone:** 5511999999999\n**Mensagem:** Preciso de ajuda\n**Página:** https://seusite.com/contato\n**Data:** 25/01/2025 12:45",
  "sessionId": "5511999999999@s.whatsapp.net",
  "remoteJid": "5511999999999@s.whatsapp.net",
  "pushName": "João",
  "contact": {
    "nome": "João",
    "email": "joao@email.com",
    "telefone": "11999999999"
  },
  "provider": {
    "instanceName": "sua_instancia",
    "serverUrl": "https://seu-servidor.com",
    "apiKey": "sua_api_key"
  }
}
```

### 🎨 **Personalização do Widget**

#### **Cores Personalizadas**
- **Cor primária**: Personalize a cor principal do widget
- **CSS automático**: Aplica variáveis CSS para compatibilidade
- **Temas**: Suporte a temas claro/escuro

#### **Textos Personalizáveis**
- **Título**: "Olá! 👋"
- **Subtítulo**: Mensagem de boas-vindas
- **Placeholder**: Texto do campo de input
- **Botão**: "Nova conversa"

### 📱 **Funcionalidades WhatsApp**

#### **Validação de Números**
- ✅ **Verificação em tempo real**: Valida números durante digitação
- ✅ **Formato E.164**: Converte para formato internacional
- ✅ **Validação AJAX**: Usa sistema de validação existente

#### **Envio de Mensagens**
- ✅ **Hooks WordPress**: Usa sistema existente do plugin
- ✅ **Formatação automática**: Adapta mensagens para WhatsApp
- ✅ **Rastreamento**: Inclui metadados da conversa

### 🚨 **Solução de Problemas**

#### **Chat Simples sempre ativo**
```
Verificar:
1. Configuração: wpwevo_ai_mode = 'simple_chat'
2. Webhook configurado corretamente
3. Logs de erro no WordPress
```

#### **Webhook não recebe mensagens**
```
Verificar:
1. URL do webhook está correta
2. Fluxo n8n está ativo
3. Permissões de CORS
4. Logs do n8n
```

#### **Fallback não funciona**
```
Verificar:
1. Função should_use_simple_chat()
2. Configurações do Chat Simples
3. Logs de erro PHP
```

### 📚 **Exemplos de Uso**

#### **Resposta Simples com Keywords**
```json
{
  "keywords": ["horario", "funcionamento", "aberto"],
  "response": "Nosso horário é de segunda a sexta, das 8h às 18h! ⏰"
}
```

#### **Resposta com Fallback**
```json
{
  "keywords": ["produto", "preço", "comprar"],
  "response": "Temos uma variedade de produtos! 🛍️ O que você está procurando?"
}
```

---

## 📊 **Sistema Completo de Logs**

### 🔍 **Níveis de Log**
- **Error**: Erros críticos do sistema
- **Warning**: Avisos importantes  
- **Info**: Informações gerais
- **Debug**: Apenas em modo debug

### 💾 **Armazenamento**
- **Banco de Dados**: Tabela `wpwevo_logs`
- **WooCommerce**: Integração com sistema de logs do WC
- **Limpeza**: Automática para manter performance

### 📋 **Histórico de Envios**
- **Envio em Massa**: Logs completos com sucesso/erro
- **Carrinho Abandonado**: Logs em tempo real
- **Limpeza**: Função para limpar histórico antigo

---

## 🔧 **Requisitos**

- **PHP:** 7.4 ou superior
- **WordPress:** 5.8 ou superior  
- **WooCommerce:** 5.0 ou superior
- **Evolution API:** Instância configurada (ou use o Quick Signup!)

---

## 🚀 **Instalação e Configuração**

### 🎯 **Opção 1: Download do GitHub (Recomendado)**

```bash
1. Baixe o ZIP direto do GitHub
2. Envie para /wp-content/plugins/ (pode ficar como "wp-whatsevolution-main")  
3. Ative no painel WordPress
4. Use "🚀 Teste Grátis" para configuração automática
```

### 🎯 **Opção 2: Configuração Manual**

```bash
1. URL da API: https://sua-api.com
2. API Key: sua-chave-aqui
3. Instância: nome-da-instancia
4. Testar Conexão ✅
```

### 3️⃣ **Ativação Carrinho Abandonado**

```bash
1. Instalar "WooCommerce Cart Abandonment Recovery"
2. Ir em "WhatsEvolution > Carrinho Abandonado" 
3. Ativar integração ✅
4. Personalizar template (opcional)
```

---

## 🔧 **Hooks para Desenvolvedores**

### 🎨 **Personalizar Mensagem Carrinho Abandonado**

```php
add_filter('wpwevo_cart_abandonment_message', function($message, $data) {
    $trigger_details = $data['trigger_details'];
    
    // Adicionar desconto para carrinhos de alto valor
    if (floatval($trigger_details['cart_total']) > 200) {
        $message .= "\n\n🎁 USE VOLTA10 e ganhe 10% OFF!";
    }
    
    return $message;
}, 10, 2);
```

### 📊 **Hook Após Envio Bem-Sucedido**

```php
add_action('wpwevo_cart_abandonment_sent', function($phone, $message, $trigger_details) {
    // Log personalizado
    error_log("WhatsApp enviado para: {$phone}");
    
    // Integração com outros sistemas
    // ...
}, 10, 3);
```

### 🎯 **Personalizar Validação de Telefone**

```php
add_filter('wpwevo_validate_phone', function($phone, $original) {
    // Lógica personalizada de validação
    if (strlen($phone) < 10) {
        return false;
    }
    
    return $phone;
}, 10, 2);
```

---

## 📊 **Todas as Funcionalidades**

### 🚀 **Quick Signup (Onboarding 1-Click)**
- ✅ Teste grátis por 7 dias
- ✅ Configuração automática em 30s
- ✅ Sem VPS/Docker necessário
- ✅ Suporte técnico incluído
- ✅ Upgrade simplificado

### 🛒 **Carrinho Abandonado**
- ✅ Interceptação interna automática
- ✅ Templates personalizáveis  
- ✅ Shortcodes dinâmicos
- ✅ Logs em tempo real
- ✅ Formatação brasileira (R$)

### 📊 **Envio por Status**
- ✅ Automação por status de pedido
- ✅ Templates por status
- ✅ Variáveis dinâmicas
- ✅ Sistema de fallback inteligente
- ✅ Configuração flexível

### 📱 **Envio Individual**
- ✅ Interface simples
- ✅ Validação automática
- ✅ Histórico de envios
- ✅ Variáveis da loja

### 📢 **Envio em Massa (v1.3.0)**
- ✅ Interface reescrita do zero
- ✅ Filtros avançados
- ✅ Importação CSV inteligente
- ✅ Controle de velocidade
- ✅ Histórico completo
- ✅ Variáveis contextuais

### ✅ **Validação Checkout**
- ✅ Campo obrigatório
- ✅ Validação tempo real
- ✅ Formatação automática
- ✅ Modal de confirmação
- ✅ Validação ultra-robusta

### 🤖 **Agente de IA (v1.4.0)**
- ✅ **Modalidades**: Agente de IA, Chat Simples e Fallback automático
- ✅ **Webhook n8n**: Integração segura com fluxos de automação
- ✅ **Chat Simples**: Sistema local de keywords → respostas
- ✅ **Fallback inteligente**: Transição transparente quando webhook falha
- ✅ **Widget integrado**: Chat responsivo com shortcode `[wpwevo_ai_chat]`
- ✅ **Formulário web**: Conversão automática para formato WhatsApp
- ✅ **Metadados ricos**: Contexto completo da página e usuário
- ✅ **Personalização**: Cores, textos e comportamento configuráveis

### 🧠 **Sistema Inteligente**
- ✅ Fallback automático de endereços
- ✅ Validação brasileira avançada
- ✅ Logs completos
- ✅ Compatibilidade total

---

## 🔒 **Segurança e Privacidade**

### 🛡️ **Proteções Implementadas**
- **Nonces**: Todos os AJAX protegidos
- **Capabilities**: `manage_options` para admin
- **Sanitização**: Input sanitization em todos os campos
- **Validação**: Números de telefone e dados críticos

### 🔒 **Dados Sensíveis**
- **Modo Managed**: Dados processados pelo backend Supabase
- **Modo Manual**: 100% local, sem calls externos
- **Logs**: Sem dados sensíveis nos logs
- **Limpeza**: Desinstalação completa via `uninstall.php`

---

## 📞 **Suporte**

### 🆘 **Canais de Ajuda**
- **Documentação**: Este README
- **Issues**: GitHub Issues
- **Quick Signup**: Suporte incluído no período de teste

### 🐛 **Reportar Bugs**
1. Verifique se é um bug real
2. Teste em ambiente limpo
3. Inclua logs de erro
4. Descreva passos para reproduzir

---

## 📄 **Licença**

Este projeto está sob a licença GPL v2 ou posterior.

---

## 🤝 **Contribuição**

Contribuições são bem-vindas! Por favor:

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

---

**🎉 Obrigado por usar o WP WhatsEvolution!** 