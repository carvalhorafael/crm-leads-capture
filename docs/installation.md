# Instalação e operação

## Requisitos

- WordPress moderno compatível com PHP 8.1 ou superior.
- Brevo API key válida.
- Para formulários Elementor: Elementor Pro ativo.

## Instalação

1. Instale o plugin no diretório `wp-content/plugins/crm-leads-capture`.
2. Ative o plugin no admin do WordPress.
3. Configure a API key Brevo em `Configurações > CRM Leads Capture` ou por constante.
4. Configure uma lista padrão Brevo, se a maioria das capturas usar a mesma lista.
5. Para materiais gratuitos, configure os metadados no próprio material.

## Configuração por constante

Preferível para produção:

```php
define( 'CRM_LEADS_CAPTURE_BREVO_API_KEY', 'xkeysib-...' );
define( 'CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID', 123 );
```

Não versione chaves reais em arquivos do projeto.

## Configuração por admin

Use `Configurações > CRM Leads Capture` para:

- salvar API key no banco do WordPress;
- definir lista padrão Brevo;
- conferir o status da configuração.

O campo de API key não renderiza o valor salvo. Deixar o campo em branco mantém a chave existente.

## Logs técnicos

O plugin só registra logs técnicos quando `WP_DEBUG` está ativo.

Os logs passam por redaction de chaves, tokens, payloads, email, telefone e WhatsApp. Mesmo assim, use logs apenas para desenvolvimento ou investigação controlada.

## Testes

Validação rápida:

```bash
composer test:unit
```

Validação completa com WordPress test suite:

```bash
composer test
```

## Pacote de instalacao

Para gerar um ZIP instalavel:

```bash
composer package
```

O pacote e gerado em `dist/` com uma pasta raiz `crm-leads-capture/`.
Antes de usar em producao, siga `docs/release-preparation.md`.

## Atualizacoes pelo WordPress

Depois da instalacao inicial por ZIP, novas versoes podem aparecer no painel de
updates do WordPress quando houver uma GitHub Release publica com o asset ZIP do
plugin.

O fluxo esta documentado em `docs/github-release-updates.md`.
