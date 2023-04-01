(function () {
    $(function () {
        var $fallbackLayout = $( '.managewiki-wikitags.oo-ui-layout' );
        var $fallbackInput = $( '#mw-input-wpwikitags' );

        var editable = $fallbackLayout.hasClass('managewiki-editable')

        var infusedFallbackLayout = OO.ui.infuse( $fallbackLayout );
        var infusedFallbackInput = OO.ui.infuse( $fallbackInput );

        infusedFallbackLayout.$element.css( 'display', 'none' );
        if ( editable ) {
            infusedFallbackInput.setDisabled( false );
        }

        var initialSelection = infusedFallbackInput.getValue().split( ',' );

        var tags = $.map( Object.entries( mw.config.get( 'wgCreateWikiAvailableTags' ) ), function(val) {
            return {
                data: val[0],
                label: val[1]
            }
        });

        var multiTagSelect = new OO.ui.MenuTagMultiselectWidget( {
            selected: initialSelection,
            options: tags,
            tagLimit: 5,
            disabled: !editable
        } );
        var multiTagSelectLayout = new OO.ui.FieldLayout( multiTagSelect, {
            label: mw.msg( 'managewiki-label-wiki-tags' ),
            align: 'top'
        } );

        multiTagSelect.on( 'change', function( items ) {
            // Map selection changes back to the fallback input so that it is included in form submit
            infusedFallbackInput.setValue( $.map( items, function(val) {
                return val.data
            } ).join( ',' ) );
        } );

        infusedFallbackLayout.$element.before( multiTagSelectLayout.$element );
    });
})();