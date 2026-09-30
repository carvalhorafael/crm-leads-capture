# Testes automatizados

Este projeto usa PHPUnit em duas camadas:

- testes unitários sem WordPress, para regras puras, normalização, validação e montagem de payload;
- testes integrados com a suíte oficial de testes do WordPress, para hooks, options, nonces, handlers, REST API e adaptadores que dependem do runtime WordPress.

## Requisitos locais

- PHP 8.1 ou superior;
- Composer 2;
- MySQL ou MariaDB;
- Subversion, usado para baixar a suíte de testes do WordPress.

## Comandos principais

Instale as dependências:

```bash
composer install
```

Rode a suíte unitária rápida:

```bash
composer test:unit
```

Instale a suíte de testes do WordPress:

```bash
composer install:wp-tests
```

Rode a suíte integrada completa:

```bash
composer test
```

## Banco de testes

O script `bin/install-wp-tests.sh` aceita os argumentos do padrão WordPress:

```bash
bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version> <skip-db-create>
```

Exemplo com senha vazia e banco já criado:

```bash
bash bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest true
```

## CI

O workflow `.github/workflows/ci.yml` roda em pushes para `main`, `develop` e branches `codex/**`, além de pull requests para `main` e `develop`.

A matriz atual executa PHPUnit com PHP 8.1, 8.2 e 8.3 usando WordPress `latest`. Cada execução:

1. instala PHP e Composer;
2. valida `composer.json`;
3. instala dependências;
4. roda `composer test:unit`;
5. instala a suíte oficial de testes do WordPress;
6. roda `composer test:wordpress`.

## Regra de cobertura

Toda feature nova ou correção de bug deve incluir testes compatíveis com o risco da mudança. Para este plugin, os pontos mínimos esperados são:

- client Brevo com sucesso, erro HTTP e `WP_Error`;
- montagem e sanitização de payload;
- normalização de nome, WhatsApp, email e UTMs;
- handlers de captura com nonce, honeypot, erro e redirecionamento;
- adaptador Elementor preservando compatibilidade de action e controles;
- update checker via GitHub Releases preservando o fluxo nativo de updates do
  WordPress;
- garantia de que chaves de API e dados sensíveis não aparecem em logs ou mensagens para usuário final.

## Matriz de regressão da arquitetura de perfis

`CaptureProviderMatrixTest` percorre o processador e os providers reais com
transporte HTTP simulado. A suíte cobre explicitamente:

| Provider global | Material | COO | Palestra |
| --- | --- | --- | --- |
| Brevo | sim | sim | sim |
| RD Station | sim | sim | sim |

As suítes complementares verificam actions/nonces/metadados legados, campos
válidos e inválidos, provider desabilitado ou ausente, erros HTTP, redação dos
logs e ausência de posts/metadados criados por submissões.
