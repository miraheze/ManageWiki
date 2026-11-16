'use strict';

const { defineConfig, globalIgnores } = require( 'eslint/config' );
const jsonc = require( 'eslint-plugin-jsonc' );
const wikimedia = require( 'eslint-config-wikimedia/flat' );

module.exports = defineConfig( [
	globalIgnores( [ 'vendor/' ] ),
	{
		ignores: [ 'i18n/**' ],
		extends: [ wikimedia( 'client', 'jquery', 'mediawiki' ) ],
		languageOptions: {
			ecmaVersion: 2024
		},
		rules: {
			'no-jquery/no-global-selector': 'off',
			'no-jquery/no-sizzle': 'off'
		}
	},
	{
		files: [ 'i18n/en.json', 'i18n/qqq.json' ],
		extends: [ jsonc.configs[ 'flat/base' ] ],
		rules: {
			'jsonc/sort-keys': 'error'
		}
	}
] );
