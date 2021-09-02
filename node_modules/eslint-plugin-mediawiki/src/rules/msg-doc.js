'use strict';

const utils = require( '../utils.js' );

// TODO: Support `new mw.Message( store, key )` syntax
const methodNames = [ 'msg', 'message', 'deferMsg' ];
// Links to https://www.mediawiki.org/wiki/Special:MyLanguage/Localisation#Using_messages
const message = 'All possible message keys should be documented. See https://w.wiki/PRw for details.';

module.exports = {
	meta: {
		type: 'suggestion',
		docs: {
			description: 'Ensures message keys are documented when they are constructed.'
		},
		schema: []
	},

	create: function ( context ) {

		return {
			CallExpression: function ( node ) {
				if (
					node.callee.type !== 'MemberExpression' ||
					!methodNames.includes( node.callee.property.name ) ||
					!node.arguments.length
				) {
					return;
				}

				if ( utils.requiresCommentList( context, node.arguments[ 0 ] ) ) {
					context.report( {
						node: node,
						message: message
					} );
				}
			}
		};
	}
};
