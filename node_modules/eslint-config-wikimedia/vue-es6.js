'use strict';

/* eslint-disable quote-props, quotes */
module.exports = {
	"overrides": [ {
		"files": [ "**/*.vue" ],
		"extends": [
			"./vue-common",
			"./language/es6"
		],
		"rules": {
			// This is a wrapper rule, but it can't be in vue-wrappers because it's ES6-specific
			"vue/no-restricted-syntax": require( './language/not-es6' ).rules[ 'no-restricted-syntax' ]
		}
	} ]
};
