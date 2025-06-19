# 🛠️ GUIA COMPLETO DE IMPLEMENTAÇÃO - ONBOARDING 1-CLICK

## 📋 **INSTRUÇÕES PARA A PRÓXIMA IA**

### 🎯 **CONTEXTO DO PROJETO**
Este é um plugin WordPress que integra WooCommerce com Evolution API. O objetivo é implementar um sistema de **onboarding 1-click** que permite ao usuário testar o serviço diretamente no plugin, sem sair da interface do WordPress.

### 🔗 **SISTEMA PRINCIPAL CONECTADO**
- **Supabase Project:** ydnobqsepveefiefmxag
- **URL:** https://ydnobqsepveefiefmxag.supabase.co
- **Sistema:** WhatsApp Evolution com tolerância inteligente
- **Anon Key:** eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o

## 🚀 **IMPLEMENTAÇÃO STEP-BY-STEP**

### 📡 **PASSO 1: CRIAR EDGE FUNCTIONS NO SISTEMA PRINCIPAL**

#### 🆕 **Edge Function: `quick-signup`**

**Localização:** `supabase/functions/quick-signup/index.ts`

```typescript
import "jsr:@supabase/functions-js/edge-runtime.d.ts"

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

interface QuickSignupRequest {
  name: string;
  email: string;
  whatsapp: string;
  source: string;
  plugin_version?: string;
}

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders })
  }

  try {
    const supabaseClient = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? '',
    )

    const { name, email, whatsapp, source, plugin_version }: QuickSignupRequest = await req.json()

    // Validar dados obrigatórios
    if (!name || !email || !whatsapp) {
      return new Response(
        JSON.stringify({ success: false, error: 'Dados obrigatórios faltando' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Verificar se email já existe
    const { data: existingAuth } = await supabaseClient.auth.admin.getUserByEmail(email)
    if (existingAuth.user) {
      return new Response(
        JSON.stringify({ success: false, error: 'Email já cadastrado. Use outro email ou faça login.' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Validar WhatsApp via Evolution API
    const { data: adminConfig } = await supabaseClient
      .from('admin_config')
      .select('evolution_api_url, evolution_api_key')
      .limit(1)
      .single()

    if (!adminConfig?.evolution_api_url || !adminConfig?.evolution_api_key) {
      return new Response(
        JSON.stringify({ success: false, error: 'Configuração do sistema indisponível' }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Validar WhatsApp
    const whatsappValidation = await fetch(`${adminConfig.evolution_api_url}/chat/whatsappNumbers/admin`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'apikey': adminConfig.evolution_api_key
      },
      body: JSON.stringify({
        numbers: [whatsapp]
      })
    })

    if (!whatsappValidation.ok) {
      return new Response(
        JSON.stringify({ success: false, error: 'Erro ao validar WhatsApp. Tente novamente.' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    const whatsappResult = await whatsappValidation.json()
    if (!whatsappResult || whatsappResult.length === 0 || !whatsappResult[0]?.exists) {
      return new Response(
        JSON.stringify({ success: false, error: 'WhatsApp inválido. Verifique o número e tente novamente.' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Criar usuário no Supabase Auth
    const { data: authUser, error: authError } = await supabaseClient.auth.admin.createUser({
      email: email,
      password: Math.random().toString(36).slice(-12), // Senha temporária
      email_confirm: true,
      user_metadata: {
        name: name,
        whatsapp: whatsapp,
        source: source,
        plugin_version: plugin_version
      }
    })

    if (authError || !authUser.user) {
      return new Response(
        JSON.stringify({ success: false, error: 'Erro ao criar conta. Tente novamente.' }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Criar perfil
    const { error: profileError } = await supabaseClient
      .from('profiles')
      .insert({
        id: authUser.user.id,
        name: name,
        email: email,
        whatsapp: whatsapp,
        role: 'user',
        plan: 'trial'
      })

    if (profileError) {
      // Cleanup: deletar usuário se falhou criar perfil
      await supabaseClient.auth.admin.deleteUser(authUser.user.id)
      return new Response(
        JSON.stringify({ success: false, error: 'Erro ao configurar perfil. Tente novamente.' }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Criar instância
    const trialDays = 7
    const trialExpiresAt = new Date()
    trialExpiresAt.setDate(trialExpiresAt.getDate() + trialDays)

    const instanceName = `plugin_${authUser.user.id.slice(0, 8)}`
    const instanceApiKey = `plugin_${Math.random().toString(36).slice(2, 15)}`

    const { data: instance, error: instanceError } = await supabaseClient
      .from('instances')
      .insert({
        user_id: authUser.user.id,
        name: instanceName,
        status: 'disconnected',
        api_key: instanceApiKey,
        trial_expires_at: trialExpiresAt.toISOString(),
        evolution_instance_id: instanceName
      })
      .select()
      .single()

    if (instanceError) {
      // Cleanup: deletar usuário e perfil
      await supabaseClient.auth.admin.deleteUser(authUser.user.id)
      return new Response(
        JSON.stringify({ success: false, error: 'Erro ao criar instância. Tente novamente.' }),
        { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Criar instância na Evolution API
    const createInstanceResponse = await fetch(`${adminConfig.evolution_api_url}/instance/create`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'apikey': adminConfig.evolution_api_key
      },
      body: JSON.stringify({
        instanceName: instanceName,
        token: instanceApiKey,
        qrcode: true,
        integration: 'WHATSAPP-BAILEYS'
      })
    })

    if (!createInstanceResponse.ok) {
      console.error('Erro ao criar instância na Evolution API')
      // Continuar mesmo com erro - instância pode ser criada manualmente
    }

    // Criar notificação de boas-vindas
    await supabaseClient
      .from('notifications')
      .insert({
        user_id: authUser.user.id,
        type: 'info',
        title: 'Bem-vindo ao WhatsEvolution!',
        message: `Sua conta foi criada com sucesso! Você tem ${trialDays} dias de trial gratuito. Configure seu WhatsApp na página de instâncias.`,
        data: {
          created_via: 'plugin',
          trial_days: trialDays,
          expires_at: trialExpiresAt.toISOString()
        },
        read: false
      })

    // Enviar WhatsApp de boas-vindas
    try {
      await supabaseClient.functions.invoke('send-whatsapp-notification', {
        body: {
          to: whatsapp,
          template: 'custom',
          data: {
            message: `🎉 Olá ${name}!\n\nSua conta WhatsEvolution foi criada com sucesso!\n\n✅ ${trialDays} dias de trial gratuito\n✅ Configuração automática no plugin\n✅ Suporte incluído\n\nAcesse o plugin e conecte seu WhatsApp para começar a testar! 🚀`
          }
        }
      })
    } catch (whatsappError) {
      console.error('Erro ao enviar WhatsApp de boas-vindas:', whatsappError)
      // Não falha por conta do WhatsApp
    }

         // Retornar credenciais para o plugin
     return new Response(
       JSON.stringify({
         success: true,
         data: {
           api_url: adminConfig.evolution_api_url,
           api_key: instanceApiKey,
           instance_name: instanceName,
           trial_expires_at: trialExpiresAt.toISOString(),
           trial_days_left: trialDays,
           qr_code_url: `${adminConfig.evolution_api_url}/instance/connect/${instanceName}`
         }
       }),
       { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
     )

  } catch (error) {
    console.error('Erro na criação rápida:', error)
    return new Response(
      JSON.stringify({ 
        success: false, 
        error: 'Erro interno do servidor. Tente novamente.' 
      }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }
})
```

#### 🔍 **Edge Function: `plugin-status`**

**Localização:** `supabase/functions/plugin-status/index.ts`

```typescript
import "jsr:@supabase/functions-js/edge-runtime.d.ts"

import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

serve(async (req) => {
  if (req.method === 'OPTIONS') {
    return new Response('ok', { headers: corsHeaders })
  }

  try {
    const supabaseClient = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? '',
    )

    const { api_key } = await req.json()

    if (!api_key) {
      return new Response(
        JSON.stringify({ success: false, error: 'API Key obrigatória' }),
        { status: 400, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Buscar instância pela API key
    const { data: instance, error: instanceError } = await supabaseClient
      .from('instances')
      .select(`
        *,
        profiles!inner(name, email, plan)
      `)
      .eq('api_key', api_key)
      .single()

    if (instanceError || !instance) {
      return new Response(
        JSON.stringify({ success: false, error: 'Instância não encontrada' }),
        { status: 404, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
      )
    }

    // Calcular dias restantes do trial
    const trialExpiresAt = new Date(instance.trial_expires_at)
    const now = new Date()
    const diffTime = trialExpiresAt.getTime() - now.getTime()
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
    const trialDaysLeft = Math.max(0, diffDays)

    // Buscar QR Code se status for connecting/disconnected
    let qr_code = null
    if (instance.status === 'connecting' || instance.status === 'disconnected') {
      const { data: adminConfig } = await supabaseClient
        .from('admin_config')
        .select('evolution_api_url, evolution_api_key')
        .limit(1)
        .single()

      if (adminConfig?.evolution_api_url && adminConfig?.evolution_api_key) {
        try {
          const qrResponse = await fetch(`${adminConfig.evolution_api_url}/instance/connect/${instance.evolution_instance_id}`, {
            method: 'GET',
            headers: {
              'apikey': adminConfig.evolution_api_key
            }
          })

          if (qrResponse.ok) {
            const qrData = await qrResponse.json()
            if (qrData.base64) {
              qr_code = qrData.base64
            }
          }
        } catch (qrError) {
          console.error('Erro ao buscar QR Code:', qrError)
        }
      }
    }

    return new Response(
      JSON.stringify({
        success: true,
        data: {
          status: instance.status,
          trial_expires_at: instance.trial_expires_at,
          trial_days_left: trialDaysLeft,
          user_name: instance.profiles.name,
          user_plan: instance.profiles.plan,
          qr_code: qr_code,
          is_trial_expired: trialDaysLeft <= 0 && instance.profiles.plan === 'trial'
        }
      }),
      { status: 200, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )

  } catch (error) {
    console.error('Erro na verificação de status:', error)
    return new Response(
      JSON.stringify({ 
        success: false, 
        error: 'Erro interno do servidor' 
      }),
      { status: 500, headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )
  }
})
```

### 🎨 **PASSO 2: MODIFICAÇÕES NO PLUGIN WORDPRESS**

#### 📁 **Arquivo: `includes/class-quick-signup.php`**

```php
<?php
namespace WpWhatsAppEvolution;

class Quick_Signup {
    private static $instance = null;

    public static function init() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wpwevo_quick_signup', [$this, 'handle_signup']);
        add_action('wp_ajax_wpwevo_save_quick_config', [$this, 'save_quick_config']);
        add_action('wp_ajax_wpwevo_check_plugin_status', [$this, 'check_plugin_status']);
    }

    public function handle_signup() {
        check_ajax_referer('wpwevo_quick_signup', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $whatsapp = sanitize_text_field($_POST['whatsapp']);
        
        if (empty($name) || empty($email) || empty($whatsapp)) {
            wp_send_json_error('Todos os campos são obrigatórios.');
        }

        if (!is_email($email)) {
            wp_send_json_error('Email inválido.');
        }

        // Chamada para a API
        $response = wp_remote_post('https://ydnobqsepveefiefmxag.supabase.co/functions/v1/quick-signup', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o'
            ],
            'body' => json_encode([
                'name' => $name,
                'email' => $email,
                'whatsapp' => $whatsapp,
                'source' => 'wordpress-plugin',
                'plugin_version' => WPWEVO_VERSION
            ]),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Erro de conexão: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || !$data['success']) {
            wp_send_json_error($data['error'] ?? 'Erro desconhecido');
        }

        wp_send_json_success($data['data']);
    }

    public function save_quick_config() {
        check_ajax_referer('wpwevo_save_quick_config', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada.');
        }

        $api_url = sanitize_url($_POST['api_url']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $instance = sanitize_text_field($_POST['instance']);

        // Salvar opções
        update_option('wpwevo_api_url', $api_url);
        update_option('wpwevo_api_key', $api_key);
        update_option('wpwevo_instance', $instance);
        
        // Marcar como configuração automática
        update_option('wpwevo_auto_configured', true);
        update_option('wpwevo_trial_started_at', time());

        wp_send_json_success('Configuração salva com sucesso!');
    }

    public function check_plugin_status() {
        check_ajax_referer('wpwevo_plugin_status', 'nonce');
        
        $api_key = get_option('wpwevo_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('Plugin não configurado');
        }

        $response = wp_remote_post('https://ydnobqsepveefiefmxag.supabase.co/functions/v1/plugin-status', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o'
            ],
            'body' => json_encode([
                'api_key' => $api_key
            ]),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Erro de conexão');
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || !$data['success']) {
            wp_send_json_error($data['error'] ?? 'Erro desconhecido');
        }

        wp_send_json_success($data['data']);
    }
}
```

#### 🔧 **Modificar: `includes/class-settings-page.php`**

```php
// Adicionar após os includes existentes:
public function render_tabs() {
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'connection';
    ?>
    <h2 class="nav-tab-wrapper">
        <a href="?page=wp-whatsapp-evolution&tab=connection" class="nav-tab <?php echo $active_tab == 'connection' ? 'nav-tab-active' : ''; ?>">
            Conexão
        </a>
        <a href="?page=wp-whatsapp-evolution&tab=quick-signup" class="nav-tab <?php echo $active_tab == 'quick-signup' ? 'nav-tab-active' : ''; ?>">
            🚀 Teste Grátis
        </a>
        <a href="?page=wp-whatsapp-evolution&tab=status-messages" class="nav-tab <?php echo $active_tab == 'status-messages' ? 'nav-tab-active' : ''; ?>">
            Mensagens por Status
        </a>
        <!-- Outras abas existentes -->
    </h2>
    <?php
}

public function render_quick_signup_tab() {
    $auto_configured = get_option('wpwevo_auto_configured', false);
    
    if ($auto_configured) {
        $this->render_trial_status();
        return;
    }
    ?>
    <div class="wpwevo-hero-section">
        <h2>🚀 Teste Grátis por 7 Dias</h2>
        <p>Não tem Evolution API? Sem problema! Teste nossa solução completa:</p>
        
        <div class="wpwevo-benefits">
            <ul>
                <li>✅ Sem VPS, sem Docker, sem complicação</li>
                <li>✅ Configuração automática em 30 segundos</li>
                <li>✅ Suporte técnico incluído</li>
                <li>✅ 7 dias grátis, sem cartão de crédito</li>
            </ul>
        </div>

        <form id="wpwevo-quick-signup" class="wpwevo-signup-form">
            <?php wp_nonce_field('wpwevo_quick_signup', 'wpwevo_nonce'); ?>
            
            <div class="form-row">
                <label>Nome Completo:</label>
                <input type="text" name="name" required />
            </div>
            
            <div class="form-row">
                <label>Email:</label>
                <input type="email" name="email" required />
            </div>
            
            <div class="form-row">
                <label>WhatsApp (com DDD):</label>
                <input type="tel" name="whatsapp" placeholder="11999999999" required />
                <small>Será validado automaticamente</small>
            </div>

            <button type="submit" class="wpwevo-btn-primary">
                🚀 Criar Conta e Testar Agora
            </button>
        </form>

        <div id="wpwevo-signup-progress" style="display: none;">
            <div class="progress-steps">
                <div class="step active" data-step="1">Validando dados...</div>
                <div class="step" data-step="2">Criando conta...</div>
                <div class="step" data-step="3">Configurando plugin...</div>
                <div class="step" data-step="4">Pronto! ✅</div>
            </div>
        </div>

        <div id="wpwevo-signup-result" style="display: none;"></div>
    </div>
    
    <style>
        .wpwevo-hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .wpwevo-benefits ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .wpwevo-benefits li {
            padding: 8px 0;
            font-size: 16px;
        }
        
        .wpwevo-signup-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 20px auto;
        }
        
        .form-row {
            margin-bottom: 20px;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-row input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .form-row input:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .wpwevo-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .wpwevo-btn-primary:hover {
            transform: translateY(-2px);
        }
        
        .wpwevo-btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        
        .step {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: #f0f0f0;
            margin: 0 5px;
            border-radius: 6px;
            transition: all 0.3s;
            color: #333;
            font-size: 14px;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step.error {
            background: #dc3545;
            color: white;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#wpwevo-quick-signup').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $progress = $('#wpwevo-signup-progress');
            const $result = $('#wpwevo-signup-result');
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Validações básicas
            const name = $form.find('input[name="name"]').val().trim();
            const email = $form.find('input[name="email"]').val().trim();
            const whatsapp = $form.find('input[name="whatsapp"]').val().trim();
            
            if (!name || !email || !whatsapp) {
                alert('Todos os campos são obrigatórios.');
                return;
            }
            
            if (whatsapp.length < 10) {
                alert('WhatsApp deve ter pelo menos 10 dígitos.');
                return;
            }
            
            // Mostrar progresso
            $form.hide();
            $progress.show();
            $result.hide();
            
            // Step 1: Validando
            updateStep(1, 'Validando dados...');
            
            // Chamada AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpwevo_quick_signup',
                    nonce: $form.find('input[name="wpwevo_nonce"]').val(),
                    name: name,
                    email: email,
                    whatsapp: whatsapp
                },
                timeout: 45000,
                success: function(response) {
                    if (response.success) {
                        // Step 2: Sucesso na criação
                        updateStep(2, 'Conta criada!', 'completed');
                        
                        // Step 3: Configurando plugin
                        updateStep(3, 'Configurando plugin...');
                        
                        // Salvar configurações
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'wpwevo_save_quick_config',
                                nonce: '<?php echo wp_create_nonce('wpwevo_save_quick_config'); ?>',
                                api_url: response.data.api_url,
                                api_key: response.data.api_key,
                                instance: response.data.instance_name
                            },
                            success: function(configResponse) {
                                if (configResponse.success) {
                                    // Step 4: Finalizado
                                                                             updateStep(4, 'Configuração completa! ✅', 'completed');
                                         
                                         setTimeout(function() {
                                             $result.html(`
                                                 <div class="success-message">
                                                     <h3>🎉 Parabéns! Sua conta foi criada com sucesso!</h3>
                                                     <p><strong>Nome:</strong> ${name}</p>
                                                     <p><strong>Email:</strong> ${email}</p>
                                                     <p><strong>Trial:</strong> 7 dias grátis</p>
                                                     <p><strong>Próximo passo:</strong> Conecte seu WhatsApp escaneando o QR Code!</p>
                                                     <div class="qr-code-container" style="margin: 20px 0; text-align: center;">
                                                         <iframe src="${response.data.qr_url}" 
                                                                 width="300" 
                                                                 height="300" 
                                                                 frameborder="0"
                                                                 style="border-radius: 8px;">
                                                         </iframe>
                                                         <p>📲 Abra o WhatsApp e escaneie o código acima</p>
                                                     </div>
                                                     <button onclick="checkConnectionStatus()" class="wpwevo-btn-secondary">
                                                         🔄 Verificar Conexão
                                                     </button>
                                                 </div>
                                             `).show();
                                             
                                             // Iniciar polling para detectar conexão
                                             startConnectionPolling();
                                         }, 1500);
                                } else {
                                    showError('Erro ao salvar configurações: ' + configResponse.data);
                                }
                            },
                            error: function() {
                                showError('Erro ao configurar plugin.');
                            }
                        });
                    } else {
                        showError(response.data || 'Erro desconhecido');
                    }
                },
                error: function(xhr, status, error) {
                    if (status === 'timeout') {
                        showError('Timeout - tente novamente.');
                    } else {
                        showError('Erro de conexão: ' + error);
                    }
                }
            });
            
            function updateStep(stepNumber, text, status = 'active') {
                const $step = $(`.step[data-step="${stepNumber}"]`);
                $step.text(text);
                
                if (status === 'completed') {
                    $step.removeClass('active').addClass('completed');
                    const $nextStep = $(`.step[data-step="${stepNumber + 1}"]`);
                    if ($nextStep.length) {
                        $nextStep.addClass('active');
                    }
                } else if (status === 'error') {
                    $step.removeClass('active').addClass('error');
                }
            }
            
            function showError(message) {
                $progress.hide();
                $result.html(`
                    <div class="error-message">
                        <h3>❌ Erro na criação da conta</h3>
                        <p>${message}</p>
                        <button onclick="location.reload()" class="wpwevo-btn-primary">
                            Tentar Novamente
                        </button>
                    </div>
                `).show();
            }
        });
    });
    </script>
    <?php
}

private function render_trial_status() {
    $trial_started = get_option('wpwevo_trial_started_at', 0);
    $trial_days = 7;
    $days_passed = floor((time() - $trial_started) / (24 * 60 * 60));
    $days_left = max(0, $trial_days - $days_passed);
    
    ?>
    <div class="wpwevo-trial-dashboard">
        <h2>🎉 Sua conta de teste está ativa!</h2>
        
        <div class="trial-info <?php echo $days_left <= 1 ? 'trial-urgent' : 'trial-active'; ?>">
            <div class="trial-status">
                <?php if ($days_left > 0): ?>
                    <span class="trial-icon">⏰</span>
                    <div>
                        <h3>Trial expira em <?php echo $days_left; ?> dias</h3>
                        <p>Aproveite para testar todas as funcionalidades!</p>
                    </div>
                <?php else: ?>
                    <span class="trial-icon">🚨</span>
                    <div>
                        <h3>Trial expirado!</h3>
                        <p>Faça upgrade para continuar usando o serviço.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="trial-actions">
                <a href="https://whats-evolution-58of8w2cq-ronald-melos-projects.vercel.app/billing" 
                   target="_blank" 
                   class="upgrade-btn <?php echo $days_left <= 1 ? 'urgent' : ''; ?>">
                    <?php echo $days_left > 0 ? 'Fazer Upgrade' : 'Renovar Agora'; ?>
                </a>
            </div>
        </div>

        <div class="next-steps">
            <h3>📋 Próximos passos:</h3>
            <ol>
                <li>✅ Conta criada e plugin configurado</li>
                <li>🔗 <a href="?page=wp-whatsapp-evolution&tab=connection">Conectar seu WhatsApp</a></li>
                <li>📱 <a href="?page=wp-whatsapp-evolution&tab=send-single">Testar envio de mensagem</a></li>
                <li>🛒 <a href="?page=wp-whatsapp-evolution&tab=cart-abandonment">Configurar carrinho abandonado</a></li>
            </ol>
        </div>
    </div>
    
    <style>
        .wpwevo-trial-dashboard {
            padding: 20px;
        }
        
        .trial-info {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .trial-info.trial-urgent {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .trial-status {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .trial-icon {
            font-size: 2em;
        }
        
        .upgrade-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .upgrade-btn.urgent {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            animation: pulse 2s infinite;
        }
        
        .upgrade-btn:hover {
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        
        .next-steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .next-steps ol {
            margin: 15px 0;
        }
        
        .next-steps li {
            padding: 5px 0;
        }
        
        .next-steps a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        
        .next-steps a:hover {
            text-decoration: underline;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
    </style>
    <?php
}
```

#### 🔧 **Modificar: `includes/class-plugin-loader.php`**

```php
// Adicionar na função init_modules():
public function init_modules() {
    Settings_Page::init();
    Send_Single::init();
    Send_By_Status::init();
    Cart_Abandonment::init();
    Bulk_Sender::init();
    Checkout_Validator::init();
    Quick_Signup::init(); // ← Nova linha
}
```

#### 📁 **Arquivo: `assets/js/quick-signup.js`**

```javascript
// Este arquivo será carregado automaticamente pelo WordPress
// Todo o JavaScript necessário já está inline no PHP acima
// para facilitar a implementação
```

## 📋 **CHECKLIST DE IMPLEMENTAÇÃO**

### ✅ **Backend (Sistema Principal)**
1. [ ] Criar Edge Function `quick-signup`
2. [ ] Criar Edge Function `plugin-status`  
3. [ ] Testar Edge Functions isoladamente
4. [ ] Deploy das Edge Functions
5. [ ] Validar integração com sistema existente

### ✅ **Frontend (Plugin WordPress)**
1. [ ] Criar arquivo `includes/class-quick-signup.php`
2. [ ] Modificar `includes/class-settings-page.php`
3. [ ] Modificar `includes/class-plugin-loader.php`
4. [ ] Adicionar nova aba "Teste Grátis"
5. [ ] Implementar formulário e JavaScript
6. [ ] Testar fluxo completo local
7. [ ] Testar integração com APIs

### ✅ **Testes e Validação**
1. [ ] Testar criação de conta completa
2. [ ] Testar validação de WhatsApp
3. [ ] Testar configuração automática
4. [ ] Testar interface de trial
5. [ ] Testar links para upgrade
6. [ ] Validar UX geral

## 🎯 **RESULTADO ESPERADO**

Após a implementação, o usuário poderá:

1. **Acessar a aba "🚀 Teste Grátis"** no plugin
2. **Preencher formulário simples** (nome, email, WhatsApp)
3. **Criar conta automaticamente** no sistema principal
4. **Plugin configurado automaticamente** com credenciais
5. **Receber WhatsApp de boas-vindas**
6. **Ver status do trial** em tempo real
7. **Link direto para upgrade** quando necessário

## 📞 **SUPORTE PÓS-IMPLEMENTAÇÃO**

### 🔧 **Comandos de Debug**

```bash
# Testar Edge Functions
curl -X POST https://ydnobqsepveefiefmxag.supabase.co/functions/v1/quick-signup \
  -H "Authorization: Bearer eyJhb..." \
  -H "Content-Type: application/json" \
  -d '{"name":"Teste","email":"teste@teste.com","whatsapp":"11999999999","source":"test"}'

# Verificar logs
# Via Dashboard Supabase > Edge Functions > Logs
```

### 📊 **Monitoramento**

- **Taxa de conversão** signup por visitante
- **Erros** nas Edge Functions  
- **Tempo de resposta** do fluxo completo
- **Feedback** dos usuários beta

---

**Esta implementação criará uma experiência de onboarding revolucionária que aumentará drasticamente a conversão do plugin! 🚀** 