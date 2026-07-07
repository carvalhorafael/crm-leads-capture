# Migração para CRM Leads Capture

Este repositório evoluiu de `brevo-leads-capture` para `crm-leads-capture`.

## Divisão de responsabilidades

O plugin `free-materials` permanece dono do domínio persistente:

- CPT `material_gratuito`
- taxonomia `material_categoria`
- metadados editoriais
- rewrites

O plugin `crm-leads-capture` é dono da captura e integração:

- endpoint `admin-post.php`
- endpoints REST
- nonce e honeypot
- normalização de lead
- escolha de provider CRM
- envio para CRM
- mensagens públicas configuráveis
- logs técnicos seguros

## Providers

O provider ativo fica em `crm_leads_capture_settings['active_provider']`.

Providers iniciais:

- `brevo`
- `rd_station`

Brevo usa o client HTTP existente para `https://api.brevo.com/v3/contacts`.

RD Station usa a API Marketing em:

```text
POST https://api.rd.services/platform/conversions?api_key=<api_key>
```

Payload RD Station:

```json
{
  "event_type": "CONVERSION",
  "event_family": "CDP",
  "payload": {
    "conversion_identifier": "Material gratuito",
    "email": "lead@example.com"
  }
}
```

A decisão acima foi baseada na documentação atual do RD Station consultada via
Context7 em 2026-07-07.

## Compatibilidade Brevo

Não apagar opções antigas automaticamente.

Fallbacks ainda lidos:

- opção `brevo_leads_capture_settings`
- opção `brevo_leads_capture_default_list_id`
- constante `BREVO_LEADS_CAPTURE_API_KEY`
- constante `BREVO_LEADS_CAPTURE_DEFAULT_LIST_ID`
- meta `_brevo_leads_capture_list_id`
- meta `_brevo_leads_capture_delivery_url`
- meta `_executive_signal_material_capture_url`
- action `brevo_leads_capture_free_material`
- nonce/action `brevo_leads_capture_free_material`
- campo REST `brevo_leads_capture_nonce`
- query arg público `brevo_leads_capture=error` com `brevo_error`

Novos nomes preferenciais:

- option `crm_leads_capture_settings`
- action `crm_leads_capture_free_material`
- REST namespace `crm-leads-capture/v1`
- JS global `CRMLeadsCaptureFreeMaterial`
- query args `crm_leads_capture=error` e `crm_error`

## Metadados por material

Novos metadados:

- `_crm_leads_capture_provider`
- `_crm_leads_capture_delivery_url`
- `_crm_leads_capture_list_id`
- `_crm_leads_capture_rd_station_conversion_identifier`
- `_crm_leads_capture_rd_station_tags`

Quando `_crm_leads_capture_provider` não estiver definido, o material usa o
provider ativo global.
