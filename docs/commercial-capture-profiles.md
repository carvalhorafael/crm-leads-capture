# Perfis comerciais administráveis

COO as a Service e convite para palestras usam perfis comuns criados em
**Configurações > Perfis de captura**. O plugin não cria esses perfis
automaticamente, portanto instalações que não oferecem esses serviços não
recebem campos, mensagens ou configurações específicas deles.

## Perfil COO as a Service

Uma instalação que ofereça esse formulário pode criar o slug
`coo-as-a-service` com os campos:

- `name`, `email` e `whatsapp` no grupo `lead`;
- `company`, `role`, `company_url` e `challenge` em `custom_fields`;
- `consent` no grupo `consent`;
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` e
  `utm_name` em `tracking`.

`role` pode ser um `select` com `founder`, `ceo` e `executive`. A mensagem de
sucesso é configurada no próprio perfil.

## Perfil convite para palestras

O slug sugerido é `speaker-invitation`, com:

- `name`, `email` e `whatsapp` em `lead`;
- `organization`, `event_name`, `objective_context`, `audience_profile`,
  `audience_size`, `event_date`, `location` e `format` em `custom_fields`;
- `consent` em `consent` e as mesmas UTMs em `tracking`.

`event_date` usa o tipo `date`; `audience_size`, `integer`; e `format` pode ser
um `select` com `presencial`, `online` e `hibrido`.

## Contrato dos templates

O tema usa o [contrato genérico de frontend](capture-frontend.md):

```php
<form
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-crm-leads-capture="coo-as-a-service"
>
	<?php crm_leads_capture_form_fields( 'coo-as-a-service' ); ?>
	<!-- campos com os nomes declarados no perfil -->
	<?php crm_leads_capture_render_message( 'coo-as-a-service' ); ?>
</form>
```

Para convites, o mesmo markup usa o slug `speaker-invitation`. Falhas do CRM
mantêm o formulário na página e exibem somente uma mensagem pública controlada.

## Mapeamento dos CRMs

Cada instalação configura no próprio perfil os destinos e mapeamentos exigidos:

- Brevo: listas e mapa de atributos, por exemplo
  `custom_fields.company=COMPANY`;
- RD Station: identificador de conversão, tags e mapa de campos, por exemplo
  `custom_fields.company=company_name`.

Os atributos personalizados precisam existir no CRM com os identificadores
informados. Overrides associados a uma página continuam tendo precedência sobre
a configuração do perfil.
