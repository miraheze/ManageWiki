'use strict';

/*
 * eslint-plugin-vue doesn't automatically apply core eslint rules to JS code inside templates,
 * but it does provide wrappers for some rules (e.g. vue/eqeqeq applies the eqeqeq rule in
 * templates). Set the values for these wrapped rules to be equal to the corresponding values
 * in common
 */
const commonRules = require( './common' ).rules;
const rulesToMap = [
	'array-bracket-spacing',
	'arrow-spacing',
	'block-spacing',
	'brace-style',
	'camelcase',
	'comma-dangle',
	'dot-location',
	'eqeqeq',
	'key-spacing',
	'keyword-spacing',
	'no-empty-pattern',
	'no-irregular-whitespace',
	'no-multi-spaces',
	// no-restricted-syntax differs between ES5/ES6, so it's set in vue-es5 / vue-es6
	'object-curly-spacing',
	'sort-keys',
	'space-infix-ops',
	'space-unary-ops'
];

const wrappedRules = {};
for ( const rule of rulesToMap ) {
	if ( rule in commonRules ) {
		wrappedRules[ 'vue/' + rule ] = commonRules[ rule ];
	}
}

module.exports = {
	rules: wrappedRules
};
