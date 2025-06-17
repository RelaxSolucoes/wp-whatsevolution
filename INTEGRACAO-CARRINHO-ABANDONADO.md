# 🛒 Integração com WooCommerce Cart Abandonment Recovery

Este guia explica como configurar a integração **interna** entre o **WP WhatsApp Evolution** e o plugin **WooCommerce Cart Abandonment Recovery** para enviar mensagens WhatsApp quando carrinhos são abandonados.

## 🚀 **Nova Abordagem - Interceptação de Webhook**

Esta integração funciona **interceptando** os webhooks do Cart Abandonment Recovery **antes** deles serem enviados:

### 🔥 **Vantagens da Interceptação:**
- 🎯 **Interceptação Automática** - Captura dados antes do envio externo
- ⚡ **Processamento Instantâneo** - Zero latência interna
- 🔒 **Mais Seguro** - Dados processados internamente
- ✅ **Mantém Funcionalidades** - Cart Abandonment funciona normalmente  
- 🧪 **Testável** - Botão "Trigger Sample" funciona perfeitamente
- 📊 **Logs Completos** - Monitoramento total do processo

### 🎯 **Como Funciona:**
1. **📝 Configure** a URL do webhook no Cart Abandonment Recovery
2. **🎯 Interceptamos** os dados ANTES do webhook ser enviado externamente
3. **📱 Processamos** e enviamos via WhatsApp instantaneamente
4. **🌐 Webhook externo** continua sendo enviado (mas não importa)
5. **📊 Registramos** tudo nos logs para monitoramento

## 📋 **Pré-requisitos**

### Plugins Necessários:
1. ✅ **WP WhatsApp Evolution** (este plugin)
2. ✅ **WooCommerce** (ativo e funcionando)
3. ✅ **WooCommerce Cart Abandonment Recovery** (grátis no repositório WordPress)

### Configuração Evolution API:
- ✅ Evolution API configurada e funcionando
- ✅ Instância WhatsApp conectada
- ✅ Número de telefone validado

## 🚀 **Passo a Passo da Configuração**

### **Passo 1: Instalar o Plugin Cart Abandonment Recovery**

1. No WordPress admin, vá em **Plugins > Adicionar Novo**
2. Procure por **"WooCommerce Cart Abandonment Recovery"**
3. Instale e ative o plugin (✅ **Gratuito no repositório oficial**)
4. Certifique-se de que o WooCommerce também está ativo

### **Passo 2: Ativar a Interceptação**

1. Vá em **WhatsApp Evolution > Carrinho Abandonado**
2. ✅ Ative a opção **"Ativar interceptação para WhatsApp"**
3. 📋 **Copie a URL do webhook** fornecida
4. ✅ Clique em **"Salvar Configurações"**

### **Passo 3: Configurar o Cart Abandonment Recovery**

Configure o webhook e comportamento no plugin Cart Abandonment Recovery:

#### **🔗 Configuração do Webhook:**
1. Vá em **WooCommerce → Cart Abandonment → Settings → Webhook Settings**
2. ✅ Ative **"Enable Webhook"**
3. 📋 Cole a **URL copiada** no campo **"Webhook URL"**
4. 💾 Clique em **"Save Changes"**
5. 🧪 **Teste com "Trigger Sample"** - deve aparecer nos logs do WhatsApp Evolution!

#### **⏰ Configurações Essenciais:**
1. Na aba **"General Settings"**:
   - **✅ Enable Tracking**: Ativado
   - **⏰ Cart abandoned cut-off time**: 15-30 minutos (recomendado)
   - **🎯 Disable Tracking For**: Configure conforme necessário

### **Passo 4: Personalizar Templates (Opcional)**

O template padrão das mensagens WhatsApp inclui:
- 👋 Saudação personalizada com nome do cliente
- 📦 Lista dos produtos abandonados
- 💰 Valor total formatado em Real (R$)
- 🔗 Link direto para finalizar a compra
- ⏰ Call-to-action para urgência

#### **Template Atual:**
```
🛒 Olá {Nome}!

Você esqueceu alguns itens no seu carrinho:
📦 {Produtos}

💰 Total: R$ {Valor}

Finalize sua compra agora:
🔗 {Link do Checkout}

⏰ Não perca essa oportunidade!
```

## 🧪 **Testando a Integração**

### **Teste 1: Trigger Sample (Recomendado)**
1. No **Cart Abandonment Recovery → Settings → Webhook Settings**
2. Clique em **"Trigger Sample"** ao lado da URL do webhook
3. Vá na página **WhatsApp Evolution > Carrinho Abandonado**
4. Verifique os logs - deve mostrar interceptação e envio!

### **Teste 2: Interceptação Simulada**
1. Na página **WhatsApp Evolution > Carrinho Abandonado**
2. Clique em **"🧪 Testar Interceptação"**
3. Verifique os logs para confirmar funcionamento
4. ✅ Deve aparecer "Teste de interceptação executado!"

### **Teste 2: Carrinho Real (Recomendado)**
1. **Como cliente**, adicione produtos ao carrinho
2. Preencha email e **telefone** no checkout
3. **Abandone** o carrinho (feche o navegador)
4. Aguarde o tempo configurado (15-30 minutos)
5. **Verifique** se a mensagem WhatsApp foi recebida

## 📊 **Monitoramento e Logs**

### **Status da Integração:**
- 🎯 **Interceptação Interna**: Ativa e Funcionando
- ✅ **Cart Abandonment Recovery**: Plugin Ativo
- ✅ **Evolution API**: Configurada

### **Logs Detalhados:**
- 🎯 Interceptações realizadas
- 📱 Mensagens enviadas com sucesso
- ❌ Erros de validação ou envio
- 🔍 Debug de processamento

### **Exemplo de Logs:**
```
[2024-01-15 14:30:15] 🎯 Webhook interceptado - Status: abandoned
[2024-01-15 14:30:15] 📱 Enviando WhatsApp para: 5511999999999
[2024-01-15 14:30:16] ✅ Mensagem enviada com sucesso para 5511999999999
```

## ❗ **Problemas Comuns e Soluções**

### **🔴 Problema: Interceptação não funciona**

**Verificar:**
1. ✅ Plugin Cart Abandonment Recovery está ativo
2. ✅ Integração interna está ativada
3. ✅ Evolution API está configurada
4. 🔍 Verifique logs para erros específicos

**Soluções:**
1. Reative o plugin Cart Abandonment Recovery
2. Teste a interceptação manualmente
3. Verifique configurações da Evolution API

### **🔴 Problema: Mensagens não são enviadas**

**Possíveis Causas:**
1. ❌ Evolution API não configurada
2. ❌ Número de telefone inválido no checkout
3. ❌ Cliente não preencheu telefone
4. ❌ Tempo de cut-off muito baixo

**Soluções:**
1. ✅ Verificar configuração da API em **Configurações**
2. ✅ Validar formato do telefone (DDD + número)
3. ✅ Tornar campo telefone obrigatório no checkout
4. ✅ Aumentar cut-off time para 30+ minutos

### **🔴 Problema: Telefone não é capturado**

**Formatos Aceitos:**
- ✅ `11999999999` (DDD + número)
- ✅ `5511999999999` (código país + DDD + número)
- ❌ `(11) 99999-9999` (formatado com símbolos)

**Solução:**
- Configure o checkout para aceitar apenas números
- Use plugins de validação de telefone

## 🔧 **Configurações Avançadas**

### **Personalização via Código:**

#### **Filtro: Modificar mensagem antes do envio**
```php
add_filter('wpwevo_cart_abandonment_message', function($message, $data) {
    // Personalizar mensagem baseada nos dados do carrinho
    $trigger_details = $data['trigger_details'];
    $checkout_details = $data['checkout_details'];
    
    // Exemplo: Adicionar desconto para carrinhos de alto valor
    if (floatval($trigger_details['cart_total']) > 200) {
        $message .= "\n\n🎁 USE O CUPOM VOLTA10 e ganhe 10% de desconto!";
    }
    
    return $message;
}, 10, 2);
```

#### **Action: Após envio de mensagem**
```php
add_action('wpwevo_cart_abandonment_sent', function($phone, $message, $trigger_details) {
    // Log personalizado, integração com CRM, etc.
    error_log("WhatsApp enviado para {$phone} - Carrinho: {$trigger_details['cart_total']}");
}, 10, 3);
```

### **Configurações de Performance:**

#### **Timing Ideal por Tipo de Produto:**
- ⚡ **10-15 min**: Produtos com estoque limitado
- 🕐 **30-60 min**: E-commerce geral (recomendado)
- 🕓 **2-4 horas**: Produtos de alto valor/consideração

#### **Otimizações:**
```php
// Hook para interceptar apenas carrinhos de alto valor
add_action('wcf_ca_before_trigger_webhook', function($trigger_details, $checkout_details, $order_status) {
    $cart_total = floatval($trigger_details['cart_total']);
    
    // Só processa carrinhos acima de R$ 50
    if ($cart_total < 50) {
        return; // Para a execução
    }
    
    // Continua processamento normal...
}, 5, 3); // Prioridade 5 para executar antes do nosso hook
```

## 🔄 **Webhook Externo (Fallback)**

Se por algum motivo a interceptação interna não funcionar em seu ambiente, você pode usar o método tradicional de webhook:

### **Configuração Fallback:**
1. Copie a URL de webhook da página de configurações
2. Vá em **WooCommerce → Cart Abandonment → Settings → Webhook Settings**
3. Ative **"Enable Webhook"**
4. Cole a URL no campo **"Webhook URL"**
5. Salve as configurações

> ⚠️ **Nota:** Use o fallback apenas se a interceptação interna não funcionar. A interceptação interna é sempre preferível.

## 📈 **Melhores Práticas**

### **📝 Mensagens Efetivas:**
1. **Personalize** sempre com nome do cliente
2. **Liste produtos** específicos abandonados
3. **Inclua valor total** em formato brasileiro (R$)
4. **Use emojis** para chamar atenção
5. **Link direto** para checkout (não homepage)
6. **Crie urgência** sem ser invasivo

### **⏰ Timing Estratégico:**
1. **Primeiro contato**: 30-60 minutos via WhatsApp
2. **Segundo contato**: 24 horas via email (Cart Abandonment)
3. **Terceiro contato**: 72 horas com oferta especial

### **🎯 Segmentação por Valor:**
```php
// Exemplo de personalização por valor do carrinho
add_filter('wpwevo_cart_abandonment_message', function($message, $data) {
    $cart_total = floatval($data['trigger_details']['cart_total']);
    
    if ($cart_total > 500) {
        // Carrinho alto valor - tratamento VIP
        $message = "🌟 Olá " . $data['trigger_details']['first_name'] . "!\n\n";
        $message .= "Notamos que você tem itens premium no seu carrinho...\n";
        $message .= "Nossa equipe VIP entrará em contato em breve! 👑";
    }
    
    return $message;
}, 10, 2);
```

## 🆘 **Suporte Técnico**

### **Debug Avançado:**
```php
// Ativar logs detalhados
add_action('wcf_ca_before_trigger_webhook', function($trigger_details, $checkout_details, $order_status) {
    error_log('=== CART ABANDONMENT DEBUG ===');
    error_log('Order Status: ' . $order_status);
    error_log('Trigger Details: ' . print_r($trigger_details, true));
    error_log('Checkout Details: ' . print_r($checkout_details, true));
}, 1, 3);
```

### **Contato:**
- 📧 **Email**: suporte@relaxsolucoes.online
- 🌐 **Site**: https://relaxsolucoes.online/
- 📖 **Documentação**: README.md do plugin

---

## 🎯 **Resumo da Nova Abordagem**

✅ **ANTES (Webhook Externo):**
- Configuração complexa de URL
- Dependência de conectividade externa
- Possíveis falhas de rede
- Latência de processamento

🚀 **AGORA (Interceptação Interna):**
- ✅ Ativação simples de 1 clique
- 🔒 Processamento interno 100% seguro
- ⚡ Zero latência - instantâneo
- 🎯 Interceptação automática de hooks

**💡 Resultado:** Integração mais rápida, confiável e fácil de configurar! 