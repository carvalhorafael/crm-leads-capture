# Changelog

Todas as mudancas relevantes deste projeto devem ser documentadas aqui.

## Nao publicado

## 0.6.1 - captura em site privado

- Corrige a captura em sites que exigem login, onde as duas chamadas REST eram
  recusadas com 403 e o visitante via "a sessao do formulario expirou" —
  mensagem que pede justamente a unica acao que nao resolve. As chamadas
  passam a enviar credenciais, e o `_wpnonce` da pagina nunca mais vai para o
  REST, porque o WordPress recusa qualquer requisicao que carregue um nonce
  que ele nao valide contra a propria acao `wp_rest`, antes de o plugin ser
  alcancado.
- Quando o endpoint do nonce esta indisponivel, o formulario volta a ser
  enviado pelo POST comum em vez de seguir com um nonce que falharia. O lead
  continua sendo criado e o material entregue.

## 0.6.0 - identificador de analise no CRM

- Encaminha ao CRM o identificador anonimo de dispositivo que a analise do site
  atribuiu ao navegador, pelo campo `analytics_device_id`. No RD Station ele
  chega como `cf_amplitude_device_id`. Permite reconstruir no CRM o caminho que
  a pessoa fez no site, sem enviar dado pessoal a ferramenta de analise. O
  plugin trata o valor como string opaca: charset restrito, 128 caracteres, sem
  interpretacao. Ausente, o lead e criado igual.

## 0.5.0 - resultado da captura observavel

- Anuncia o resultado da captura no proprio formulario, como um CustomEvent
  `crm-leads-capture:result` com `success`, `materialId` e `errorCode`. O envio
  e por fetch, entao sucesso e erro compartilham a mesma URL e nenhuma
  ferramenta de analise conseguia distingui-los. O plugin nao sabe qual
  ferramenta o site usa: quem escuta decide o que fazer.

## 0.4.0 - feedback de captura sem identidade herdada

- Remove as classes do Executive Signal Design System (`es-panel`, `es-badge`,
  `es-operational-feedback`) do feedback de captura de material gratuito. O
  markup passa a usar classes neutras do proprio plugin e o CSS expoe custom
  properties para o tema hospedeiro definir a identidade visual.

## 0.3.0 - release

- Corrige workflow de release para gerar e publicar o ZIP do plugin `crm-leads-capture`.
- Prepara release com suporte multi-provider, configuracoes por abas e URL de entrega padrao.

## 0.2.0 - release

- Preparacao de release.

## 0.1.0 - pre-release

- Adiciona client Brevo centralizado para criar ou atualizar contatos.
- Adiciona montagem normalizada de payload de lead.
- Adiciona configuracoes globais de API key e lista padrao.
- Adiciona captura de materiais gratuitos via `admin-post.php`.
- Adiciona adaptador Elementor Pro preservando action `brevo` e controles
  `brevo_*` do plugin antigo.
- Adiciona update checker via GitHub Releases para updates pelo painel do
  WordPress depois da instalacao inicial por ZIP.
- Adiciona workflow de GitHub Actions para validar, empacotar e publicar o ZIP
  como asset da release.
- Adiciona workflow de preparacao de release para calcular bump, atualizar
  versoes e abrir PR de release.
- Adiciona logs tecnicos com redaction quando `WP_DEBUG` esta ativo.
- Adiciona suites unitarias e integradas com WordPress.
- Adiciona documentacao operacional para instalacao, tema, settings, testes,
  compatibilidade Elementor, migracao Elementor real e preparacao de release.
