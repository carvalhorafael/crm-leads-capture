# Núcleo de perfis de captura

O núcleo genérico representa cada formulário por um perfil de captura. O
[contrato genérico de frontend](capture-frontend.md) já permite que novos
formulários usem REST com aprimoramento progressivo ou `admin-post.php` sem
JavaScript. Os formulários existentes serão migrados quando seus adaptadores de
compatibilidade estiverem prontos.

## Contratos

- `CRM_Leads_Capture_Field` define nome, tipo, grupo, obrigatoriedade,
  sanitização e validação de um campo.
- `CRM_Leads_Capture_Profile` reúne schema, contexto confiável,
  configuração por provider e comportamento de sucesso.
- `CRM_Leads_Capture_Profile_Registry` registra e resolve perfis por
  slug. Adaptadores legados podem construir e registrar perfis em memória.
- `CRM_Leads_Capture_Submission` mantém os dados normalizados separados
  em `lead`, `tracking`, `consent` e `custom_fields`.
- `CRM_Leads_Capture_Processor` verifica nonce e honeypot, normaliza os
  campos declarados, resolve o provider global e encaminha a submissão.

Campos ausentes do schema são ignorados. Assim, valores enviados pelo navegador
não podem escolher provider, listas, conversões ou mapeamentos.

## Tipos e grupos

Os tipos iniciais são `text`, `textarea`, `email`, `phone`, `url`, `integer`,
`number`, `boolean`, `date` e `select`. Sanitizadores e validadores adicionais
podem ser fornecidos como callbacks na definição do campo.

Os grupos canônicos são:

- `lead`: identidade e contato;
- `tracking`: origem, UTMs e identificadores analíticos;
- `consent`: consentimentos explícitos;
- `custom_fields`: dados específicos de cada perfil.

## Segurança e privacidade

O processador recebe apenas contexto confiável resolvido pelo adaptador no
servidor. O payload do navegador não define o provider nem sua configuração.
Falhas retornam códigos públicos controlados e os logs técnicos contêm apenas
slug do perfil, provider, status e nomes dos campos inválidos, nunca os valores
submetidos.

O núcleo não chama APIs de persistência do WordPress. A submissão existe apenas
em memória durante o processamento e é encaminhada diretamente ao provider
global ativo.

Para o destino Brevo, consulte o
[mapeamento de listas e atributos por perfil](brevo-profile-mapping.md).
