# Núcleo de perfis de captura

O núcleo genérico representa cada formulário por um perfil de captura. O
[contrato genérico de frontend](capture-frontend.md) já permite que novos
formulários usem REST com aprimoramento progressivo ou `admin-post.php` sem
JavaScript. Materiais gratuitos passam por um adaptador do pipeline; os perfis
comerciais nativos usam diretamente o contrato genérico nos novos templates.

## Contratos

- `CRM_Leads_Capture_Field` define nome, tipo, grupo, obrigatoriedade,
  sanitização e validação de um campo.
- `CRM_Leads_Capture_Profile` reúne schema, contexto confiável,
  configuração por provider e comportamento de sucesso.
- `CRM_Leads_Capture_Profile_Registry` registra e resolve perfis por
  slug. Adaptadores de integrações existentes podem construir e registrar
  perfis em memória.
- `CRM_Leads_Capture_Submission` mantém os dados normalizados separados
  em `lead`, `tracking`, `consent` e `custom_fields`.
- `CRM_Leads_Capture_Processor` verifica nonce e honeypot, normaliza os
  campos declarados, resolve o provider global e encaminha a submissão.

O registry também recebe dois perfis comerciais nativos:

- `coo-as-a-service`;
- `speaker-invitation`.

Esse registro depende do módulo **Perfis comerciais**. Sites que não oferecem
esses formulários podem desativá-lo sem afetar o registry, o processador ou os
perfis próprios.

Perfis salvos pelo administrador são carregados depois dos padrões nativos. Um
perfil persistido com o mesmo slug substitui o padrão de forma explícita.

Campos ausentes do schema são ignorados. Assim, valores enviados pelo navegador
não podem escolher provider, listas, conversões ou mapeamentos.

## Provider global

`crm_leads_capture_settings['active_provider']` é a única origem da decisão
entre Brevo e RD Station. Perfis e páginas guardam apenas configurações de
destino daquele provider, como listas, conversão, tags e mapas. Mesmo que o
navegador envie um campo chamado `provider`, ele é ignorado.

Ao trocar o provider global, as configurações do provider inativo continuam
preservadas para uma troca futura, mas não participam do envio atual.

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

Options e metadados persistem apenas configuração: schemas de perfis,
associações de páginas e destinos no CRM. Eles nunca contêm a submissão do
visitante.

Para o destino Brevo, consulte o
[mapeamento de listas e atributos por perfil](brevo-profile-mapping.md).
Para o RD Station, consulte o
[mapeamento de conversão, tags e campos por perfil](rd-station-profile-mapping.md).
A criação e associação desses perfis está descrita em
[administração de perfis e páginas](capture-profile-administration.md).
