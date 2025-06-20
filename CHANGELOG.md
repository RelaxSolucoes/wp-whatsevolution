# Changelog - WP WhatsEvolution

## [1.2.6] - 2025-01-XX

### 🎯 CORREÇÃO CRÍTICA - SUBMENUS PRINCIPAIS
- **🚨 PROBLEMA RESOLVIDO**: Todos os 6 submenus agora aparecem corretamente no admin
- **✅ SUBMENUS FUNCIONAIS**: Envio Individual, Envio por Status e Envio em Massa 100% visíveis
- **🔧 HOOKS OTIMIZADOS**: Ordem de inicialização corrigida (menu_title definido antes de admin_menu)
- **⚡ PERFORMANCE**: Propriedades movidas de setup() para __construct() em 3 classes

### 🎨 CSS FUNCIONANDO CORRETAMENTE
- **🐛 BUGFIX CRÍTICO**: Condição invertida `strpos() === false` corrigida para `!== false`
- **✅ ESTILOS CARREGANDO**: CSS agora carrega em todas as páginas do plugin
- **🎯 ARQUIVOS CORRIGIDOS**: 6 arquivos com condição de carregamento CSS corrigida
- **🛠️ COMPATIBILIDADE**: Estilos funcionam independente do nome da pasta do plugin

### 📋 DETALHES TÉCNICOS
- **Classes corrigidas**: Send_Single, Send_By_Status, Bulk_Sender
- **Propriedades movidas**: menu_title e page_title para __construct()
- **Ordem de hooks**: admin_menu executa ANTES de init, dados devem estar prontos
- **CSS loading**: Condição `strpos($hook, 'wpwevo') !== false` corrigida

### 🎯 IMPACTO
- **Interface completa**: Todos os submenus visíveis e funcionais
- **UX melhorada**: Navegação completa pelo plugin
- **Zero quebras**: Funcionalidades existentes preservadas
- **Regra WordPress**: Dados para hooks devem estar disponíveis ANTES da execução

## [1.0.10] - 2025-01-XX

### 🔧 CORREÇÃO CRÍTICA - WEBHOOK CARRINHO ABANDONADO
- **🚨 PROBLEMA RESOLVIDO**: "Trigger Sample" não fica mais "eternamente disparando"
- **✅ RESPOSTA JSON**: Webhook agora responde com JSON estruturado como esperado pelo Cart Abandonment Recovery
- **🎯 COMPATIBILIDADE**: Análise completa do código JavaScript do plugin Cart Abandonment para resposta perfeita
- **📊 LOGS MELHORADOS**: Detecção mais precisa de testes vs carrinhos reais

### 🛠️ MELHORIAS TÉCNICAS
- **Content-Type**: Mudado de `text/plain` para `application/json; charset=utf-8`
- **Detecção de teste**: Melhorada para identificar dados fictícios do Trigger Sample
- **Resposta estruturada**: JSON com `status`, `message` e `customer` para debug
- **Headers otimizados**: Compatibilidade máxima com diferentes ambientes

### 📋 IMPACTO
- **UX melhorada**: Botão "Trigger Sample" funciona instantaneamente
- **Zero falsos positivos**: Diferenciação clara entre teste e carrinho real
- **Debug facilitado**: Logs mais informativos sobre o tipo de requisição

## [1.0.9] - 2025-06-17

### 🔧 CORREÇÃO - INTERFERÊNCIA NA DIGITAÇÃO
- **🐛 BUG CORRIGIDO**: Campo "pulando" caracteres durante digitação (ex: 19 virando 91)
- **⚡ MELHORADO**: Não manipula mais o valor do campo, apenas extrai números para validação
- **🎯 OTIMIZADO**: Debounce aumentado para 1.5s para evitar conflitos com máscaras
- **✅ EVENTOS**: Adicionado suporte para 'input' e 'paste' além de 'keyup'

### 🛡️ COMPATIBILIDADE
- **Brazilian Market**: Zero interferência com máscaras de CPF/telefone
- **WooCommerce**: Compatível com formatação automática
- **Navegadores**: Não conflita com auto-complete

## [1.0.8] - 2025-06-17

### 🚨 CORREÇÃO CRÍTICA - SPAM DE REQUESTS AJAX
- **🔥 BUG CORRIGIDO**: MutationObserver causando spam infinito de requests
- **⚡ PERFORMANCE**: Adicionado debounce e controle de execução única
- **🎯 OTIMIZADO**: Observer só reinicializa se houver novos campos de telefone
- **✅ RESULTADO**: 1 request por validação (ao invés de centenas)

### 🛠️ MELHORIAS TÉCNICAS
- **Debounce de inicialização** (100ms)
- **Controle de execução única** (`isInitialized`)
- **MutationObserver inteligente** com verificação de campos
- **Debounce do observer** (500ms)

## [1.0.7] - 2025-06-17

### 🔥 CORREÇÃO URGENTE - VALIDAÇÃO API KEY
- **🚨 REMOVIDA**: Validação local da API Key completamente removida
- **✅ DEIXA**: Evolution API validar a própria chave
- **🛡️ ZERO**: Interferência do plugin na validação
- **🎯 FUNCIONA**: Com QUALQUER Evolution API existente

## [1.0.6] - 2025-06-17

### 🔒 SEGURANÇA & LIMPEZA
- **🚨 CRÍTICO**: Removidas informações sensíveis da documentação pública
- **🛡️ LIMPEZA**: API Keys e URLs específicas removidas dos arquivos
- **✅ DESINSTALAÇÃO**: Corrigido problema crítico de desinstalação incompleta

### 🗑️ CORREÇÃO DESINSTALAÇÃO
- **📁 ARQUIVO DEDICADO**: Criado `uninstall.php` para limpeza completa
- **🔄 MIGRAÇÃO**: Corrigida inconsistência `wpwevo_instance_name` → `wpwevo_instance`
- **📋 LIMPEZA COMPLETA**: Remove tabelas, opções, transients, cron jobs e metadados
- **🛠️ MIGRAÇÃO AUTOMÁTICA**: Instalações existentes são corrigidas automaticamente

### 🎯 IMPACTO
- **Desinstalação 100% limpa** - Zero resíduos no banco de dados
- **Segurança aprimorada** - Nenhuma informação sensível em arquivos públicos
- **Compatibilidade total** - Funciona em instalações novas e existentes

## [1.0.5] - 2025-06-16

### 🔧 CORREÇÃO CRÍTICA - VALIDAÇÃO API KEY
- **🚨 CORRIGIDO**: Validação muito restritiva da API Key que impedia uso de APIs válidas
- **✅ FLEXIBILIZADO**: Regex de validação para aceitar formato real da Evolution API
- **🎯 SUPORTE**: API Keys em formato UUID flexível
- **🛡️ MANTIDO**: Validação básica do formato `XXXX-XXXX-XXXX-XXXX-XXXX`

### 📋 DETALHES TÉCNICOS
- **Antes**: Exigia UUID v4 específico com padrão rígido
- **Depois**: Aceita qualquer combinação A-F e 0-9 no formato padrão
- **Compatível**: Evolution API v2.2.3+ testada
- **Resolve**: Erro "Formato da API Key inválido" com APIs funcionais

### 🎯 IMPACTO
- **Zero quebras**: Mantém compatibilidade com APIs antigas
- **Maior flexibilidade**: Suporte a diferentes provedores Evolution API
- **UX melhorada**: Menos erros de validação desnecessários

---

## [1.0.4] - 2024-12-17

### 🏷️ REBRANDING
- **Plugin renomeado** para WP WhatsEvolution (evita problemas legais com marca WhatsApp)
- **Repositório renomeado** para wp-whatsevolution
- **Todas configurações preservadas** na migração

### ✨ NOVA FUNCIONALIDADE - CARRINHO ABANDONADO
- **🎯 Interceptação interna** - Sistema revolucionário via hooks internos
- **⚡ Zero configuração** - Ativação com 1 clique, sem URLs de webhook
- **🛠️ Integração automática** com plugin "WooCommerce Cart Abandonment Recovery"
- **📝 Templates personalizáveis** com shortcodes dinâmicos
- **🇧🇷 Formatação brasileira** - R$ ao invés de símbolos HTML

### 🎨 SHORTCODES DISPONÍVEIS
- `{first_name}` - Nome do cliente
- `{product_names}` - Produtos no carrinho  
- `{cart_total}` - Valor formatado (R$ 149,90)
- `{checkout_url}` - Link para finalizar compra
- `{coupon_code}` - Código do cupom

### 📊 MELHORIAS
- **Logs otimizados** - Apenas informações essenciais para usuários
- **Interface melhorada** - Logs com cores e ícones
- **Performance** - Redução de overhead no sistema de logs

### 🐛 CORREÇÕES
- **Formatação de moeda** - Corrigido &#36; → R$
- **Compatibilidade** - Melhorada integração com plugins externos
- **Validação** - Números de telefone brasileiros

### 📋 COMO USAR CARRINHO ABANDONADO
1. Instale plugin "WooCommerce Cart Abandonment Recovery"
2. Acesse "WhatsEvolution > Carrinho Abandonado"
3. Ative a integração
4. Personalize mensagem (opcional)
5. Pronto! Sistema funciona automaticamente

### 🔧 TEMPLATE PADRÃO
```
🛒 Oi {first_name}!

Vi que você adicionou estes itens no carrinho:
📦 {product_names}

💰 Total: {cart_total}

🎁 Use o cupom *{coupon_code}* e ganhe desconto especial!
⏰ Mas corre que é só por hoje!

Finalize agora:
👆 {checkout_url}
```

### 📥 INSTALAÇÃO
1. Baixe o código via "Code > Download ZIP"
2. Upload via WordPress Admin > Plugins > Adicionar Novo
3. Ative o plugin
4. Configure Evolution API
5. Ative funcionalidades desejadas

**Todas configurações são preservadas na atualização!**

---

## [1.0.3] - 2024-11-15
- Envio por status de pedido
- Envio em massa melhorado
- Validação de checkout

## [1.0.2] - 2024-10-10
- Envio em massa
- Melhorias na interface

## [1.0.1] - 2024-09-05
- Envio individual
- Melhorias na conexão

## [1.0.0] - 2024-08-01
- Versão inicial
- Conexão com Evolution API 