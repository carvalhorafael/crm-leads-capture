(function () {
	'use strict';

	var config = window.CRMLeadsCaptureServiceInterest || {};
	var NONCE_UNAVAILABLE = 'crm-leads-capture:nonce-unavailable';

	if (!config.restUrl || !window.fetch || !window.FormData) {
		return;
	}

	function isCaptureForm(form) {
		var action = form.querySelector('input[name="action"]');

		return action && action.value === 'crm_leads_capture_service_interest';
	}

	function messageContainer(form) {
		var container = form.parentElement && form.parentElement.querySelector('[data-crm-leads-capture-message]');

		if (container) {
			return container;
		}

		container = document.createElement('div');
		container.className = 'crm-leads-capture-message';
		container.setAttribute('data-crm-leads-capture-message', '');
		container.setAttribute('role', 'status');
		container.setAttribute('aria-live', 'polite');
		container.setAttribute('tabindex', '-1');
		container.innerHTML = '<span class="crm-leads-capture-message__badge"></span><p class="crm-leads-capture-message__text"></p>';
		form.insertBefore(container, form.firstChild);

		return container;
	}

	function setFeedback(form, tone, label, message) {
		var container = messageContainer(form);
		var badge = container.querySelector('.crm-leads-capture-message__badge');
		var text = container.querySelector('.crm-leads-capture-message__text');

		container.hidden = false;
		container.setAttribute('data-feedback-tone', tone);
		if (badge) {
			badge.textContent = label;
		}
		if (text) {
			text.textContent = message;
		}
		container.focus({ preventScroll: true });
		container.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
	}

	function setSubmitting(form, submitting) {
		Array.prototype.forEach.call(form.querySelectorAll('button, input[type="submit"]'), function (button) {
			button.disabled = submitting;
			button.setAttribute('aria-busy', submitting ? 'true' : 'false');
		});
	}

	function clearVisibleFields(form) {
		Array.prototype.forEach.call(form.elements, function (field) {
			if (!field.name || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
				return;
			}
			if (field.type === 'checkbox' || field.type === 'radio') {
				field.checked = false;
				return;
			}
			field.value = '';
		});
	}

	function freshNonce(formData) {
		if (!config.nonceUrl) {
			return Promise.resolve(formData);
		}

		var nonceUrl = new URL(config.nonceUrl, window.location.href);
		nonceUrl.searchParams.set('_', Date.now().toString());

		return fetch(nonceUrl.toString(), { method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
			.then(function (response) {
				if (!response.ok) {
					throw new Error(NONCE_UNAVAILABLE);
				}
				return response.json();
			})
			.then(function (data) {
				if (!data || !data.nonce) {
					throw new Error(NONCE_UNAVAILABLE);
				}
				formData.delete('_wpnonce');
				formData.set('crm_leads_capture_nonce', data.nonce);
				return formData;
			});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!(form instanceof HTMLFormElement) || !isCaptureForm(form)) {
			return;
		}

		event.preventDefault();
		setSubmitting(form, true);

		freshNonce(new FormData(form))
			.then(function (payload) {
				return fetch(config.restUrl, { method: 'POST', body: payload, credentials: 'same-origin', headers: { Accept: 'application/json' } });
			})
			.then(function (response) {
				return response.json().catch(function () { return {}; }).then(function (data) {
					if (!response.ok) {
						throw data;
					}
					return data;
				});
			})
			.then(function (data) {
				clearVisibleFields(form);
				setFeedback(form, 'success', config.successLabel || 'Recebido', data.message || config.successMessage);
				form.dispatchEvent(new window.CustomEvent('crm-leads-capture:result', { bubbles: true, detail: { success: true, type: 'service_interest' } }));
			})
			.catch(function (data) {
				if (data instanceof Error && data.message === NONCE_UNAVAILABLE) {
					form.submit();
					return;
				}
				setFeedback(form, 'danger', config.errorLabel || 'Erro', (data && data.message) || config.genericMessage);
				form.dispatchEvent(new window.CustomEvent('crm-leads-capture:result', { bubbles: true, detail: { success: false, type: 'service_interest', errorCode: (data && data.code) || 'unknown' } }));
			})
			.finally(function () {
				setSubmitting(form, false);
			});
	});
})();
