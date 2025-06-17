# 📋 REGRAS DE DESENVOLVIMENTO - Sequência WhatsApp

## 🎯 PRINCÍPIOS FUNDAMENTAIS

### ✅ **JAMAIS QUEBRAR O EXISTENTE**
- NUNCA modificar arquivos do plugin Cart Abandonment Recovery
- NUNCA alterar tabelas existentes do banco
- SEMPRE usar hooks e filters para interceptação
- SEMPRE manter compatibilidade com sistema atual

### ⚡ **PERFORMANCE EM PRIMEIRO LUGAR**
- NUNCA sobrecarregar WordPress Cron
- SEMPRE usar queries otimizadas
- SEMPRE implementar cache quando possível
- NUNCA fazer loops desnecessários

### 🔒 **SEGURANÇA E VALIDAÇÃO**
- SEMPRE validar dados antes de processar
- SEMPRE sanitizar inputs do usuário
- SEMPRE usar prepared statements
- NUNCA confiar em dados externos

### 🧪 **DESENVOLVIMENTO INCREMENTAL**
- SEMPRE começar com MVP funcional
- SEMPRE testar cada etapa isoladamente
- SEMPRE documentar mudanças
- NUNCA implementar tudo de uma vez

---

## 🚫 REGRAS RESTRITIVAS

### ❌ **O QUE NUNCA FAZER**
1. **Modificar arquivos do plugin externo**
2. **Alterar estrutura de tabelas existentes**
3. **Desativar sistema de e-mail completamente**
4. **Fazer queries sem WHERE clause**
5. **Implementar sem sistema de logs**
6. **Hardcoded valores de configuração**
7. **Enviar WhatsApp sem validar telefone**
8. **Processar sem verificar se Evolution API está ativa**

### 🚨 **VALIDAÇÕES OBRIGATÓRIAS**
- Verificar se plugin Cart Abandonment está ativo
- Validar formato de telefone brasileiro
- Confirmar configuração da Evolution API
- Verificar se usuário tem permissões adequadas
- Validar dados antes de cada envio

---

## 🎨 PADRÕES DE CÓDIGO

### 📁 **Estrutura de Arquivos**
```
includes/
├── class-email-sequence.php          // Classe principal
├── class-email-interceptor.php       // Interceptação de e-mails
├── class-template-converter.php      // Conversão templates
├── class-whatsapp-sequencer.php      // Agendamento WhatsApp
└── helpers/
    ├── sequence-helpers.php          // Funções auxiliares
    └── template-helpers.php          // Helpers de templates
```

### 🏷️ **Nomenclatura**
- **Classes**: `WP_WhatsApp_Email_Sequence`
- **Métodos**: `convert_template_to_whatsapp()`
- **Hooks**: `wp_whatsapp_sequence_*`
- **Options**: `wp_whatsapp_sequence_settings`

### 📝 **Documentação**
```php
/**
 * Intercepta e-mails agendados do Cart Abandonment
 * 
 * @param array $email_data Dados do e-mail a ser enviado
 * @return bool True se interceptado com sucesso
 * @since 1.1.0
 */
public function intercept_scheduled_email($email_data) {
    // Código aqui
}
```

---

## 🔄 FLUXO DE DESENVOLVIMENTO

### 📋 **ETAPAS OBRIGATÓRIAS**

#### 1️⃣ **ANÁLISE** (Sempre primeiro)
- [ ] Estudar código existente
- [ ] Mapear hooks disponíveis
- [ ] Documentar descobertas
- [ ] Validar viabilidade técnica

#### 2️⃣ **PROTOTIPAGEM** (MVP primeiro)
- [ ] Implementar versão mínima
- [ ] Testar conceito básico
- [ ] Validar interceptação
- [ ] Confirmar funcionamento

#### 3️⃣ **DESENVOLVIMENTO** (Incremental)
- [ ] Implementar por módulos
- [ ] Testar cada módulo isoladamente
- [ ] Integrar gradualmente
- [ ] Validar compatibilidade

#### 4️⃣ **TESTES** (Sempre obrigatório)
- [ ] Teste unitário de cada função
- [ ] Teste de integração
- [ ] Teste de performance
- [ ] Teste de compatibilidade

#### 5️⃣ **DOCUMENTAÇÃO** (Nunca esquecer)
- [ ] Atualizar CHANGELOG
- [ ] Documentar novas configurações
- [ ] Criar exemplos de uso
- [ ] Atualizar README

---

## 🛡️ CONTROLE DE QUALIDADE

### ✅ **CHECKLIST PRE-COMMIT**
- [ ] Código segue padrões estabelecidos
- [ ] Todas validações implementadas
- [ ] Logs adicionados para debug
- [ ] Tratamento de erros implementado
- [ ] Testes básicos passando
- [ ] Documentação atualizada

### 🧪 **CHECKLIST DE TESTES**
- [ ] Plugin funciona SEM Cart Abandonment
- [ ] Plugin funciona COM Cart Abandonment inativo
- [ ] Plugin funciona COM Evolution API inativa
- [ ] Templates convertem corretamente
- [ ] Timing respeitado
- [ ] Performance aceitável

### 📊 **MÉTRICAS DE QUALIDADE**
- **Cobertura de testes**: Mínimo 70%
- **Performance**: Máximo 100ms por interceptação
- **Memory usage**: Máximo 2MB adicional
- **Compatibilidade**: WordPress 5.0+, PHP 7.4+

---

## 🎯 CRITÉRIOS DE SUCESSO**
- Interceptação funciona 100%
- Zero quebra de funcionalidades existentes
- Performance dentro dos limites
- Interface amigável ao usuário
- Documentação completa

---

*Regras estabelecidas em: 17/12/2024*
*Versão: 1.0*
*Status: 📋 Ativo* 