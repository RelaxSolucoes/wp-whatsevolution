# WP WhatsEvolution v1.4.9

🚀 **📲 Mais vendas, menos trabalho — automação total entre WooCommerce e WhatsApp + SMS**

[![Assista a atualização no YouTube](https://i9.ytimg.com/vi_webp/U52eaHWuP0g/mqdefault.webp?v=684717de&sqp=CNzQucgG&rs=AOn4CLCBCKj5AIUBV6Uqp61UDIZX8EQMgg)](https://www.youtube.com/watch?v=U52eaHWuP0g)

---

## 🆕 **NOVO na v1.4.9: Envio por SMS via SMSGate**

### 📱 **Terceiro Canal de Envio — 100% Independente do WhatsApp**

Agora você pode enviar notificações via SMS usando o app **android-sms-gateway** (SMSGate), diretamente do seu celular Android, sem depender de WhatsApp ou Evolution API.

### 🎯 **Três Modos de Operação**

| Modo | Canal | Ideal para |
|------|-------|------------|
| 🚀 **Managed** | WhatsApp automático | Quem usa o Teste Grátis |
| ⚙️ **Manual** | WhatsApp com suas credenciais | Quem tem Evolution API própria |
| 📱 **SMS** | SMSGate (android-sms-gateway) | Alternativa sem WhatsApp |

### 🔄 **Fallback Automático WhatsApp → SMS**
- **✅ Ative em 1 clique**: Checkbox "Usar SMS como fallback se WhatsApp falhar"
- **✅ Transparente**: Se o WhatsApp falhar, SMS é enviado automaticamente
- **✅ Log de fallback**: Cada evento de fallback é registrado com detalhes
- **✅ Requer SMSGate configurado**: Só ativa se as credenciais estiverem salvas

### ✅ **Compatível com Todos os Módulos**
- **📊 Envio por Status**: Notificações de pedido via SMS
- **🛒 Carrinho Abandonado**: Recuperação via SMS
- **📱 Envio Único**: Mensagem individual via SMS
- **📢 Envio em Massa**: Campanhas via SMS
- **⚠️ Validação no Checkout**: Não se aplica ao SMS (requer WhatsApp)

### 🔧 **Como Configurar**
1. Baixe o APK do SMSGate: [android-sms-gateway](https://github.com/capcom6/android-sms-gateway/releases/latest/download/app-release.apk)
2. Instale no celular Android e ative o **Cloud Server**
3. No WordPress: **WhatsEvolution → Conexão → selecione 📱 SMS → Salvar Modo**
4. Vá na aba **📱 SMS**, preencha Username, Password e Device ID
5. Clique em **"Salvar e Testar"** — um SMS de teste será enviado para o número admin

### 📞 **Formatação Automática de Número**
- Qualquer formato brasileiro é aceito (com ou sem DDI)
- Convertido automaticamente para `+55XXXXXXXXXXX`
- Funciona com os mesmos números já cadastrados nos pedidos

---

## 🆕 **NOVO na v1.4.8: Sistema de Mensagens Aleatórias**

### 🎲 **Múltiplos Templates com Seleção Automática**
- **✅ Interface Dinâmica**: Botão "+" para adicionar quantas mensagens quiser
- **✅ Seleção Aleatória**: Sistema escolhe automaticamente uma mensagem diferente para cada contato
- **✅ Anti-Spam Natural**: Variação de conteúdo evita detecção como spam
- **✅ Compatibilidade Total**: Funciona com todas as variáveis ({customer_name}, {order_id}, etc.)
- **✅ Gerenciamento Fácil**: Botão "Remover" em cada mensagem (mínimo 1)
- **✅ Numeração Automática**: Mensagens numeradas sequencialmente

### 🎯 **Como Funciona**
1. **Crie Templates**: Adicione múltiplas versões da sua mensagem
2. **Use Variáveis**: Todas as variáveis funcionam normalmente em cada template
3. **Envio Automático**: Sistema seleciona aleatoriamente uma mensagem para cada número
4. **Distribuição**: Exemplo - Número 1 recebe msg 3, Número 2 recebe msg 1, Número 3 recebe msg 5

### 💡 **Casos de Uso**
- **Variação de Tom**: Formal, casual e entusiasta
- **Testes A/B**: Compare diferentes CTAs e abordagens
- **Naturalidade**: Cada cliente recebe uma mensagem única
- **Prevenção**: Evita bloqueios por mensagens idênticas em massa

### 🔧 **Recursos Técnicos**
- **✅ Algoritmo**: `array_rand()` para seleção verdadeiramente aleatória
- **✅ Validação**: Pelo menos 1 mensagem obrigatória
- **✅ Sanitização**: Todas as mensagens sanitizadas automaticamente
- **✅ Compatibilidade**: Funciona em todas as abas (Clientes WooCommerce, CSV, Manual, etc.)

---

## 🆕 **NOVO na v1.4.7: Variáveis de Rastreamento e Sistema de Logs**

### 📦 **Variáveis de Rastreamento para Envios**
- **✅ {tracking_code}**: Código de rastreio do pedido (ex: AB646739409BR)
- **✅ {tracking_url}**: Link automático de rastreamento via Melhor Rastreio
- **✅ {shipping_company}**: Nome da transportadora ou método de envio

### 🚚 **Compatibilidade com Plugins de Rastreio**
- **✅ Melhor Envio**: Suporte automático ao meta field `melhorenvio_tracking`
- **✅ WooCommerce Shipment Tracking**: Compatível com o plugin oficial
- **✅ Plugins Genéricos**: Fallback para `_tracking_code` e `_tracking_number`
- **✅ Melhor Rastreio**: Links automáticos usando melhorrastreio.com.br para Correios

### 📊 **Nova Aba de Logs Centralizada**
- **✅ Visualização Completa**: Todos os logs de envio em um só lugar
- **✅ Filtros Avançados**: Filtre por nível (Error, Warning, Info, Debug)
- **✅ Busca Inteligente**: Pesquise por número, pedido ou mensagem
- **✅ Contexto Detalhado**: Expanda para ver informações completas
- **✅ Limpeza Fácil**: Botão para remover logs antigos
- **✅ Paginação**: Interface organizada com 50 logs por página

### 💡 **Casos de Uso das Variáveis de Rastreamento**
- **Notificação de Envio**: "Seu pedido #{order_id} foi enviado! 📦 Código de rastreio: {tracking_code}"
- **Link Direto**: "Acompanhe sua encomenda: {tracking_url}"
- **Informação Completa**: "Enviado via {shipping_company} - Rastreio: {tracking_code}"
- **Automação Perfeita**: Use em templates de status "Concluído" ou "Enviado"

### 🔧 **Recursos Técnicos**
- **✅ Fallback Inteligente**: Variáveis vazias se não houver código de rastreio
- **✅ Detecção Automática**: Identifica formato dos Correios (BR123456789BR)
- **✅ Performance**: Busca otimizada em múltiplas fontes de dados
- **✅ Logs Limpos**: Removidos logs desnecessários que poluíam o banco

---

## 🆕 **NOVO na v1.4.6: Notificações Admin por Status**

### 🔔 **Sistema de Notificação ao Administrador**
- **✅ WhatsApp Admin**: Campo dedicado para número do administrador
- **✅ Notificação por Status**: Checkbox para ativar notificação em cada status
- **✅ Mensagens Personalizadas**: Template exclusivo para notificações admin
- **✅ Validação em Tempo Real**: Verificação automática do número do admin
- **✅ Envio Duplo Sequencial**: Cliente recebe primeiro, depois admin é notificado
- **✅ Fallback Inteligente**: Mensagem padrão se campo admin vazio

### 🎯 **Como Funciona**
1. **Configure WhatsApp Admin**: Insira o número em "Whats Evolution > Conexão"
2. **Ative por Status**: Marque "🔔 Notificar Admin" em cada status desejado
3. **Personalize Mensagens**: Campo exclusivo aparece para cada status
4. **Automático**: Quando pedido mudar de status, cliente e admin recebem notificações

### 💡 **Casos de Uso**
- **Novos Pedidos**: Admin recebe alerta imediato de novos pedidos
- **Pedidos Aprovados**: Notificação para processar envio
- **Cancelamentos**: Alerta para verificar motivo
- **Alto Valor**: Mensagens personalizadas para pedidos VIP

### 🔧 **Recursos Técnicos**
- **✅ Variáveis Completas**: Usa todas as variáveis disponíveis ({order_id}, {customer_name}, etc.)
- **✅ Notas no Pedido**: Registra envios para cliente e admin
- **✅ Logs de Erro**: Falha no admin não afeta envio ao cliente
- **✅ Retrocompatibilidade**: Configurações antigas continuam funcionando

---

## 🆕 **NOVO na v1.4.5: Filtros Avançados e Envio para Todos os Clientes**

### 🎯 **Filtros Avançados de Valor**
- **✅ Filtros de Valor**: Segmentação precisa por valor mínimo e máximo de clientes
- **✅ Sistema Inteligente**: Permite criar campanhas direcionadas por faixa de gastos
- **✅ Interface Intuitiva**: Campos de valor com formatação automática
- **✅ Validação Robusta**: Prevenção de erros e valores inválidos

### ⏱️ **Sistema de Intervalo Inteligente**
- **✅ Modo Fixo**: Intervalos constantes entre envios (5-60 segundos)
- **✅ Modo Aleatório**: Simula comportamento humano (2-9 segundos)
- **✅ Alternância Inteligente**: Interface que adapta opções conforme o modo selecionado
- **✅ Prevenção de Spam**: Evita bloqueios por envio muito rápido

### 👥 **Nova Aba: Todos os Clientes**
- **✅ Busca Universal**: Envia para todos os usuários cadastrados no WordPress
- **✅ Filtro de Aniversário**: Segmentação por mês de nascimento (Janeiro-Dezembro)
- **✅ Compatibilidade Total**: Suporte a `billing_phone`, `billing_cellphone` e `phone`
- **✅ Variáveis Completas**: `{customer_name}`, `{customer_phone}`, `{customer_email}`, `{birthdate}`, `{user_id}`, `{display_name}`
- **✅ Preview Inteligente**: Visualização de todos os clientes antes do envio
- **✅ Integração Brasileira**: Compatível com Brazilian Market on WooCommerce

### 🔧 **Melhorias Técnicas**
- **✅ Padrão Consistente**: Todas as variáveis vazias ficam em branco (não mostram `{variável}`)
- **✅ Compatibilidade Expandida**: Suporte a múltiplos campos de telefone
- **✅ Interface Responsiva**: Tooltips informativos e validações em tempo real
- **✅ Sistema Robusto**: Tratamento de erros e fallbacks inteligentes

### 🎯 **Benefícios das Novas Funcionalidades**
- **📊 Segmentação Avançada**: Campanhas direcionadas por valor e aniversário
- **🤖 Comportamento Humano**: Intervalos aleatórios evitam detecção de spam
- **👥 Alcance Total**: Acesso a todos os clientes, não apenas com pedidos
- **🇧🇷 Mercado Brasileiro**: Otimizado para plugins e padrões brasileiros
- **⚡ Performance**: Sistema otimizado para grandes volumes de envio

---

## 🆕 **NOVO na v1.4.4: Correção Crítica no Cart Abandonment**

### 🐛 **Correção de Erro JavaScript**
- **✅ Problema Resolvido**: Erro `Cannot read properties of undefined (reading 'saving')` ao salvar templates
- **✅ Sistema Robusto**: Verificações de segurança com fallbacks para textos padrão
- **✅ Cache Atualizado**: Versionamento do script para garantir atualizações
- **✅ Funcionamento Perfeito**: Sistema de salvamento de templates sem erros JavaScript

---

## 🆕 **NOVO na v1.4.3: Compatibilidade Total com Brazilian Market on WooCommerce**

### 🇧🇷 **Integração Completa com Brazilian Market**
- **✅ Endereços Completos**: `{shipping_address_full}` e `{billing_address_full}` agora incluem número da casa e bairro
- **✅ Detecção Automática**: Sistema inteligente que detecta automaticamente se o Brazilian Market está ativo
- **✅ Fallback Inteligente**: Funciona perfeitamente com ou sem o plugin Brazilian Market
- **✅ Ordem Correta**: Endereços formatados como "Rua, Número, Bairro, Cidade, Estado, CEP"

### 🔧 **Melhorias Técnicas**
- **🔍 Captura de Meta Fields**: Acesso aos campos customizados `_shipping_number`, `_shipping_neighborhood`, `_billing_number`, `_billing_neighborhood`
- **⚡ Função Auxiliar**: Código reutilizável e limpo para montagem de endereços
- **📊 Compatibilidade**: Zero impacto para usuários que não usam Brazilian Market
- **🎯 Precisão**: Endereços sempre completos e formatados corretamente

### 🎯 **Benefícios da Integração**
- **📍 Endereços Precisos**: Número da casa e bairro sempre incluídos nas mensagens
- **🇧🇷 Mercado Brasileiro**: Otimizado para o padrão de endereços do Brasil
- **🚀 Plug & Play**: Funciona automaticamente sem configuração adicional
- **💬 Mensagens Profissionais**: Endereços completos para melhor experiência do cliente

---

## 🆕 **NOVO na v1.4.2: Correção Anti-Bug para Cart Abandonment Recovery v2.0**

### 🐛 **Problema Identificado e Solucionado**
- **✅ Bug Corrigido**: Plugin Cart Abandonment Recovery v2.0 marca pedidos finalizados como abandonados
- **✅ Solução Implementada**: Verificação automática que remove carrinhos de clientes que já finalizaram pedidos
- **✅ Critérios Inteligentes**: Remove apenas carrinhos com pedidos nas últimas 2 horas
- **✅ Status Considerados**: completed, processing, on-hold, pending
- **✅ Logs Detalhados**: Rastreamento completo de carrinhos removidos por pedidos finalizados

### 🔧 **Melhorias Técnicas**
- **🔍 Busca Otimizada**: Uso correto de meta_query para _billing_phone no WooCommerce
- **⚡ Performance**: Verificação individual antes do processamento de carrinhos
- **📊 Auditoria**: Sistema de logs para monitoramento e debug

### 🎯 **Benefícios da Correção**
- **🚫 Sem Spam**: Clientes não recebem mensagens desnecessárias
- **📈 Taxa de Conversão**: Mensagens apenas para carrinhos realmente abandonados
- **👥 Experiência do Cliente**: Evita confusão sobre status do pedido
- **💰 Eficiência**: Reduz custos de envio desnecessário

---

## 🆕 **NOVO na v1.4.1: Correções Críticas e Melhorias Significativas**

### 🐛 **Correções Críticas Implementadas**
- **✅ Submenus Funcionando**: Corrigido problema de submenus ausentes que afetava toda a navegação
- **✅ Variáveis Dinâmicas**: Sistema robusto de exibição por aba no envio em massa
- **✅ Fallback Inteligente**: Endereços de envio sempre funcionam, mesmo com configurações WooCommerce complexas

### 🚀 **Melhorias de Interface e Performance**
- **🎨 Interface Intuitiva**: Variáveis WooCommerce sempre visíveis por padrão
- **⚡ Sistema Robusto**: Fallbacks múltiplos para sessionStorage e compatibilidade
- **🔧 Código Otimizado**: Reorganização completa seguindo boas práticas WordPress
- **📱 Responsividade**: Interface moderna e adaptável a todos os dispositivos

### 🏗️ **Arquitetura WordPress Corrigida**
- **⏰ Timing dos Hooks**: Propriedades de menu definidas ANTES dos hooks WordPress
- **🔗 Compatibilidade HPOS**: Suporte completo ao novo sistema de pedidos WooCommerce
- **📚 Boas Práticas**: Código seguindo padrões WordPress e PHP modernos

---

## 🆕 **NOVO na v1.4.0: Integração com Agentes de IA do n8n**

### 🤖 **Sistema Completo de Agente de IA**

#### **🎯 Modalidades Disponíveis**
1. **Agente de IA**: Conecta com n8n para respostas personalizadas via IA
2. **Chat Simples**: Respostas locais baseadas em palavras-chave
3. **Fallback Inteligente**: Automaticamente usa Chat Simples quando webhook falha

#### **🔧 Funcionalidades Avançadas**
- **Webhook Proxy**: Seguro, não expõe URLs externas
- **Metadados Ricos**: Inclui contexto da página, usuário e sessão
- **Formatação Automática**: Adapta mensagens para diferentes canais
- **Validação Inteligente**: Testa webhook e ativa fallback automaticamente

#### **💬 Chat Simples (Sistema Local)**
- **Respostas baseadas em keywords**: Sistema de palavras-chave → respostas
- **Configurável**: Interface amigável para adicionar/editar respostas
- **Fallback inteligente**: Mensagem padrão quando não encontra keywords
- **Sem dependências externas**: Funciona offline

#### **📱 Widget Inteligente**
- **Chat integrado ao site**: Shortcode `[wpwevo_ai_chat]`
- **Personalização completa**: Cores, textos e comportamento
- **Injeção automática**: Opção de injetar no footer do site
- **Responsivo**: Adapta-se a todos os dispositivos

#### **🔄 Sistema de Fallback Automático**
- **Transparente para o usuário**: Chat continua funcionando sem interrupções
- **Detecção inteligente**: Identifica falhas de webhook automaticamente
- **Recuperação automática**: Volta ao Agente de IA quando possível
- **Aviso discreto**: Informa quando está usando Chat Simples

---

## 🆕 **NOVO na v1.3.2: Padronização e Estabilidade**

### 🔧 Padronizações e Melhorias
- **I18n**: Text domain padronizado para `wp-whatsevolution`
- **Arquivo Principal**: Renomeado para `wp-whatsevolution.php`
- **Checkout**: Validação apenas de telefone (CPF/CNPJ ignorados)
- **Compatibilidade**: Cart Abandonment Recovery silenciosa (sem aviso na UI)
- **Templates**: Fallback automático quando ausentes (Quick Signup)
- **Traduções**: Geração automática de `.mo` quando faltar

---

## 🆕 **Destaques da v1.3.0: Envio em Massa Reescrito do Zero!**

### ✨ **Revolução do Envio em Massa**
- **📊 Interface Intuitiva** - Abas organizadas (WooCommerce, CSV, Manual)
- **🔍 Detecção Inteligente** - CSV com separadores automáticos (vírgula/ponto e vírgula)
- **📱 Variáveis Contextuais** - Diferentes variáveis por fonte de dados
- **⚡ Performance Otimizada** - Sistema robusto e à prova de falhas
- **🎯 Histórico Completo** - Logs detalhados com limpeza automática

### 🔧 **Sistema de Fallback Inteligente**
- **📍 Endereços de Envio** - Detecta automaticamente quando endereço está vazio e usa dados de cobrança
- **📱 Validação Ultra-Robusta** - Aceita 8, 9, 10, 11, 12, 13 dígitos brasileiros
- **🔄 Compatibilidade Total** - Funciona com qualquer configuração do WooCommerce

---

## 📋 **Descrição**

O **WP WhatsEvolution** é o plugin mais avançado para integração de mensagens + WooCommerce, oferecendo:

- 🚀 **Quick Signup** com teste grátis de 7 dias
- 🛒 **Carrinho Abandonado** com interceptação interna revolucionária
- 📊 **Envio por Status** de pedido automatizado
- 📱 **Envio Individual** para clientes específicos
- 📢 **Envio em Massa** com filtros avançados (REESCRITO v1.3.0)
- ✅ **Validação no Checkout** em tempo real
- 🎨 **Templates Personalizáveis** com shortcodes dinâmicos
- 📦 **Rastreamento Integrado** com Melhor Envio e Correios
- 🧠 **Sistema Inteligente** de fallback e validação

---

## 🔧 **Três Modos de Operação**

### 🚀 **Modo Managed (Free Trial)**
- **Backend Supabase**: Usado apenas para onboarding e verificação de status
- **Configuração**: Automática em 30 segundos
- **Custo**: 7 dias grátis, depois pago
- **Ideal para**: Quem quer testar sem complicações técnicas

### ⚙️ **Modo Manual (Credenciais Próprias)**
- **Backend**: 100% local, sem calls externos
- **Configuração**: URL, API Key, Instância próprias
- **Custo**: Zero (usa sua Evolution API)
- **Ideal para**: Quem já tem Evolution API configurada

### 📱 **Modo SMS (SMSGate)**
- **Backend**: App android-sms-gateway no seu celular Android
- **Configuração**: Username, Password e Device ID gerados pelo app
- **Custo**: Zero (usa o chip do seu celular)
- **Fallback**: Pode ser usado como fallback automático do WhatsApp
- **Ideal para**: Quem quer SMS como canal principal ou reserva

## 🔍 **Compatibilidade de Versões da Evolution API**

### ✅ **Versões Suportadas**
- **Evolution API V2.x.x**: ✅ **Totalmente compatível** - Todas as funcionalidades funcionam perfeitamente
- **Evolution API V1.x.x**: ⚠️ **Compatibilidade limitada** - Sistema funciona mas com avisos de incompatibilidade

### 🚨 **Avisos Automáticos**
- **Detecção Inteligente**: Sistema verifica automaticamente a versão da sua API
- **Avisos Visuais**: Interface mostra claramente quando há incompatibilidade
- **Recomendações**: Orientações para atualização quando necessário

### 📱 **Como Funciona**
1. **Configuração**: Insira URL, API Key e Nome da Instância
2. **Verificação**: Sistema testa conexão e verifica versão automaticamente
3. **Feedback**: Interface mostra status e avisos de compatibilidade
4. **Ação**: Atualize para V2 se necessário ou continue usando V1 com limitações

---

## 🚀 **Quick Signup - Comece em 30 Segundos**

### 🎯 **Para quem não tem Evolution API**

1. **Acesse** "WhatsEvolution > 🚀 Teste Grátis"
2. **Preencha** seus dados (nome, email, WhatsApp)
3. **Clique** em "Criar Conta e Testar Agora"
4. **Aguarde** a configuração automática (30 segundos)
5. **Conecte** seu WhatsApp via QR Code
6. **Pronto!** Teste todas as funcionalidades por 7 dias

### ✨ **Vantagens do Quick Signup**
- 🆓 **7 dias grátis** sem cartão de crédito
- ⚡ **Configuração automática** de toda a Evolution API  
- 🛠️ **Sem complicações técnicas** (VPS, Docker, etc.)
- 📞 **Suporte técnico incluído** no período de teste
- 🔄 **Fácil upgrade** quando decidir continuar

---

## 🛒 **Carrinho Abandonado - Funcionalidades Detalhadas**

### 🔧 **Configuração da Integração**

A configuração é feita em duas partes: ativar em nosso plugin e configurar o plugin parceiro.

#### **Passo 1: Instale o Plugin Parceiro**
1.  **Instale e ative** o plugin "WooCommerce Cart Abandonment Recovery".

#### **Passo 2: Configure a Integração**
Siga as instruções que aparecem na nossa página de configurações:

1.  Navegue até: **WooCommerce → Cart Abandonment → Settings → Webhook Settings**.
2.  **Ative** a opção `"Enable Webhook"`.
3.  **Cole a URL** fornecida pelo nosso plugin no campo `"Webhook URL"`.
4.  **Teste** a integração clicando em `"Trigger Sample"`.
5.  **Salve as configurações**.

### 🎯 **Interceptação Interna Revolucionária**
- **⚡ Integração Simplificada** - Apenas ative o webhook no plugin parceiro.
- **🔒 100% Seguro** - Dados nunca saem do servidor WordPress para o webhook.
- **⚡ Zero Latência** - Processamento instantâneo via hooks internos.
- **🎯 Interceptação Inteligente** - Captura carrinhos ANTES do webhook externo ser enviado.

### 🎨 **Shortcodes Disponíveis**

| Shortcode | Descrição | Exemplo |
|-----------|-----------|---------|
| `{first_name}` | Nome do cliente | João |
| `{full_name}` | Nome completo | João Silva |
| `{email}` | Email do cliente | joao@email.com |
| `{product_names}` | Produtos no carrinho | Produto A, Produto B |
| `{cart_total}` | Valor formatado | R$ 149,90 |
| `{cart_total_raw}` | Valor sem formatação | 149.90 |
| `{checkout_url}` | Link finalizar compra | https://loja.com/checkout?token=abc |
| `{coupon_code}` | Código do cupom | DESCONTO10 |
| `{site_name}` | Nome da loja | Minha Loja |
| `{site_url}` | URL da loja | https://loja.com |

### 📱 **Template Padrão Brasileiro**

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

---

## 📢 **Envio em Massa - Revolução v1.3.0**

### 🆕 **Nova Interface Intuitiva**

#### **🛒 Aba WooCommerce**
- **Filtros Avançados**: Status de pedido, período, valor mínimo
- **Variáveis Dinâmicas**: Dados completos do cliente e pedidos
- **Preview Inteligente**: Visualize clientes antes do envio

#### **📄 Aba Importação CSV**
- **Detecção Automática**: Separadores vírgula (,) ou ponto e vírgula (;)
- **Codificação UTF-8**: Suporte completo a acentos brasileiros
- **Template de Exemplo**: Download de arquivo modelo
- **Variáveis**: `{customer_name}`, `{customer_phone}`

#### **✍️ Aba Lista Manual**
- **Interface Simples**: Um número por linha
- **Validação Automática**: Formato brasileiro
- **Sem Variáveis**: Mensagem fixa para todos

### ⚡ **Controle de Envio**
- **Velocidade Configurável**: Segundos entre cada envio
- **Intervalo Aleatório**: Simula comportamento humano (2-9 segundos)
- **Agendamento**: Data e hora para iniciar
- **Progresso em Tempo Real**: Barra de progresso com status
- **Histórico Completo**: Logs detalhados com limpeza

### 🎯 **Filtros Avançados de Valor**
- **Valor Mínimo**: Clientes que gastaram acima de R$ X
- **Valor Máximo**: Clientes que gastaram até R$ X
- **Faixa de Valores**: Segmentação precisa por faixa de gasto
- **Casos de Uso**: Campanhas para diferentes perfis de cliente

#### **💡 Exemplos Práticos de Segmentação**
- **Clientes VIP**: Valor mínimo R$ 500,00
- **Clientes de Baixo Valor**: Valor máximo R$ 100,00
- **Cliente Médio**: Entre R$ 100,00 e R$ 500,00
- **Promoção Específica**: Apenas clientes que gastam até R$ 50,00

### ⏱️ **Sistema de Intervalo Inteligente**

#### **Modo Fixo (Padrão)**
- **Intervalo Configurável**: 1 a 60 segundos entre envios
- **Controle Total**: Usuário define exatamente o tempo
- **Compatibilidade**: Mantém comportamento atual

#### **Modo Aleatório (Novo)**
- **Intervalo Variável**: 2 a 9 segundos automaticamente
- **Comportamento Humano**: Simula digitação natural
- **Anti-Detecção**: Evita padrões robóticos
- **Performance**: Média de 5,5 segundos por mensagem

#### **🎯 Quando Usar Cada Modo**
- **Modo Fixo**: Para controle preciso de timing
- **Modo Aleatório**: Para maior naturalidade e segurança

---

## 📊 **Envio por Status - Automação Inteligente**

### 🎯 **Status Suportados**
- **pending/on-hold**: Pedido recebido, aguardando pagamento
- **processing**: Pedido aprovado, preparando envio
- **completed**: Pedido concluído com sucesso
- **cancelled**: Pedido cancelado
- **refunded**: Reembolso processado
- **failed**: Problema no pedido

### 🧠 **Sistema de Fallback Inteligente**
- **Detecção Automática**: Quando endereço de entrega está vazio
- **Fallback Inteligente**: Usa dados de cobrança automaticamente
- **Compatibilidade Total**: Funciona com qualquer configuração do WooCommerce

### 📱 **Templates por Status**
Cada status tem template personalizável com variáveis específicas do pedido.

---

## 🏷️ **Variáveis Disponíveis (Expandidas)**

### 🛒 **Dados do Pedido**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{order_id}` | ID do pedido | #1234 |
| `{order_total}` | Valor total | R$ 149,90 |
| `{order_url}` | Link do pedido | https://loja.com/pedido/1234 |
| `{payment_method}` | Método de pagamento | Cartão de Crédito |
| `{shipping_method}` | Método de envio | Correios |

### 👤 **Dados do Cliente**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{customer_name}` | Nome completo | João Silva |
| `{customer_email}` | Email | joao@email.com |
| `{customer_phone}` | Telefone | +55 11 99999-9999 |
| `{total_orders}` | Total de pedidos | 5 |
| `{last_order_date}` | Último pedido | 15/01/2025 |

### 📍 **Endereços (Com Fallback + Brazilian Market)**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{shipping_address_full}` | Endereço completo de entrega (com número e bairro) | Rua A, 123, Bairro Centro, São Paulo, SP, 01234-567 |
| `{billing_address_full}` | Endereço de cobrança (com número e bairro) | Rua B, 456, Bairro Jardins, Rio de Janeiro, RJ, 20000-000 |

### 📦 **Rastreamento (Melhor Envio + Correios)**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{tracking_code}` | Código de rastreio | AB646739409BR |
| `{tracking_url}` | Link de rastreamento | https://melhorrastreio.com.br/app/correios/AB646739409BR |
| `{shipping_company}` | Nome da transportadora | Correios / PAC |

**Compatibilidade:**
- ✅ **Melhor Envio**: Campo `melhorenvio_tracking`
- ✅ **WooCommerce Shipment Tracking**: Campo `_wc_shipment_tracking_items`
- ✅ **Plugins Genéricos**: Campos `_tracking_code` e `_tracking_number`
- ✅ **Fallback Inteligente**: Se não encontrar, retorna vazio (não mostra a variável)

### 🏪 **Dados da Loja**
| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `{store_name}` | Nome da loja | Minha Loja |
| `{store_url}` | URL da loja | https://loja.com |
| `{store_email}` | Email da loja | contato@loja.com |

---

## ✅ **Validação no Checkout - Ultra-Robusta**

### 📱 **Validação Brasileira Avançada**
- **Múltiplos Formatos**: Aceita 8, 9, 10, 11, 12, 13 dígitos
- **DDDs Válidos**: Validação de códigos 11-99
- **Formato Antigo**: Detecta e corrige celulares sem o 9
- **Zero Erros**: Nunca mais "Formato de telefone inválido"

### ⚡ **Funcionalidades**
- **Tempo Real**: Validação enquanto digita
- **Modal Inteligente**: Confirmação quando número inválido
- **Compatibilidade**: Não interfere com máscaras existentes
- **Customização**: Títulos e mensagens personalizáveis

---

## 🧠 **Sistema Inteligente de Fallback**

### 📍 **Endereços de Envio**
- **Detecção Automática**: Quando endereço de entrega está vazio
- **Fallback Inteligente**: Usa dados de cobrança automaticamente
- **Compatibilidade**: Funciona com qualquer configuração do WooCommerce
- **Zero Configuração**: Funciona automaticamente

### 📱 **Validação Ultra-Robusta**
- **Números Brasileiros**: Aceita 8, 9, 10, 11, 12, 13 dígitos
- **DDDs Válidos**: Validação de códigos 11-99
- **Formato Antigo**: Detecta e corrige celulares sem o 9
- **Zero Erros**: Nunca mais "Formato de telefone inválido"

---

## 🤖 **Sistema de Agente de IA - Documentação Completa**

### 🎯 **Configuração e Uso**

#### **1. Configuração do Agente de IA**
```
1. Acesse: WP WhatsApp Evolution > Agente de IA
2. Selecione "Agente de IA" como modalidade
3. Configure o webhook do n8n
4. Personalize textos e cores do widget
5. Salve as configurações
```

#### **2. Configuração do Chat Simples**
```
1. Selecione "Chat Simples" como modalidade
2. Adicione respostas personalizadas:
   - Palavras-chave: "oi, olá, hello"
   - Resposta: "Olá! Como posso ajudar?"
3. Configure mensagem de fallback
4. Salve as configurações
```

### 📱 **Shortcodes Disponíveis**

#### **Widget de Chat**
```php
[wpwevo_ai_chat mode="window"]
```
**Parâmetros:**
- `mode`: "window" (padrão) ou "inline"

#### **Formulário de Contato**
```php
[wpwevo_ai_form title="Fale Conosco" button="Enviar" show_phone="true"]
```
**Parâmetros:**
- `title`: Título do formulário
- `button`: Texto do botão
- `show_phone`: "true" ou "false"

### 🔄 **Sistema de Fallback Automático**

#### **Como Funciona**
1. **Agente de IA ativo** → Tenta enviar para webhook n8n
2. **Se webhook falhar** → Automaticamente ativa Chat Simples
3. **Chat Simples ativo** → Responde com keywords locais
4. **Quando webhook volta** → Retorna automaticamente ao Agente de IA

#### **Detecção de Falhas**
- ❌ **Erro de conexão**: `wp_remote_post` falha
- ❌ **Erro HTTP 4xx/5xx**: Webhook retorna erro
- ❌ **Timeout**: Webhook não responde em 20 segundos

#### **Transparência**
- ✅ **Usuário não percebe**: Chat continua funcionando
- ✅ **Aviso discreto**: "💡 Chat Simples ativo - Agente de IA temporariamente indisponível"
- ✅ **Recuperação automática**: Volta ao Agente de IA quando possível

### 🌐 **Integração com n8n**

#### **Payload Enviado**
```json
{
  "chatInput": "mensagem do usuário",
  "sessionId": "chat_123456",
  "metadata": {
    "source": "n8n_chat_widget",
    "sourceType": "chat_widget",
    "page_url": "https://seusite.com",
    "page_title": "Título da Página",
    "user_agent": "Mozilla/5.0...",
    "timestamp": "2025-01-25T15:45:47+00:00",
    "responseConfig": {
      "shouldRespond": true,
      "responseTarget": "chat_widget"
    },
    "wordpress": true,
    "site_url": "https://seusite.com",
    "ajax_proxy": true
  }
}
```

#### **Formulário Web (Canal Especial)**
```json
{
  "channel": "web_form",
  "chatInput": "**Nova solicitação via formulário:**\n\n**Nome:** João\n**E-mail:** joao@email.com\n**Telefone:** 5511999999999\n**Mensagem:** Preciso de ajuda\n**Página:** https://seusite.com/contato\n**Data:** 25/01/2025 12:45",
  "sessionId": "5511999999999@s.whatsapp.net",
  "remoteJid": "5511999999999@s.whatsapp.net",
  "pushName": "João",
  "contact": {
    "nome": "João",
    "email": "joao@email.com",
    "telefone": "11999999999"
  },
  "provider": {
    "instanceName": "sua_instancia",
    "serverUrl": "https://seu-servidor.com",
    "apiKey": "sua_api_key"
  }
}
```

### 🎨 **Personalização do Widget**

#### **Cores Personalizadas**
- **Cor primária**: Personalize a cor principal do widget
- **CSS automático**: Aplica variáveis CSS para compatibilidade
- **Temas**: Suporte a temas claro/escuro

#### **Textos Personalizáveis**
- **Título**: "Olá! 👋"
- **Subtítulo**: Mensagem de boas-vindas
- **Placeholder**: Texto do campo de input
- **Botão**: "Nova conversa"

### 📱 **Funcionalidades WhatsApp**

#### **Validação de Números**
- ✅ **Verificação em tempo real**: Valida números durante digitação
- ✅ **Formato E.164**: Converte para formato internacional
- ✅ **Validação AJAX**: Usa sistema de validação existente

#### **Envio de Mensagens**
- ✅ **Hooks WordPress**: Usa sistema existente do plugin
- ✅ **Formatação automática**: Adapta mensagens para WhatsApp
- ✅ **Rastreamento**: Inclui metadados da conversa

### 🚨 **Solução de Problemas**

#### **Chat Simples sempre ativo**
```
Verificar:
1. Configuração: wpwevo_ai_mode = 'simple_chat'
2. Webhook configurado corretamente
3. Logs de erro no WordPress
```

#### **Webhook não recebe mensagens**
```
Verificar:
1. URL do webhook está correta
2. Fluxo n8n está ativo
3. Permissões de CORS
4. Logs do n8n
```

#### **Fallback não funciona**
```
Verificar:
1. Função should_use_simple_chat()
2. Configurações do Chat Simples
3. Logs de erro PHP
```

### 📚 **Exemplos de Uso**

#### **Resposta Simples com Keywords**
```json
{
  "keywords": ["horario", "funcionamento", "aberto"],
  "response": "Nosso horário é de segunda a sexta, das 8h às 18h! ⏰"
}
```

#### **Resposta com Fallback**
```json
{
  "keywords": ["produto", "preço", "comprar"],
  "response": "Temos uma variedade de produtos! 🛍️ O que você está procurando?"
}
```

---

## 📊 **Sistema Completo de Logs**

### 🔍 **Níveis de Log**
- **Error**: Erros críticos do sistema
- **Warning**: Avisos importantes  
- **Info**: Informações gerais
- **Debug**: Apenas em modo debug

### 💾 **Armazenamento**
- **Banco de Dados**: Tabela `wpwevo_logs`
- **WooCommerce**: Integração com sistema de logs do WC
- **Limpeza**: Automática para manter performance

### 📋 **Histórico de Envios**
- **Envio em Massa**: Logs completos com sucesso/erro
- **Carrinho Abandonado**: Logs em tempo real
- **Limpeza**: Função para limpar histórico antigo

---

## 🔧 **Requisitos**

- **PHP:** 7.4 ou superior
- **WordPress:** 5.8 ou superior  
- **WooCommerce:** 5.0 ou superior
- **Evolution API:** Instância configurada (ou use o Quick Signup!)

---

## 🚀 **Instalação e Configuração**

### 🎯 **Opção 1: Download do GitHub (Recomendado)**

```bash
1. Baixe o ZIP direto do GitHub
2. Envie para /wp-content/plugins/ (pode ficar como "wp-whatsevolution-main")  
3. Ative no painel WordPress
4. Use "🚀 Teste Grátis" para configuração automática
```

### 🎯 **Opção 2: Configuração Manual**

```bash
1. URL da API: https://sua-api.com
2. API Key: sua-chave-aqui
3. Instância: nome-da-instancia
4. Testar Conexão ✅
```

### 3️⃣ **Ativação Carrinho Abandonado**

```bash
1. Instalar "WooCommerce Cart Abandonment Recovery"
2. Ir em "WhatsEvolution > Carrinho Abandonado" 
3. Ativar integração ✅
4. Personalizar template (opcional)
```

---

## 🔧 **Hooks para Desenvolvedores**

### 🎨 **Personalizar Mensagem Carrinho Abandonado**

```php
add_filter('wpwevo_cart_abandonment_message', function($message, $data) {
    $trigger_details = $data['trigger_details'];
    
    // Adicionar desconto para carrinhos de alto valor
    if (floatval($trigger_details['cart_total']) > 200) {
        $message .= "\n\n🎁 USE VOLTA10 e ganhe 10% OFF!";
    }
    
    return $message;
}, 10, 2);
```

### 📊 **Hook Após Envio Bem-Sucedido**

```php
add_action('wpwevo_cart_abandonment_sent', function($phone, $message, $trigger_details) {
    // Log personalizado
    error_log("WhatsApp enviado para: {$phone}");
    
    // Integração com outros sistemas
    // ...
}, 10, 3);
```

### 🎯 **Personalizar Validação de Telefone**

```php
add_filter('wpwevo_validate_phone', function($phone, $original) {
    // Lógica personalizada de validação
    if (strlen($phone) < 10) {
        return false;
    }
    
    return $phone;
}, 10, 2);
```

---

## 📊 **Todas as Funcionalidades**

### 🚀 **Quick Signup (Onboarding 1-Click)**
- ✅ Teste grátis por 7 dias
- ✅ Configuração automática em 30s
- ✅ Sem VPS/Docker necessário
- ✅ Suporte técnico incluído
- ✅ Upgrade simplificado

### 🛒 **Carrinho Abandonado**
- ✅ Interceptação interna automática
- ✅ Templates personalizáveis  
- ✅ Shortcodes dinâmicos
- ✅ Logs em tempo real
- ✅ Formatação brasileira (R$)

### 📊 **Envio por Status**
- ✅ Automação por status de pedido
- ✅ Templates por status
- ✅ Variáveis dinâmicas
- ✅ Sistema de fallback inteligente

### 🎯 **Envio em Massa Avançado**
- ✅ **4 Abas Especializadas**: Clientes WooCommerce, Todos os Clientes, Importar CSV, Lista Manual
- ✅ **Filtros de Valor**: Segmentação por valor mínimo e máximo de pedidos
- ✅ **Sistema de Intervalo Inteligente**: Modo fixo (5-60s) e aleatório (2-9s)
- ✅ **Filtro de Aniversário**: Segmentação por mês de nascimento
- ✅ **Compatibilidade Total**: Suporte a `billing_phone`, `billing_cellphone` e `phone`
- ✅ **Variáveis Completas**: 6+ variáveis por aba com substituição inteligente
- ✅ **Preview Inteligente**: Visualização antes do envio
- ✅ **Histórico Completo**: Rastreamento de todos os envios
- ✅ **Importação CSV Inteligente**: Detecção automática de colunas
- ✅ **Controle de Velocidade**: Prevenção de spam e bloqueios
- ✅ **Interface Reescrita**: Sistema moderno e intuitivo

### 📱 **Envio Individual**
- ✅ Interface simples
- ✅ Validação automática
- ✅ Histórico de envios
- ✅ Variáveis da loja

### ✅ **Validação Checkout**
- ✅ Campo obrigatório
- ✅ Validação tempo real
- ✅ Formatação automática
- ✅ Modal de confirmação
- ✅ Validação ultra-robusta

### 🤖 **Agente de IA (v1.4.0)**
- ✅ **Modalidades**: Agente de IA, Chat Simples e Fallback automático
- ✅ **Webhook n8n**: Integração segura com fluxos de automação
- ✅ **Chat Simples**: Sistema local de keywords → respostas
- ✅ **Fallback inteligente**: Transição transparente quando webhook falha
- ✅ **Widget integrado**: Chat responsivo com shortcode `[wpwevo_ai_chat]`
- ✅ **Formulário web**: Conversão automática para formato WhatsApp
- ✅ **Metadados ricos**: Contexto completo da página e usuário
- ✅ **Personalização**: Cores, textos e comportamento configuráveis

### 🧠 **Sistema Inteligente**
- ✅ Fallback automático de endereços
- ✅ Validação brasileira avançada
- ✅ Logs completos
- ✅ Compatibilidade total

---

## 🔒 **Segurança e Privacidade**

### 🛡️ **Proteções Implementadas**
- **Nonces**: Todos os AJAX protegidos
- **Capabilities**: `manage_options` para admin
- **Sanitização**: Input sanitization em todos os campos
- **Validação**: Números de telefone e dados críticos

### 🔒 **Dados Sensíveis**
- **Modo Managed**: Dados processados pelo backend Supabase
- **Modo Manual**: 100% local, sem calls externos
- **Logs**: Sem dados sensíveis nos logs
- **Limpeza**: Desinstalação completa via `uninstall.php`

---

## 📞 **Suporte**

### 🆘 **Canais de Ajuda**
- **Documentação**: Este README
- **Issues**: GitHub Issues
- **Quick Signup**: Suporte incluído no período de teste

### 🐛 **Reportar Bugs**
1. Verifique se é um bug real
2. Teste em ambiente limpo
3. Inclua logs de erro
4. Descreva passos para reproduzir

---

## 📄 **Licença**

Este projeto está sob a licença GPL v2 ou posterior.

---

## 🤝 **Contribuição**

Contribuições são bem-vindas! Por favor:

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

---

**🎉 Obrigado por usar o WP WhatsEvolution!** 
