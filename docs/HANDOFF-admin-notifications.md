# Handoff — Correção: notificação ao admin não é enviada

> **Status:** ✅ **implementado e validado** na v1.6.1 (01/09/2026).
> **Branch:** `claude/order-message-admin-issue-xrsbhc`
>
> O diagnóstico abaixo (seções 1 e 2) está preservado como registro. As correções da seção 4
> foram implementadas e a matriz da seção 5 foi executada por inteiro — resultado na seção 9.
>
> **Sobre o achado "`handle_new_order()` é código morto" (seção 2):** confirmado como
> **decisão deliberada, não bug**. Enganchar `woocommerce_new_order` duplicaria a mensagem para
> quem usa gateway de pagamento. Não corrigir era o certo — ver cenário 12 na seção 9.

---

## 1. O sintoma relatado

Um usuário em produção relata que **a mensagem de pedido não chega ao admin**. O cliente recebe
normalmente. Nenhum erro aparece nos logs do WordPress dele.

Versão do plugin: **1.6.0** (`wp-whatsevolution.php`).
A funcionalidade de notificação ao admin foi introduzida na **v1.4.6**, pelo commit
**`0af9bc1`** — *"feat(admin-notifications): add WhatsApp admin notifications and validation"*
(06/11/2025).

---

## 2. Diagnóstico — três erros de implementação no commit `0af9bc1`

A investigação partiu do commit que introduziu a feature (`git show 0af9bc1`). Os três problemas
abaixo estão confirmados por leitura do diff e do código atual.

### Erro 1 — o bloco do admin foi colado dentro do `else` do envio ao cliente

O hunk do diff é `@@ -842,6 +883,48 @@`: o código novo foi anexado logo depois do
`$order->add_order_note(...)`, que é a **última instrução do `else`** do envio ao cliente.

Antes do commit (`git show 0af9bc1^:includes/class-send-by-status.php`), `handle_status_change()`
era um pipeline linear e exclusivamente do cliente:

```
guard: status enabled?      -> return
guard: message vazia?       -> return
guard: pedido tem telefone? -> return
guard: API configurada?     -> return
envia cliente
   |- falhou -> log de erro
   \- else   -> nota no pedido
                 [<- o bloco do admin foi enxertado AQUI]
```

O autor abriu o arquivo, foi até o fim da função e escreveu o código novo. O aninhamento foi
herdado sem intenção. A prova disso está no comentário que ele mesmo escreveu na linha do log —
*"Log de erro caso falhe o envio ao admin (não afeta o cliente)"* — e no CHANGELOG da v1.4.6:
*"Independência: falha no envio ao admin não afeta cliente"*. A independência foi documentada e
implementada ao contrário.

**Consequência:** hoje o admin só é notificado se **todas** estas condições, que não têm relação
nenhuma com o admin, forem verdadeiras:

1. o checkbox "Ativar" (`enabled`) do status estiver marcado;
2. a mensagem **do cliente** naquele status não estiver vazia;
3. o pedido tiver telefone de cliente válido (senão a função dá `return` antes);
4. o envio ao cliente retornar `success`.

**Regra de negócio correta (confirmada com o dono do produto):** as duas mensagens são
*sequenciais por limitação da API* (não dá para enviar duas numa chamada só), mas são
**logicamente independentes**. O admin deve ser notificado mesmo que o número do cliente esteja
errado, mesmo que o pedido não tenha telefone, e mesmo que a mensagem ao cliente esteja desligada
naquele status.

Localização atual do bloco enxertado: `includes/class-send-by-status.php:968-1003`.

### Erro 2 — o campo "WhatsApp Admin" herdou `disabled($is_managed)` por copiar-colar

O commit inseriu o campo logo depois do bloco "Nome da Instância" em
`includes/class-settings-page.php`, copiando o bloco vizinho inteiro — **incluindo**
`<?php disabled($is_managed, true); ?>` (hoje na linha `591`).

Esse flag **não** é novo: existe desde `ac6c8ed` e tem propósito legítimo — proteger as
**credenciais da Evolution API** (URL, API key, instância), que no modo `managed` são provisionadas
automaticamente e o usuário não deve editar. O número do WhatsApp do admin **não é credencial**,
é preferência do lojista. Não havia motivo para bloqueá-lo.

**Consequência:** quem ativa pelo fluxo automático/pago cai em `wpwevo_connection_mode = 'managed'`
(`includes/class-quick-signup.php:332`) e **não consegue nem digitar nem salvar** o número.
O botão "Salvar Configurações" do mesmo formulário também está desabilitado no modo managed
(`class-settings-page.php:601`). Campo desabilitado não é enviado no POST.

**Agravantes estruturais:**

- A opção `wpwevo_admin_whatsapp` só é gravada **dentro de `validate_settings()`**
  (`class-settings-page.php:155`), que é o endpoint AJAX de validação de **credenciais** e exige
  `api_url` + `api_key` + `instance` preenchidos (`:133`). Uma preferência de usuário foi
  estacionada dentro do salvamento de credenciais. Por isso um usuário em modo `sms`
  (que não tem credenciais Evolution) também não consegue salvar o número.
- Como esse `update_option` roda com `$admin_whatsapp = $_POST['admin_whatsapp'] ?? ''`, um save
  de credenciais em modo manual com o campo ausente do POST **sobrescreve o número com vazio**.
- O commit adicionou `register_setting('wpwevo_settings', 'wpwevo_admin_whatsapp', ...)` (`:68`)
  que **nunca é usado** — o form é interceptado por AJAX em `assets/js/admin.js:3` e os `name`
  dos campos nem batem com os nomes das options. É código morto que dá falsa sensação de
  persistência.

### Erro 3 — falha silenciosa

`if (!empty($admin_phone))` (`class-send-by-status.php:972`) não tem `else` e não loga nada.
Quando o número está vazio — o que é **garantido** no modo managed — não sai absolutamente nada,
em lugar nenhum. Todos os outros caminhos de falha dessa função logam; esse não. Foi por isso que
o cliente não achou nada nos logs.

### Achados menores (mesmo commit / mesma área)

- **`handle_new_order()` é código morto.** A função existe em `class-send-by-status.php:830` mas
  **não está registrada em nenhum hook**. O único hook é
  `woocommerce_order_status_changed` (`:60`). Pedido criado já em status final, sem transição
  (gateway/importação/API), não dispara nada — nem cliente nem admin. **Não corrigir neste PR**
  (fora de escopo, mexe no fluxo do cliente); apenas registrar como dívida técnica.
- **`{order_url}` na mensagem padrão do admin** (`:986`) resolve para `get_view_order_url()` —
  link da área do cliente, que o admin não consegue abrir.
- **`handle_save_messages()` não chama `setup()`** se `$this->available_statuses` estiver vazio
  (`:751`), diferente de `handle_status_change()` que chama (`:904-906`). Se estiver vazio, salva
  `[]` e **apaga todas as configurações**. Probabilidade baixa em AJAX (o `init` já rodou), mas é
  inconsistência real.
- **Seletor frágil no JS** (`assets/js/send-by-status.js:268`):
  `$form.find('div[style*="border-left: 4px solid"]')` depende do inline style exato. Se algum
  bloco não tiver o checkbox `[enabled]`, `$checkboxEnabled.attr('name')` vira `undefined` e o
  `.match()` estoura um TypeError, matando o submit inteiro. Hoje o markup garante o checkbox.

---

## 3. Decisões já tomadas — não re-discutir

| # | Decisão | Motivo |
|---|---|---|
| 1 | **Manter o checkbox "🔔 Notificar Admin" por status** e a textarea de mensagem por status, como estão hoje | Já existem, já são persistidos, e a UI já entrega a granularidade desejada. Zero migração, zero risco para quem já configurou. |
| 2 | **Mensagem do admin em branco continua usando o template padrão** | Mudar para "branco = não envia" faria quem depende do template padrão parar de receber silenciosamente. |
| 3 | **O checkbox `enabled` do status passa a governar apenas a mensagem do cliente** | É o que realiza a independência pedida. Ver "risco de mudança de comportamento" abaixo. |
| 4 | **Não tocar no mecanismo de envio** (`Api_Connection::send_message()`) | Já funciona em produção; é o que entrega a mensagem do cliente hoje. |
| 5 | **Não corrigir `handle_new_order()` neste PR** | Fora de escopo; mexeria no fluxo do cliente. |

---

## 4. Plano de implementação

### 4.1 Separar a notificação do admin em um callback próprio

**Abordagem escolhida:** registrar um **segundo callback no mesmo hook**, em vez de refatorar a
função existente. Isso mantém o código do cliente **byte a byte idêntico** ao que roda hoje em
produção — o diff deixa isso evidente e o risco de regressão no fluxo do cliente é nulo.

Em `includes/class-send-by-status.php`, no construtor (hoje linha `60`):

```php
// Hook para mudança de status
add_action('woocommerce_order_status_changed', [$this, 'handle_status_change'], 10, 4);
// Notificação ao admin — independente do envio ao cliente
add_action('woocommerce_order_status_changed', [$this, 'handle_admin_notification'], 20, 4);
```

Prioridade 20 preserva a ordem atual (cliente primeiro, admin depois) sem criar dependência.

Depois:

1. **Remover** o bloco `968-1003` de dentro do `else` de `handle_status_change()`, restaurando a
   função ao estado pré-`0af9bc1`. Conferir com:
   `git diff 0af9bc1^ -- includes/class-send-by-status.php` (o corpo do cliente deve voltar a ser
   equivalente).
2. **Criar** `handle_admin_notification($order_id, $old_status, $new_status, $order)` com guards
   próprios, dependendo **apenas** do que importa para o admin:
   - WooCommerce ativo e `$order` válido (mesmos guards de contexto);
   - `$this->available_statuses` inicializado (`if (empty(...)) $this->setup();`);
   - `!empty($settings[$new_status]['notify_admin'])` — **e nada de `enabled`**;
   - `$admin_phone = get_option('wpwevo_admin_whatsapp')` não vazio → **senão, `wpwevo_log('warning', ...)`**;
   - `$api->is_configured()` → senão, log;
   - monta a mensagem (custom ou template padrão), `replace_variables()`, envia, e registra nota
     no pedido / log de erro como já faz hoje.

### 4.2 Liberar o campo do número e dar a ele um caminho de salvamento próprio

Em `includes/class-settings-page.php`:

1. **Remover `disabled($is_managed, true)` apenas do input `admin_whatsapp`** (`:591`).
   Os três campos de credencial continuam protegidos exatamente como estão.
2. **Tirar o campo de dentro do form de credenciais** e colocá-lo em um card próprio, com botão e
   AJAX próprios (ex.: `wp_ajax_wpwevo_save_admin_whatsapp` + nonce próprio). Assim funciona nos
   três modos — managed, manual e sms — e deixa de depender de credenciais Evolution.
3. **Remover o `update_option('wpwevo_admin_whatsapp', ...)` órfão de dentro de
   `validate_settings()`** (`:154-155`) — é ele que hoje pode zerar o número num save de credenciais.
4. Avaliar remover o `register_setting` morto (`:67-72`) ou passar a usá-lo de fato.
5. **Modo managed:** oferecer `get_option('wpwevo_user_whatsapp')` (número informado no cadastro,
   gravado em `class-quick-signup.php:339`) como **sugestão** com um botão "usar este número".
   **Não copiar automaticamente** — começar a enviar para um número que o lojista nunca confirmou
   é pior que não enviar.

### 4.3 Tornar a falha visível

- `wpwevo_log('warning', ...)` quando `notify_admin` está ligado mas o número está vazio.
- Botão "enviar teste para o admin" na tela de configuração.

### 4.4 Melhoria barata: link do pedido para o admin

Adicionar a variável **`{admin_order_url}`** (`$order->get_edit_order_url()`) em
`replace_variables()` (`class-send-by-status.php:224+`) e usá-la no template padrão do admin
(`:986`). **Variável nova** — não alterar a semântica de `{order_url}`, que já está nas mensagens
de cliente de todas as instalações.

### 4.5 Documentação

Atualizar README/CHANGELOG deixando explícito (o README está desatualizado — cabeçalho diz v1.4.9,
o plugin está em 1.6.0):

> O checkbox "Ativar" de cada status controla **apenas a mensagem enviada ao cliente**.
> O sininho "🔔 Notificar Admin" é independente: marque nos status em que quiser ser avisado,
> preencha o número em Conexão → WhatsApp Admin, e pronto. Se a mensagem do admin ficar em branco,
> é usado o template padrão. O admin é notificado mesmo que o envio ao cliente falhe ou que o
> pedido não tenha telefone.

E bumpar a versão em `wp-whatsevolution.php` (`Version:`) + `WPWEVO_VERSION` + `readme.txt` +
`CHANGELOG.md`.

---

## 5. Plano de teste no ambiente local (XAMPP)

O ponto de atenção **não** é o admin: é garantir que o fluxo do cliente, usado pela maioria dos
usuários, não regrida. A matriz abaixo é o critério de aceite.

| # | Cenário | Esperado |
|---|---|---|
| 1 | Cliente com número válido, `notify_admin` **off** | Cliente recebe. Admin não. Igual a hoje. |
| 2 | Cliente com número válido, `notify_admin` **on** | Cliente recebe **e** admin recebe. |
| 3 | Cliente com número **inválido**, `notify_admin` on | Cliente falha e loga; **admin recebe**. ← bug principal |
| 4 | Pedido **sem telefone** do cliente, `notify_admin` on | **Admin recebe** (hoje não recebe). |
| 5 | `enabled` **off** + `notify_admin` on | Só o admin recebe. |
| 6 | `enabled` off + `notify_admin` off | Nenhuma chamada à API. |
| 7 | `notify_admin` on + **número do admin vazio** | Nada é enviado, mas **loga warning**. |
| 8 | Mensagem do admin em branco | Envia o **template padrão**. |
| 9 | Salvar o número nos 3 modos: `managed`, `manual`, `sms` | Persiste em `wpwevo_admin_whatsapp` nos três. |
| 10 | Salvar credenciais em modo manual | **Não** zera `wpwevo_admin_whatsapp`. |
| 11 | Instalação que só usa mensagem de cliente (sem admin configurado) | Comportamento idêntico ao da v1.6.0. |

Cenários 3 e 4 são os que provam a correção. Cenários 1, 6 e 11 são os que provam que nada quebrou.

**Dicas de execução:**

- Para o cenário 3 sem depender de um número real inválido, dá para forçar a falha com um filtro
  `pre_http_request` num mu-plugin de teste, ou apontar para um número claramente inexistente.
- Testar em **HPOS ligado e desligado** (WooCommerce → Configurações → Avançado → Armazenamento de
  pedidos). `woocommerce_order_status_changed` dispara igual nos dois, mas vale confirmar.
- Conferir compatibilidade com **PHP 7.4** (o plugin declara 7.4+): sem `match`, sem named
  arguments, sem promoção de propriedades no construtor.
- `php -l` em todos os arquivos alterados.

---

## 6. Riscos conhecidos

1. **Mudança de comportamento intencional (decisão 3):** uma instalação que hoje tenha
   `notify_admin = true` **e** `enabled = false` num status não recebe nada; depois da correção,
   passa a receber. É o comportamento desejado, e na prática deve afetar ~zero instalações, já que
   a feature está quebrada no modo managed desde o lançamento. Mencionar no CHANGELOG.
2. **Dois callbacks no mesmo hook:** um fatal error no callback do cliente impediria o do admin de
   rodar (e vice-versa). Manter os guards defensivos em ambos.
3. **Ordem das mensagens:** com prioridade 20, o admin recebe depois do cliente — igual a hoje.
   Se o envio ao cliente for lento (timeout de 15s na Evolution API), a do admin atrasa na mesma
   proporção. Aceitável.

---

## 7. Como retomar no PC

```powershell
git fetch origin
git checkout claude/order-message-admin-issue-xrsbhc
git pull origin claude/order-message-admin-issue-xrsbhc
```

Depois, abrir o Claude Code na pasta do repositório e começar com algo assim:

> Leia `docs/HANDOFF-admin-notifications.md` por inteiro. Ele contém o diagnóstico completo de um
> bug já investigado e as decisões já tomadas — não re-investigue nem re-discuta as decisões da
> seção 3. Implemente a seção 4 e depois vamos validar juntos a matriz de testes da seção 5 no meu
> XAMPP, com uma instância Evolution real.

Comandos úteis para reconferir o diagnóstico:

```bash
git show 0af9bc1                                    # o commit que introduziu a feature
git show 0af9bc1^:includes/class-send-by-status.php # como era antes (fluxo só do cliente)
```

Diagnóstico do site afetado (WP-CLI), se precisar confirmar a causa em produção:

```bash
wp option get wpwevo_connection_mode      # provável: managed
wp option get wpwevo_admin_whatsapp       # provável: vazio
wp option get wpwevo_user_whatsapp        # número do cadastro, se houver
wp option get wpwevo_status_messages --format=json
```

Workaround imediato para o usuário afetado, sem esperar a correção:

```bash
wp option update wpwevo_admin_whatsapp 5511999999999
```

(e conferir que o sininho "Notificar Admin" está marcado no status que ele usa, e que a mensagem
ao cliente naquele status está funcionando — hoje o admin depende dela).

---

## 8. Arquivos que a correção deve tocar

| Arquivo | O quê |
|---|---|
| `includes/class-send-by-status.php` | Novo hook (`:60`); remover bloco `968-1003` do `else`; criar `handle_admin_notification()`; `{admin_order_url}` em `replace_variables()` |
| `includes/class-settings-page.php` | Remover `disabled` do campo (`:591`); card + AJAX próprios; remover `update_option` órfão (`:154-155`); sugestão do número do cadastro no modo managed |
| `assets/js/admin.js` | Handler do novo botão de salvar o número |
| `README.md` / `readme.txt` / `CHANGELOG.md` | Documentar a semântica correta; bump de versão |
| `wp-whatsevolution.php` | `Version:` + `WPWEVO_VERSION` |

---

## 9. Resultado da validação (01/09/2026)

### Ambiente

Cópia de staging isolada do site de produção `hortolandia.relaxnarguiles.com`, no mesmo servidor:
arquivos em `/www/wwwroot/staging-wpwevo`, banco `wpwevo_stg` (clone das 78 tabelas, 887 pedidos).
Instância Evolution **real e compartilhada** (`RelaxHortolandia`, Evolution v2.3.7) — os envios de
WhatsApp foram de verdade. WooCommerce com HPOS.

A produção não foi tocada: plugin permaneceu em 1.6.0, 887 pedidos, nenhum arquivo alterado.

No staging foram desativados os plugins que disparam para sistemas externos em evento de pedido
(`sige-importer`, PagBank, Melhor Envio, Jetpack, recuperação de carrinho, `wp-whatsapp-chat`),
para que os pedidos de teste não vazassem para o ERP nem para gateways. E-mails transacionais
foram bloqueados no harness.

Números usados: cliente `5519989881838`, admin `5519998874934`.

### Matriz da seção 5

| # | Cenário | Resultado | Evidência |
|---|---|---|---|
| 1 | Cliente válido, `notify_admin` off | ✅ PASSOU | 1 chamada, destino CLIENTE |
| 2 | Cliente válido, `notify_admin` on | ✅ PASSOU | 2 chamadas: CLIENTE + ADMIN |
| 3 | **Envio ao cliente falha, `notify_admin` on** | ✅ PASSOU | cliente falhou e logou; ADMIN recebeu |
| 4 | **Pedido sem telefone, `notify_admin` on** | ✅ PASSOU | 0 ao cliente, 1 ao ADMIN |
| 5 | `enabled` off + `notify_admin` on | ✅ PASSOU | só o ADMIN recebeu |
| 6 | `enabled` off + `notify_admin` off | ✅ PASSOU | 0 chamadas à API |
| 7 | `notify_admin` on + número do admin vazio | ✅ PASSOU | 0 envios + `warning` em `wpwevo_logs` |
| 8 | Mensagem do admin em branco | ✅ PASSOU | template padrão, `{admin_order_url}` resolvido |
| 9 | Salvar o número em `managed`, `manual` e `sms` | ✅ PASSOU | persistiu nos três |
| 10 | Salvar credenciais em modo manual | ✅ PASSOU | número do admin sobreviveu |
| 11 | Instalação só com mensagem de cliente | ✅ PASSOU | 1 envio ao cliente, 0 logs novos |
| **12** | **Fluxo de gateway (não duplicar)** | ✅ PASSOU | **0 chamadas na criação do pedido** |

Cenários 3 e 4 provam a correção. 1, 6 e 11 provam que o fluxo do cliente não regrediu.
O 12 é o guarda-corpo descrito abaixo.

### Cenário 12 — por que ele existe

Uma primeira tentativa desta correção subiu junto um trabalho local que enganchava
`woocommerce_new_order`. Isso foi **revertido**: na maioria das lojas o pedido nasce por um gateway
de pagamento, que define o status logo em seguida. Com os dois hooks ativos o cliente receberia a
mensagem do status inicial **e** a da transição — duas mensagens por pedido, para todo mundo, para
resolver um caso isolado de quem cria pedido sem gateway.

O cenário 12 configura `pending` e `processing` ligados, cria o pedido (nasce em `pending`) e só
então move para `processing`, como faria um gateway. O harness conta as chamadas HTTP disparadas
**na criação**: tem que ser zero, e o total tem que ser uma mensagem ao cliente e uma ao admin.
É o teste que trava a volta desse comportamento.

### Verificações extras

- **HPOS ligado e desligado**: cenários 3 e 12 repetidos com
  `woocommerce_custom_orders_table_enabled=no`, confirmado pela gravação em `wp_posts`
  (pedidos 78581/78582) em vez de `wp_wc_orders`. Ambos passaram.
- **Botão "Enviar Teste"**: entregou a mensagem ao número do admin.
- **Validação de número**: `save_admin_whatsapp` rejeita entrada inválida (`123`) com mensagem de erro.
- **`php -l`** limpo em todos os arquivos alterados.

### Não verificado

- **PHP 7.4.** O servidor de teste só tem PHP 8.0 instalado. O código foi revisado e não usa nada
  posterior ao 7.4 (sem `match`, sem argumentos nomeados, sem promoção de propriedades,
  sem `str_contains`/`str_starts_with`), mas não foi **executado** num runtime 7.4.
- **A interface no navegador.** O staging não tem vhost; toda a validação foi via CLI, exercitando
  os mesmos métodos que o AJAX chama. O card novo e os botões não foram vistos renderizados.
