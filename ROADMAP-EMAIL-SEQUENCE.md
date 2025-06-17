# 🚀 ROADMAP: Sequência de E-mails Automática via WhatsApp

## 📋 OBJETIVO
Interceptar o sistema de sequência de e-mails do plugin "WooCommerce Cart Abandonment Recovery" e converter para envio via WhatsApp, mantendo toda a funcionalidade original (timing, templates, cupons).

## 🎯 RESULTADO ESPERADO
Cliente recebe sequência automática via WhatsApp:
- **30 min**: "Problema na compra?"
- **45 min**: "Precisa de ajuda?"  
- **60 min**: "Desconto exclusivo + cupom!"

---

## 🗺️ FASES DO DESENVOLVIMENTO

### 📊 **FASE 1: ANÁLISE E DOCUMENTAÇÃO** *(1-2 dias)*

#### 🔍 1.1 Mapeamento do Banco de Dados
- [ ] Analisar tabela `cartflows_ca_email_history`
- [ ] Analisar tabela `cartflows_ca_email_templates` 
- [ ] Analisar tabela `cartflows_ca_cart_abandonment`
- [ ] Documentar relacionamentos entre tabelas
- [ ] Mapear campos necessários para WhatsApp

#### 🕵️ 1.2 Análise do Sistema de Cron
- [ ] Identificar hook `cartflows_ca_send_email_templates`
- [ ] Analisar classe `Cartflows_Ca_Email_Schedule`
- [ ] Mapear fluxo de agendamento de e-mails
- [ ] Identificar ponto de interceptação ideal

#### 📝 1.3 Mapeamento de Templates
- [ ] Analisar estrutura dos templates de e-mail
- [ ] Identificar shortcodes disponíveis
- [ ] Documentar sistema de cupons
- [ ] Criar especificação de conversão para WhatsApp

---

### 🛠️ **FASE 2: DESENVOLVIMENTO CORE** *(2-3 dias)*

#### ⚙️ 2.1 Sistema de Interceptação
- [ ] Criar classe `WP_WhatsApp_Email_Interceptor`
- [ ] Implementar hook no cron do cart abandonment
- [ ] Sistema de query para e-mails pendentes
- [ ] Validação de dados antes do envio

#### 🔄 2.2 Conversor de Templates
- [ ] Classe `Email_To_WhatsApp_Converter`
- [ ] Conversão de HTML para texto limpo
- [ ] Mapeamento de shortcodes e-mail → WhatsApp
- [ ] Sistema de templates WhatsApp personalizáveis

#### 📱 2.3 Integração com Evolution API
- [ ] Adaptar sistema atual para sequência
- [ ] Gerenciamento de timing e agendamento
- [ ] Sistema de retry em caso de falha
- [ ] Logs específicos para sequência

---

### 🎨 **FASE 3: INTERFACE E CONFIGURAÇÃO** *(1-2 dias)*

#### 🖥️ 3.1 Painel de Configuração
- [ ] Aba "Sequência de E-mails" no admin
- [ ] Toggle para ativar/desativar interceptação
- [ ] Configuração de templates WhatsApp
- [ ] Preview de conversão e-mail → WhatsApp

#### 📊 3.2 Sistema de Logs e Relatórios
- [ ] Logs específicos da sequência
- [ ] Relatório de conversão e-mail vs WhatsApp
- [ ] Dashboard com métricas
- [ ] Sistema de debug avançado

---

### 🧪 **FASE 4: TESTES E REFINAMENTO** *(1-2 dias)*

#### ✅ 4.1 Testes Funcionais
- [ ] Teste de interceptação de e-mails
- [ ] Teste de conversão de templates
- [ ] Teste de timing correto
- [ ] Teste de geração/aplicação de cupons

#### 🚀 4.2 Testes de Performance
- [ ] Impact no WordPress Cron
- [ ] Teste com múltiplos carrinhos abandonados
- [ ] Validação de memory usage
- [ ] Teste de compatibilidade

---

## 🎯 ESPECIFICAÇÕES TÉCNICAS

### 📊 Dados Necessários (da interface atual)
```
✅ Nome: RONALD MELO
✅ Telefone: 19989881838  
✅ Email: rony.campinas@hotmail.com
✅ Produtos no carrinho
✅ Valor total
✅ Link de finalização
✅ Cupons gerados
```

### 🗄️ Tabelas do Banco
```sql
cartflows_ca_email_history
- id, template_id, ca_session_id
- scheduled_time, email_sent, coupon_code

cartflows_ca_email_templates  
- id, template_name, email_subject
- email_body, frequency, frequency_unit

cartflows_ca_cart_abandonment
- session_id, email, cart_contents
- cart_total, other_fields, time
```

### ⚙️ Hooks de Interceptação
```php
// Principal
add_action('cartflows_ca_send_email_templates', 'intercept_emails', 5);

// Alternativo
add_filter('wp_mail', 'convert_to_whatsapp', 999);

// Agendamento
add_action('cartflows_ca_email_scheduled', 'schedule_whatsapp');
```

---

## 🎨 EXEMPLOS DE CONVERSÃO

### 📧 Template 1 (30min) → 📱 WhatsApp
```
E-MAIL: "Purchase issue? Complete your order now"

WHATSAPP: 
🛒 Oi {first_name}!

Vi que você estava finalizando uma compra mas parou... 🤔
Aconteceu algum problema?

Finalize aqui: {checkout_url}
```

### 📧 Template 2 (45min) → 📱 WhatsApp  
```
E-MAIL: "Need help? We're here to assist you"

WHATSAPP:
😊 Oi novamente!

Precisa de ajuda para finalizar?
Estou aqui para te ajudar! 💬

Link rápido: {checkout_url}
```

### 📧 Template 3 (60min) → 📱 WhatsApp
```
E-MAIL: "Exclusive discount for you. Let's get things started!"

WHATSAPP:
🎁 OFERTA ESPECIAL!

Como você não finalizou, liberei um desconto exclusivo:

*{coupon_code}* 🎟️

⏰ Válido só hoje!
Aproveite: {checkout_url}
```

---

## 📈 ESTIMATIVAS

### ⏱️ Tempo de Desenvolvimento
- **Desenvolvedor experiente**: 5-7 dias
- **Desenvolvedor intermediário**: 8-12 dias

### 🎯 Nível de Dificuldade: **5/10**
- Interceptação: 4/10
- Conversão: 3/10  
- Interface: 5/10
- Testes: 6/10

### 💰 ROI Esperado
- **Conversão atual (só e-mail)**: ~15%
- **Conversão esperada (WhatsApp)**: ~35-45%
- **Aumento**: 2-3x na recuperação

---

## ⚠️ RISCOS E MITIGAÇÕES

### 🚨 Riscos Identificados
1. **Plugin atualizar** e quebrar interceptação
2. **Performance impact** no WordPress Cron
3. **Conflitos** com outros plugins de e-mail
4. **Rate limiting** da Evolution API

### 🛡️ Mitigações
1. **Versionamento** e testes de compatibilidade
2. **Queue system** para não sobrecarregar
3. **Hooks prioritários** e validações
4. **Throttling** e retry logic

---

## 🎉 ENTREGÁVEIS

### 📦 Versão Final
- [ ] Interceptação automática funcional
- [ ] 3+ templates WhatsApp prontos
- [ ] Interface de configuração
- [ ] Sistema de logs e relatórios
- [ ] Documentação de uso
- [ ] Testes automatizados

### 📋 Documentação
- [ ] Manual de instalação
- [ ] Guia de configuração
- [ ] Troubleshooting
- [ ] FAQ técnico

---

## 🚀 PRÓXIMOS PASSOS IMEDIATOS

1. **ANÁLISE**: Fazer debug das tabelas do banco
2. **PROTOTYPE**: Criar interceptação básica
3. **TEST**: Validar conceito com 1 template
4. **EXPAND**: Implementar sistema completo
5. **POLISH**: Interface e documentação

---

*Roadmap criado em: 17/12/2024*
*Versão: 1.0*
*Status: 📋 Planejamento* 