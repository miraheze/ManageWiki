'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './merge' );
const rules = {
	"rules": {
		"es/no-promise-any": "error",
		"no-restricted-properties": [
			"error",
			{
				"property": "replaceAll",
				"message": "Unsupported method String.prototype.replaceAll requires ES2021."
			}
		]
	}
};
module.exports = merge( rules, require( './not-es2021' ) );
