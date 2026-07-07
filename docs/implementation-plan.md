# Especificacao inicial: CRM Leads Capture

## Contexto

Existe um plugin anterior em `carvalhorafael/site-rafaelcarvalho`, no caminho:

```text
wp-content/plugins/elementor-form-brevo-action
```

Esse plugin adiciona uma action ao Elementor Pro para enviar dados de formularios ao Brevo CRM. Ele ja implementa pontos importantes:

- action `Brevo CRM` para Elementor;
- campos de configuracao para API key, list ID e mapeamento de campos;
- montagem de payload para `POST https://api.brevo.com/v3/contacts`;
- uso de `updateEnabled: true`;
- envio de `listIds`;
- mapeamento de atributos como nome, sobrenome, WhatsApp e UTMs;
- tratamento basico de erros da API.

O novo plugin `crm-leads-capture` deve reaproveitar essa experiencia, mas separar a integracao Brevo da camada Elementor.

## Objetivo do produto

Permitir que diferentes formularios WordPress capturem leads e enviem contatos ao Brevo CRM de forma centralizada.

O objetivo final para o tema `executive-signal-wordpress-theme`:

1. Usuario visita a pagina de um material gratuito.
2. Usuario preenche nome, email e WhatsApp.
3. Usuario clica em baixar material.
4. Plugin valida a submissao.
5. Plugin cria ou atualiza o contato no Brevo e adiciona a lista configurada.
6. Usuario e redirecionado para uma pagina ou URL de entrega do material.

## Principios arquiteturais

- O tema nao deve chamar a API da Brevo diretamente.
- Elementor deve ser apenas um adaptador de entrada, nao a camada principal do plugin.
- A chamada Brevo deve ficar em uma classe reaproveitavel.
- A configuracao de credenciais deve ser centralizada.
- Cada origem de captura deve montar um payload padrao e chamar a mesma camada Brevo.
- O plugin deve permitir extrair/alterar adaptadores sem quebrar o core.

## Arquitetura proposta

```text
crm-leads-capture/
├── crm-leads-capture.php
├── includes/
│   ├── class-plugin.php
│   ├── class-brevo-client.php
│   ├── class-lead-payload.php
│   ├── class-settings.php
│   ├── class-free-material-capture.php
│   └── integrations/
│       └── class-elementor-form-action.php
├── languages/
├── tests/
└── docs/
```

### `class-brevo-client.php`

Responsavel por falar com a API da Brevo.

Responsabilidades:

- receber API key;
- montar headers;
- fazer `wp_remote_post`;
- enviar payload de contato;
- interpretar status `200`, `201` e `204` como sucesso;
- retornar objeto/array de resultado padronizado;
- nunca expor API key em erro;
- opcionalmente suportar `GET /contacts/lists` para diagnostico/admin.

Metodo sugerido:

```php
public function create_or_update_contact( array $lead ): CRM_Leads_Capture_Result
```

Payload esperado:

```php
[
    'email' => 'lead@example.com',
    'attributes' => [
        'FIRSTNAME' => 'Rafael',
        'WHATSAPP' => '+5511999999999',
        'MATERIAL' => 'Nome do material',
        'SOURCE' => 'free_material',
    ],
    'listIds' => [123],
    'updateEnabled' => true,
]
```

### `class-lead-payload.php`

Objeto ou helper para normalizar dados comuns.

Responsabilidades:

- validar email;
- normalizar WhatsApp;
- separar nome completo em primeiro nome/sobrenome se necessario;
- limpar strings;
- preservar UTMs;
- montar atributos Brevo em caixa alta.

### `class-settings.php`

Responsavel por configuracoes globais.

Decisoes pendentes:

- API key via constante `CRM_LEADS_CAPTURE_BREVO_API_KEY`;
- API key via pagina de settings no admin;
- list ID padrao via settings;
- permitir sobrescrever list ID por origem/material.

Recomendacao inicial:

- suportar constante para API key primeiro;
- adicionar settings admin apenas se for necessario para operacao sem editar `wp-config.php`.

### `class-free-material-capture.php`

Responsavel pela captura vinda do tema `executive-signal-wordpress-theme`.

Opcoes de endpoint:

1. `admin-post.php` com actions:
   - `admin_post_nopriv_crm_leads_capture_free_material`
   - `admin_post_crm_leads_capture_free_material`
2. REST API:
   - `POST /wp-json/crm-leads-capture/v1/free-material`

Recomendacao inicial:

- usar `admin-post.php` para formulario server-rendered simples e redirecionamento nativo;
- considerar REST API depois se houver UX AJAX.

Campos esperados do formulario:

- `name`
- `email`
- `whatsapp`
- `material_id`
- `_wpnonce`
- honeypot oculto
- UTMs opcionais

Fluxo:

1. Validar nonce.
2. Validar honeypot.
3. Validar `material_id`.
4. Buscar configuracoes do material:
   - Brevo List ID;
   - URL de entrega;
   - label do botao.
5. Montar payload.
6. Enviar para Brevo.
7. Em sucesso, redirecionar para URL de entrega.
8. Em erro, redirecionar de volta para o material com query arg controlada ou mensagem transiente.

### `integrations/class-elementor-form-action.php`

Substitui a classe atual `Brevo_Action_After_Submit`, mantendo compatibilidade.

Responsabilidades:

- registrar action no Elementor Pro;
- manter controles existentes, quando possivel;
- converter campos Elementor para payload padrao;
- chamar `Brevo_Client`;
- reportar sucesso/erro ao handler do Elementor.

Importante:

- manter o action name `brevo` se ja houver formularios em producao usando esse identificador;
- preservar nomes de controles existentes para evitar quebrar configuracoes salvas no Elementor;
- mover a logica de HTTP para `Brevo_Client`;
- mover normalizacao de WhatsApp para helper comum.

## Metadados necessarios no tema/material

O tema hoje tem metadados:

- `_executive_signal_material_capture_url`
- `_executive_signal_material_capture_label`

Para a integracao, deve-se avaliar migrar ou adicionar:

- `_crm_leads_capture_list_id`
- `_crm_leads_capture_delivery_url`
- `_crm_leads_capture_success_mode` (opcional)

Alternativa:

- manter o campo atual de URL como delivery URL;
- adicionar apenas Brevo List ID;
- renomear labels no admin futuramente para reduzir ambiguidades.

## Contrato com o tema `executive-signal-wordpress-theme`

O tema deve:

- renderizar o formulario;
- incluir `action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"`;
- incluir `method="post"`;
- incluir hidden `action=crm_leads_capture_free_material`;
- incluir hidden `material_id`;
- incluir nonce gerado pelo plugin ou por contrato documentado;
- nao guardar API key;
- nao chamar Brevo diretamente.

O plugin deve:

- registrar o handler;
- validar e enviar lead;
- redirecionar.

## Compatibilidade com o plugin antigo

Plano de migracao:

1. Copiar o plugin antigo como referencia, nao como codigo final direto.
2. Criar core Brevo independente.
3. Reimplementar a action Elementor usando o mesmo action name `brevo`.
4. Preservar controles:
   - `brevo_api_key`
   - `brevo_list_id`
   - `brevo_email_field`
   - `brevo_name_field`
   - `brevo_last_name_field`
   - `brevo_whatsapp_field`
   - campos UTM;
   - demais campos customizados existentes.
5. Testar em um formulario Elementor existente antes de substituir o plugin antigo.

## Segurança e privacidade

- Usar nonce em toda captura.
- Usar honeypot para reduzir spam simples.
- Validar email com `is_email`.
- Sanitizar telefone e strings.
- Redirecionar apenas com `wp_safe_redirect`.
- Nao registrar API key em logs.
- Nao expor resposta bruta da Brevo ao usuario final.
- Considerar LGPD: deixar claro no texto do formulario que os dados serao usados para envio do material e comunicacoes relacionadas, se aplicavel.

## Testes recomendados

### Unitarios

- normalizacao de WhatsApp;
- validacao de email;
- montagem de payload;
- mapeamento de campos Elementor;
- mapeamento de campos de material gratuito.

### Integracao WordPress

- handler `admin-post.php` valida nonce;
- handler rejeita email invalido;
- handler chama `Brevo_Client` com list ID correto;
- handler redireciona para delivery URL em sucesso;
- handler retorna ao formulario em falha.

### Mock HTTP

- sucesso `201`;
- sucesso `204`;
- erro `400` com mensagem Brevo;
- `WP_Error` de rede;
- contato existente com `updateEnabled: true`.

## Fases de desenvolvimento

### Fase 1: Bootstrap do plugin

- Criar `crm-leads-capture.php`.
- Definir constantes.
- Criar autoload simples ou includes manuais.
- Criar classe principal de bootstrap.
- Adicionar text domain.

### Fase 2: Core Brevo

- Criar `Brevo_Client`.
- Criar resultado padronizado.
- Criar normalizadores.
- Adicionar testes para payload e HTTP mockado.

### Fase 3: Elementor

- Migrar action atual para adaptador.
- Manter compatibilidade de controles.
- Remover chamada HTTP duplicada da action.
- Validar com formulario Elementor real.

### Fase 4: Materiais gratuitos

- Criar handler `admin-post.php`.
- Definir metadados/configuracao por material.
- Atualizar tema para postar no plugin.
- Adicionar mensagem/fluxo de erro.
- Redirecionar para URL de entrega em sucesso.

### Fase 5: Hardening

- Logs tecnicos controlados.
- Admin settings se necessario.
- Documentacao de instalacao.
- Checklist de migracao do plugin antigo.

## Perguntas pendentes

- A API key deve ser global por site ou configuravel por formulario/material?
- Cada material gratuito tera sua propria lista Brevo ou uma lista unica?
- A entrega sera uma pagina WordPress, arquivo direto, video embed ou URL externa?
- O plugin deve salvar localmente um registro minimo do lead/submissao ou apenas enviar para Brevo?
- O fluxo deve ser server-side com redirect ou AJAX com mensagem inline?
- Quais atributos Brevo definitivos devem existir para materiais gratuitos?

## Recomendacao inicial

Comecar com plugin isolado e core reutilizavel. Preservar Elementor como adaptador e adicionar materiais gratuitos como segundo adaptador.

Esse caminho reduz acoplamento no tema e aproveita a experiencia ja validada com Brevo, sem duplicar regra de negocio de CRM no `executive-signal-wordpress-theme`.
