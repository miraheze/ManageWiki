( function () {
	$( function () {
		var tabs, previousTab, switchingNoHash;

		infuse = $( '.oo-ui-menuLayout-content' ).find( '[data-ooui*="managewiki-infuse"], .managewiki-infuse[name]' );
		infuse.each( function () {
			OO.ui.infuse( this );
		} );
		
		tabs = OO.ui.infuse( $( '.mw-managewiki-tabs' ) );

		tabs.$element.addClass( 'mw-managewiki-tabs-infused' );

		function enhancePanel( panel ) {
			if ( !panel.$element.data( 'mw-section-infused' ) ) {
				panel.$element.removeClass( 'mw-htmlform-autoinfuse-lazy' );
				mw.hook( 'htmlform.enhance' ).fire( panel.$element );
				panel.$element.data( 'mw-section-infused', true );
			}
		}

		function onTabPanelSet( panel ) {
			var scrollTop, active;

			if ( switchingNoHash ) {
				return;
			}
			// Handle hash manually to prevent jumping,
			// therefore save and restore scrollTop to prevent jumping.
			scrollTop = $( window ).scrollTop();
			// Changing the hash apparently causes keyboard focus to be lost?
			// Save and restore it. This makes no sense though.
			active = document.activeElement;
			location.hash = '#' + panel.getName();
			if ( active ) {
				active.focus();
			}
			$( window ).scrollTop( scrollTop );
		}

		tabs.on( 'set', onTabPanelSet );

		/**
		 * @ignore
		 * @param {string} name The name of a tab
		 * @param {boolean} [noHash] A hash will be set according to the current
		 *  open section. Use this flag to suppress this.
		 */
		function switchManageWikiTab( name, noHash ) {
			if ( noHash ) {
				switchingNoHash = true;
			}
			tabs.setTabPanel( name );
			enhancePanel( tabs.getCurrentTabPanel() );
			if ( noHash ) {
				switchingNoHash = false;
			}
		}

		// Jump to correct section as indicated by the hash.
		// This function is called onload and onhashchange.
		function detectHash() {
			var hash = location.hash,
				matchedElement, $parentSection;
			if ( hash.match( /^#mw-section-[\w]+$/ ) ) {
				mw.storage.session.remove( 'mwmanagewiki-prevTab' );
				switchManageWikiTab( hash.slice( 1 ) );
			} else if ( hash.match( /^#mw-[\w-]+$/ ) ) {
				matchedElement = document.getElementById( hash.slice( 1 ) );
				$parentSection = $( matchedElement ).closest( '.mw-managewiki-section-fieldset' );
				if ( $parentSection.length ) {
					mw.storage.session.remove( 'mwmanagewiki-prevTab' );
					// Switch to proper tab and scroll to selected item.
					switchManageWikiTab( $parentSection.attr( 'id' ), true );
					matchedElement.scrollIntoView();
				}
			}
		}

		$( window ).on( 'hashchange', function () {
			var hash = location.hash;
			if ( hash.match( /^#mw-[\w-]+/ ) ) {
				detectHash();
			}
		} )
			// Run the function immediately to select the proper tab on startup.
			.trigger( 'hashchange' );

		// Restore the active tab after saving the settings
		previousTab = mw.storage.session.get( 'mwmanagewiki-prevTab' );
		if ( previousTab ) {
			switchManageWikiTab( previousTab, true );
			// Deleting the key, the tab states should be reset until we press Save
			mw.storage.session.remove( 'mwmanagewiki-prevTab' );
		}

		$( "[id*=\"mw-baseform-\"]" ).on( 'submit', function () {
			var value = tabs.getCurrentTabPanelName();
			mw.storage.session.set( 'mwmanagewiki-prevTab', value );
		} );
	} );
}() );
