/*!
 * JavaScript for Special:ManageWiki: Enable save button and prevent the window being accidentally
 * closed when any form field is changed.
 */
( function () {
	$( () => {
		if ( !( $( '#managewiki-submit' ).length > 0 ) ) {
			return;
		}

		// Check if all of the form values are unchanged.
		// (This function could be changed to infuse and check OOUI widgets, but that would only
		// make it slower and more complicated. It works fine to treat them as HTML elements.)
		function isManageWikiChanged() {
			let $fields, i;

			$fields = $( '#managewiki-form  .mw-htmlform-cloner-ul' );
			for ( i = 0; i < $fields.length; i++ ) {
				const initialSize = Number( $fields[ i ].dataset.initialFieldSize );
				const currentSize = $fields[ i ].children.length;

				if ( initialSize !== currentSize ) {
					return true;
				}
			}

			$fields = $( '#managewiki-form :input[name]' )
				.not( '#managewiki-submit-reason :input[name]' );

			for ( i = 0; i < $fields.length; i++ ) {
				if ( $fields[ i ].disabled ) {
					continue;
				}

				if (
					$fields[ i ].defaultChecked !== undefined &&
					$fields[ i ].type === 'checkbox' &&
					$fields[ i ].defaultChecked !== $fields[ i ].checked
				) {
					return true;
				} else if (
					$fields[ i ].defaultValue !== undefined &&
					$fields[ i ].defaultValue !== $fields[ i ].value
				) {
					return true;
				}
			}

			return false;
		}

		// Check if a submit reason was entered
		function hasSubmitReason() {
			const $reason = $( '#managewiki-submit-reason' ).find( ':input[name]' );
			for ( let i = 0; i < $reason.length; i++ ) {
				if ( $reason[ i ].value.trim() !== '' ) {
					return true;
				}
			}
			return false;
		}

		const saveButton = OO.ui.infuse( $( '#managewiki-submit' ) );

		// Determine if the save button should be enabled
		function updateSaveButtonState() {
			const changed = isManageWikiChanged();
			const reasonFilled = hasSubmitReason();
			// eslint-disable-next-line no-jquery/no-class-state
			const isCreateNamespace = $( 'body' ).hasClass( 'ext-managewiki-create-namespace' );
			saveButton.setDisabled( !( changed || ( isCreateNamespace && reasonFilled ) ) );
		}

		// Store the initial number of children of cloners for later use, as an equivalent of
		// defaultValue.
		$( '#managewiki-form .mw-htmlform-cloner-ul' ).each( function () {
			if ( this.dataset.initialFieldSize === undefined ) {
				this.dataset.initialFieldSize = this.children.length;
			}
		} );

		// Disable the save button unless settings have changed
		// Check if settings have been changed before JS has finished loading
		updateSaveButtonState();

		// Attach capturing event handlers to the document, to catch events inside OOUI dropdowns:
		// * Use capture because OO.ui.SelectWidget also does, and it stops event propagation,
		//   so the event is not fired on descendant elements
		// * Attach to the document because the dropdowns are in the .oo-ui-defaultOverlay element
		//   (and it doesn't exist yet at this point, so we can't attach them to it)
		[ 'change', 'keyup', 'mouseup' ].forEach( ( eventType ) => {
			document.addEventListener( eventType, () => {
				// Make sure SelectWidget's event handlers run first
				setTimeout( updateSaveButtonState );
			}, true );
		} );

		// Set up a message to notify users if they try to leave the page without
		// saving.
		const allowCloseWindow = mw.confirmCloseWindow( {
			test: isManageWikiChanged,
			message: mw.msg( 'managewiki-warning-changes', mw.msg( 'managewiki-save' ) )
		} );

		$( '#managewiki-form' ).on( 'submit', allowCloseWindow.release );
	} );
}() );
