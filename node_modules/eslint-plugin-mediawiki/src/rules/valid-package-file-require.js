'use strict';

const path = require( 'upath' );

function dotSlashPrefixIfMissing( fileName ) {
	return fileName.indexOf( '.' ) !== 0 ? `./${fileName}` : fileName;
}

function getFullRelativeFilePath( name, context ) {
	const contextDirPath = path.dirname( context.getFilename() );
	const absolutePath = require.resolve(
		// `require( 'foo.js' )` will be resolved in older node version, whereas for newer ones
		// it should always be `require( './foo.js' )` for files in the same directory.
		// => always prefix with './'
		dotSlashPrefixIfMissing( name ),
		{ paths: [ contextDirPath ] }
	);
	const relativePath = path.relative( contextDirPath, absolutePath );

	return dotSlashPrefixIfMissing( relativePath );
}

function isValidPackageFileRequireForPath( requiredFile, fullRelativeFilePath ) {
	return requiredFile === fullRelativeFilePath ||
		requiredFile.startsWith( './../' ) && requiredFile.substr( 2 ) === fullRelativeFilePath;
}

module.exports = {
	meta: {
		type: 'problem',
		docs: {
			description: 'Ensures `require`d files are in the format that is expected within [ResourceLoader package modules](https://www.mediawiki.org/wiki/ResourceLoader/Package_modules).'
		},
		fixable: 'code',
		schema: [],
		messages: {
			badFilePath: 'Incorrect file path in require(): use {{ fullRelativeFilePath }} instead'
		}
	},

	create: function ( context ) {
		return {
			CallExpression: ( node ) => {
				if (
					node.callee.type !== 'Identifier' ||
					node.callee.name !== 'require' ||
					!node.arguments.length ||
					node.arguments[ 0 ].type !== 'Literal'
				) {
					return;
				}

				const requiredFileOrModule = node.arguments[ 0 ].value;
				// Check if the argument starts with ./ or ../, or ends with .js or .json
				if ( !requiredFileOrModule.match( /(^\.\.?\/)|(\.(js|json)$)/ ) ) {
					// If not, it's probably a ResourceLoader module; ignore
					return;
				}

				let fullRelativeFilePath;
				try {
					fullRelativeFilePath = getFullRelativeFilePath( requiredFileOrModule, context );
				} catch ( e ) {
					// File doesn't exist, probably a virtual file in a packageFiles module; ignore
					return;
				}

				if (
					!isValidPackageFileRequireForPath( requiredFileOrModule, fullRelativeFilePath )
				) {
					context.report( {
						node,
						messageId: 'badFilePath',
						data: { fullRelativeFilePath },
						fix( fixer ) {
							const escapedNewPath = fullRelativeFilePath.replace( /'/g, '\\\'' );
							return fixer.replaceText( node.arguments[ 0 ], `'${escapedNewPath}'` );
						}
					} );
				}
			}
		};
	}
};
