# Configurações globais

O plugin usa configurações globais para dados compartilhados por todas as origens de captura.

## Página no admin

A página fica em:

```text
Configurações > CRM Leads Capture
```

Campos disponíveis:

- **API key Brevo**: chave usada para autenticar chamadas à API da Brevo.
- **Lista padrão Brevo**: list ID usado quando um material gratuito não define uma lista própria.
- **Mensagens de erro**: textos públicos exibidos perto do formulário quando a captura falha.

## Constantes

As constantes têm prioridade sobre os valores salvos no banco:

```php
define( 'CRM_LEADS_CAPTURE_BREVO_API_KEY', 'sua-chave' );
define( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID', 123 );
```

Quando `CRM_LEADS_CAPTURE_BREVO_API_KEY` está definida, o campo de API key no admin fica desabilitado.

Quando `CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID` está definida, o campo de lista padrão no admin fica desabilitado.

## Mensagens para usuários

As mensagens configuráveis são indexadas pelos códigos internos controlados do
plugin, como `invalid_lead`, `brevo_permission_error` e `brevo_error`.

Esses textos são seguros para exibição pública e não recebem resposta bruta da
Brevo. Detalhes técnicos continuam apenas nos logs quando `WP_DEBUG` está ativo.

Se um campo de mensagem ficar vazio no admin, o plugin usa o texto padrão para
aquele código. Use `brevo_error` como mensagem genérica para falhas não
classificadas.

## Segurança

Não versione chaves reais em arquivos do projeto.

Para ambientes de produção, prefira definir `CRM_LEADS_CAPTURE_BREVO_API_KEY` no `wp-config.php` ou em mecanismo seguro de configuração do ambiente.

Se a API key for salva pelo admin, ela fica armazenada no banco de dados do WordPress. O campo não exibe o valor salvo; deixar o campo em branco mantém a chave existente.

## Relação com materiais gratuitos

Para cada material gratuito, o plugin tenta usar primeiro o metadado:

```text
_crm_leads_capture_list_id
```

Se esse metadado estiver vazio, o plugin usa a lista padrão global.
