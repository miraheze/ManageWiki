/*!
 * JavaScript for Special:ManageWiki: Enable save button and prevent the window being accidentally
 * closed when any form field is changed.
 */
( function () {
	$( function () {
		var allowCloseWindow, saveButton;

		// Check if all of the form values are unchanged.
		// (This function could be changed to infuse and check OOUI widgets, but that would only make it
		// slower and more complicated. It works fine to treat them as HTML elements.)
		function isManageWikiChanged() {
			var $inputs = $( '#managewiki-form :input[name]:not( #managewiki-submit-reason :input[name] )' ),
				input, $input, inputType,
				index, optIndex,
				opt;

			for ( index = 0; index < $inputs.length; index++ ) {
				input = $inputs[ index ];
				$input = $( input );

				// Different types of inputs have different methods for accessing defaults
				if ( $input.is( 'select' ) ) {
					// <select> has the property defaultSelected for each option
					for ( optIndex = 0; optIndex < input.options.length; optIndex++ ) {
						opt = input.options[ optIndex ];
						if ( opt.selected !== opt.defaultSelected ) {
							return true;
						}
					}
				} else if ( $input.is( 'input' ) || $input.is( 'textarea' ) ) {
					// <input> has defaultValue or defaultChecked
					inputType = input.type;
					if ( inputType === 'radio' || inputType === 'checkbox' ) {
						if ( input.checked !== input.defaultChecked ) {
							return true;
						}
					} else if ( input.value !== input.defaultValue ) {
						return true;
					}
				}
			}

			return false;
		}

		saveButton = OO.ui.infuse( $( '#managewiki-submit' ) );

		// Disable the button to save unless settings have changed
		// Check if settings have been changed before JS has finished loading
		saveButton.setDisabled( !isManageWikiChanged() );
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
