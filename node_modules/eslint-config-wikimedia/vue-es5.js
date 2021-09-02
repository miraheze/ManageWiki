'use strict';

/* eslint-disable quote-props, quotes */
module.exports = {
	"overrides": [ {
		"files": [ "**/*.vue" ],
		"extends": [
			"./vue-common",
			// We can't use ./language/es5 here, because ecmaVersion: 5 breaks the Vue plugin
			// Instead, use ES6, then es/no-2015 to prohibit ES6+ syntax
			// But don't use ./language/es-6 directly, because we don't want rules-es6
			"./language/rules-es5",
			"./language/not-es5",
			"plugin:es/restrict-to-es5"
		],
		"plugins": [ "es" ],
		// The Vue plugin sets sourceType: "module" and enables JSX: undo those things
		"parserOptions": {
			// ecmaVersion: 5 breaks the Vue plugin, we have to use 6 (see also above)
			"ecmaVersion": 6,
			"sourceType": "script",
			"ecmaFeatures": {
				"jsx": false
			}
		},
		"env": {
			"es6": false
		},
		"rules": {
			// This is a wrapper rule, but it can't be in vue-wrappers because it's ES5-specific
			"vue/no-restricted-syntax": require( './language/not-es5' ).rules[ 'no-restricted-syntax' ]
		}
	} ]
};
