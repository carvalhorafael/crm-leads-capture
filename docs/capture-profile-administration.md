# Administração de perfis e associação por página

Administradores podem criar perfis em **Configurações > Perfis de captura**.
Cada perfil salva nome, slug, origem, schema de campos, obrigatoriedade,
mensagem de sucesso, redirect e a configuração do provider global ativo.
Perfis são adicionados e removidos nessa própria tela; nenhum perfil de negócio
é criado automaticamente pelo plugin.

A tela nunca oferece seletor de provider. Ao trocar o provider global nas
configurações do plugin, o editor passa a mostrar o painel correspondente sem
apagar a configuração anteriormente salva para o provider inativo.

## Campos e diagnóstico

Cada campo possui nome, tipo, grupo canônico e indicação de obrigatoriedade.
Campos `select` também aceitam uma lista de valores permitidos. O botão
**Adicionar campo** permite montar schemas de qualquer tamanho e cada linha pode
ser removida individualmente. O painel de destino permite configurar:

- Brevo: uma ou mais listas e o mapa de atributos;
- RD Station: identificador de conversão, tags e o mapa de campos.

Avisos aparecem quando falta destino no perfil e também quando um campo que não
tem mapeamento padrão precisa de configuração explícita. Os identificadores
remotos ainda são validados novamente pelo provider antes do HTTP.

## Associação a páginas

O painel **Perfil de captura** na edição de páginas seleciona um perfil
reutilizável. O mesmo perfil pode ser associado a quantas páginas forem
necessárias.

Overrides de destino só são considerados quando a opção de override da página
está marcada. A página preserva separadamente as configurações Brevo e RD
Station; editar o provider ativo não apaga o inativo. Overrides vazios não
substituem a configuração válida do perfil.

No envio, o formulário fornece apenas `crm_leads_capture_profile` e
`crm_leads_capture_page_id`. O servidor confirma a associação da página e então
resolve listas, conversão, tags e mapas salvos. Valores de destino enviados
livremente pelo navegador continuam ignorados.

## Segurança e REST

O Settings API protege alterações de perfil com a capability `manage_options` e
nonce. A associação por página exige nonce próprio e `edit_post`, além de ignorar
autosaves e revisões.

Somente o slug de associação da página é registrado para edição via REST. Os
destinos e overrides dos providers não são exportados pela API REST, pois não são
necessários ao editor público.
