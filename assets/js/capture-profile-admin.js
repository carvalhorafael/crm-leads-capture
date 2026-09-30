( function () {
	'use strict';

	function initialize( container ) {
		var list = container.querySelector( '[data-crm-profile-field-list]' );
		var template = container.querySelector( '[data-crm-profile-field-template]' );
		var addButton = container.querySelector( '[data-crm-profile-add-field]' );

		if ( ! list || ! template || ! addButton ) {
			return;
		}

		addButton.addEventListener( 'click', function () {
			var index = parseInt( container.getAttribute( 'data-next-index' ), 10 ) || 0;
			var markup = template.innerHTML.replace( /__INDEX__/g, String( index ) );
			list.insertAdjacentHTML( 'beforeend', markup );
			container.setAttribute( 'data-next-index', String( index + 1 ) );
		} );

		container.addEventListener( 'click', function ( event ) {
			var removeButton = event.target.closest( '[data-crm-profile-remove-field]' );
			if ( ! removeButton || ! container.contains( removeButton ) ) {
				return;
			}

			var row = removeButton.closest( '[data-crm-profile-field-row]' );
			if ( row ) {
				row.remove();
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-crm-profile-fields]' ).forEach( initialize );
	} );
}() );
