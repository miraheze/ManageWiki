/*!
 * JavaScript for Special:ManageWiki: Enable save button and prevent the window being accidentally
 * closed when any form field is changed, and ask the user to confirm before saving whenever one of
 * the pending changes has a warning attached to it.
 */
( function () {
	$( () => {
		if ( !( $( '#managewiki-submit' ).length > 0 ) ) {
			return;
		}

		function getConfigFields() {
			return $( '#managewiki-form :input[name]' )
				.not( '#managewiki-submit-reason :input[name]' )
				.not( ':disabled' );
		}

		// Check if a field differs from the value it was loaded with.
		// (This function could be changed to infuse and check OOUI widgets, but that would only
		// make it slower and more complicated. It works fine to treat them as HTML elements.)
		function isFieldChanged( field ) {
			if ( field.defaultChecked !== undefined && field.type === 'checkbox' ) {
				return field.defaultChecked !== field.checked;
			}

			return field.defaultValue !== undefined && field.defaultValue !== field.value;
		}

		// Check if all the form values are unchanged.
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

			$fields = getConfigFields();
			for ( i = 0; i < $fields.length; i++ ) {
				if ( isFieldChanged( $fields[ i ] ) ) {
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

		// Collect the warnings shown next to the fields that are being changed.
		function collectSaveWarnings() {
			const items = [];
			getConfigFields().each( function () {
				const $field = $( this ).closest( '.oo-ui-fieldLayout' );
				const $warning = $field.find( '.ext-managewiki-save-warning' );
				if ( !$warning.length || !isFieldChanged( this ) ) {
					return;
				}

				// Grab config name (e.g. name of extension)
				const label = $field
					.find( '.oo-ui-fieldLayout-header .oo-ui-labelElement-label' )
					.not( '.oo-ui-inline-help' )
					.first()
					.text()
					.trim();

				const $item = $( '<li>' );
				if ( label ) {
					$item.append( $( '<strong>' ).text( label ), $( '<br>' ) );
				}
				$item.append( $warning.clone().contents() );

				items.push( $item );
			} );

			return items;
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

		const $form = $( '#managewiki-form' );
		// User clicked confirm when saving with warnings
		let confirmSave = false;

		$form.on( 'submit', ( e ) => {
			const warnings = collectSaveWarnings();
			if ( confirmSave || !warnings.length ) {
				allowCloseWindow.release();
				return;
			}

			e.preventDefault();

			const $list = $( '<ul>' )
				.addClass( 'ext-managewiki-save-warning-list' )
				.append( warnings );

			OO.ui.confirm( $list, {
				title: mw.msg( 'managewiki-save-warnings-title' ),
				size: 'medium',
				actions: [
					{
						action: 'reject',
						label: OO.ui.deferMsg( 'ooui-dialog-message-reject' ),
						flags: 'safe'
					},
					{
						action: 'accept',
						label: mw.msg( 'managewiki-save' ),
						flags: [ 'primary', 'destructive' ]
					}
				]
			} ).then( ( accepted ) => {
				if ( accepted ) {
					confirmSave = true;
					$form[ 0 ].requestSubmit();
				}
			} );
		} );
	} );
}() );
