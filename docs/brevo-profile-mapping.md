# Mapeamento de perfis para a Brevo

O provider Brevo aceita tanto os payloads planos legados quanto a submissão
canônica produzida por `CRM_Leads_Capture_Processor`. A configuração de destino
fica no perfil, no servidor; valores enviados pelo navegador não escolhem listas
nem nomes de atributos.

## Configuração do perfil

```php
'providers' => array(
	'brevo' => array(
		'list_ids' => array( 12, 18 ),
		'attribute_map' => array(
			'custom_fields.company'   => 'COMPANY',
			'custom_fields.team_size' => 'TEAM_SIZE',
			'consent.consent'         => 'PRIVACY_CONSENT',
		),
	),
),
```

`list_ids` aceita uma ou mais listas positivas e remove duplicatas. O formato
singular legado `list_id` continua aceito. Se o perfil não definir uma lista, o
provider usa a lista padrão global; sem nenhuma lista válida, o processamento
termina localmente com `missing_list`.

`attribute_map` relaciona o caminho canônico ao atributo remoto. Campos em
`custom_fields` e `consent`, além de campos não padronizados em `lead` e
`tracking`, precisam de mapeamento explícito quando tenham valor. Isso impede
que o nome de um campo do formulário seja usado implicitamente como destino
remoto. Dois campos não podem apontar para o mesmo atributo.

## Atributos padrão e customizados

O provider preserva automaticamente os atributos existentes:

- `FIRSTNAME`, `LASTNAME` e `WHATSAPP`, a partir de `lead`;
- `SOURCE` e `MATERIAL`, a partir do contexto confiável do perfil;
- `UTM_SOURCE`, `UTM_MEDIUM`, `UTM_CAMPAIGN`, `UTM_TERM`, `UTM_CONTENT` e
  `UTM_NAME`, a partir de `tracking`.

Os atributos Brevo precisam estar previamente criados na conta. Esta versão não
faz alterações remotas no schema. O nome configurado deve começar com uma letra
maiúscula e conter apenas `A-Z`, números e `_`. Os valores aceitos são string,
inteiro, decimal, booleano ou lista de strings. Valores vazios não são enviados.

Uma configuração de atributo ou tipo inválido retorna `invalid_payload` antes da
chamada HTTP. O payload sempre usa `updateEnabled: true`, inclusive para várias
listas, e trata HTTP 200, 201 e 204 como sucesso.

## Privacidade operacional

Destinos e diagnósticos podem expor apenas slugs, nomes de chaves e IDs de listas.
Valores dos campos, e-mail, telefone e credenciais não devem aparecer em logs.
