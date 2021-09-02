const bananaChecker = require( '../src/banana.js' );

/*!
 * Grunt task wrapper for the banana-checker
 */
module.exports = function ( grunt ) {
	grunt.registerMultiTask( 'banana', function () {
		const options = this.options();
		const messageDirs = this.filesSrc.length;

		if ( messageDirs === 0 ) {
			grunt.log.error( 'Target directory does not exist.' );
			return false;
		}

		let ok = true;
		for ( const dir of this.filesSrc ) {
			ok = bananaChecker( dir, options, grunt.log.error ) && ok;
		}

		if ( !ok ) {
			return false;
		}

		grunt.log.ok( `${messageDirs} message director${( messageDirs > 1 ? 'ies' : 'y' )} checked.` );
	} );
};
