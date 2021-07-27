( function () {
	$( function () {
		function ProcessDialog( config ) {
			ProcessDialog.super.call( this, config );
		}

		OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

		ProcessDialog.static.name = 'managewiki-review';

		ProcessDialog.static.title = mw.msg( 'managewiki-review-title' );

		ProcessDialog.static.actions = [ {
			label: OO.ui.deferMsg( 'managewiki-review-close' ),
			flags: 'safe'
		} ];

		ProcessDialog.prototype.initialize = function () {
			ProcessDialog.super.prototype.initialize.apply( this, arguments );

			this.content = new OO.ui.PanelLayout( {
				padded: true,
				expanded: false
			} );

			var dialog = this;
			$( '#managewiki-review' ).click( function () {
				dialog.content.$element.html( '' );
				$( '#managewiki-form :input:not( #managewiki-submit-reason :input )' ).each( function () {
					if ( this.type == 'checkbox' && this.defaultChecked != undefined && this.defaultChecked != this.checked ) {
						dialog.content.$element.append( '<li>' + mw.message( 'managewiki-review-checkboxchanged', this.name.replace( 'wp', '' ).replace( '[]', '[' + this.value + ']' ), ( this.checked == true ? mw.msg( 'managewiki-review-checkboxenabled' ) : mw.msg( 'managewiki-review-checkboxdisabled' ) ) ).parse() + '</li>' );
					} else if ( this.defaultValue != undefined && this.defaultValue != this.value ) {
						dialog.content.$element.append( '<li>' + mw.message( 'managewiki-review-inputchanged', this.name.replace( 'wp', '' ), ( this.defaultValue ? this.defaultValue : mw.msg( 'managewiki-review-none' ) ), ( this.value ? this.value : mw.msg( 'managewiki-review-none' ) ) ).parse() + '</li>' );
					}
				} );

				if ( !dialog.content.$element.html() ) {
					dialog.content.$element.append( mw.message( 'managewiki-review-nochanges' ) );
				}

				dialog.$body.append( dialog.content.$element );
			} );
		};

		ProcessDialog.prototype.getActionProcess = function ( action ) {
			var dialog = this;
			if ( action ) {
				return new OO.ui.Process( function () {
					dialog.close( {
						action: action
					} );
				} );
			}

			return ProcessDialog.super.prototype.getActionProcess.call( this, action );
		};

		ProcessDialog.prototype.getBodyHeight = function () {
			return this.content.$element.outerHeight( true );
		};

		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		var processDialog = new ProcessDialog( {
			size: 'medium'
		} );

		windowManager.addWindows( [ processDialog ] );

		$( '#managewiki-review' ).click( function () {
			windowManager.openWindow( processDialog );
		} );
	} );
}() );
