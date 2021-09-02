'use strict';

module.exports = {
	rules: {
		'class-doc': require( './rules/class-doc.js' ),
		'msg-doc': require( './rules/msg-doc.js' ),
		'no-vue-dynamic-i18n': require( './rules/no-vue-dynamic-i18n.js' ),
		'valid-package-file-require': require( './rules/valid-package-file-require.js' )
	}
};
