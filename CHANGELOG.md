# Changelog - WP WhatsEvolution

## [1.0.4] - 2024-12-17

### 🏷️ REBRANDING
- **Plugin renomeado** para WP WhatsEvolution (evita problemas legais com marca WhatsApp)
- **Repositório renomeado** para wp-whatsevolution
- **Todas configurações preservadas** na migração

### ✨ NOVA FUNCIONALIDADE - CARRINHO ABANDONADO
- **🎯 Interceptação interna** - Sistema revolucionário via hooks internos
- **⚡ Zero configuração** - Ativação com 1 clique, sem URLs de webhook
- **🛠️ Integração automática** com plugin "WooCommerce Cart Abandonment Recovery"
- **📝 Templates personalizáveis** com shortcodes dinâmicos
- **🇧🇷 Formatação brasileira** - R$ ao invés de símbolos HTML

### 🎨 SHORTCODES DISPONÍVEIS
- `{first_name}` - Nome do cliente
- `{product_names}` - Produtos no carrinho  
- `{cart_total}` - Valor formatado (R$ 149,90)
- `{checkout_url}` - Link para finalizar compra
- `{coupon_code}` - Código do cupom

### 📊 MELHORIAS
- **Logs otimizados** - Apenas informações essenciais para usuários
- **Interface melhorada** - Logs com cores e ícones
- **Performance** - Redução de overhead no sistema de logs

### 🐛 CORREÇÕES
- **Formatação de moeda** - Corrigido &#36; → R$
- **Compatibilidade** - Melhorada integração com plugins externos
- **Validação** - Números de telefone brasileiros

### 📋 COMO USAR CARRINHO ABANDONADO
1. Instale plugin "WooCommerce Cart Abandonment Recovery"
2. Acesse "WhatsEvolution > Carrinho Abandonado"
3. Ative a integração
4. Personalize mensagem (opcional)
5. Pronto! Sistema funciona automaticamente

### 🔧 TEMPLATE PADRÃO
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

### 📥 INSTALAÇÃO
1. Baixe o código via "Code > Download ZIP"
2. Upload via WordPress Admin > Plugins > Adicionar Novo
3. Ative o plugin
4. Configure Evolution API
5. Ative funcionalidades desejadas

**Todas configurações são preservadas na atualização!**

---

## [1.0.3] - 2024-11-15
- Envio por status de pedido
- Envio em massa melhorado
- Validação de checkout

## [1.0.2] - 2024-10-10
- Envio em massa
- Melhorias na interface

## [1.0.1] - 2024-09-05
- Envio individual
- Melhorias na conexão

## [1.0.0] - 2024-08-01
- Versão inicial
- Conexão com Evolution API 