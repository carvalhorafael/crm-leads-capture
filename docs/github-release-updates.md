# Atualizacoes do plugin via GitHub Releases

O plugin suporta instalacao inicial por ZIP e atualizacoes futuras pelo painel
do WordPress a partir de GitHub Releases.

## Como o WordPress descobre a atualizacao

O arquivo principal do plugin declara:

```text
Update URI: https://github.com/carvalhorafael/crm-leads-capture
```

Esse header evita colisao com plugins do diretório wordpress.org.

Para buscar updates, o plugin registra um update checker proprio usando os
hooks nativos do WordPress:

- `pre_set_site_transient_update_plugins`
- `plugins_api`

O checker consulta:

```text
https://api.github.com/repos/carvalhorafael/crm-leads-capture/releases/latest
```

Se a ultima release publica tiver uma tag maior que a versao instalada e tiver
um asset com o nome esperado, o WordPress passa a exibir a atualizacao no admin.

Nome esperado do asset:

```text
crm-leads-capture-<versao>.zip
```

Exemplo:

```text
crm-leads-capture-0.2.0.zip
```

O ZIP precisa conter uma pasta raiz:

```text
crm-leads-capture/
```

## Fluxo de release automatizado

Quando for hora de preparar uma nova release, rode o workflow `Prepare Release`
em `develop`.

Patch release padrao:

```bash
gh workflow run prepare-release.yml --ref develop -f bump=patch -f base_branch=develop
```

Versao explicita:

```bash
gh workflow run prepare-release.yml --ref develop -f version=0.2.0 -f base_branch=develop
```

Esse workflow:

1. calcula a proxima versao;
2. atualiza o header `Version`;
3. atualiza `CRM_LEADS_CAPTURE_VERSION`;
4. adiciona uma entrada em `CHANGELOG.md`;
5. abre ou atualiza uma PR `release/vX.Y.Z` para `develop`.

Depois que a PR de release for mergeada em `develop`, abra e mergeie a PR de
`develop` para `main`. Quando `main` receber a versao preparada, o workflow
`Release` roda automaticamente.

O workflow `Release` cria a tag `vX.Y.Z` se ela ainda nao existir, valida
Composer, roda testes, gera o ZIP e cria ou atualiza a GitHub Release.

Tambem e possivel acionar o workflow manualmente a partir de `main`:

```bash
gh workflow run release.yml --ref main -f tag=v0.2.0 -f source_ref=main -f create_tag=true
```

## Papel do agente

Quando o pedido for "vamos fazer uma release nova", o agente deve seguir o
fluxo de `AGENTS.md`: acionar `Prepare Release`, acompanhar a PR de release para
`develop` e depois acompanhar a promocao de `develop` para `main`.

Merges normais em `develop` nao publicam release automaticamente. A publicacao
acontece quando a versao preparada chega em `main`.

## Requisitos importantes

- O repositorio precisa estar publico para o update checker anonimo funcionar.
- A release nao pode ser draft.
- A release nao pode ser pre-release.
- A tag precisa seguir `vX.Y.Z`.
- O asset da release precisa seguir `crm-leads-capture-X.Y.Z.zip`.
- A versao do header e a constante precisam bater com a tag sem o prefixo `v`.

## Tempo ate aparecer no WordPress

O WordPress usa cache de update em transients. O checker do plugin tambem guarda
a resposta do GitHub por 6 horas.

Em uma instalacao de teste, da para forcar nova checagem pela tela de updates do
WordPress ou limpando o transient:

```bash
wp transient delete --network crm_leads_capture_github_release
wp transient delete --network update_plugins
```

Use esses comandos apenas em ambiente onde WP-CLI esteja disponivel.

## Limitacoes

O checker usa a API publica do GitHub sem token. Isso e adequado para repositorio
publico e evita armazenar credenciais no WordPress. Se o repositorio ficar
privado, o WordPress nao conseguira baixar a release sem adicionar um mecanismo
autenticado de update, o que deve ser tratado como uma decisao de seguranca
separada.
