'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './merge' );
const rules = {
	"rules": {
		"es/no-object-fromentries": "error",
		"no-restricted-properties": [
			"error",
			{
				"property": "trimEnd",
				"message": "Unsupported method String.prototype.trimEnd requires ES2019."
			},
			{
				"property": "trimLeft",
				"message": "Unsupported method String.prototype.trimLeft requires ES2019."
			},
			{
				"property": "trimRight",
				"message": "Unsupported method String.prototype.trimRight requires ES2019."
			},
			{
				"property": "trimStart",
				"message": "Unsupported method String.prototype.trimStart requires ES2019."
			},
			{
				"property": "flat",
				"message": "Unsupported method Array.prototype.flat requires ES2019."
			},
			{
				"property": "flatMap",
				"message": "Unsupported method Array.prototype.flatMap requires ES2019."
			}
		]
	}
};
module.exports = merge( rules, require( './not-es2019' ) );
