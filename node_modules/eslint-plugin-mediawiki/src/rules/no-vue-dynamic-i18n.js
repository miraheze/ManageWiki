'use strict';

const vueUtils = require( 'eslint-plugin-vue/lib/utils' );

module.exports = {
	meta: {
		type: 'suggestion',
		docs: {
			description: 'Prohibits dynamic i18n message keys in Vue templates'
		},
		schema: [],
		messages: {
			'dynamic-i18n': 'Dynamic message keys should not be used in templates. Use a computed property instead.'
		}
	},
	create( context ) {
		return vueUtils.defineTemplateBodyVisitor( context, {
			"VExpressionContainer CallExpression[callee.name='$i18n']"( node ) {
				if ( node.arguments.length > 0 && node.arguments[ 0 ].type !== 'Literal' ) {
					context.report( { node, messageId: 'dynamic-i18n' } );
				}
			},
			"VAttribute[directive=true][key.name.name='i18n-html']"( node ) {
				if ( node.key.argument && node.key.argument.type === 'VExpressionContainer' ) {
					context.report( { node, messageId: 'dynamic-i18n' } );
				}
			}
		} );
	}
};
