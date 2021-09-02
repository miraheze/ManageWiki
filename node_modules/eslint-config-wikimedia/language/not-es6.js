'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './merge' );
const rules = {
	"rules": {
		"no-restricted-syntax": [
			"error",
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='includes']",
				"message": "Unsupported method Array.prototype.includes requires ES2016."
			}
		]
	}
};
module.exports = merge( rules, require( './not-es2016' ) );
