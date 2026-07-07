# CRM Leads Capture

Plugin WordPress para centralizar captura de leads e envio para CRMs.

## Responsabilidade

Este plugin processa submissões de captura, valida nonce/honeypot/dados do lead,
monta um payload normalizado e delega o envio ao provider de CRM ativo.

O domínio persistente de materiais gratuitos continua fora deste plugin. Quando
o plugin `free-materials` estiver ativo, este plugin consome o CPT
`material_gratuito`, mas não registra CPT, taxonomia, rewrites ou templates
públicos completos.

## Providers

Providers iniciais:

- `brevo`: reaproveita o client de contatos da Brevo e suporta API key, lista
  padrão e lista por material.
- `rd_station`: envia conversões para a API Marketing do RD Station em
  `https://api.rd.services/platform/conversions`, com `event_type=CONVERSION`,
  `event_family=CDP` e payload de conversão.

O provider ativo é escolhido em `Configurações > CRM Leads Capture`.

## Configuração

Opção nova:

```php
crm_leads_capture_settings
```

Formato principal:

```php
array(
    'active_provider' => 'brevo',
    'providers'       => array(
        'brevo'      => array(
            'api_key'         => '',
            'default_list_id' => 0,
        ),
        'rd_station' => array(
            'api_key'                       => '',
            'default_conversion_identifier' => '',
            'default_tags'                  => '',
        ),
    ),
)
```

Constantes suportadas:

```php
define( 'CRM_LEADS_CAPTURE_BREVO_API_KEY', '...' );
define( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID', 123 );
define( 'CRM_LEADS_CAPTURE_RD_STATION_API_KEY', '...' );
```

Constantes antigas da Brevo continuam lidas como fallback:

```php
define( 'BREVO_LEADS_CAPTURE_API_KEY', '...' );
define( 'BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID', 123 );
```

Campos de credencial nunca exibem o valor salvo no admin.

## Materiais Gratuitos

Meta keys novas:

- `_crm_leads_capture_provider`
- `_crm_leads_capture_delivery_url`
- `_crm_leads_capture_list_id`
- `_crm_leads_capture_rd_station_conversion_identifier`
- `_crm_leads_capture_rd_station_tags`

Fallbacks legados preservados:

- `_brevo_leads_capture_list_id`
- `_brevo_leads_capture_delivery_url`
- `_executive_signal_material_capture_url`

## Endpoints

Admin post:

```text
action=crm_leads_capture_free_material
```

REST:

```text
POST /wp-json/crm-leads-capture/v1/free-material
GET  /wp-json/crm-leads-capture/v1/free-material/nonce
```

Compatibilidade temporária:

- action antigo `brevo_leads_capture_free_material`
- nonce/action antigo `brevo_leads_capture_free_material`
- campo REST antigo `brevo_leads_capture_nonce`

## Segurança

- Entradas são sanitizadas e nonces validados.
- Falhas externas não expõem API keys, payload bruto com dados pessoais nem
  resposta sensível do CRM no front-end.
- Logs técnicos só são emitidos quando `WP_DEBUG` está ativo e passam por
  redação de chaves, tokens, e-mails, telefones, payloads e bodies.

## Testes

Com PHP e dependências instaladas:

```bash
composer test:unit
composer test:wordpress
composer test
```
