# Contrato genérico de frontend

Todo perfil registrado pode usar o mesmo formulário progressivamente aprimorado.
O tema continua responsável pela marcação visual, pelos rótulos e pela disposição
dos campos; o plugin fornece somente transporte, segurança e feedback neutro.

## Formulário com fallback nativo

```php
<form
	method="post"
	action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
	data-crm-leads-capture="meu-perfil"
>
	<?php crm_leads_capture_form_fields( 'meu-perfil' ); ?>

	<label>
		Nome
		<input type="text" name="name" required>
	</label>

	<label>
		E-mail
		<input type="email" name="email" required>
	</label>

	<button type="submit">Enviar</button>
	<?php crm_leads_capture_render_message( 'meu-perfil' ); ?>
</form>
```

Sem JavaScript, o formulário usa a action genérica
`crm_leads_capture_submit`, valida o nonce do perfil e retorna à página de
origem com um estado público de sucesso ou erro. Se o perfil definir um redirect
de sucesso, somente essa URL configurada no servidor pode ser usada como destino
externo.

## Aprimoramento REST

O script `assets/js/capture.js` reconhece exclusivamente formulários com
`data-crm-leads-capture`. Ele solicita um nonce fresco para o perfil e envia os
mesmos campos para:

- `GET /wp-json/crm-leads-capture/v1/capture/{perfil}/nonce`;
- `POST /wp-json/crm-leads-capture/v1/capture/{perfil}`.

As duas requisições são feitas sem cookies. O nonce REST é específico do perfil,
não reutiliza o nonce de autenticação da REST API e a resposta que o entrega é
marcada como `no-store`.

O navegador informa apenas o slug e os campos declarados no perfil. Provider,
lista, identificador de conversão, mapeamento e redirect são sempre resolvidos
no servidor; campos extras são ignorados pelo processador.

## Feedback acessível

O helper `crm_leads_capture_render_message()` e o script compartilham uma
estrutura visual neutra com `role="status"` e `aria-live="polite"`. O tema pode
estilizá-la pelas classes `crm-leads-capture-message*`, sem depender de um design
system específico. Erros de provider nunca expõem a resposta técnica externa.
