# stylelint-config-wikimedia
[![NPM version](http://img.shields.io/npm/v/stylelint-config-wikimedia.svg)](https://www.npmjs.org/package/stylelint-config-wikimedia)
> Wikimedia CSS Coding Standards shareable config for stylelint

Configuration rules to ensure your CSS is compliant with the [Wikimedia CSS Coding Standards](https://www.mediawiki.org/wiki/Manual:Coding_conventions/CSS).


## Installation

```console
$ npm install -D stylelint-config-wikimedia
```


## Usage

Set your `stylelint` config file, `.stylelintrc.json`, to:

```json
{
	"extends": "stylelint-config-wikimedia"
}
```

If you would also like to enable rules to disallow CSS which is unsupported by modern browsers [Grade A](https://www.mediawiki.org/wiki/Compatibility#Modern_(Grade_A)) or basic supported browsers [Grade C](https://www.mediawiki.org/wiki/Compatibility#Basic_(Grade_C)) browsers, you can use the following configurations instead:

```json
{
	"extends": "stylelint-config-wikimedia/support-modern"
}
```

```json
{
	"extends": "stylelint-config-wikimedia/support-basic"
}
```


### Extend or override the configuration
Add a `"rules"` object to your config file, and add your overrides or additional rules there, for example:
```json
{
	"extends": "stylelint-config-wikimedia/support-basic",
	"rules": {
		"max-empty-lines": null
	}
}
```


## Changelog
You can read the [changelog](CHANGELOG.md) for release versions.


## License
This is available under the [MIT License](LICENSE).
