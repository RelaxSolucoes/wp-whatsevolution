# 🤖 INSTRUÇÕES ESPECÍFICAS PARA A PRÓXIMA IA

## 🎯 **CONTEXTO RÁPIDO**

Você está trabalhando em um **plugin WordPress** que integra WooCommerce com WhatsApp via Evolution API. O objetivo é implementar um **sistema de onboarding 1-click** que permite usuários testarem o serviço gratuitamente por 7 dias sem sair do plugin.

## 🔗 **SISTEMA CONECTADO**

- **Supabase:** https://ydnobqsepveefiefmxag.supabase.co
- **Anon Key:** eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inlkbm9icXNlcHZlZWZpZWZteGFnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDk2NDkwOTAsImV4cCI6MjA2NTIyNTA5MH0.PlLrBA3eauvanWT-gQoKdvpTaPRrwgtuW8gZhbrlO7o
- **Sistema:** WhatsApp Evolution com tolerância inteligente (já funcionando)

## 🚀 **OBJETIVO DA IMPLEMENTAÇÃO**

Criar fluxo onde usuário:
1. Acessa aba "🚀 Teste Grátis" no plugin
2. Preenche formulário (nome, email, WhatsApp)
3. Sistema cria conta automaticamente
4. Plugin se configura sozinho
5. **QR Code é mostrado automaticamente**
6. Usuário escaneia QR e conecta WhatsApp
7. Plugin detecta conexão e libera funcionalidades

## 📁 **ARQUIVOS PRINCIPAIS DO PLUGIN**

### 🔧 **Estrutura Atual:**
```
wp-whatsevolution-main/
├── wp-whatsapp-evolution.php (arquivo principal)
├── includes/
│   ├── class-plugin-loader.php (carrega módulos)
│   ├── class-settings-page.php (interface admin)
│   ├── class-api-connection.php (conexão Evolution API)
│   └── class-autoloader.php (autoload classes)
├── assets/ (CSS/JS)
└── docs/ (documentação)
```

### 🎯 **O QUE PRECISA SER CRIADO:**

#### 1️⃣ **EDGE FUNCTIONS NO SUPABASE** (Sistema Principal)
- `quick-signup` - Cria conta e instância automaticamente
- `plugin-status` - Verifica status da instância criada pelo plugin

#### 2️⃣ **ARQUIVOS NO PLUGIN WORDPRESS**
- `includes/class-quick-signup.php` - Classe para o onboarding
- Modificar `includes/class-settings-page.php` - Adicionar nova aba
- Modificar `includes/class-plugin-loader.php` - Carregar nova classe

## 🛠️ **IMPLEMENTAÇÃO PRÁTICA**

### ⚡ **PASSO 1: CRIAR EDGE FUNCTIONS**

#### 📡 **Edge Function: quick-signup**
**Localização:** `supabase/functions/quick-signup/index.ts`

**O que faz:**
1. Recebe dados do formulário (nome, email, whatsapp)
2. Valida WhatsApp via Evolution API
3. Cria usuário no Supabase Auth
4. Cria perfil na tabela profiles
5. Cria instância na tabela instances
6. Retorna credenciais para o plugin

**Resposta esperada:**
```json
{
  "success": true,
  "data": {
    "api_url": "https://evolution-api.com",
    "api_key": "plugin_xyz123",
    "instance_name": "plugin_abc123",
    "trial_expires_at": "2025-07-01T00:00:00.000Z",
    "trial_days_left": 7,
    "qr_code_url": "https://evolution-api.com/instance/connect/plugin_abc123"
  }
}
```

#### 🔍 **Edge Function: plugin-status**
**Localização:** `supabase/functions/plugin-status/index.ts`

**O que faz:**
1. Recebe API key da instância
2. Busca dados da instância
3. Calcula dias restantes do trial
4. Retorna status atual + QR Code se necessário

### ⚡ **PASSO 2: MODIFICAR PLUGIN WORDPRESS**

#### 📁 **Criar: includes/class-quick-signup.php**

**Responsabilidades:**
- Handler AJAX para criação de conta
- Handler AJAX para salvar configurações
- Handler AJAX para verificar status

**Endpoints AJAX:**
- `wpwevo_quick_signup` - Chama Edge Function quick-signup
- `wpwevo_save_quick_config` - Salva credenciais no WordPress
- `wpwevo_check_plugin_status` - Verifica status da instância

#### 🔧 **Modificar: includes/class-settings-page.php**

**Adicionar:**
- Nova aba "🚀 Teste Grátis" nas configurações
- Formulário de cadastro (nome, email, whatsapp)
- Interface de progresso em tempo real
- Tela de status do trial após criação

#### 🔧 **Modificar: includes/class-plugin-loader.php**

**Adicionar:**
- `Quick_Signup::init();` na função `init_modules()`

## 🎨 **INTERFACE ESPERADA**

### 📱 **Aba "Teste Grátis" (Antes do Cadastro)**
```html
🚀 Teste Grátis por 7 Dias

Não tem Evolution API? Sem problema! Teste nossa solução completa:
✅ Sem VPS, sem Docker, sem complicação
✅ Configuração automática em 30 segundos  
✅ Suporte técnico incluído
✅ 7 dias grátis, sem cartão de crédito

[Formulário com: Nome, Email, WhatsApp]
[Botão: 🚀 Criar Conta e Testar Agora]
```

### 📱 **Durante o Processo**
```html
[Barra de Progresso com 4 etapas:]
1. Validando dados... (ativo)
2. Criando conta...
3. Configurando plugin...
4. Pronto! ✅
```

### 📱 **Após Sucesso**
```html
🎉 Sua conta de teste está ativa!

⏰ Trial expira em 7 dias
Aproveite para testar todas as funcionalidades!

📋 Próximos passos:
✅ Conta criada e plugin configurado
🔗 Conectar seu WhatsApp
📱 Testar envio de mensagem
🛒 Configurar carrinho abandonado

[Botão: Fazer Upgrade]
```

## 🔑 **DADOS TÉCNICOS IMPORTANTES**

### 📊 **Tabelas do Supabase:**
- `profiles` - Dados do usuário (name, email, whatsapp, role, plan)
- `instances` - Instâncias WhatsApp (user_id, name, status, api_key, trial_expires_at)
- `admin_config` - Config global (evolution_api_url, evolution_api_key)

### 🔐 **Segurança:**
- Plugin usa **anon key** (segura para frontend)
- Edge Functions usam **service_role** (acesso total)
- API keys das instâncias são **individuais** (seguras para usuários)

### ⚙️ **Configurações WordPress:**
- `wpwevo_api_url` - URL da Evolution API
- `wpwevo_api_key` - API key individual da instância
- `wpwevo_instance` - Nome da instância
- `wpwevo_auto_configured` - Se foi configurado automaticamente
- `wpwevo_trial_started_at` - Timestamp do início do trial

## 🧪 **COMO TESTAR**

### 1️⃣ **Testar Edge Functions isoladamente:**
```bash
# Teste quick-signup
curl -X POST https://ydnobqsepveefiefmxag.supabase.co/functions/v1/quick-signup \
  -H "Authorization: Bearer eyJhbGciOiJIUzI..." \
  -H "Content-Type: application/json" \
  -d '{"name":"Teste","email":"teste@teste.com","whatsapp":"11999999999","source":"test"}'

# Resposta esperada: { "success": true, "data": {...} }
```

### 2️⃣ **Testar no Plugin:**
1. Ativar plugin no WordPress
2. Ir em "WhatsEvolution" > aba "🚀 Teste Grátis"
3. Preencher formulário
4. Verificar se progresso funciona
5. Confirmar se plugin se configura automaticamente

## ⚠️ **PONTOS DE ATENÇÃO**

### 🚨 **Críticos:**
1. **Validação de WhatsApp** - Deve funcionar via Evolution API
2. **Cleanup em erro** - Se algo falha, limpar dados criados
3. **Timeout handling** - Operação pode demorar até 30 segundos
4. **Nonces WordPress** - Usar para segurança AJAX

### 💡 **Melhorias UX:**
1. **Feedback visual** - Mostrar progresso em tempo real
2. **Mensagens de erro** - Claras e acionáveis
3. **Botão de retry** - Se algo der errado
4. **Link para upgrade** - Quando trial vencer

## 📋 **CHECKLIST DE SUCESSO**

### ✅ **Backend Funcionando:**
- [ ] Edge Function quick-signup retorna credenciais
- [ ] Edge Function plugin-status retorna dados corretos
- [ ] Validação de WhatsApp funciona
- [ ] Criação de conta completa (auth + profile + instance)

### ✅ **Frontend Funcionando:**
- [ ] Nova aba aparece no plugin
- [ ] Formulário valida dados
- [ ] AJAX chama Edge Functions corretamente
- [ ] Progresso visual funciona
- [ ] Plugin se configura automaticamente

### ✅ **Fluxo Completo:**
- [ ] Usuário preenche formulário
- [ ] Conta é criada em 30 segundos
- [ ] Plugin funciona imediatamente
- [ ] Trial expira em 7 dias
- [ ] Link para upgrade funciona

## 🎯 **RESULTADO FINAL ESPERADO**

**ANTES:** Usuário precisa criar conta no site, pegar 3 credenciais, voltar ao plugin, configurar manualmente.

**DEPOIS:** Usuário preenche 3 campos no plugin, clica um botão, em 30 segundos está testando.

**IMPACTO:** Taxa de conversão sobe de ~10% para ~40% (estimativa baseada na remoção de fricção).

## 🆘 **SE TIVER DÚVIDAS**

### 📚 **Documentação de Referência:**
- `ONBOARDING-STRATEGY.md` - Análise completa da estratégia
- `IMPLEMENTACAO-DETALHADA.md` - Código completo para implementar
- `README.md` - Funcionalidades atuais do plugin

### 🔧 **Comandos Úteis:**
```bash
# Ver logs das Edge Functions
# Dashboard Supabase > Edge Functions > Logs

# Testar plugin local  
# WordPress admin > Plugins > WP WhatsEvolution

# Debug PHP
error_log('Debug: ' . print_r($data, true));
```

---

**🚀 MISSÃO: Transformar o plugin em uma máquina de conversão que funciona em 30 segundos!**

**🎯 FOCO: UX perfeita + integração técnica sólida + taxa de conversão máxima** 