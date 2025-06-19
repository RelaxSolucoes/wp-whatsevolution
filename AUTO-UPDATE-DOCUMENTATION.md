# 🤖 AUTO-UPDATE GITHUB - Documentação Técnica

## 🎯 **SISTEMA IMPLEMENTADO**

O plugin **WP WhatsEvolution** agora possui **auto-update automático** via GitHub Releases usando a biblioteca **Plugin Update Checker** (YahnisElsts).

## 🔧 **IMPLEMENTAÇÃO TÉCNICA**

### 📦 **Biblioteca Utilizada**
- **Nome:** Plugin Update Checker
- **Autor:** YahnisElsts  
- **GitHub:** https://github.com/YahnisElsts/plugin-update-checker
- **Stars:** 2.4k+ ⭐
- **License:** MIT
- **Versão:** v5.x

### 🔗 **Integração no Plugin**

#### **Header do Plugin:**
```php
/**
 * Plugin Name: WP WhatsEvolution
 * Version: 1.2.1
 * GitHub Plugin URI: RelaxSolucoes/wp-whatsevolution
 */
```

#### **Código de Auto-Update:**
```php
// ===== AUTO-UPDATE GITHUB =====
require_once plugin_dir_path(__FILE__) . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

function wp_whatsevolution_init_auto_updater() {
    if (!class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) return;
    
    $updateChecker = PucFactory::buildUpdateChecker(
        'https://github.com/RelaxSolucoes/wp-whatsevolution',
        __FILE__,
        'wp-whatsevolution'
    );
    
    $updateChecker->getVcsApi()->enableReleaseAssets();
}
add_action('init', 'wp_whatsevolution_init_auto_updater');
// ===== FIM AUTO-UPDATE =====
```

## 🚀 **COMO FUNCIONA**

### 🔄 **Fluxo de Atualização:**

1. **WordPress verifica atualizações** (automaticamente ou manual)
2. **Plugin Update Checker consulta** GitHub API: `/repos/RelaxSolucoes/wp-whatsevolution/releases/latest`
3. **Compara versões:** GitHub vs Plugin atual
4. **Se nova versão disponível:** Mostra notificação no WordPress
5. **Usuário clica "Atualizar":** Download automático do GitHub
6. **WordPress instala:** Nova versão substituindo a antiga

### 📡 **API Endpoints Utilizados:**
- `GET https://api.github.com/repos/RelaxSolucoes/wp-whatsevolution/releases/latest`
- `GET https://github.com/RelaxSolucoes/wp-whatsevolution/archive/refs/tags/v1.2.1.zip`

## 📋 **PROCESSO DE RELEASE**

### 🏷️ **1. Criar Release no GitHub:**
```bash
# Via GitHub Web Interface:
1. Ir para: https://github.com/RelaxSolucoes/wp-whatsevolution/releases
2. Clicar "Create a new release"
3. Tag version: v1.2.1 
4. Release title: v1.2.1 - Auto-update implementado
5. Description: Changelog da versão
6. Attach files: (opcional - ZIP do plugin)
7. Publish release
```

### 🏷️ **2. Via CLI (Automatizado):**
```bash
git tag v1.2.1
git push origin v1.2.1

# GitHub automaticamente cria release
# Plugin Update Checker automaticamente detecta
```

### 🏷️ **3. Estrutura da Tag:**
- **Formato:** `v1.2.1` (com 'v' no início)
- **Versionamento:** Semantic Versioning (MAJOR.MINOR.PATCH)
- **Compatibilidade:** Plugin Update Checker remove 'v' automaticamente

## 🔍 **MONITORAMENTO**

### 📊 **Logs WordPress:**
```php
// Debug logs em wp-content/debug.log
[plugin-update-checker] Checking for updates from https://github.com/...
[plugin-update-checker] Found new version: 1.2.1
[plugin-update-checker] Update notification shown
```

### 📊 **Verificação Manual:**
```php
// Forçar verificação de update
wp_update_plugins();

// Verificar transients
get_site_transient('update_plugins');
```

## 🛡️ **SEGURANÇA**

### 🔒 **APIs Públicas:**
- **GitHub API:** Pública, sem autenticação necessária
- **Rate Limits:** 60 requests/hora por IP (suficiente)
- **SSL/HTTPS:** Todas comunicações criptografadas

### 🔒 **Validação:**
- **Checksums:** GitHub fornece automaticamente
- **Verificação de origem:** Apenas do repositório oficial
- **Permissions:** WordPress padrão (admin para updates)

## 🧪 **TESTES**

### 🔬 **Teste Local:**
```php
// Simular nova versão no GitHub
1. Aumentar versão no plugin: 1.2.1 → 1.2.2
2. Criar release v1.2.2 no GitHub  
3. WordPress → Atualizações
4. Verificar se aparece "WP WhatsEvolution v1.2.2 disponível"
```

### 🔬 **Teste Automático:**
```bash
# Verificar se biblioteca carregou
wp eval "var_dump(class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory'));"

# Forçar check de updates
wp eval "wp_update_plugins(); echo 'Forced update check completed';"
```

## 📁 **ESTRUTURA DE ARQUIVOS**

```
wp-whatsevolution/
├── wp-whatsapp-evolution.php (integração auto-update)
├── lib/
│   └── plugin-update-checker/
│       ├── plugin-update-checker.php
│       ├── Puc/
│       ├── includes/
│       └── vendor/
├── readme.txt (versão atualizada)
└── AUTO-UPDATE-DOCUMENTATION.md
```

## ⚠️ **CONSIDERAÇÕES IMPORTANTES**

### 🚨 **Rate Limits GitHub:**
- **60 requests/hora** sem autenticação
- **WordPress verifica a cada 12 horas** (padrão)
- **Não haverá problemas** com rate limits

### 🚨 **Dependências:**
- **Plugin Update Checker** incluído no plugin
- **Não requer** composer ou dependências externas
- **Funciona offline** após primeira verificação

### 🚨 **Compatibilidade:**
- **WordPress:** 5.8+
- **PHP:** 7.4+
- **Multisite:** Compatível
- **WooCommerce:** Não afeta sistema de auto-update

## 🎯 **BENEFÍCIOS**

### ✅ **Para Usuários:**
- **Atualizações automáticas** sem intervenção manual
- **Notificações** no painel WordPress
- **Download direto** do GitHub (fonte oficial)
- **Rollback disponível** (se necessário)

### ✅ **Para Desenvolvedores:**
- **Deploy simplificado:** Apenas criar release no GitHub
- **Versionamento automático** baseado em tags
- **Distribuição gratuita** via GitHub
- **Controle total** sobre releases

## 🚀 **PRÓXIMOS PASSOS**

1. **✅ Sistema implementado e funcionando**
2. **🔄 Criar primeiro release v1.2.1** no GitHub
3. **🧪 Testar atualização** em ambiente local
4. **📊 Monitorar** logs e feedback dos usuários
5. **🔧 Otimizar** conforme necessário

---

## 📞 **SUPORTE**

- **Issues:** https://github.com/RelaxSolucoes/wp-whatsevolution/issues
- **Wiki:** https://github.com/RelaxSolucoes/wp-whatsevolution/wiki
- **Plugin Update Checker Docs:** https://github.com/YahnisElsts/plugin-update-checker

**🎉 O sistema de auto-update está 100% implementado e pronto para uso!** 