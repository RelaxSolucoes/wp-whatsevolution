# WP WhatsEvolution v1.0.4

🚀 **Integração completa com WooCommerce usando Evolution API**

## 🆕 **NOVO na v1.0.4: Carrinho Abandonado Revolucionário!**

### 🎯 **Interceptação Interna 100% Automática**
- **⚡ Zero Configuração de Webhook** - Ativação com 1 clique
- **🔒 100% Seguro** - Dados nunca saem do servidor WordPress
- **⚡ Zero Latência** - Processamento instantâneo via hooks internos
- **🎯 Interceptação Inteligente** - Captura carrinhos antes mesmo do webhook externo

### 🛒 **Integração Perfeita com Cart Abandonment Recovery**
- Funciona automaticamente com o plugin **"WooCommerce Cart Abandonment Recovery"**
- Intercepta carrinhos abandonados em tempo real
- Templates brasileiros com formatação de moeda (R$)
- Shortcodes dinâmicos para personalização total

---

## 📋 **Descrição**

O **WP WhatsEvolution** é o plugin mais avançado para integração de mensagens + WooCommerce, oferecendo:

- 🛒 **Carrinho Abandonado** com interceptação interna revolucionária
- 📊 **Envio por Status** de pedido automatizado  
- 📱 **Envio Individual** para clientes específicos
- 📢 **Envio em Massa** com filtros avançados
- ✅ **Validação no Checkout** em tempo real
- 🎨 **Templates Personalizáveis** com shortcodes dinâmicos

---

## 🛒 **Carrinho Abandonado - Funcionalidades Detalhadas**

### 🔧 **Configuração Ultra-Simples**

1. **Instale** o plugin "WooCommerce Cart Abandonment Recovery"
2. **Ative** a integração em "WhatsEvolution > Carrinho Abandonado"  
3. **Personalize** a mensagem com shortcodes
4. **Pronto!** O sistema funciona automaticamente

### 🎨 **Shortcodes Disponíveis**

| Shortcode | Descrição | Exemplo |
|-----------|-----------|---------|
| `{first_name}` | Nome do cliente | João |
| `{full_name}` | Nome completo | João Silva |
| `{product_names}` | Produtos no carrinho | Produto A, Produto B |
| `{cart_total}` | Valor formatado | R$ 149,90 |
| `{checkout_url}` | Link finalizar compra | https://loja.com/checkout?token=abc |
| `{coupon_code}` | Código do cupom | DESCONTO10 |
| `{site_name}` | Nome da loja | Minha Loja |

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

## 🔧 **Requisitos**

- **PHP:** 7.4 ou superior
- **WordPress:** 5.8 ou superior  
- **WooCommerce:** 5.0 ou superior
- **Evolution API:** Instância configurada

---

## 🚀 **Instalação e Configuração**

### 1️⃣ **Instalação Básica**

```bash
1. Upload do plugin para /wp-content/plugins/
2. Ativar no painel WordPress
3. Acessar "WhatsEvolution" no menu admin
```

### 2️⃣ **Configuração Evolution API**

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

## 📊 **Todas as Funcionalidades**

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
- ✅ Configuração flexível

### 📱 **Envio Individual**
- ✅ Interface simples
- ✅ Validação automática
- ✅ Histórico de envios

### 📢 **Envio em Massa**
- ✅ Filtros avançados
- ✅ Importação CSV
- ✅ Controle de velocidade
- ✅ Logs detalhados

### ✅ **Validação Checkout**
- ✅ Campo obrigatório
- ✅ Validação tempo real
- ✅ Formatação automática

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
    // Log personalizado, integração CRM, etc.
    error_log("Mensagem enviada para {$phone} - Valor: {$trigger_details['cart_total']}");
}, 10, 3);
```

### ✅ **Validação Personalizada**

```php
add_filter('wpwevo_validate_whatsapp', function($is_valid, $number) {
    // Sua lógica de validação personalizada
    return $is_valid;
}, 10, 2);
```

---

## 📝 **Changelog**

### 🆕 **v1.0.4 - 2024-12-17**
- **🚀 NOVO:** Interceptação interna de carrinho abandonado
- **🚀 NOVO:** Integração com "WooCommerce Cart Abandonment Recovery"  
- **🚀 NOVO:** Templates personalizáveis com shortcodes
- **✨ MELHORIA:** Logs otimizados e mais limpos
- **🔧 CORREÇÃO:** Formatação automática moeda brasileira (R$)
- **🐛 CORREÇÃO:** Múltiplas correções de compatibilidade
- **🏷️ REBRANDING:** Plugin renomeado para WP WhatsEvolution

### v1.0.3 - 2024-11-15
- ✨ Envio por status de pedido
- ✨ Envio em massa melhorado
- 🔧 Validação de checkout
- 🐛 Correções gerais

### v1.0.2 - 2024-10-10  
- ✨ Envio em massa
- 🔧 Melhorias interface
- 🐛 Correções de bugs

### v1.0.1 - 2024-09-05
- ✨ Envio individual
- 🔧 Melhorias conexão
- 🐛 Correções iniciais

### v1.0.0 - 2024-08-01
- 🚀 Versão inicial
- ✨ Conexão Evolution API
- ✨ Configurações básicas

---

## 🆘 **Suporte**

- 📧 **Email:** suporte@relaxsolucoes.online
- 🌐 **Site:** [relaxsolucoes.online](https://relaxsolucoes.online/)
- 💬 **GitHub:** [RelaxSolucoes/wp-whatsevolution](https://github.com/RelaxSolucoes/wp-whatsevolution)

---

## 📄 **Licença**

**GPL v2 ou posterior** - Plugin 100% gratuito e open source

---

## 👨‍💻 **Desenvolvido por**

**🏢 Relax Soluções**  
🌐 [relaxsolucoes.online](https://relaxsolucoes.online/)  
📧 contato@relaxsolucoes.online

---

**⭐ Se este plugin foi útil, deixe uma estrela no GitHub!** 