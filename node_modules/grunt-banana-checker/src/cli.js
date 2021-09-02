#!/usr/bin/env node
'use strict';

// argv: 0 = bin/node, 1 = src/cli.js, 2... = params
const params = process.argv.slice( 2 );
const dirs = [];
const options = {};
for ( const param of params ) {
	if ( !param.startsWith( '--' ) ) {
		dirs.push( param );
		continue;
	}
	const [ , key, value ] = /([a-zA-Z]+)(?:=(.+))?/.exec( param.slice( 2 ) );
	switch ( key ) {
		// String option
		case 'sourceFile':
		case 'documentationFile':
			options[ key ] = value;
			break;

		// Boolean option
		case 'disallowBlankTranslations':
		case 'disallowDuplicateTranslations':
		case 'disallowUnusedDocumentation':
		case 'disallowUnusedTranslations':
		case 'ignoreMissingBlankTranslations':
		case 'requireCompleteMessageDocumentation':
		case 'requireLowerCase':
		case 'requireMetadata':
			if ( value === undefined || value === '1' || value === 'true' ) {
				options[ key ] = true;
			} else if ( value === '0' || value === 'false' ) {
				options[ key ] = false;
			} else {
				console.error( `banana-check: Invalid option ignored, --${key}=${value}` );
			}
			break;

		// Array option
		case 'requireCompleteTranslationLanguages':
		case 'requireCompleteTranslationMessages':
		case 'requireKeyPrefix':
			options[ key ] = ( value || '' ).split( ',' );
			break;
		default:
			console.error( `banana-check: Invalid option ignored, --${key}` );
	}
}

if ( !dirs.length ) {
	console.error( 'banana-check: Specify one or more directories.' );
	process.exit( 1 );
}

const bananaChecker = require( '../src/banana.js' );
const result = dirs.every( ( dir ) => {
	return bananaChecker(
		dir,
		options,
		console.error
	);
} );
if ( !result ) {
	process.exit( 1 );
}

console.log( `Checked ${dirs.length} message director${( dirs.length > 1 ? 'ies' : 'y' )}.` );
