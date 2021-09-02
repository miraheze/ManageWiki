'use strict';

/* eslint-disable quote-props, quotes */
const merge = require( './merge' );
const rules = {
	"rules": {
		// ES2018 introduces Promise.prototype.finally, but linting for this
		// will produce false positives with existing promise libraries.
	}
};
module.exports = merge( rules, require( './not-es2018' ) );
