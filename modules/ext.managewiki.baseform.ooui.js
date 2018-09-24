( function () {
	$( function () {
		var $baseform, tabs, wrapper, previousTab, switchingNoHash;

		$baseform = $( '#baseform' );

		// Make sure the accessibility tip is selectable so that screen reader users take notice,
		// but hide it by default to reduce visual clutter.
		// Make sure it becomes visible when focused.
		$( '<div>' ).addClass( 'mw-navigation-hint' )
			.text( mw.msg( 'baseform-tabs-navigation-hint' ) )
			.attr( 'tabIndex', 0 )
			.prependTo( '#mw-content-text' );

		tabs = new OO.ui.IndexLayout( {
			expanded: false,
			// Do not remove focus from the tabs menu after choosing a tab
			autoFocus: false
		} );

		mw.config.get( 'wgManageWikiBaseFormTabs' ).forEach( function ( tabConfig ) {
			var panel, $panelContents;

			panel = new OO.ui.TabPanelLayout( tabConfig.name, {
				expanded: false,
				label: tabConfig.label
			} );
			$panelContents = $( '#mw-section-' + tabConfig.name );

			// Hide the unnecessary PHP PanelLayouts
			// (Do not use .remove(), as that would remove event handlers for everything inside them)
			$panelContents.parent().detach();

			panel.$element.append( $panelContents );
			tabs.addTabPanels( [ panel ] );

			// Remove duplicate labels
			// (This must be after .addTabPanels(), otherwise the tab item doesn't exist yet)
			$panelContents.children( 'legend' ).remove();
			$panelContents.attr( 'aria-labelledby', panel.getTabItem().getElementId() );
		} );

		wrapper = new OO.ui.PanelLayout( {
			expanded: false,
			padded: false,
			framed: true
		} );
		wrapper.$element.append( tabs.$element );
		$baseform.prepend( wrapper.$element );
		$( '.mw-baseform-faketabs' ).remove();

		function enhancePanel( panel ) {
			if ( !panel.$element.data( 'mw-section-infused' ) ) {
				// mw-htmlform-autoinfuse-lazy class has been removed by replacing faketabs
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
			location.hash = '#mw-section-' + panel.getName();
			if ( active ) {
				active.focus();
			}
			$( window ).scrollTop( scrollTop );
		}

		tabs.on( 'set', onTabPanelSet );

		/**
		 * @ignore
		 * @param {string} name the name of a tab without the prefix ("mw-section-")
		 * @param {boolean} [noHash] A hash will be set according to the current
		 *  open section. Use this flag to suppress this.
		 */
		function switchBaseFormTab( name, noHash ) {
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
				matchedElement, parentSection;
			if ( hash.match( /^#mw-section-[\w]+$/ ) ) {
				mw.storage.session.remove( 'mwbaseform-prevTab' );
				switchBaseFormTab( hash.replace( '#mw-section-', '' ) );
			} else if ( hash.match( /^#mw-[\w-]+$/ ) ) {
				matchedElement = document.getElementById( hash.slice( 1 ) );
				parentSection = $( matchedElement ).parent().closest( '[id^="mw-section-"]' );
				if ( parentSection.length ) {
					mw.storage.session.remove( 'mwbaseform-prevTab' );
					// Switch to proper tab and scroll to selected item.
					switchBaseFormTab( parentSection.attr( 'id' ).replace( 'mw-section-', '' ), true );
					matchedElement.scrollIntoView();
				}
			}
		}

		$( window ).on( 'hashchange', function () {
			var hash = location.hash;
			if ( hash.match( /^#mw-[\w-]+/ ) ) {
				detectHash();
			} else if ( hash === '' ) {
				switchBaseFormTab( 'personal', true );
			}
		} )
			// Run the function immediately to select the proper tab on startup.
			.trigger( 'hashchange' );

		// Restore the active tab after saving
		previousTab = mw.storage.session.get( 'mwbaseform-prevTab' );
		if ( previousTab ) {
			switchBaseFormTab( previousTab, true );
			// Deleting the key, the tab states should be reset until we press Save
			mw.storage.session.remove( 'mwbaseform-prevTab' );
		}

		$( '#mw-baseform-form' ).on( 'submit', function () {
			var value = tabs.getCurrentTabPanelName();
			mw.storage.session.set( 'mwbaseform-prevTab', value );
		} );

	} );
}() );
