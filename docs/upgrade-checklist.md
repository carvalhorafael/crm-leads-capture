# Checklist de atualização para perfis de captura

Este roteiro permite atualizar uma instalação existente sem conhecer as classes
internas do plugin. Faça primeiro em staging e mantenha uma cópia do ZIP que
está em produção para rollback.

## Antes da atualização

1. Faça backup do banco e de `wp-content/plugins/crm-leads-capture`.
2. Anote o provider selecionado em **Configurações > CRM Leads Capture > General**.
3. Confirme que esse provider está ativo e que a API key aparece como
   **Configurada** no painel de status.
4. Se o provider for Brevo, confirme a lista padrão ou a lista de cada material.
5. Se for RD Station, confirme o identificador de conversão padrão ou o de cada
   material.
6. Em **General > Compatibilidade**, mantenha **Materiais gratuitos legados**
   ativo enquanto o site ainda utilizar esse contrato.
7. Abra um material gratuito e anote sua URL de entrega, quando esse módulo for usado.
8. Verifique se o admin mostra o aviso de configuração legada de provider. O
   aviso é diagnóstico: o valor antigo será preservado, mas todos os envios
   usam o provider global.

Não apague options nem metadados antigos antes do teste. A atualização lê os
fallbacks legados e não exige migração manual dos materiais existentes.

## Instalação do pacote

1. Gere ou obtenha `crm-leads-capture-<versao>.zip`.
2. No WordPress, acesse **Plugins > Adicionar plugin > Enviar plugin**.
3. Selecione o ZIP e confirme a substituição da versão instalada.
4. Reative o plugin somente se o WordPress não o mantiver ativo.
5. Abra **Configurações > CRM Leads Capture** e confirme novamente provider,
   credencial e status.
6. Antes de publicar formulários de COO ou palestras, crie os respectivos
   perfis na aba **Configurações > CRM Leads Capture > Perfis de captura**. Eles
   não são instalados automaticamente em sites que não os utilizam.

O pacote precisa conter uma única pasta raiz `crm-leads-capture/`. Ele não deve
conter `tests/`, `vendor/`, `.github/`, `.env`, logs ou dumps.

## Validação após a atualização

Execute somente as linhas que correspondem ao provider global da instalação:

| Fluxo | Brevo | RD Station | Conferência no CRM |
| --- | --- | --- | --- |
| Material gratuito | enviar um material existente | enviar um material existente | contato, destino e UTMs corretos |
| COO as a Service | enviar o formulário COO | enviar o formulário COO | empresa, papel, site/LinkedIn, desafio, consentimento e origem |
| Convite para palestra | enviar o formulário de convite | enviar o formulário de convite | organização, evento, público, data, local, formato, consentimento e origem |

Em todos os envios:

- a mensagem de sucesso deve corresponder ao fluxo;
- uma falha simulada de credencial ou destino deve manter o formulário na
  página com mensagem pública, sem resposta bruta do CRM;
- materiais antigos devem continuar aceitando actions, nonces e metadados
  legados;
- nenhum post `crm_service_interest` deve ser criado;
- logs não devem conter nome, e-mail, telefone, payload, API key ou resposta
  bruta.

## Perfis e campos customizados

Para criar um formulário novo, use a aba **Configurações > CRM Leads Capture >
Perfis de captura**:

1. Defina nome, slug e origem.
2. Cadastre cada campo com tipo, grupo e obrigatoriedade.
3. Configure apenas o destino do provider global exibido na tela.
4. Para Brevo, informe listas e `caminho=ATRIBUTO` no mapa de atributos.
5. Para RD Station, informe conversão, tags e `caminho=campo` no mapa.
6. Corrija todos os avisos de destino ou mapeamento antes de publicar.
7. Associe o perfil no painel lateral da página e use o slug no template.

Exemplo de caminho customizado: `custom_fields.company`. Consentimentos usam
`consent.consent`; valores confiáveis do servidor, como a página de origem,
usam `context.page_url`. Os atributos/campos remotos precisam existir no CRM.

## Rollback

Se um fluxo essencial falhar:

1. restaure o ZIP anterior do plugin;
2. restaure o banco apenas se configurações tiverem sido editadas durante o
   teste;
3. confirme novamente um material gratuito;
4. preserve o ZIP e os logs redigidos da versão com falha para diagnóstico.

O plugin não migra nem apaga metadados legados durante uma submissão. Também
não armazena submissões, portanto não existe uma tabela ou fila local de leads
para converter ou recuperar no rollback.
