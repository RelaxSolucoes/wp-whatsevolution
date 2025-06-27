# 📦 Guia de Releases - WP WhatsEvolution

Este documento explica como criar releases do plugin WP WhatsEvolution de forma automatizada.

## 🚀 Processo de Release

### 1. Preparação

Antes de criar um release, certifique-se de que:

- ✅ Todas as funcionalidades estão implementadas e testadas
- ✅ O CHANGELOG.md foi atualizado com as mudanças
- ✅ A versão foi incrementada nos arquivos principais
- ✅ Todos os commits foram enviados para o repositório

### 2. Arquivos que precisam ser atualizados

Para cada nova versão, atualize:

1. **wp-whatsapp-evolution.php**
   - Linha 6: `Version: X.X.X`
   - Linha 20: `define('WPWEVO_VERSION', 'X.X.X');`

2. **readme.txt**
   - Linha 7: `Stable tag: X.X.X`
   - Seção `== Changelog ==`: Adicionar nova entrada
   - Seção `== Upgrade Notice ==`: Adicionar nova entrada

3. **CHANGELOG.md**
   - Adicionar nova seção no topo com as mudanças

### 3. Criando o Release

#### Opção A: Script Automatizado (Recomendado)

**Linux/Mac:**
```bash
# No diretório raiz do plugin
chmod +x scripts/create-release.sh
./scripts/create-release.sh 1.3.1
```

**Windows:**
```powershell
# No diretório raiz do plugin
.\scripts\create-release.ps1 1.3.1
```

#### Opção B: Manual

```bash
# 1. Commit das mudanças
git add .
git commit -m "Release 1.3.1: Adiciona sistema de notas nos pedidos"

# 2. Criar e enviar tag
git tag -a v1.3.1 -m "Release 1.3.1"
git push origin v1.3.1
```

### 4. O que acontece automaticamente

Quando você cria uma tag `v*`, o GitHub Actions:

1. **Detecta a tag** e executa o workflow
2. **Cria o release** no GitHub com descrição automática
3. **Gera o arquivo ZIP** do plugin
4. **Faz upload** do ZIP como asset do release
5. **Atualiza** o sistema de auto-update

### 5. Verificação

Após criar o release:

1. Acesse: https://github.com/RelaxSolucoes/wp-whatsevolution/releases
2. Verifique se o release foi criado corretamente
3. Teste o download do arquivo ZIP
4. Verifique se o auto-update funciona em instalações existentes

## 📋 Checklist de Release

- [ ] Versão atualizada em `wp-whatsapp-evolution.php`
- [ ] Versão atualizada em `readme.txt`
- [ ] CHANGELOG.md atualizado
- [ ] Todos os commits enviados
- [ ] Tag criada e enviada
- [ ] Release verificado no GitHub
- [ ] Auto-update testado

## 🔧 Troubleshooting

### Erro: "Tag já existe"
```bash
# Remover tag local
git tag -d v1.3.1

# Remover tag remota
git push origin --delete v1.3.1

# Criar nova tag
git tag -a v1.3.1 -m "Release 1.3.1"
git push origin v1.3.1
```

### Erro: "Workflow não executou"
- Verifique se a tag segue o padrão `v*` (ex: v1.3.1)
- Verifique se o workflow está no branch correto
- Verifique os logs do GitHub Actions

### Auto-update não funciona
- Verifique se o repositório está correto em `wp-whatsapp-evolution.php`
- Verifique se o `stable tag` no `readme.txt` está correto
- Aguarde alguns minutos para propagação

## 📞 Suporte

Em caso de problemas:

1. Verifique os logs do GitHub Actions
2. Consulte a documentação do plugin-update-checker
3. Abra uma issue no repositório

---

**Nota**: Este processo garante que todas as instalações do plugin recebam atualizações automaticamente através do sistema de auto-update do WordPress. 