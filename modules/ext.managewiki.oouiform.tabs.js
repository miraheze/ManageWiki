( function () {
	$( function () {
		// The lazy loading is temporarily disabled to prevent conflict with the cloner widget.
		var autoinfuseLazy = false,
			tabs, previousTab, switchingNoHash;

		tabs = OO.ui.infuse( $( '.managewiki-tabs' ) );

		tabs.$element.addClass( 'managewiki-tabs-infused' );

		function enhancePanel( panel ) {
			var $infuse = $( panel.$element ).find( '.managewiki-infuse' );
			$infuse.each( function () {
				try {
					OO.ui.infuse( this );
				} catch ( error ) {
					return;
				}
			} );

			if ( autoinfuseLazy && !panel.$element.data( 'mw-section-infused' ) ) {
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
			if ( hash.match( /^#mw-section-[\w-]+$/ ) ) {
				mw.storage.session.remove( 'managewiki-prevTab' );
				switchManageWikiTab( hash.slice( 1 ) );
			} else if ( hash.match( /^#mw-[\w-]+$/ ) ) {
				matchedElement = document.getElementById( hash.slice( 1 ) );
				$parentSection = $( matchedElement ).closest( '.managewiki-section-fieldset' );
				if ( $parentSection.length ) {
					mw.storage.session.remove( 'managewiki-prevTab' );
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
			} else if ( hash === '' ) {
				switchManageWikiTab( $( '[id*=mw-section-]' ).attr( 'id' ), true );
			}
		} )
			// Run the function immediately to select the proper tab on startup.
			.trigger( 'hashchange' );

		// Restore the active tab after saving the settings
		previousTab = mw.storage.session.get( 'managewiki-prevTab' );
		if ( previousTab ) {
			switchManageWikiTab( previousTab, true );
			// Deleting the key, the tab states should be reset until we press Save
			mw.storage.session.remove( 'managewiki-prevTab' );
		}

		$( '#managewiki-form' ).on( 'submit', function () {
			var value = tabs.getCurrentTabPanelName();
			mw.storage.session.set( 'managewiki-prevTab', value );
		} );

		// Search index
		var index, texts;
		function buildIndex() {
			index = {};
			var $fields = tabs.contentPanel.$element.find(
				'[class^=mw-htmlform-field-]:not( .managewiki-search-noindex )'
			);
			$fields.each( function () {
				var $field = $( this );
				var $wrapper = $field.parents( '.managewiki-fieldset-wrapper' );
				var $tabPanel = $field.closest( '.oo-ui-tabPanelLayout' );

				function addToIndex( $label, $highlight ) {
					var text = $label.val() || $label[ 0 ].innerText.toLowerCase().trim().replace( /\s+/, ' ' );
					if ( text ) {
						index[ text ] = index[ text ] || [];
						index[ text ].push( {
							$highlight: $highlight || $label,
							$field: $field,
							$wrapper: $wrapper,
							$tabPanel: $tabPanel
						} );
					}
				}

				$field.find( '.oo-ui-labelElement-label, .oo-ui-textInputWidget .oo-ui-inputWidget-input, p' ).add(
					$wrapper.find( '> .oo-ui-fieldsetLayout > .oo-ui-fieldsetLayout-header .oo-ui-labelElement-label' )
				).each( function () {
					addToIndex( $( this ) );

					// Check if there we are in an infusable dropdown and collect other options
					var $dropdown = $( this ).closest( '.oo-ui-dropdownInputWidget[data-ooui],.mw-widget-selectWithInputWidget[data-ooui]' );
					if ( $dropdown.length ) {
						var dropdown = OO.ui.infuse( $dropdown[ 0 ] );
						var dropdownWidget = ( dropdown.dropdowninput || dropdown ).dropdownWidget;
						if ( dropdownWidget ) {
							dropdownWidget.getMenu().getItems().forEach( function ( option ) {
								// Highlight the dropdown handle and the matched label, for when the dropdown is opened
								addToIndex( option.$label, dropdownWidget.$handle );
								addToIndex( option.$label, option.$label );
							} );
						}
					}
				} );
			} );
			mw.hook( 'managewiki.search.buildIndex' ).fire( index );
			texts = Object.keys( index );
		}
		function infuseAllPanels() {
			tabs.stackLayout.items.forEach( function ( tabPanel ) {
				var wasVisible = tabPanel.isVisible();
				// Force panel to be visible while infusing
				tabPanel.toggle( true );

				enhancePanel( tabPanel );

				// Restore visibility
				tabPanel.toggle( wasVisible );
			} );
		}

		var search = OO.ui.infuse( $( '.managewiki-search' ) ).fieldWidget;
		search.$input.on( 'focus', function () {
			if ( !index ) {
				// Lazy-build index on first focus
				// Infuse all widgets as we may end up showing a large subset of them
				infuseAllPanels();
				buildIndex();
			}
		} );
		var $noResults = $( '<div>' ).addClass( 'managewiki-search-noresults' ).text( mw.msg( 'managewiki-search-noresults' ) );
		search.on( 'change', function ( val ) {
			if ( !index ) {
				// In case 'focus' hasn't fired yet
				infuseAllPanels();
				buildIndex();
			}
			var isSearching = !!val;
			tabs.$element.toggleClass( 'managewiki-tabs-searching', isSearching );
			tabs.tabSelectWidget.toggle( !isSearching );
			$( '.managewiki-search-matched' ).removeClass( 'managewiki-search-matched' );
			$( '.managewiki-search-highlight' ).removeClass( 'managewiki-search-highlight' );
			var hasResults = false;
			if ( isSearching ) {
				val = val.toLowerCase();
				texts.forEach( function ( text ) {
					// TODO: Could use Intl.Collator.prototype.compare like OO.ui.mixin.LabelElement.static.highlightQuery
					// but might be too slow.
					if ( text.indexOf( val ) !== -1 ) {
						index[ text ].forEach( function ( item ) {
							item.$highlight.addClass( 'managewiki-search-highlight' );
							item.$field.addClass( 'managewiki-search-matched' );
							item.$wrapper.addClass( 'managewiki-search-matched' );
							item.$tabPanel.addClass( 'managewiki-search-matched' );
						} );
						hasResults = true;
					}
				} );
			}
			if ( isSearching && !hasResults ) {
				tabs.$element.append( $noResults );
			} else {
				$noResults.detach();
			}
		} );

		// Handle the initial value in case the user started typing before this JS code loaded,
		// or the browser restored the value for a closed tab
		if ( search.getValue() ) {
			search.emit( 'change', search.getValue() );
		}
	} );
}() );
