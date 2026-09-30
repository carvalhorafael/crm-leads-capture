# Mapeamento de perfis para o RD Station

O provider RD Station aceita os payloads planos legados e a submissão canônica
produzida por `CRM_Leads_Capture_Processor`. Conversão, tags e destinos de campo
ficam no perfil no servidor, nunca em valores escolhidos pelo navegador.

## Configuração do perfil

```php
'providers' => array(
	'rd_station' => array(
		'conversion_identifier' => 'coo-interest',
		'tags' => array( 'coo', 'inbound' ),
		'field_map' => array(
			'custom_fields.company'          => 'company_name',
			'custom_fields.company_site'     => 'company_site',
			'custom_fields.job_title'        => 'job_title',
			'custom_fields.challenge'        => 'cf_biggest_challenge',
			'consent.consent'                => 'cf_privacy_consent',
			'context.source'                 => 'cf_lead_source',
		),
	),
),
```

`conversion_identifier` é obrigatório para perfis canônicos. Se estiver vazio,
o provider tenta o padrão global e, na ausência dele, retorna
`missing_conversion` antes do HTTP. O fallback pelo nome do material continua
somente para o fluxo legado de materiais gratuitos.

`tags` aceita array ou texto separado por vírgulas. Espaços, duplicatas e itens
vazios são removidos. Quando não há tags válidas, a chave não é enviada; assim o
plugin não faz uma atualização vazia que possa interferir nas tags existentes.

## Campos padrão e customizados

Os campos canônicos `name`, `email`, `whatsapp`, `job_title`, `company_name` e
`company_site` são convertidos diretamente quando estão no grupo `lead`. As UTMs
`utm_source`, `utm_medium`, `utm_campaign` e `utm_term` usam os campos de tráfego
documentados pelo RD Station. O identificador analítico continua em
`cf_amplitude_device_id` quando disponível.

Outros campos precisam de `field_map`. O destino pode ser um campo padrão
documentado, como `company_name`, `company_site`, `job_title` ou `mobile_phone`,
ou o `api_identifier` exato de um campo criado previamente no RD Station. Campos
customizados devem usar `cf_` seguido apenas por letras minúsculas, números e
`_`. Esta versão não cria campos remotos.

Mapeamento ausente, duplicado ou inválido retorna `invalid_payload` antes da
chamada HTTP. O resumo técnico de uma falha remota contém somente status, tipos
de erro e caminhos de campo; mensagens que possam repetir dados pessoais não são
incluídas nos logs.
