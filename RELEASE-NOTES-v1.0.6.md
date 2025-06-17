# 🔒 Release v1.0.6 - Segurança e Correção Crítica

**Data:** 17 de Junho de 2025  
**Prioridade:** 🚨 CRÍTICA - Atualização obrigatória

---

## 🚨 **CORREÇÃO CRÍTICA DE SEGURANÇA**

### ❌ **Problema Identificado**
- **Informações sensíveis** expostas em documentação pública
- **Desinstalação incompleta** deixava resíduos no banco de dados
- **Inconsistência** nos nomes de opções causava problemas

### ✅ **Solução Implementada**
- **🛡️ LIMPEZA COMPLETA**: Removidas todas informações sensíveis
- **🗑️ DESINSTALAÇÃO CORRIGIDA**: Arquivo dedicado `uninstall.php`
- **🔄 MIGRAÇÃO AUTOMÁTICA**: Corrige instalações existentes

---

## 🔧 **CORREÇÕES IMPLEMENTADAS**

### 1. **Segurança Aprimorada**
- ✅ Removidas API Keys de exemplos
- ✅ Removidas URLs específicas da documentação
- ✅ Limpeza completa de arquivos públicos

### 2. **Desinstalação Corrigida**
- ✅ Arquivo `uninstall.php` dedicado
- ✅ Remove TODAS as opções do plugin
- ✅ Limpa tabelas, transients e cron jobs
- ✅ Remove metadados relacionados

### 3. **Migração Automática**
- ✅ Corrige inconsistência `wpwevo_instance_name` → `wpwevo_instance`
- ✅ Funciona automaticamente em instalações existentes
- ✅ Zero quebras de funcionalidade

---

## 📋 **ITENS LIMPOS NA DESINSTALAÇÃO**

### Opções Removidas
- ✅ Configurações básicas (API, instância, versão)
- ✅ Configurações de checkout
- ✅ Configurações de carrinho abandonado
- ✅ Histórico de envios em massa
- ✅ Configurações futuras (sequência de emails)

### Dados Removidos
- ✅ Tabela de logs (`wpwevo_logs`)
- ✅ Transients e cache
- ✅ Cron jobs agendados
- ✅ User meta e post meta relacionados

---

## 🎯 **IMPACTO DA ATUALIZAÇÃO**

### ✅ **Benefícios**
- **Segurança aprimorada** - Nenhuma informação sensível exposta
- **Desinstalação limpa** - Zero resíduos no banco de dados
- **Compatibilidade total** - Funciona em todas as instalações
- **Performance mantida** - Todas funcionalidades preservadas

### 🔄 **Compatibilidade**
- ✅ **Instalações novas**: Funcionam perfeitamente
- ✅ **Instalações existentes**: Migração automática
- ✅ **Configurações**: Todas preservadas
- ✅ **Funcionalidades**: Zero impacto

---

## 🚀 **COMO ATUALIZAR**

### **Método 1: WordPress Admin (Recomendado)**
1. Acesse **Plugins > Plugins Instalados**
2. Localize **WP WhatsEvolution**
3. Clique em **Atualizar agora**
4. Aguarde a conclusão

### **Método 2: Upload Manual**
1. Baixe a versão v1.0.6
2. Desative o plugin atual
3. Remova a pasta antiga
4. Faça upload da nova versão
5. Ative o plugin

---

## 🧪 **TESTADO COM**

- **✅ Evolution API**: v2.2.3+
- **✅ WordPress**: 5.8+
- **✅ WooCommerce**: 5.0+
- **✅ PHP**: 7.4+

---

## 📞 **SUPORTE**

### **Se você ainda tem problemas:**
1. **Verifique**: Sua API está funcionando (teste manual)
2. **Confirme**: URL, API Key e Nome da Instância estão corretos
3. **Teste**: Conexão via painel Evolution API
4. **Contato**: Abra uma issue se o problema persistir

### **Exemplo de Teste Manual**
```bash
curl --request GET \
  --url 'https://sua-api.com/instance/fetchInstances?instanceName=SuaInstancia' \
  --header 'apikey: SUA-API-KEY'
```

---

## 🎉 **PRÓXIMOS PASSOS**

Esta correção prepara o terreno para as próximas funcionalidades:
- **🏆 Prioridade 1**: Metabox no Pedido (3 dias)
- **🔄 Prioridade 2**: Sequência de E-mails (13 dias)

---

## ⚠️ **IMPORTANTE**

- **Atualização obrigatória** para todos os usuários
- **Nenhuma configuração será perdida**
- **Todas funcionalidades mantidas**
- **Segurança significativamente aprimorada**

---

**⚡ Esta é uma atualização crítica de segurança. Atualize imediatamente!**

*Changelog completo disponível em [CHANGELOG.md](CHANGELOG.md)* 