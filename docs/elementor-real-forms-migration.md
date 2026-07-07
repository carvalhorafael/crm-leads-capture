# Migracao controlada dos formularios Elementor reais

Este documento define como migrar formularios Elementor Pro que usam o plugin
antigo `elementor-form-brevo-action` para `crm-leads-capture`.

O objetivo nao e trocar todos os formularios de uma vez. A migracao deve ser
feita por inventario, formulario piloto, janela controlada, evidencia e
rollback claro.

## Escopo da migracao

Inclui formularios Elementor Pro que hoje usam a action:

```text
brevo
```

com label historico:

```text
Brevo CRM
```

O novo plugin preserva esse identificador e os controles `brevo_*` para manter
as configuracoes salvas no Elementor. A documentacao tecnica dessa
compatibilidade esta em `docs/elementor-compatibility.md`.

## Principio de corte

Cada formulario real so deve ser considerado migrado quando houver evidencia de:

- formulario enviado com um lead de teste controlado;
- contato criado ou atualizado no Brevo;
- contato associado a lista Brevo correta;
- atributos esperados preenchidos;
- mensagem ao usuario sem erro tecnico;
- ausencia de segredo, email, telefone ou payload sensivel em logs.

## Inventario antes da troca

Crie uma tabela de inventario antes de alterar producao:

| Campo | Como preencher |
| --- | --- |
| Pagina | URL publica ou slug da pagina onde o formulario aparece. |
| Nome do formulario | Nome interno no Elementor, quando existir. |
| Criticidade | Alta, media ou baixa, conforme volume/impacto comercial. |
| Lista Brevo atual | ID da lista configurada no formulario ou plugin antigo. |
| Campo de email | Valor configurado em `brevo_email_field`. |
| Campo de nome | Valor configurado em `brevo_name_field`. |
| Campo de sobrenome | Valor configurado em `brevo_last_name_field`, se usado. |
| Campo de WhatsApp | Valor configurado em `brevo_whatsapp_field`, se usado. |
| UTMs | IDs dos campos UTM ou mecanismo que preenche UTMs via JavaScript. |
| Campos customizados | IDs dos campos que alimentam atributos comerciais. |
| Pos-envio | Mensagem, redirect ou automacao disparada pelo Elementor. |
| Dono de validacao | Pessoa que confere o lead no Brevo. |
| Status | Pendente, piloto, migrado, pausado ou revertido. |

Priorize formularios de baixa criticidade para o piloto e deixe formularios de
maior volume para depois de pelo menos um ciclo bem-sucedido.

## Preparacao do ambiente

Antes de qualquer teste em producao:

1. Instale e ative `crm-leads-capture`.
2. Configure `CRM_LEADS_CAPTURE_BREVO_API_KEY` no ambiente ou salve a API key no
   admin do plugin.
3. Configure `CRM_LEADS_CAPTURE_BREVO_DEFAULT_LIST_ID` somente se houver uma lista
   padrao segura para formularios sem `brevo_list_id`.
4. Confirme que Elementor Pro esta ativo.
5. Confirme que `WP_DEBUG` nao esta expondo logs em tela publica.
6. Separe uma lista de teste no Brevo quando o formulario permitir trocar
   temporariamente o `brevo_list_id`.
7. Defina um lead de teste com email identificavel, por exemplo
   `teste+elementor-<formulario>@example.com`.

Em producao, prefira API key por constante no `wp-config.php` ou mecanismo de
segredo do provedor. Nao copie chaves reais para arquivos versionados.

## Execucao por formulario

Para cada formulario do inventario:

1. Abra a pagina no Elementor.
2. Confirme que `Brevo CRM` continua selecionado em `Actions After Submit`.
3. Abra a secao `Brevo CRM`.
4. Confira os controles preservados:
   - `brevo_api_key`;
   - `brevo_list_id`;
   - `brevo_email_field`;
   - `brevo_name_field`;
   - `brevo_last_name_field`;
   - `brevo_whatsapp_field`;
   - campos comerciais customizados;
   - campos UTM.
5. Se a API key global estiver configurada, deixe claro no inventario que ela
   tem prioridade sobre `brevo_api_key` do formulario.
6. Se o formulario depender da lista global, registre isso explicitamente.
7. Envie o lead de teste pela pagina publica, nao apenas pelo editor.
8. Confira o contato no Brevo.
9. Confira lista, atributos, WhatsApp e UTMs.
10. Marque o formulario como migrado somente depois da conferencia no Brevo.

Nao salve alteracoes estruturais no formulario durante essa etapa. A migracao
deve validar compatibilidade, nao redesenhar a captura.

## UTMs e JavaScript legado

O adaptador Elementor preserva o comportamento em que campos UTM podem chegar
via `$_POST` quando o Elementor nao os inclui no record normalizado.

Campos suportados:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `utm_name`
- `utm_term`

Se uma pagina usa script legado para preencher UTMs, teste pelo menos um envio
com query string real:

```text
?utm_source=teste&utm_medium=migracao&utm_campaign=crm-leads-capture
```

## Evidencia minima

Para cada formulario migrado, registre:

- data e horario do teste;
- URL testada;
- email do lead de teste;
- ID da lista Brevo esperada;
- resultado no Brevo;
- divergencias encontradas;
- decisao final: migrado, pausado ou revertido.

Nao registre API key, payload completo, telefone real de lead ou qualquer dado
pessoal desnecessario.

## Criterios de pausa

Pause a migracao se qualquer item ocorrer:

- action `Brevo CRM` nao aparece no Elementor;
- formulario perde configuracao `brevo_*` salva;
- lead nao chega ao Brevo;
- lead chega sem email, lista ou atributo comercial essencial;
- resposta de erro aparece com detalhe tecnico para usuario final;
- logs exibem API key, email, telefone, WhatsApp ou payload sensivel;
- mais de um formulario apresenta a mesma divergencia.

Ao pausar, mantenha o plugin novo ativo somente se os formularios ja validados
continuarem funcionando e o problema estiver isolado.

## Rollback por formulario

Rollback recomendado:

1. Reative temporariamente o plugin antigo, se ele tiver sido desativado.
2. Confirme que a action historica `brevo` volta a ser atendida pelo plugin
   antigo.
3. Reenvie um lead de teste.
4. Confirme o contato no Brevo.
5. Registre o motivo do rollback no inventario.

Evite manter dois plugins registrando a mesma action por mais tempo que o
necessario. Depois do rollback, defina qual plugin deve responder pela action
antes da proxima janela de teste.

## Plano de ondas sugerido

1. Piloto: um formulario de baixa criticidade.
2. Segunda onda: formularios com mesmos campos e mesma lista do piloto.
3. Terceira onda: formularios com UTMs e campos customizados.
4. Corte final: formularios de maior volume ou impacto comercial.
5. Encerramento: remover plugin antigo apenas depois de todos os formularios
   reais estarem validados.

## Checklist final de corte

- Todos os formularios do inventario estao como migrados ou explicitamente fora
  de escopo.
- O plugin antigo esta desativado em staging.
- O plugin antigo esta desativado em producao apos janela de observacao.
- Nenhum formulario critico depende de `brevo_api_key` local sem necessidade.
- A lista padrao global esta configurada apenas quando for segura.
- Logs tecnicos foram conferidos sem vazamento de segredo ou dados sensiveis.
- A documentacao operacional foi atualizada com qualquer excecao encontrada.
