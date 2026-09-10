# Changelog

Todas as mudancas relevantes deste projeto devem ser documentadas aqui.

## Nao publicado

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
