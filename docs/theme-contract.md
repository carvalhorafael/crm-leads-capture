# Contrato com o tema

Este plugin concentra captura e envio para Brevo. O tema deve cuidar apenas da renderização.

## Responsabilidades do tema

- Renderizar o formulário.
- Postar para `admin_url( 'admin-post.php' )`.
- Incluir `action=crm_leads_capture_free_material`.
- Incluir nonce `crm_leads_capture_free_material`.
- Incluir `material_id`.
- Renderizar campos de nome, email e WhatsApp.
- Renderizar honeypot vazio.
- Preencher UTMs, se existirem.
- Manter layout, texto do botão e experiência visual.
- Renderizar o container de mensagem próximo ao formulário quando quiser exibir
  erros sem depender apenas da query string.

## Responsabilidades do plugin

- Validar nonce e honeypot.
- Validar material.
- Ler list ID e URL de entrega do material.
- Montar payload Brevo.
- Enviar contato para Brevo.
- Redirecionar em sucesso ou falha.
- Fornecer mensagem pública configurável para cada código de erro controlado.
- Fornecer endpoint JSON para progressive enhancement do formulário.
- Manter API key fora do tema.

## Action do formulário

```php
<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
```

Campo obrigatório:

```html
<input type="hidden" name="action" value="crm_leads_capture_free_material">
```

Nonce:

```php
wp_nonce_field( 'crm_leads_capture_free_material' );
```

Material atual:

```php
<input type="hidden" name="material_id" value="<?php echo esc_attr( get_the_ID() ); ?>">
```

Honeypot:

```html
<input type="text" name="crm_leads_capture_website" value="" autocomplete="off" tabindex="-1">
```

## Metadados por material

- `_crm_leads_capture_list_id`: lista Brevo específica do material.
- `_crm_leads_capture_delivery_url`: URL de entrega pós-captura.

Fallback temporário:

- `_executive_signal_material_capture_url`: usado como URL de entrega quando `_crm_leads_capture_delivery_url` está vazio.

## Campos enviados

- `name`
- `email`
- `whatsapp`
- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_term`
- `utm_content`

## Erros

O tema não deve tentar interpretar resposta da Brevo.

Em falha, o plugin redireciona com:

```text
crm_leads_capture=error
crm_error=<codigo-controlado>
```

Para exibir a mensagem junto do formulário no fallback server-rendered, o tema
pode chamar:

```php
<?php crm_leads_capture_render_free_material_error_message(); ?>
```

Ou usar o shortcode:

```text
[crm_leads_capture_error]
```

O markup gerado segue o padrão `OperationalFeedback` do Executive Signal Design
System: `es-panel es-operational-feedback`,
`data-feedback-tone="danger"`, badge `es-badge` e mensagem
`es-operational-feedback__message`. Também inclui
`data-crm-leads-capture-message`, `role="alert"` e `aria-live="polite"`.
Quando não há erro na query string, o container é renderizado vazio e oculto
para também servir ao enhancement em JavaScript.

As mensagens públicas são configuráveis em `Configurações > CRM Leads Capture`.
O tema deve tratar o texto como conteúdo pronto para exibição e não deve mapear
os códigos internamente.

## Progressive enhancement

O plugin enfileira um JavaScript leve que intercepta formulários com:

```html
<input type="hidden" name="action" value="crm_leads_capture_free_material">
```

Quando o JavaScript está disponível, a submissão é enviada para:

```text
POST /wp-json/crm-leads-capture/v1/free-material
```

Antes do POST, o script busca um nonce fresco, sem cookies e com cache
desabilitado, em:

```text
GET /wp-json/crm-leads-capture/v1/free-material/nonce
```

No envio REST, o script remove `_wpnonce` do payload e envia esse valor fresco
em `crm_leads_capture_nonce`. O campo `_wpnonce` continua existindo no HTML
para o fallback `admin-post.php`, mas não deve ser usado no POST REST porque o
WordPress reserva `_wpnonce` para a verificação nativa `wp_rest`.

Em sucesso, o plugin limpa os campos visíveis do formulário, exibe uma mensagem
configurável em `OperationalFeedback` com `data-feedback-tone="success"`, mostra
um link para acessar o material imediatamente e redireciona automaticamente após
5 segundos. Em falha, a URL da página não muda e a mensagem configurada é
exibida no container `data-crm-leads-capture-message` mais próximo do
formulário. Se o container não existir, o script cria um dentro do próprio
formulário usando o mesmo markup `OperationalFeedback` com
`data-feedback-tone="danger"`.

O script também envia o POST sem cookies. Como a captura é pública e validada
pelo nonce específico do formulário, isso evita que a autenticação por cookie da
REST API bloqueie a chamada antes do handler do plugin. Erros nativos da REST
API não são exibidos diretamente para o usuário; o script cai nas mensagens
públicas configuradas no plugin.

Quando uma mensagem foi renderizada por redirect com query string, o script
remove `crm_leads_capture` e `crm_error` da URL após o carregamento. Assim,
atualizar a página não mantém o mesmo erro visível por causa da URL antiga.

Sem JavaScript, o fluxo continua usando `admin-post.php` e redirect com query
string.
