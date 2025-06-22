# Cenários de Uso - WP WhatsApp Evolution

Este documento descreve como o plugin WP WhatsApp Evolution funciona em todos os cenários possíveis de uso, garantindo que os usuários possam alternar entre as abas "Teste Grátis" e "Conexão" sem perder configurações.

## 🎯 Cenários Suportados

### ⚠️ **IMPORTANTE: Fluxo de Pagamento vs Teste Grátis**

**🆓 Teste Grátis:**
- 7 dias de acesso gratuito
- Sem cartão de crédito
- Acesso completo às funcionalidades
- **NÃO pode ser renovado** - quando expira, precisa fazer pagamento

**💳 Processo de Pagamento:**
- Quando trial expira, usuário precisa pagar
- Botão "🔄 Renovar Conta" = **Processo de pagamento**
- Ativa plano pago (não renova teste grátis)
- Mantém dados do usuário e configurações

**🔄 "Renovar Conta" = Pagamento, NÃO renovação do trial gratuito**

### 1. **Usuário cadastrou através da aba "Teste Grátis", depois resolveu usar através da aba conexão**

**✅ FUNCIONAMENTO:**
- O plugin detecta que está no modo "managed" (configuração automática)
- Na aba "Conexão", mostra um aviso informando que está no modo automático
- Oferece botão "Clique aqui para configurar manualmente"
- Ao clicar, limpa as configurações gerenciadas e permite configuração manual
- Preserva os dados do usuário para possível retorno futuro

**🔧 IMPLEMENTAÇÃO:**
```php
// Em class-settings-page.php
if ($is_managed) {
    // Mostra aviso de modo automático
    // Botão para forçar modo manual
    <a href="force_manual_mode=1">Clique aqui para configurar manualmente</a>
}
```

### 2. **Usuário cadastrou através da aba conexão depois resolveu usar através da aba "Teste Grátis"**

**✅ FUNCIONAMENTO:**
- O plugin detecta que não está configurado automaticamente
- Mostra a aba "Teste Grátis" por padrão
- Permite criação de conta managed
- Preserva configurações manuais anteriores em `wpwevo_previous_manual_config`
- Sobrescreve configurações manuais com as novas managed

**🔧 IMPLEMENTAÇÃO:**
```php
// Em class-settings-page.php
if (!Quick_Signup::is_auto_configured() && !get_option('wpwevo_api_url', '')) {
    $active_tab = 'quick-signup'; // Mostra teste grátis por padrão
}

// Em class-quick-signup.php
if (get_option('wpwevo_connection_mode') === 'manual') {
    $previous_manual_config = [
        'api_url' => get_option('wpwevo_api_url'),
        'api_key' => get_option('wpwevo_manual_api_key'),
        'instance' => get_option('wpwevo_instance')
    ];
    update_option('wpwevo_previous_manual_config', $previous_manual_config);
}
```

### 3. **Usuário cadastrou através da aba "Teste Grátis", depois resolveu usar através da aba conexão, depois resolveu voltar a usar através da aba "Teste Grátis" para fazer pagamento**

**✅ FUNCIONAMENTO:**
- Detecta que o email já existe no sistema
- Identifica que o usuário quer fazer pagamento (não renovar teste grátis)
- Preenche automaticamente os campos com dados salvos
- Muda o texto do botão para "🔄 Renovar Conta" (processo de pagamento)
- Envia flag `is_renewal: true` para a API (indica pagamento)
- Preserva configurações manuais anteriores se existirem

**🔧 IMPLEMENTAÇÃO:**
```php
// Em class-quick-signup.php
$previous_managed_email = get_option('wpwevo_user_email', '');
if ($previous_managed_email === $email) {
    $is_renewal = true; // Indica processo de pagamento, não renovação do trial
    // Preserva configurações manuais se existirem
}

// Em class-settings-page.php
$current_user_email = get_option('wpwevo_user_email', '');
<input value="<?php echo esc_attr($current_user_email); ?>">
<button><?php echo $current_user_email ? '🔄 Renovar Conta' : '🚀 Criar Conta'; ?></button>
// "Renovar Conta" = Processo de pagamento para ativar plano pago
```

### 4. **Usuário cadastrou através da aba conexão depois resolveu usar através da aba "Teste Grátis", venceu e resolveu voltar a usar através da aba conexão**

**✅ FUNCIONAMENTO:**
- Detecta trial expirado com `Quick_Signup::should_show_upgrade_modal()`
- Mostra aviso específico de "Trial Expirado" na aba conexão
- Oferece múltiplas opções:
  - 🔄 Renovar conta (link para aba quick-signup - **processo de pagamento**)
  - 🔙 Restaurar configuração manual anterior (se existir)
  - ⚙️ Configurar manualmente (limpa tudo)
- Restaura configurações manuais anteriores preservadas

**🔧 IMPLEMENTAÇÃO:**
```php
// Em class-settings-page.php
$is_trial_expired = Quick_Signup::should_show_upgrade_modal();
$has_previous_manual = get_option('wpwevo_previous_manual_config');

if ($is_trial_expired) {
    // Mostra aviso de trial expirado
    // Links para pagamento, restaurar manual ou configurar manualmente
}

// Restauração de configurações manuais
if (isset($_GET['restore_manual'])) {
    $previous_config = get_option('wpwevo_previous_manual_config');
    if ($previous_config) {
        update_option('wpwevo_connection_mode', 'manual');
        update_option('wpwevo_api_url', $previous_config['api_url']);
        update_option('wpwevo_manual_api_key', $previous_config['api_key']);
        update_option('wpwevo_instance', $previous_config['instance']);
    }
}
```

## 🔧 Sistema de Modos de Conexão

### Modo "managed" (Teste Grátis)
- Configuração automática via API externa
- Dados salvos em `wpwevo_managed_api_key`
- Interface bloqueada para edição
- Trial de 7 dias

### Modo "manual" (Conexão)
- Configuração manual pelo usuário
- Dados salvos em `wpwevo_manual_api_key`
- Interface totalmente editável
- Sem limitação de tempo

## 📊 Opções do WordPress Utilizadas

| Opção | Descrição | Modo |
|-------|-----------|------|
| `wpwevo_connection_mode` | 'managed' ou 'manual' | Ambos |
| `wpwevo_managed_api_key` | Chave da API gerenciada | Managed |
| `wpwevo_manual_api_key` | Chave da API manual | Manual |
| `wpwevo_api_url` | URL da API | Ambos |
| `wpwevo_instance` | Nome da instância | Ambos |
| `wpwevo_user_email` | Email do usuário | Managed |
| `wpwevo_user_name` | Nome do usuário | Managed |
| `wpwevo_user_whatsapp` | WhatsApp do usuário | Managed |
| `wpwevo_previous_manual_config` | Configurações manuais anteriores | Backup |
| `wpwevo_trial_expires_at` | Data de expiração do trial | Managed |

## 🚀 Funcionalidades Implementadas

### ✅ Detecção de Renovação
- Compara email atual com email salvo
- Preenche formulário automaticamente
- Muda texto do botão
- Envia flag para API

### ✅ Preservação de Configurações
- Salva configurações manuais antes de mudar para managed
- Permite restauração posterior
- Evita perda de dados

### ✅ Detecção de Trial Expirado
- Verifica data de expiração
- Mostra avisos específicos
- Oferece múltiplas opções de ação

### ✅ Interface Adaptativa
- Muda textos baseado no estado atual
- Mostra/oculta elementos conforme necessário
- Feedback visual claro para o usuário

### ✅ Limpeza de Dados
- Remove configurações antigas ao mudar de modo
- Evita conflitos entre managed e manual
- Mantém backup para restauração

## 🎯 Benefícios para o Usuário

1. **Flexibilidade Total**: Pode alternar entre modos sem perder configurações
2. **Experiência Contínua**: Não precisa reconfigurar tudo do zero
3. **Clareza**: Interface sempre mostra o estado atual e opções disponíveis
4. **Segurança**: Preserva dados importantes para restauração
5. **Simplicidade**: Processo intuitivo com feedback visual claro

## 🔍 Testes Recomendados

Para garantir que todos os cenários funcionem corretamente, teste:

1. ✅ Criar conta via Teste Grátis → Mudar para Manual
2. ✅ Configurar Manual → Criar conta via Teste Grátis
3. ✅ Renovar conta existente via Teste Grátis
4. ✅ Trial expirado → Restaurar configuração manual
5. ✅ Trial expirado → Renovar conta
6. ✅ Trial expirado → Configurar manualmente do zero

O plugin está 100% preparado para todos os cenários mencionados, oferecendo uma experiência fluida e sem perda de dados para os usuários. 