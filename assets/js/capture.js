( function () {
	'use strict';

	var config = window.CRMLeadsCapture || {};
	var forms = document.querySelectorAll( 'form[data-crm-leads-capture]' );

	if ( ! forms.length || ! window.fetch || ! window.FormData || ! config.restBase ) {
		return;
	}

	function messageElement( form ) {
		var message = form.querySelector( '[data-crm-leads-capture-message]' );

		if ( message ) {
			return message;
		}

		message = document.createElement( 'div' );
		message.className = 'crm-leads-capture-message';
		message.setAttribute( 'data-crm-leads-capture-message', '' );
		message.setAttribute( 'role', 'status' );
		message.setAttribute( 'aria-live', 'polite' );
		message.setAttribute( 'tabindex', '-1' );
		message.hidden = true;
		message.innerHTML = '<span class="crm-leads-capture-message__badge"></span><p class="crm-leads-capture-message__text"></p>';
		form.appendChild( message );

		return message;
	}

	function showMessage( form, successful, text ) {
		var message = messageElement( form );
		var badge = message.querySelector( '.crm-leads-capture-message__badge' );
		var body = message.querySelector( '.crm-leads-capture-message__text' );

		message.hidden = false;
		message.setAttribute( 'data-feedback-tone', successful ? 'success' : 'danger' );
		if ( badge ) {
			badge.textContent = successful ? config.successLabel : config.errorLabel;
		}
		if ( body ) {
			body.textContent = text || ( successful ? config.successMessage : config.genericMessage );
		}
		message.focus();
	}

	function profileSlug( form ) {
		var hidden = form.querySelector( '[name="crm_leads_capture_profile"]' );

		return ( form.getAttribute( 'data-crm-leads-capture' ) || ( hidden && hidden.value ) || '' ).trim();
	}

	function endpoint( profile, suffix ) {
		return config.restBase.replace( /\/$/, '' ) + '/' + encodeURIComponent( profile ) + ( suffix || '' );
	}

	function setBusy( form, busy ) {
		var submit = form.querySelector( '[type="submit"]' );

		form.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
		if ( submit ) {
			submit.disabled = busy;
		}
	}

	function submitNormally( form ) {
		HTMLFormElement.prototype.submit.call( form );
	}

	function enhance( form ) {
		form.addEventListener( 'submit', function ( event ) {
			var profile = profileSlug( form );

			if ( ! profile ) {
				return;
			}

			event.preventDefault();
			setBusy( form, true );

			fetch( endpoint( profile, '/nonce' ), {
				method: 'GET',
				credentials: 'omit',
				cache: 'no-store',
				headers: { Accept: 'application/json' }
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'nonce' );
					}

					return response.json();
				} )
				.then( function ( nonceResponse ) {
					var data = new FormData( form );

					data.delete( '_wpnonce' );
					data.set( config.nonceField, nonceResponse.nonce );

					return fetch( endpoint( profile ), {
						method: 'POST',
						credentials: 'omit',
						body: data,
						headers: { Accept: 'application/json' }
					} );
				} )
				.then( function ( response ) {
					return response.json().catch( function () {
						return {};
					} ).then( function ( payload ) {
						return { ok: response.ok, payload: payload };
					} );
				} )
				.then( function ( result ) {
					var payload = result.payload;
					var successful = result.ok && payload.success;

					showMessage( form, successful, payload.message );
					form.dispatchEvent( new CustomEvent( 'crm-leads-capture:result', {
						detail: {
							profile: profile,
							success: successful,
							code: payload.code || ''
						}
					} ) );

					if ( successful && payload.redirect_url ) {
						window.location.assign( payload.redirect_url );
					}
				} )
				.catch( function () {
					// If enhancement is unavailable, preserve the native admin-post flow.
					submitNormally( form );
				} )
				.finally( function () {
					setBusy( form, false );
				} );
		} );
	}

	Array.prototype.forEach.call( forms, enhance );
}() );
