'use strict';

module.exports = function ( childConfig, parentConfig ) {
	const mergedConfig = {};

	// Merge top level config
	Object.assign( mergedConfig, parentConfig, childConfig );

	// Merge rules
	mergedConfig.rules = {};
	Object.assign( mergedConfig.rules, parentConfig.rules, childConfig.rules );

	// For the specified keys, concatenate the lists
	[ 'no-restricted-syntax', 'no-restricted-properties' ].forEach( function ( key ) {
		if ( !mergedConfig.rules[ key ] ) {
			// If both are unset, do nothing
			return;
		} else {
			// Assume mode is 'error'.
			mergedConfig.rules[ key ] = [ 'error' ]
				.concat( ( parentConfig.rules[ key ] || [] ).slice( 1 ) )
				.concat( ( childConfig.rules[ key ] || [] ).slice( 1 ) );
		}
	} );

	return mergedConfig;
};
