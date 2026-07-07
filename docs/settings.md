# Configurações globais

O plugin usa configurações globais para dados compartilhados por todas as origens
de captura.

## Página no admin

A página fica em:

```text
Configurações > CRM Leads Capture
```

A tela é organizada em abas:

- **General**: define o provider padrão usado por materiais sem override.
  Também define uma URL de entrega padrão.
- **Messages**: personaliza mensagens públicas de sucesso e erro.
- **RD Station**: ativa o provider RD Station e configura suas credenciais e
  padrões de conversão.
- **Brevo**: ativa o provider Brevo e configura suas credenciais e lista padrão.

Mais de um provider pode estar ativo/configurado ao mesmo tempo. Cada material
pode escolher um provider específico; quando não escolhe, usa o provider padrão
da aba **General**.

## Estrutura da option

A option principal é:

```text
crm_leads_capture_settings
```

Campos principais:

```php
array(
    'active_provider' => 'brevo',
    'default_delivery_url' => 'https://example.com/obrigado',
    'providers'       => array(
        'brevo'      => array(
            'enabled'         => true,
            'api_key'         => '',
            'default_list_id' => 0,
        ),
        'rd_station' => array(
            'enabled'                       => true,
            'api_key'                       => '',
            'default_conversion_identifier' => '',
            'default_tags'                  => '',
        ),
    ),
)
```

## Constantes

As constantes têm prioridade sobre os valores salvos no banco:

```php
define( 'CRM_LEADS_CAPTURE_BREVO_API_KEY', 'sua-chave' );
define( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID', 123 );
define( 'CRM_LEADS_CAPTURE_RD_STATION_API_KEY', 'sua-chave' );
```

Constantes legadas da Brevo continuam lidas como fallback:

```php
define( 'BREVO_LEADS_CAPTURE_API_KEY', 'sua-chave' );
define( 'BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID', 123 );
```

Quando uma constante de credencial está definida, o campo correspondente no admin
fica desabilitado.

## Mensagens para usuários

## Campos RD Station

**Identificador de conversão** é o nome do evento enviado para a RD Station. Ele
ajuda a identificar qual captura gerou o lead, por exemplo
`Download - Guia ENEM`.

**Tags** são enviadas junto com o lead para segmentação e automações. Separe
múltiplas tags por vírgulas.

As mensagens configuráveis são indexadas pelos códigos internos controlados do
plugin, como `invalid_lead`, `provider_error`, `brevo_permission_error` e
`rd_station_error`.

Esses textos são seguros para exibição pública e não recebem resposta bruta do
CRM. Detalhes técnicos continuam apenas nos logs quando `WP_DEBUG` está ativo.

Se um campo de mensagem ficar vazio no admin, o plugin usa o texto padrão para
aquele código.

## Segurança

Não versione chaves reais em arquivos do projeto.

Para ambientes de produção, prefira definir credenciais por constantes no
`wp-config.php` ou em mecanismo seguro de configuração do ambiente.

Se uma API key for salva pelo admin, ela fica armazenada no banco de dados do
WordPress. O campo não exibe o valor salvo; deixar o campo em branco mantém a
chave existente.

## Relação com materiais gratuitos

Para cada material gratuito, o plugin tenta usar primeiro os metadados genéricos:

```text
_crm_leads_capture_provider
_crm_leads_capture_list_id
_crm_leads_capture_delivery_url
_crm_leads_capture_rd_station_conversion_identifier
_crm_leads_capture_rd_station_tags
```

Se o provider do material estiver vazio, o plugin usa o provider padrão global.
Para Brevo, se a lista por material estiver vazia, usa a lista padrão global.

Para URL de entrega, a ordem é:

1. `_crm_leads_capture_delivery_url` no material.
2. Fallbacks legados do material.
3. `default_delivery_url` configurada na aba **General**.

Se todas estiverem vazias, a captura falha com `missing_delivery`.
