# Compatibilidade Elementor

O plugin registra uma action de formulário para Elementor Pro com o mesmo identificador do plugin antigo:

```text
brevo
```

Label exibido:

```text
Brevo CRM
```

## Registro

A action é registrada no hook:

```php
elementor_pro/forms/actions/register
```

O arquivo que estende `ElementorPro\Modules\Forms\Classes\Action_Base` só é carregado quando o Elementor Pro registra as actions. Isso evita erro fatal em instalações sem Elementor Pro ativo.

## Configurações preservadas

Os nomes dos controles antigos foram mantidos para preservar formulários existentes:

- `brevo_api_key`
- `brevo_list_id`
- `brevo_email_field`
- `brevo_name_field`
- `brevo_last_name_field`
- `brevo_whatsapp_field`
- `brevo_who_is_field`
- `brevo_already_sell_field`
- `brevo_what_sells_field`
- `brevo_current_situation_field`
- `brevo_is_seeking_help_field`
- `brevo_faturamento_digital_field`
- `brevo_biggest_challenge_field`
- `brevo_utm_source_field`
- `brevo_utm_medium_field`
- `brevo_utm_campaign_field`
- `brevo_utm_content_field`
- `brevo_utm_name_field`
- `brevo_utm_term_field`

## Provider e fallbacks globais

O nome e os controles `brevo_*` permanecem para compatibilidade dos formulários,
mas a action envia pelo provider global da instalação. O controle legado
`brevo_api_key` não sobrescreve mais as credenciais globais.

Quando Brevo é o provider global, `brevo_list_id` continua definindo a lista do
formulário; se estiver vazio, o plugin usa a lista padrão global. Quando RD
Station é o provider global, configurações Brevo são ignoradas.

## UTMs

O plugin preserva o comportamento do plugin antigo para campos UTM adicionados por JavaScript: quando o Elementor não inclui esses campos no record, o mapper pode injetar valores enviados em `$_POST`.

Campos suportados:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `utm_name`
- `utm_term`

## WhatsApp

Para compatibilidade com o plugin antigo, valores de WhatsApp vindos do adaptador Elementor recebem prefixo `+55` quando não começam com `+`.

## HTTP

O adaptador Elementor não chama `wp_remote_post` diretamente. Ele monta a
entrada normalizada e delega o envio ao provider global registrado.
