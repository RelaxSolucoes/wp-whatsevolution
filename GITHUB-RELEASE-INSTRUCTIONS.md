# 📋 INSTRUÇÕES PARA GITHUB RELEASE

## 🚀 **COMO CRIAR RELEASE NO GITHUB**

### 1️⃣ **Preparação (Já Feito!)**
- ✅ Código commitado 
- ✅ Tag `v1.0.4` criada
- ✅ Release notes prontas

### 2️⃣ **Passos no GitHub**

1. **📤 Push da tag**
   ```bash
   git push origin v1.0.4
   ```

2. **🌐 Ir para GitHub**
   - Acesse: https://github.com/RelaxSolucoes/wp-whatsevolution
   - Vá em **"Releases"** → **"Create a new release"**

3. **🏷️ Configurar Release**
   - **Tag version:** `v1.0.4`
   - **Release title:** `🚀 WP WhatsEvolution v1.0.4 - Carrinho Abandonado + REBRANDING`

4. **📝 Descrição (copiar do RELEASE-NOTES-v1.0.4.md)**
   - Cole o conteúdo do arquivo `RELEASE-NOTES-v1.0.4.md`

5. **📦 Upload do ZIP**
   - Criar ZIP do plugin (sem pasta .git)
   - Upload como `wp-whatsevolution-v1.0.4.zip`

6. **✅ Publicar**
   - Marcar **"Set as the latest release"**
   - Clicar **"Publish release"**

---

## 📦 **COMO CRIAR O ZIP PARA DOWNLOAD**

### **Opção 1: Manual**
```bash
# Na pasta do plugin
zip -r wp-whatsevolution-v1.0.4.zip . -x "*.git*" "*.md" "GITHUB-*"
```

### **Opção 2: GitHub Automático**
- GitHub gera automaticamente o ZIP do código
- Usuários podem baixar direto da release

---

## 📢 **APÓS PUBLICAR**

### **📣 Comunicação aos Usuários**

1. **📧 Email/Newsletter**
   ```
   🚀 NOVA VERSÃO: WP WhatsEvolution v1.0.4
   
   🏷️ IMPORTANTE: Plugin renomeado para WP WhatsEvolution 
   🛒 NOVO: Carrinho Abandonado com interceptação interna
   
   📥 Baixe: https://github.com/RelaxSolucoes/wp-whatsevolution/releases/latest
   ```

2. **💬 Redes Sociais**
   ```
   🚀 Lançado WP WhatsEvolution v1.0.4!
   
   ✨ Carrinho abandonado automático
   🏷️ Novo nome (questões legais) 
   🔧 Logs otimizados
   
   #WordPress #WooCommerce #EvolutionAPI
   ```

3. **📱 Grupos/Comunidades**
   - Avisar nos grupos do WordPress
   - Fóruns de WooCommerce
   - Comunidades da Evolution API

---

## 🎯 **PRÓXIMOS PASSOS**

### **📊 Fase 1 Completa - Distribuição Manual Organizada**
- ✅ Releases organizadas no GitHub
- ✅ Release notes profissionais
- ✅ ZIP para download fácil
- ✅ Comunicação estruturada

### **🔄 Fase 2 - Sistema de Update (Próxima versão)**
```php
// Para implementar na v1.0.5
require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/RelaxSolucoes/wp-whatsevolution/',
    __FILE__,
    'wp-whatsevolution'
);
```

---

## ✅ **CHECKLIST FINAL**

- [ ] Push da tag `v1.0.4`
- [ ] Release criada no GitHub
- [ ] ZIP anexado na release
- [ ] Release notes copiadas
- [ ] Release marcada como "latest"
- [ ] Comunicação enviada aos usuários

**🎉 RELEASE PRONTA PARA DISTRIBUIÇÃO!** 