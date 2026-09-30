# Checklist de migração Elementor

Use este checklist antes de substituir o plugin antigo `elementor-form-brevo-action`.

Para a migracao controlada dos formularios reais, use o procedimento completo em
`docs/elementor-real-forms-migration.md`. Este arquivo continua como checklist
rapido de compatibilidade por formulario.

## Antes da troca

- Confirme que `crm-leads-capture` está ativo.
- Escolha e habilite o provider global no admin do plugin.
- Configure a credencial do provider global e, para Brevo, a lista padrão se aplicável.
- Escolha um formulário Elementor real para teste.

## Compatibilidade preservada

A action continua usando:

```text
brevo
```

O label continua:

```text
Brevo CRM
```

Os controles `brevo_*` foram preservados para manter configurações existentes.

## Teste por formulário

Para cada formulário crítico:

1. Abra o formulário no Elementor.
2. Confirme que a action `Brevo CRM` segue selecionada em Actions After Submit.
3. Confirme o mapeamento de campos:
   - `brevo_email_field`
   - `brevo_name_field`
   - `brevo_last_name_field`
   - `brevo_whatsapp_field`
   - UTMs e campos customizados.
4. Envie um lead de teste.
5. Confirme no provider global que o contato foi criado ou atualizado.
6. Para Brevo, confirme que a lista correta recebeu o contato.
7. Confirme que os atributos customizados foram preenchidos.

## Diferenças intencionais

- A chamada HTTP é delegada ao provider global registrado.
- A API key configurada no formulário é preservada como dado legado, mas não sobrescreve a credencial global.
- Se `brevo_list_id` estiver vazio, o plugin usa a lista padrão global.
- Mensagens de erro ao usuário são genéricas para não expor resposta bruta da Brevo.
- Logs técnicos só aparecem com `WP_DEBUG` ativo e passam por redaction.

## Rollback

Se algum formulário crítico falhar:

1. Reative temporariamente o plugin antigo.
2. Desative a action correspondente neste plugin, se necessário.
3. Colete logs com `WP_DEBUG` ativo em ambiente controlado.
4. Corrija o mapeamento ou abra ajuste no adaptador Elementor.
