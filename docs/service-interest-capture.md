# Captura de interesse em serviços

O plugin oferece um contrato específico para páginas de serviços. O tema renderiza a experiência; o plugin valida, registra o interesse no WordPress e envia o contato ao provider ativo.

## Formulário

O formulário envia `POST` para `admin-post.php` com:

- `action=crm_leads_capture_service_interest`;
- nonce da action `crm_leads_capture_service_interest`;
- honeypot `crm_leads_capture_website` vazio;
- `name`, `email`, `company`, `role`, `challenge` e `consent=1`;
- `whatsapp`, `company_url`, `page_url` e UTMs quando disponíveis.

Papéis aceitos: `founder`, `ceo` e `executive`.

O helper `crm_leads_capture_service_interest_nonce_field()` renderiza o nonce. O helper `crm_leads_capture_render_service_interest_message()` renderiza o container de feedback e mensagens do fallback sem JavaScript.

## Progressive enhancement

O script do plugin intercepta apenas formulários com a action de interesse em serviço. Ele busca um nonce fresco em `GET /wp-json/crm-leads-capture/v1/service-interest/nonce` e envia o conteúdo para `POST /wp-json/crm-leads-capture/v1/service-interest`.

Sucesso e falha são anunciados no container com `aria-live`. Se o endpoint de nonce estiver indisponível, o formulário volta ao fluxo `admin-post.php`.

## Registro administrativo

Cada submissão validada cria um registro privado do tipo `crm_service_interest`, visível apenas para administradores em Configurações > Interesses em serviços. O registro preserva qualificação, consentimento, origem, UTMs, provider e status de envio.

O contato básico também é enviado ao provider ativo:

- Brevo usa a lista padrão configurada;
- RD Station usa a conversão `COO as a Service - Interesse` e as tags padrão.

Detalhes de qualificação permanecem no WordPress para não depender de atributos personalizados previamente configurados no CRM.
