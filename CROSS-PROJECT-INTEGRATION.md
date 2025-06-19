# 🔗 INTEGRAÇÃO CROSS-PROJECT - Plugin WordPress ↔ Sistema Principal

## 🎯 **CONTEXTO**

Este plugin WordPress será usado **independentemente** do sistema principal WhatsApp Evolution. Porém, para o **onboarding 1-click** funcionar, precisa integrar com Edge Functions que estão em **projeto separado**.

## 📁 **ESTRUTURA DOS PROJETOS**

### 🔵 **PROJETO 1: Sistema Principal (Supabase)**
- **Localização:** Projeto separado (não nesta pasta)
- **Supabase Project ID:** ydnobqsepveefiefmxag
- **URL:** https://ydnobqsepveefiefmxag.supabase.co
- **Responsabilidades:** Edge Functions, Database, Autenticação

### 🟡 **PROJETO 2: Plugin WordPress (Este aqui)**
- **Localização:** wp-whatsevolution-main/
- **Responsabilidades:** Interface, Formulários, Integração com WooCommerce
- **Comunicação:** Via API calls para Edge Functions

## 🌉 **PONTE DE INTEGRAÇÃO**

### 📡 **Edge Functions Necessárias (PROJETO 1)**

#### 🆕 **1. quick-signup**
**Endpoint:** `POST /functions/v1/quick-signup`

**Payload:**
```json
{
  "name": "João Silva",
  "email": "joao@email.com", 
  "whatsapp": "11999999999",
  "source": "wordpress-plugin",
  "plugin_version": "1.0.0"
}
```

**Response Success:**
```json
{
  "success": true,
  "data": {
    "api_url": "https://evolution.minhaapi.com",
    "api_key": "plugin_abc123def", 
    "instance_name": "plugin_abc123",
    "trial_expires_at": "2025-07-01T00:00:00.000Z",
    "trial_days_left": 7,
    "qr_code_url": "https://evolution.minhaapi.com/instance/connect/plugin_abc123"
  }
}
```

**Response Error:**
```json
{
  "success": false,
  "error": "WhatsApp inválido. Verifique o número e tente novamente."
}
```

#### 🔍 **2. plugin-status**
**Endpoint:** `POST /functions/v1/plugin-status`

**Payload:**
```json
{
  "api_key": "plugin_abc123def"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status": "connecting",
    "trial_expires_at": "2025-07-01T00:00:00.000Z",
    "trial_days_left": 6,
    "user_name": "João Silva",
    "user_plan": "trial",
    "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
    "qr_code_url": "https://evolution.minhaapi.com/instance/connect/plugin_abc123",
    "is_trial_expired": false
  }
}
```

### 🔌 **Integração WordPress (PROJETO 2)**

#### ⚙️ **Configurações de Conexão**
```php
// wp-whatsevolution-main/includes/config.php
define('WHATSEVOLUTION_API_BASE', 'https://ydnobqsepveefiefmxag.supabase.co');
define('WHATSEVOLUTION_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o');
define('WHATSEVOLUTION_TIMEOUT', 45); // segundos
```

## 🔄 **FLUXO COMPLETO COM QR CODE**

### 📱 **FASE 1: Criação de Conta (30s)**
```
Usuário preenche formulário → Plugin chama quick-signup → Edge Function cria conta → 
Edge Function cria instância → Evolution API: CREATE instance → Status: connecting → 
Retorna credenciais + QR URL → Plugin salva configs → Mostra QR Code para usuário
```

### 📱 **FASE 2: Conexão WhatsApp (60s)**
```
Plugin mostra QR Code → Usuário escaneia QR → WhatsApp conecta → 
Evolution API: Status = connected → Plugin polling: plugin-status → 
Detecta connected → Interface atualiza: ✅ Conectado
```

### 📱 **FASE 3: Monitoramento Trial**
```
Plugin verifica status diariamente → plugin-status API → Trial expirado? → 
Se NÃO: Mostra dias restantes → Se SIM: Mostra botão upgrade → 
Link para billing do sistema
```

## 🛠️ **IMPLEMENTAÇÃO PRÁTICA**

### 🔵 **TAREFAS PROJETO 1 (Sistema Principal)**

#### ✅ **Edge Function: quick-signup**
```typescript
// Funcionalidades específicas para QR Code
const createInstanceResponse = await fetch(`${adminConfig.evolution_api_url}/instance/create`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'apikey': adminConfig.evolution_api_key
  },
  body: JSON.stringify({
    instanceName: instanceName,
    token: instanceApiKey,
    qrcode: true, // ← IMPORTANTE: Habilitar QR
    integration: 'WHATSAPP-BAILEYS'
  })
})

// Retornar URL do QR Code também
return {
  success: true,
  data: {
    api_url: adminConfig.evolution_api_url,
    api_key: instanceApiKey,
    instance_name: instanceName,
    trial_expires_at: trialExpiresAt.toISOString(),
    trial_days_left: trialDays,
    qr_code_url: `${adminConfig.evolution_api_url}/instance/connect/${instanceName}` // ← NOVO
  }
}
```

#### ✅ **Edge Function: plugin-status**
```typescript
// Buscar QR Code se necessário
let qr_code = null;
let qr_code_url = null;

if (instance.status === 'connecting' || instance.status === 'disconnected') {
  qr_code_url = `${adminConfig.evolution_api_url}/instance/connect/${instance.evolution_instance_id}`;
  
  try {
    const qrResponse = await fetch(qr_code_url, {
      method: 'GET',
      headers: { 'apikey': adminConfig.evolution_api_key }
    });

    if (qrResponse.ok) {
      const qrData = await qrResponse.json();
      if (qrData.base64) {
        qr_code = qrData.base64; // QR em base64 para mostrar diretamente
      }
    }
  } catch (qrError) {
    console.error('Erro ao buscar QR Code:', qrError);
  }
}

return {
  success: true,
  data: {
    status: instance.status,
    qr_code: qr_code, // ← Para mostrar inline
    qr_code_url: qr_code_url, // ← Para abrir em nova aba
    // ... outros dados
  }
}
```

### 🟡 **TAREFAS PROJETO 2 (Plugin WordPress)**

#### ✅ **Interface QR Code**
```php
// wp-whatsevolution-main/includes/class-qr-display.php
public function render_qr_interface($qr_data) {
    ?>
    <div class="wpwevo-qr-section">
        <h3>📱 Conecte seu WhatsApp</h3>
        
        <?php if ($qr_data['qr_code']): ?>
            <div class="qr-container">
                <img src="<?php echo $qr_data['qr_code']; ?>" 
                     alt="QR Code WhatsApp" 
                     class="qr-image" />
                <p>📲 Abra o WhatsApp no seu celular e escaneie este código</p>
            </div>
        <?php endif; ?>
        
        <div class="qr-actions">
            <button id="refresh-qr" class="wpwevo-btn-secondary">
                🔄 Atualizar QR Code
            </button>
            
            <a href="<?php echo $qr_data['qr_code_url']; ?>" 
               target="_blank" 
               class="wpwevo-btn-link">
                🔗 Abrir QR em nova aba
            </a>
        </div>
        
        <div class="connection-status">
            <span class="status-indicator connecting">
                🟡 Aguardando conexão...
            </span>
        </div>
    </div>
    
    <script>
    // Polling para detectar conexão
    var connectionPolling = setInterval(function() {
        checkConnectionStatus();
    }, 3000); // Verifica a cada 3 segundos
    
    function checkConnectionStatus() {
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'wpwevo_check_plugin_status',
                nonce: wpwevo_vars.nonce
            },
            success: function(response) {
                if (response.success && response.data.status === 'connected') {
                    jQuery('.connection-status').html(`
                        <span class="status-indicator connected">
                            ✅ WhatsApp conectado com sucesso!
                        </span>
                    `);
                    
                    // Parar polling e mostrar próximos passos
                    clearInterval(connectionPolling);
                    showNextSteps();
                }
            }
        });
    }
    </script>
    <?php
}
```

#### ✅ **Fluxo Completo Integrado**
```php
// wp-whatsevolution-main/includes/class-quick-signup.php (ATUALIZADO)

public function handle_signup() {
    // ... validações básicas ...
    
    // Chamada para quick-signup
    $response = wp_remote_post(WHATSEVOLUTION_API_BASE . '/functions/v1/quick-signup', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY
        ],
        'body' => json_encode([
            'name' => $name,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'source' => 'wordpress-plugin',
            'plugin_version' => WPWEVO_VERSION
        ]),
        'timeout' => WHATSEVOLUTION_TIMEOUT
    ]);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Erro de conexão: ' . $response->get_error_message());
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!$data['success']) {
        wp_send_json_error($data['error']);
    }
    
    // Salvar configurações E dados do QR
    update_option('wpwevo_api_url', $data['data']['api_url']);
    update_option('wpwevo_api_key', $data['data']['api_key']);
    update_option('wpwevo_instance', $data['data']['instance_name']);
    update_option('wpwevo_qr_url', $data['data']['qr_code_url']);
    update_option('wpwevo_auto_configured', true);
    update_option('wpwevo_trial_started_at', time());
    
    wp_send_json_success([
        'configured' => true,
        'qr_required' => true,
        'qr_url' => $data['data']['qr_code_url'],
        'trial_days' => $data['data']['trial_days_left']
    ]);
}

public function check_plugin_status() {
    $api_key = get_option('wpwevo_api_key');
    
    $response = wp_remote_post(WHATSEVOLUTION_API_BASE . '/functions/v1/plugin-status', [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . WHATSEVOLUTION_API_KEY
        ],
        'body' => json_encode(['api_key' => $api_key]),
        'timeout' => 15
    ]);
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (!$data['success']) {
        wp_send_json_error($data['error']);
    }
    
    wp_send_json_success($data['data']);
}
```

## 📋 **DOCUMENTAÇÃO CROSS-PROJECT**

### 🔵 **Para o Sistema Principal**
```markdown
# NOVA FEATURE: Plugin WordPress Integration

## Edge Functions para Implementar:
1. quick-signup - Criação automática de contas via plugin
2. plugin-status - Status e QR Code para plugin

## Endpoints Evolution API Utilizados:
- POST /instance/create (com qrcode: true)
- GET /instance/connect/{instance} (para QR Code)
- GET /chat/whatsappNumbers/admin (validação WhatsApp)

## Fluxo de Dados:
Plugin → Edge Function → Evolution API → Database → Response
```

### 🟡 **Para o Plugin WordPress**
```markdown
# NOVA FEATURE: Onboarding 1-Click

## APIs Externas Necessárias:
- Sistema: https://ydnobqsepveefiefmxag.supabase.co
- Endpoints: /functions/v1/quick-signup, /functions/v1/plugin-status
- Auth: Bearer token (anon key)

## Novos Arquivos:
- includes/class-quick-signup.php
- includes/config.php (constantes API)
- assets/css/quick-signup.css
- assets/js/qr-polling.js

## Novas Opções WordPress:
- wpwevo_qr_url, wpwevo_auto_configured, wpwevo_trial_started_at
```

## 🔄 **VERSIONAMENTO E COMPATIBILIDADE**

### 📦 **Plugin WordPress**
```php
// wp-whatsapp-evolution.php
define('WPWEVO_VERSION', '2.0.0'); // ← Incrementar para onboarding
define('WPWEVO_MIN_SYSTEM_VERSION', '1.5.0'); // ← Versão mínima do sistema
```

### 📦 **Sistema Principal**
```typescript
// Verificar versão do plugin nas Edge Functions
if (plugin_version && plugin_version < '2.0.0') {
  return new Response(
    JSON.stringify({ 
      success: false, 
      error: 'Plugin desatualizado. Atualize para versão 2.0.0+' 
    }),
    { status: 400, headers: corsHeaders }
  )
}
```

## 🧪 **TESTES CROSS-PROJECT**

### 🔬 **Teste Isolado da API**
```bash
# Testar quick-signup
curl -X POST https://ydnobqsepveefiefmxag.supabase.co/functions/v1/quick-signup \
  -H "Authorization: Bearer eyJhbGciOiJIUzI..." \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Teste Plugin",
    "email": "teste.plugin@email.com",
    "whatsapp": "11999999999",
    "source": "wordpress-plugin",
    "plugin_version": "2.0.0"
  }'

# Resposta esperada:
# {
#   "success": true,
#   "data": {
#     "api_url": "https://...",
#     "api_key": "plugin_...",
#     "instance_name": "plugin_...",
#     "qr_code_url": "https://.../instance/connect/plugin_...",
#     "trial_expires_at": "...",
#     "trial_days_left": 7
#   }
# }
```

### 🔬 **Teste Integração WordPress**
```php
// wp-admin/admin-ajax.php?action=wpwevo_test_integration
public function test_integration() {
    $test_data = [
        'name' => 'Teste Integração',
        'email' => 'teste.integracao@email.com',
        'whatsapp' => '11999999999'
    ];
    
    // Simular chamada
    $result = $this->call_quick_signup($test_data);
    
    wp_send_json([
        'integration_working' => $result['success'],
        'api_response_time' => $result['response_time'],
        'error' => $result['error'] ?? null
    ]);
}
```

## 🚨 **PONTOS DE ATENÇÃO**

### ⚠️ **Timeout e Fallbacks**
- **Quick-signup:** Pode demorar até 45s (criação completa)
- **Plugin-status:** Máximo 15s (verificação rápida)
- **QR Polling:** A cada 3s até conectar

### ⚠️ **Tratamento de Erros**
- **WhatsApp inválido:** Mostrar campo em vermelho
- **Email duplicado:** Sugerir recuperação de senha
- **API indisponível:** Mostrar opção de configuração manual

### ⚠️ **Segurança**
- **Anon Key:** Apenas no frontend (segura)
- **Service Role:** Apenas nas Edge Functions
- **API Keys individuais:** Uma por instância/usuário

---

## 🎯 **RESULTADO FINAL**

Com esta documentação, **ambos os projetos** podem ser desenvolvidos **independentemente** mas funcionarão perfeitamente **integrados**:

- **Sistema Principal:** Sabe exatamente quais Edge Functions criar
- **Plugin WordPress:** Sabe exatamente como integrar
- **QR Code:** Fluxo completo documentado
- **Cross-compatibility:** Versionamento e testes cobertos

**🚀 O onboarding 1-click funcionará perfeitamente entre os dois projetos separados!** 