module.exports = {
	root: true,
	overrides: [
		{
			files: [ 'en.json', 'qqq.json' ],
			extends: [ 'plugin:jsonc/base' ],
			rules: { 'jsonc/sort-keys': 'error' }
		}
	]
};
