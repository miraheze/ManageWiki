'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './merge' );
const rules = {
	"plugins": [ "es" ],
	"rules": {
		"es/no-array-from": "error",
		"es/no-array-of": "error",
		"es/no-math-acosh": "error",
		"es/no-math-asinh": "error",
		"es/no-math-atanh": "error",
		"es/no-math-cbrt": "error",
		"es/no-math-clz32": "error",
		"es/no-math-cosh": "error",
		"es/no-math-expm1": "error",
		"es/no-math-fround": "error",
		"es/no-math-hypot": "error",
		"es/no-math-imul": "error",
		"es/no-math-log10": "error",
		"es/no-math-log1p": "error",
		"es/no-math-log2": "error",
		"es/no-math-sign": "error",
		"es/no-math-sinh": "error",
		"es/no-math-tanh": "error",
		"es/no-math-trunc": "error",
		"es/no-number-epsilon": "error",
		"es/no-number-isfinite": "error",
		"es/no-number-isinteger": "error",
		"es/no-number-isnan": "error",
		"es/no-number-issafeinteger": "error",
		"es/no-number-maxsafeinteger": "error",
		"es/no-number-minsafeinteger": "error",
		"es/no-number-parsefloat": "error",
		"es/no-number-parseint": "error",
		"es/no-object-assign": "error",
		"es/no-object-getownpropertysymbols": "error",
		"es/no-object-is": "error",
		"es/no-string-fromcodepoint": "error",
		"es/no-string-raw": "error",
		"no-restricted-properties": [
			"error",
			{
				"property": "codePointAt",
				"message": "Unsupported method String.prototype.codePointAt requires ES6."
			},
			{
				"property": "endsWith",
				"message": "Unsupported method String.prototype.endsWith requires ES6."
			},
			{
				"property": "normalize",
				"message": "Unsupported method String.prototype.normalize requires ES6."
			},
			{
				"property": "repeat",
				"message": "Unsupported method String.prototype.repeat requires ES6."
			},
			{
				"property": "startsWith",
				"message": "Unsupported method String.prototype.startsWith requires ES6."
			},
			{
				"property": "copyWithin",
				"message": "Unsupported method Array.prototype.copyWithin requires ES6."
			},
			{
				"property": "fill",
				"message": "Unsupported method Array.prototype.fill requires ES6."
			},
			{
				"property": "findIndex",
				"message": "Unsupported method Array.prototype.findIndex requires ES6."
			}
		],
		"no-restricted-syntax": [
			"error",
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='includes']",
				"message": "Unsupported method String.prototype.includes requires ES6."
			},
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='entries'][callee.object.name!='Object']",
				"message": "Unsupported method Array.prototype.entries requires ES6."
			},
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='find'][arguments.length=1][arguments.0.type='FunctionExpression']",
				"message": "Unsupported method Array.prototype.find requires ES6."
			},
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='keys'][callee.object.name!='Object']",
				"message": "Unsupported method Array.prototype.keys requires ES6."
			},
			{
				"selector": "CallExpression[callee.type='MemberExpression'][callee.property.type='Identifier'][callee.property.name='values'][callee.object.name!='Object']",
				"message": "Unsupported method Array.prototype.values requires ES6."
			}
		]
	}
};

module.exports = merge( rules, require( './not-es6' ) );
