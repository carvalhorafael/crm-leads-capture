(function () {
	'use strict';

	var config = window.CRMLeadsCaptureFreeMaterial || {};

	if (!config.restUrl || !window.fetch || !window.FormData) {
		return;
	}

	function clearErrorQueryArgs() {
		if (!window.history || !window.URL || !window.location.search) {
			return;
		}

		var url = new URL(window.location.href);

		if (
			url.searchParams.get('crm_leads_capture') !== 'error' &&
			url.searchParams.get('brevo_leads_capture') !== 'error' &&
			!url.searchParams.has('crm_error') &&
			!url.searchParams.has('brevo_error')
		) {
			return;
		}

		url.searchParams.delete('crm_leads_capture');
		url.searchParams.delete('brevo_leads_capture');
		url.searchParams.delete('crm_error');
		url.searchParams.delete('brevo_error');
		window.history.replaceState(window.history.state, document.title, url.toString());
	}

	function isCaptureForm(form) {
		var action = form.querySelector('input[name="action"]');

		return action && (action.value === 'crm_leads_capture_free_material' || action.value === 'brevo_leads_capture_free_material');
	}

	function findMessageContainer(form, createIfMissing) {
		var container = form.querySelector('[data-crm-leads-capture-message]');

		if (container) {
			return container;
		}

		if (form.parentElement) {
			container = form.parentElement.querySelector('[data-crm-leads-capture-message]');
		}

		if (container) {
			return container;
		}

		if (!createIfMissing) {
			return null;
		}

		container = document.createElement('div');
		container.className = 'crm-leads-capture-message es-panel es-operational-feedback';
		container.setAttribute('data-crm-leads-capture-message', '');
		container.setAttribute('data-tone', 'muted');
		container.setAttribute('data-padding', 'md');
		container.setAttribute('role', 'alert');
		container.setAttribute('aria-live', 'polite');
		container.innerHTML = '<span class="es-badge"></span><p class="es-operational-feedback__message"></p><p class="crm-leads-capture-message__action"></p>';
		form.insertBefore(container, form.firstChild);

		return container;
	}

	function setFeedback(container, tone, label, message, redirectUrl) {
		var badgeNode = container.querySelector('.es-badge');
		var messageNode = container.querySelector('.es-operational-feedback__message');
		var actionNode = container.querySelector('.crm-leads-capture-message__action');

		if (!messageNode) {
			messageNode = container;
		}

		container.setAttribute('data-feedback-tone', tone);
		if (badgeNode) {
			badgeNode.setAttribute('data-tone', tone);
			badgeNode.textContent = label;
		}

		container.hidden = false;
		messageNode.textContent = message || config.genericMessage || 'Nao conseguimos concluir seu cadastro agora. Tente novamente em instantes.';

		if (!actionNode) {
			return;
		}

		actionNode.textContent = '';
		if (redirectUrl) {
			var link = document.createElement('a');
			link.href = redirectUrl;
			link.textContent = config.redirectLinkLabel || 'Acessar o material agora';
			actionNode.appendChild(link);
			actionNode.hidden = false;
		} else {
			actionNode.hidden = true;
		}
	}

	function setSubmitting(form, isSubmitting) {
		Array.prototype.forEach.call(form.querySelectorAll('button, input[type="submit"]'), function (button) {
			button.disabled = isSubmitting;
		});
	}

	function requestHeaders() {
		return {
			Accept: 'application/json',
		};
	}

	function publicErrorMessage(data) {
		if (data && data.success === false && data.message) {
			return data.message;
		}

		if (data && data.code === 'rest_cookie_invalid_nonce') {
			return config.invalidNonceMessage;
		}

		return config.genericMessage;
	}

	function clearFormFields(form) {
		Array.prototype.forEach.call(form.elements, function (field) {
			if (!field.name || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
				return;
			}

			if (field.type === 'checkbox' || field.type === 'radio') {
				field.checked = false;
				return;
			}

			if ('value' in field) {
				field.value = '';
			}
		});
	}

	function scheduleRedirect(url) {
		window.setTimeout(function () {
			window.location.assign(url);
		}, Number(config.redirectDelayMs) || 5000);
	}

	function refreshCaptureNonce(formData) {
		if (!config.nonceUrl) {
			return Promise.resolve(formData);
		}

		var nonceUrl = new URL(config.nonceUrl, window.location.href);
		nonceUrl.searchParams.set('_', Date.now().toString());

		return fetch(nonceUrl.toString(), {
			method: 'GET',
			credentials: 'omit',
			cache: 'no-store',
			headers: requestHeaders(),
		})
			.then(function (response) {
				if (!response.ok) {
					return {};
				}

				return response.json().catch(function () {
					return {};
				});
			})
			.then(function (data) {
				if (data && data.nonce) {
					formData.delete('_wpnonce');
					formData.set('crm_leads_capture_nonce', data.nonce);
				}

				return formData;
			})
			.catch(function () {
				return formData;
			});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;

		if (!(form instanceof HTMLFormElement) || !isCaptureForm(form)) {
			return;
		}

		event.preventDefault();

		var messageContainer = findMessageContainer(form, false);
		var formData = new FormData(form);

		if (messageContainer) {
			messageContainer.hidden = true;
			var currentMessage = messageContainer.querySelector('.es-operational-feedback__message');
			if (currentMessage) {
				currentMessage.textContent = '';
			} else {
				messageContainer.textContent = '';
			}
		}
		setSubmitting(form, true);

		refreshCaptureNonce(formData)
			.then(function (payload) {
				return fetch(config.restUrl, {
					method: 'POST',
					body: payload,
					credentials: 'omit',
					headers: requestHeaders(),
				});
			})
			.then(function (response) {
				return response
					.json()
					.catch(function () {
						return {};
					})
					.then(function (data) {
						if (!response.ok) {
							throw data;
						}

						return data;
					});
			})
			.then(function (data) {
				if (data && data.success && data.redirect_url) {
					clearFormFields(form);
					setFeedback(
						findMessageContainer(form, true),
						'success',
						config.successLabel || 'Sucesso',
						data.message || config.successMessage,
						data.redirect_url
					);
					scheduleRedirect(data.redirect_url);
					return;
				}

				throw data || {};
			})
			.catch(function (data) {
				setFeedback(
					findMessageContainer(form, true),
					'danger',
					config.errorLabel || 'Erro',
					publicErrorMessage(data),
					''
				);
				setSubmitting(form, false);
			});
	});

	clearErrorQueryArgs();
})();
