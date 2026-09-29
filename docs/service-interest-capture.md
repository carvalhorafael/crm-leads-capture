# Capturas comerciais

O plugin registra dois perfis comerciais nativos e envia suas submissões
diretamente ao provider global ativo. Nenhum lead, consentimento ou outro dado
pessoal é persistido no WordPress.

## Perfis e campos

`coo-as-a-service` recebe:

- `name`, `email` e `whatsapp`;
- `company`, `role`, `company_url` e `challenge`;
- `consent`;
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content` e
  `utm_name`.

`role` aceita `founder`, `ceo` ou `executive`.

`speaker-invitation` recebe:

- `name`, `email` e `whatsapp`;
- `organization`, `event_name`, `objective_context`, `audience_profile`,
  `audience_size`, `event_date`, `location` e `format`;
- `consent` e as mesmas UTMs do perfil de COO.

`event_date` usa `AAAA-MM-DD`; `format` aceita `presencial`, `online` ou
`hibrido`. A URL da página de origem é resolvida pelo adaptador no servidor e
não precisa ser um campo editável.

## Contrato dos templates

Os templates novos devem usar o [contrato genérico de frontend](capture-frontend.md):

```php
<form
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-crm-leads-capture="coo-as-a-service"
>
	<?php crm_leads_capture_form_fields( 'coo-as-a-service' ); ?>
	<!-- campos com os nomes declarados acima -->
	<?php crm_leads_capture_render_message( 'coo-as-a-service' ); ?>
</form>
```

Para convites, o mesmo markup usa `speaker-invitation` nos dois pontos. Cada
perfil devolve sua própria mensagem de sucesso. Falhas do CRM mantêm o
formulário na página e exibem somente uma mensagem pública controlada.

## Mapeamento dos CRMs

Os dois perfis trazem mapeamentos padrão para todos os campos:

- Brevo usa a lista global quando o perfil ou a página não define outra e
  envia qualificação, consentimento e página de origem como atributos;
- RD Station usa conversão e tags próprias de cada perfil e envia os campos de
  qualificação, consentimento, origem e UTMs no evento.

Os atributos personalizados precisam existir no CRM com os identificadores
documentados na configuração dos perfis. Overrides associados a uma página
continuam tendo precedência sobre os padrões nativos.

## Compatibilidade temporária do COO

O contrato antigo continua disponível durante a migração do tema:

- action `crm_leads_capture_service_interest`;
- helper `crm_leads_capture_service_interest_nonce_field()`;
- helper `crm_leads_capture_render_service_interest_message()`;
- endpoints `/service-interest` e `/service-interest/nonce`.

Esse adaptador encaminha a submissão ao perfil `coo-as-a-service`. Ele não
registra o post type histórico `crm_service_interest`, não cria tela
administrativa, não grava metadados e não chama APIs de persistência de posts.
