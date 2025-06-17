# 📋 REGRAS DE DESENVOLVIMENTO - WP WhatsApp Evolution

## 🎯 PRINCÍPIOS FUNDAMENTAIS

### ✅ **JAMAIS QUEBRAR O EXISTENTE**
- NUNCA modificar arquivos de plugins externos (Cart Abandonment Recovery, etc.)
- NUNCA alterar tabelas existentes do banco
- NUNCA modificar estrutura core do WooCommerce
- SEMPRE usar hooks e filters para interceptação
- SEMPRE manter compatibilidade com sistema atual

### ⚡ **PERFORMANCE EM PRIMEIRO LUGAR**
- NUNCA sobrecarregar WordPress Cron
- SEMPRE usar queries otimizadas
- SEMPRE implementar cache quando possível
- NUNCA fazer loops desnecessários
- SEMPRE considerar impacto em sites com alta concorrência

### 🔒 **SEGURANÇA E VALIDAÇÃO**
- SEMPRE validar dados antes de processar
- SEMPRE sanitizar inputs do usuário
- SEMPRE usar prepared statements
- SEMPRE verificar capabilities (manage_options, edit_shop_orders)
- SEMPRE usar nonces em AJAX
- NUNCA confiar em dados externos

### 🧪 **DESENVOLVIMENTO INCREMENTAL**
- SEMPRE começar com MVP funcional
- SEMPRE testar cada etapa isoladamente
- SEMPRE documentar mudanças
- SEMPRE fazer commits pequenos e descritivos
- NUNCA implementar tudo de uma vez

---

## 🚫 REGRAS RESTRITIVAS GERAIS

### ❌ **O QUE NUNCA FAZER**
1. **Modificar arquivos de plugins externos**
2. **Alterar estrutura de tabelas existentes**
3. **Desativar funcionalidades core do WooCommerce**
4. **Fazer queries sem WHERE clause**
5. **Implementar sem sistema de logs**
6. **Hardcoded valores de configuração**
7. **Enviar WhatsApp sem validar telefone**
8. **Processar sem verificar se Evolution API está ativa**
9. **Quebrar compatibilidade com HPOS**
10. **Ignorar namespaces estabelecidos**

### 🚨 **VALIDAÇÕES OBRIGATÓRIAS SEMPRE**
- Verificar se WooCommerce está ativo
- Verificar se plugin Cart Abandonment está ativo
- Validar formato de telefone brasileiro
- Confirmar configuração da Evolution API
- Verificar se usuário tem permissões adequadas
- Validar dados antes de cada envio
- Verificar compatibilidade HPOS

---

## 📦 **REGRAS ESPECÍFICAS POR FUNCIONALIDADE**

### 🛒 **METABOX NO PEDIDO**

#### ✅ **Boas Práticas**
- SEMPRE usar `add_meta_box()` adequadamente
- SEMPRE verificar `current_user_can('edit_shop_orders')`
- SEMPRE usar nonces em formulários AJAX
- SEMPRE validar $order_id antes de processar
- SEMPRE adicionar notas ao pedido após envio

#### ❌ **Restrições Metabox**
- NUNCA fazer envio sem confirmar telefone válido
- NUNCA processar sem verificar se ordem existe
- NUNCA ignorar feedback visual para o usuário
- NUNCA sobrecarregar interface com muitos campos
- NUNCA fazer requests AJAX sem loading state

#### 🎯 **Padrões de Código Metabox**
```php
// ✅ CORRETO - Estrutura do metabox
add_meta_box(
    'wpwevo_order_metabox',
    __('Enviar WhatsApp', 'wp-whatsapp-evolution'),
    [$this, 'render_metabox'],
    'shop_order',
    'side',
    'default'
);

// ✅ CORRETO - Validação no handler AJAX
public function ajax_handler() {
    check_ajax_referer('wpwevo_send_order_message', 'nonce');
    
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Permissão negada.');
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Pedido não encontrado.');
    }
    
    // ... resto do código
}
```

### 📧 **SEQUÊNCIA DE E-MAILS**

#### ✅ **Boas Práticas Sequência**
- SEMPRE usar hooks de interceptação apropriados
- SEMPRE respeitar timing original dos e-mails
- SEMPRE manter opção de fallback para e-mail
- SEMPRE validar se plugin Cart Abandonment está ativo

#### ❌ **Restrições Sequência**
- NUNCA desativar sistema de e-mail completamente
- NUNCA processar sem verificar se plugin externo está ativo
- NUNCA ignorar configurações de timing
- NUNCA enviar sem validar dados do carrinho

#### 🎯 **Padrões de Código Sequência**
```php
// ✅ CORRETO - Interceptação segura
public function intercept_email($email_data) {
    // Verificar se interceptação está ativa
    if (!get_option('wpwevo_email_sequence_enabled', false)) {
        return; // Deixa e-mail passar normalmente
    }
    
    // Validar dados
    if (empty($email_data['phone'])) {
        return; // Deixa e-mail passar normalmente
    }
    
    // Processar WhatsApp
    $this->send_whatsapp($email_data);
    
    // Marcar e-mail como processado (não cancelar)
}
```

---

## 🎨 PADRÕES DE CÓDIGO GERAIS

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

### 🏷️ **Nomenclatura Padronizada**
- **Classes**: `Order_Metabox`, `Email_Sequence`
- **Métodos**: `send_order_message()`, `convert_template_to_whatsapp()`
- **Hooks**: `wpwevo_metabox_*`, `wpwevo_sequence_*`
- **Options**: `wpwevo_metabox_settings`, `wpwevo_sequence_settings`
- **AJAX Actions**: `wpwevo_send_order_message`, `wpwevo_preview_template`

### 📝 **Documentação Obrigatória**
```php
/**
 * Envia mensagem WhatsApp diretamente do pedido
 * 
 * @param int $order_id ID do pedido WooCommerce
 * @param string $message Mensagem a ser enviada
 * @return array Resultado do envio
 * @since 1.1.0
 */
public function send_order_message($order_id, $message) {
    // Código aqui
}
```

### 🔧 **Namespace e Autoloader**
```php
<?php
namespace WpWhatsAppEvolution;

// ✅ SEMPRE usar namespace
class Order_Metabox {
    // Autoloader vai carregar automaticamente
}

// ✅ SEMPRE seguir padrão de nomes
// class-order-metabox.php → Order_Metabox
```

---

## 🔄 FLUXO DE DESENVOLVIMENTO ATUALIZADO

### 📋 **ETAPAS OBRIGATÓRIAS PARA QUALQUER FUNCIONALIDADE**

#### 1️⃣ **ANÁLISE** (Sempre primeiro)
- [ ] Estudar código existente
- [ ] Mapear hooks disponíveis
- [ ] Verificar compatibilidade HPOS
- [ ] Documentar descobertas
- [ ] Validar viabilidade técnica

#### 2️⃣ **PROTOTIPAGEM** (MVP primeiro)
- [ ] Implementar versão mínima
- [ ] Testar conceito básico
- [ ] Validar integração com API existente
- [ ] Confirmar funcionamento isolado

#### 3️⃣ **DESENVOLVIMENTO** (Incremental)
- [ ] Implementar por módulos pequenos
- [ ] Testar cada módulo isoladamente
- [ ] Integrar gradualmente
- [ ] Validar compatibilidade com existente

#### 4️⃣ **TESTES** (Sempre obrigatório)
- [ ] Teste unitário de cada função
- [ ] Teste de integração com WooCommerce
- [ ] Teste de performance em ambiente real
- [ ] Teste de compatibilidade com plugins comuns

#### 5️⃣ **DOCUMENTAÇÃO** (Nunca esquecer)
- [ ] Atualizar CHANGELOG
- [ ] Documentar novas configurações
- [ ] Criar exemplos de uso
- [ ] Atualizar README se necessário

---

## 🛡️ CONTROLE DE QUALIDADE

### ✅ **CHECKLIST PRE-COMMIT UNIVERSAL**
- [ ] Código segue padrões estabelecidos
- [ ] Todas validações implementadas
- [ ] Logs adicionados para debug
- [ ] Tratamento de erros implementado
- [ ] Testes básicos passando
- [ ] Documentação atualizada
- [ ] Namespace correto usado
- [ ] Compatibilidade HPOS verificada

### 🧪 **CHECKLIST DE TESTES ESPECÍFICOS**

#### **Para Metabox:**
- [ ] Metabox aparece corretamente na tela
- [ ] Templates carregam adequadamente
- [ ] Preview funciona em tempo real
- [ ] AJAX funciona sem erros
- [ ] Notas são adicionadas ao pedido
- [ ] Permissões são respeitadas

#### **Para Sequência de E-mails:**
- [ ] Plugin funciona SEM Cart Abandonment
- [ ] Plugin funciona COM Cart Abandonment inativo
- [ ] Interceptação não quebra e-mails normais
- [ ] Templates convertem corretamente
- [ ] Timing é respeitado
- [ ] Performance aceitável

### 📊 **MÉTRICAS DE QUALIDADE**
- **Cobertura de testes**: Mínimo 70%
- **Performance**: Máximo 100ms por operação
- **Memory usage**: Máximo 2MB adicional por funcionalidade
- **Compatibilidade**: WordPress 5.8+, PHP 7.4+, WooCommerce 5.0+

---

## 🔧 CONFIGURAÇÕES DE DESENVOLVIMENTO

### 🐛 **DEBUG MODE**
```php
// wp-config.php
define('WP_WHATSAPP_EVOLUTION_DEBUG', true);
define('WPWEVO_LOG_LEVEL', 'INFO');
```

### 📝 **LOGS OBRIGATÓRIOS POR FUNCIONALIDADE**

#### **Metabox:**
```php
// Sempre logar estes eventos:
- Renderização do metabox
- Seleção de template
- Envio de mensagem
- Erros de validação
- Falhas de API
```

#### **Sequência de E-mails:**
```php
// Sempre logar estes eventos:
- Interceptação de e-mail
- Conversão de template
- Agendamento de WhatsApp
- Envio bem-sucedido
- Falhas de conversão
```

### ⚙️ **CONFIGURAÇÕES PADRÃO**
```php
$default_settings = [
    // Metabox
    'metabox_enabled' => true,             // Ativado por padrão
    'metabox_show_preview' => true,        // Preview habilitado
    'metabox_log_level' => 'ERROR',        // Apenas erros por padrão
    
    // Sequência
    'sequence_enabled' => false,           // Desativado por padrão
    'fallback_to_email' => true,          // Se WhatsApp falhar, manter e-mail
    'timing_offset' => 0,                 // Sem delay adicional
    'max_retries' => 3,                   // Máximo tentativas
];
```

---

## 🚀 DEPLOYMENT E VERSIONAMENTO

### 📦 **PREPARAÇÃO PARA RELEASE**
1. **Versioning**: Seguir semantic versioning
   - Metabox: 1.1.0 (nova funcionalidade)
   - Sequência: 1.2.0 (nova funcionalidade)
2. **Changelog**: Documentar todas mudanças
3. **Backup**: Criar backup antes de atualizar
4. **Rollback**: Ter plano de rollback pronto

### 🎯 **RELEASE CHECKLIST EXPANDIDO**
- [ ] Todos testes passando (unitários + integração)
- [ ] Performance validada em ambiente real
- [ ] Compatibilidade testada com plugins populares
- [ ] Documentação completamente atualizada
- [ ] Changelog detalhado criado
- [ ] Screenshots atualizadas (se aplicável)
- [ ] Tag Git criada com versão correta
- [ ] Release notes preparadas em português

### 📊 **COMPATIBILIDADE OBRIGATÓRIA**
- **WordPress**: 5.8+ 
- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **Plugins testados**: 
  - WooCommerce Cart Abandonment Recovery
  - Brazilian Market for WooCommerce
  - WooCommerce PDF Invoices & Packing Slips
  - HPOS (High-Performance Order Storage)

---

*Regras atualizadas em: 18/12/2024*
*Versão: 2.0 - Expandida para Metabox + Sequência*
*Status: 📋 Pronto para aplicação* 