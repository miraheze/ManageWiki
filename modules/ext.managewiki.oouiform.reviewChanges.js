( function () {
	$( () => {
		function ProcessDialog( config ) {
			ProcessDialog.super.call( this, config );
		}

		OO.inheritClass( ProcessDialog, OO.ui.ProcessDialog );

		ProcessDialog.static.name = 'managewiki-review';
		ProcessDialog.static.title = mw.msg( 'managewiki-review-title' );

		ProcessDialog.static.actions = [ {
			icon: 'close',
			flags: [ 'safe', 'close' ]
		} ];

		ProcessDialog.prototype.initialize = function () {
			ProcessDialog.super.prototype.initialize.apply( this, arguments );

			this.content = new OO.ui.PanelLayout( {
				padded: true,
				expanded: false
			} );

			this.$body.append( this.content.$element );

			$( '#managewiki-review' ).off( 'click.review' ).on( 'click.review', () => {
				this.content.$element.empty();

				const $inputs = $( '#managewiki-form :input[name]' )
					.not( '#managewiki-submit-reason :input[name]' );

				let hasChanges = false;

				$inputs.each( function () {
					const $input = $( this );
					const name = this.name
						.replace( 'wp', '' )
						.replace( /-namespace|-namespacetalk|ext-|set-/, '' );

					const label = $input
						.closest( 'fieldset' )
						.contents()
						.first()
						.text()
						.trim();

					if (
						this.type === 'checkbox' &&
						this.defaultChecked !== undefined &&
						this.defaultChecked !== this.checked
					) {
						const changeText = this.checked
							? mw.msg( 'managewiki-review-enabled' )
							: mw.msg( 'managewiki-review-disabled' );

						const message = mw.message(
							'managewiki-review-was-checkbox',
							`${ name.replace( '[]', `[${ this.value }]` ) } (${ label })`,
							changeText
						).text();

						this.content.$element.append( $( '<li>' ).text( message ) );
						hasChanges = true;
					} else if (
						this.defaultValue !== undefined &&
						this.defaultValue !== this.value
					) {
						const oldVal = this.defaultValue || mw.msg( 'managewiki-review-none' );
						const newVal = this.value || mw.msg( 'managewiki-review-none' );

						const message = mw.message(
							'managewiki-review-changed-from',
							`${ name } (${ label })`,
							oldVal,
							newVal
						).text();

						this.content.$element.append( $( '<li>' ).text( message ) );
						hasChanges = true;
					}
				}.bind( this ) );

				if ( !hasChanges ) {
					this.content.$element.append( $( '<i>' ).text( mw.msg( 'managewiki-review-nochanges' ) ) );
				}

				const windowManager = OO.ui.getWindowManagerForElement( this.$element );
				if ( windowManager ) {
					windowManager.openWindow( this );
				}
			} );
		};

		ProcessDialog.prototype.getActionProcess = function ( action ) {
			if ( action ) {
				return new OO.ui.Process( () => this.close( { action } ) );
			}
			return ProcessDialog.super.prototype.getActionProcess.call( this, action );
		};

		ProcessDialog.prototype.getBodyHeight = function () {
			return this.content.$element.outerHeight( true );
		};

		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		const processDialog = new ProcessDialog( {
			size: 'large'
		} );
		windowManager.addWindows( [ processDialog ] );
	} );
}() );
