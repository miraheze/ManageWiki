/*!
 * JavaScript for Special:ManageWiki: Enable save and review buttons and prevent the window being accidentally
 * closed when any form field is changed.
 */
( function () {
	$( function () {
		var allowCloseWindow, reviewButton, saveButton;

		// Check if all of the form values are unchanged.
		// (This function could be changed to infuse and check OOUI widgets, but that would only make it
		// slower and more complicated. It works fine to treat them as HTML elements.)
		function isManageWikiChanged() {
			 var result = false;

			$( '#managewiki-form :input:not( #managewiki-submit-reason :input )' ).each( function () {
				if ( this.defaultChecked != undefined && this.type == 'checkbox' && this.defaultChecked != this.checked ) {
					result = true;

					return false;
				} else if ( this.defaultValue != undefined && this.defaultValue != this.value ) {
					result = true;

					return false;
				}
			} );

			return result;
		}

		saveButton = OO.ui.infuse( $( '#managewiki-submit' ) );
		reviewButton =  OO.ui.infuse( $( '#managewiki-review' ) );

		// Disable the buttons unless settings have changed
		// Check if settings have been changed before JS has finished loading
		saveButton.setDisabled( !isManageWikiChanged() );
		reviewButton.setDisabled( !isManageWikiChanged() );

		// Attach capturing event handlers to the document, to catch events inside OOUI dropdowns:
		// * Use capture because OO.ui.SelectWidget also does, and it stops event propagation,
		//   so the event is not fired on descendant elements
		// * Attach to the document because the dropdowns are in the .oo-ui-defaultOverlay element
		//   (and it doesn't exist yet at this point, so we can't attach them to it)
		[ 'change', 'keyup', 'mouseup' ].forEach( function ( eventType ) {
			document.addEventListener( eventType, function () {
				// Make sure SelectWidget's event handlers run first
				setTimeout( function () {
					saveButton.setDisabled( !isManageWikiChanged() );
					reviewButton.setDisabled( !isManageWikiChanged() );
				} );
			}, true );
		} );

		// Set up a message to notify users if they try to leave the page without
		// saving.
		allowCloseWindow = mw.confirmCloseWindow( {
			test: isManageWikiChanged,
			message: mw.msg( 'managewiki-warning-changes', mw.msg( 'managewiki-save' ) )
		} );

		$( '#managewiki-form' ).on( 'submit', allowCloseWindow.release );
	} );
}() );
