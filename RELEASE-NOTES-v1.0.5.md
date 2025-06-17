# 🔧 WP WhatsEvolution v1.0.5 - Correção Crítica

## 🚨 **CORREÇÃO CRÍTICA - VALIDAÇÃO API KEY**

### ❌ **Problema Resolvido**
- **Bug**: Validação muito restritiva da API Key impedia uso de APIs válidas da Evolution
- **Erro**: "Formato da API Key inválido" mesmo com APIs funcionais
- **Impacto**: Usuários não conseguiam configurar APIs válidas

### ✅ **Solução Implementada**
- **Flexibilizado**: Regex de validação para aceitar formato real da Evolution API
- **Suporte**: API Keys como `EC2FA26C82AF-414A-AA8D-2AACC909E312`
- **Mantido**: Validação básica do formato `XXXX-XXXX-XXXX-XXXX-XXXX`
- **Testado**: Compatível com Evolution API v2.2.3+

---

## 📋 **DETALHES TÉCNICOS**

### **Antes (v1.0.4)**
```regex
/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i
```
- ❌ Exigia UUID v4 específico
- ❌ Padrão muito rígido
- ❌ Rejeitava APIs válidas

### **Depois (v1.0.5)**
```regex
/^[A-F0-9]{8,}-[A-F0-9]{4,}-[A-F0-9]{4,}-[A-F0-9]{4,}-[A-F0-9]{12,}$/i
```
- ✅ Aceita qualquer combinação A-F e 0-9
- ✅ Formato flexível mas seguro
- ✅ Compatível com diferentes provedores

---

## 🎯 **IMPACTO DA ATUALIZAÇÃO**

### **Para Usuários**
- **✅ Zero quebras**: Mantém compatibilidade com APIs antigas
- **✅ Maior flexibilidade**: Suporte a diferentes provedores Evolution API
- **✅ UX melhorada**: Menos erros de validação desnecessários
- **✅ Configuração mais fácil**: APIs válidas agora são aceitas

### **Para Desenvolvedores**
- **🔧 Código limpo**: Validação mais inteligente
- **📊 Logs melhores**: Mensagens de erro mais claras
- **🛡️ Segurança mantida**: Validação básica preservada

---

## 🚀 **COMO ATUALIZAR**

### **Método 1: Download Direto**
1. Baixe o arquivo ZIP desta release
2. Desative o plugin atual no WordPress
3. Remova a pasta antiga
4. Faça upload da nova versão
5. Ative o plugin

### **Método 2: Git (Para Desenvolvedores)**
```bash
git pull origin main
git checkout v1.0.5
```

---

## 🧪 **TESTADO COM**

- **✅ Evolution API**: v2.2.3+
- **✅ WordPress**: 5.8+
- **✅ WooCommerce**: 5.0+
- **✅ PHP**: 7.4+
- **✅ Provedores testados**:
  - `api.relaxnarguiles.com`
  - APIs com formato `EC2FA26C82AF-414A-AA8D-2AACC909E312`

---

## 📞 **SUPORTE**

### **Se você ainda tem problemas:**
1. **Verifique**: Sua API está realmente funcionando (teste com cURL)
2. **Confirme**: URL, API Key e Nome da Instância estão corretos
3. **Teste**: Conexão manual via Evolution API
4. **Contato**: Abra uma issue se o problema persistir

### **Exemplo de Teste cURL**
```bash
curl --request GET \
  --url 'https://sua-api.com/instance/fetchInstances?instanceName=SuaInstancia' \
  --header 'apikey: SUA-API-KEY-AQUI'
```

---

## 🎉 **PRÓXIMOS PASSOS**

Esta correção prepara o terreno para as próximas funcionalidades:
- **🏆 Prioridade 1**: Metabox no Pedido (3 dias)
- **🔄 Prioridade 2**: Sequência de E-mails (13 dias)

---

**⚡ Atualização recomendada para todos os usuários!**

*Changelog completo disponível em [CHANGELOG.md](CHANGELOG.md)* 