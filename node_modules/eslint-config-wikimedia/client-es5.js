'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './language/merge' );
const rules = {
	"extends": [
		"./client-common",
		"./language/es5",
		"./vue-es5"
	],
	"rules": {
		"no-restricted-properties": [
			"error",
			{
				"property": "parentElement",
				"message": "Prefer parentNode to parentElement as Node.parentElement is not supported by IE11."
			}
		]
	}
};
// no-restricted-properties from not-es5 is overwritten by local value here,
// so use merge to fix this.
// If another language config is loaded later it will overwrite this, but the
// local rule here would not apply in browsers which properly support ES6.
module.exports = merge( rules, require( './language/not-es5' ) );
