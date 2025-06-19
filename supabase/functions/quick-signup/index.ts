import { serve } from "https://deno.land/std@0.168.0/http/server.ts"
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
}

interface SignupRequest {
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
    const { name, email, whatsapp, source, plugin_version }: SignupRequest = await req.json()

    // Validações básicas
    if (!name || !email || !whatsapp) {
      return new Response(
        JSON.stringify({ success: false, error: 'Dados obrigatórios não fornecidos' }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 400 }
      )
    }

    // Valida email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(email)) {
      return new Response(
        JSON.stringify({ success: false, error: 'Email inválido' }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 400 }
      )
    }

    // Valida WhatsApp (pelo menos 10 dígitos)
    const cleanWhatsapp = whatsapp.replace(/\D/g, '')
    if (cleanWhatsapp.length < 10) {
      return new Response(
        JSON.stringify({ success: false, error: 'WhatsApp deve ter pelo menos 10 dígitos' }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 400 }
      )
    }

    // Cria cliente Supabase com service_role para acesso total
    const supabase = createClient(
      Deno.env.get('SUPABASE_URL') ?? '',
      Deno.env.get('SUPABASE_SERVICE_ROLE_KEY') ?? ''
    )

    // Busca configuração global da Evolution API
    const { data: adminConfig } = await supabase
      .from('admin_config')
      .select('evolution_api_url, evolution_api_key')
      .single()

    if (!adminConfig) {
      return new Response(
        JSON.stringify({ success: false, error: 'Configuração da Evolution API não encontrada' }),
        { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 500 }
      )
    }

    // 1. Valida WhatsApp via Evolution API
    try {
      const validateResponse = await fetch(`${adminConfig.evolution_api_url}/validate-whatsapp`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'apikey': adminConfig.evolution_api_key
        },
        body: JSON.stringify({ numbers: [cleanWhatsapp] })
      })
      
      if (!validateResponse.ok) {
        console.log('Validação WhatsApp falhou, mas continuando...')
      }
    } catch (error) {
      console.log('Erro na validação WhatsApp:', error)
      // Continua mesmo se a validação falhar
    }

    // 2. Verifica se usuário já existe
    const { data: existingUser } = await supabase.auth.admin.getUserByEmail(email)
    
    let userId: string
    
    if (existingUser.user) {
      userId = existingUser.user.id
      
      // Verifica se já tem trial ativo
      const { data: existingProfile } = await supabase
        .from('profiles')
        .select('plan, trial_expires_at')
        .eq('id', userId)
        .single()
        
      if (existingProfile && existingProfile.plan === 'trial') {
        const trialExpires = new Date(existingProfile.trial_expires_at)
        if (trialExpires > new Date()) {
          return new Response(
            JSON.stringify({ success: false, error: 'Você já possui um trial ativo' }),
            { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 400 }
          )
        }
      }
    } else {
      // 3. Cria usuário no Supabase Auth
      const { data: newUser, error: authError } = await supabase.auth.admin.createUser({
        email: email,
        password: Math.random().toString(36).slice(-12), // Password temporário
        email_confirm: true,
        user_metadata: { name, whatsapp: cleanWhatsapp, source, plugin_version }
      })

      if (authError || !newUser.user) {
        return new Response(
          JSON.stringify({ success: false, error: 'Erro ao criar usuário: ' + authError?.message }),
          { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 500 }
        )
      }

      userId = newUser.user.id
    }

    // 4. Cria/atualiza perfil na tabela profiles
    const trialExpiresAt = new Date()
    trialExpiresAt.setDate(trialExpiresAt.getDate() + 7)

    await supabase
      .from('profiles')
      .upsert({
        id: userId,
        name,
        email,
        whatsapp: cleanWhatsapp,
        role: 'customer',
        plan: 'trial',
        trial_expires_at: trialExpiresAt.toISOString(),
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      })

    // 5. Gera nomes únicos para a instância
    const instanceName = `plugin_${userId.slice(0, 8)}_${Date.now()}`
    const apiKey = `plugin_${Math.random().toString(36).slice(2, 15)}`

    // 6. Cria instância na Evolution API
    let qrCodeUrl = ''
    try {
      const createInstanceResponse = await fetch(`${adminConfig.evolution_api_url}/instance/create`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'apikey': adminConfig.evolution_api_key
        },
        body: JSON.stringify({
          instanceName,
          token: apiKey,
          qrcode: true,
          integration: 'WHATSAPP-BAILEYS'
        })
      })

      if (createInstanceResponse.ok) {
        // Tenta buscar o QR Code
        const qrResponse = await fetch(`${adminConfig.evolution_api_url}/instance/connect/${instanceName}`, {
          headers: { 'apikey': apiKey }
        })
        
        if (qrResponse.ok) {
          const qrData = await qrResponse.json()
          qrCodeUrl = qrData.qrcode || `${adminConfig.evolution_api_url}/instance/connect/${instanceName}`
        }
      }
    } catch (error) {
      console.log('Erro ao criar instância na Evolution API:', error)
      // Continua mesmo se falhar
    }

    // 7. Salva instância no banco
    await supabase
      .from('instances')
      .insert({
        user_id: userId,
        name: instanceName,
        api_key: apiKey,
        status: 'created',
        trial_expires_at: trialExpiresAt.toISOString(),
        created_at: new Date().toISOString()
      })

    // 8. Retorna dados para o plugin
    const responseData = {
      api_url: adminConfig.evolution_api_url,
      api_key: apiKey,
      instance_name: instanceName,
      trial_expires_at: trialExpiresAt.toISOString(),
      trial_days_left: 7,
      qr_code_url: qrCodeUrl || `${adminConfig.evolution_api_url}/instance/connect/${instanceName}`
    }

    return new Response(
      JSON.stringify({ success: true, data: responseData }),
      { headers: { ...corsHeaders, 'Content-Type': 'application/json' } }
    )

  } catch (error) {
    console.error('Erro na edge function quick-signup:', error)
    return new Response(
      JSON.stringify({ success: false, error: 'Erro interno do servidor' }),
      { headers: { ...corsHeaders, 'Content-Type': 'application/json' }, status: 500 }
    )
  }
}) 