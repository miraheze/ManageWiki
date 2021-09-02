'use strict';

const utils = require( '../utils.js' );

// TODO: Support other class setting methods:
// * jQuery.attr
const jQueryMethodNames = [ 'addClass', 'removeClass', 'toggleClass' ];
const domMethodNames = [ 'add', 'remove', 'replace', 'toggle' ];

// Links to https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Coding_conventions/CSS#Constructed_class_names
const message = 'All possible CSS classes should be documented. See https://w.wiki/PS2 for details.';

function isPropName( node, prop ) {
	return (
		node.type === 'Identifier' && node.name === prop
	) || (
		node.type === 'Literal' && node.value === prop
	);
}

module.exports = {
	meta: {
		type: 'suggestion',
		docs: {
			description: 'Ensures CSS classes are documented when they are constructed.'
		},
		schema: []
	},

	create: function ( context ) {

		return {
			ObjectExpression: function ( node ) {
				const classesProp = node.properties.find(
					( prop ) => prop.type === 'Property' && isPropName( prop.key, 'classes' )
				);
				if ( !classesProp ) {
					return;
				}

				if ( utils.requiresCommentList( context, classesProp.value ) ) {
					context.report( {
						node: node,
						message: message
					} );
				}
			},

			AssignmentExpression: function ( node ) {
				if (
					node.left.type === 'MemberExpression' &&
					isPropName( node.left.property, 'className' ) &&
					utils.requiresCommentList( context, node.right )
				) {
					context.report( {
						node: node,
						message: message
					} );
				}
			},

			CallExpression: function ( node ) {
				if ( node.callee.type !== 'MemberExpression' ) {
					return;
				}

				if (
					!(
						jQueryMethodNames.includes( node.callee.property.name ) &&
						node.arguments.length &&
						utils.requiresCommentList( context, node.arguments[ 0 ] )
					) &&
					!(
						domMethodNames.includes( node.callee.property.name ) &&
						node.callee.object.property &&
						isPropName( node.callee.object.property, 'classList' ) &&
						node.arguments.some( ( arg ) => utils.requiresCommentList( context, arg ) )
					)
				) {
					return;
				}

				context.report( {
					node: node,
					message: message
				} );
			}
		};
	}
};
