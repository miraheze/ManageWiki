[![Build Status](https://travis-ci.org/wikimedia/eslint-config-wikimedia.svg?branch=master)](https://travis-ci.org/wikimedia/eslint-config-wikimedia)

# Wikimedia ESLint config

## Installation

```sh
npm install --save-dev eslint-config-wikimedia
```

Then, [configure ESLint](https://eslint.org/docs/user-guide/configuring) with one or more `.eslintrc.json` files as appropriate.

## Example configurations
Below are some potential recommended uses:

### A typical front-end project
This config allows ES5 code and browser native functions. It will complain about ES6+ code and language features.
It also automatically applies the Vue plugin and Vue-specific rules to `.vue` files.

`.eslintrc.json`:
```json
{
	"extends": "wikimedia/client-es5"
}
```
If you want to allow ES6 code and browser native functions as well, you can use `wikimedia/client-es6` instead.

#### jQuery
This config adds the jQuery `$` global, and additional rules preventing the use of jQuery features which are deprecated, have performance issues, or have simple ES5 alternatives.
`.eslintrc.json`:
```json
{
	"extends": [
		"wikimedia/client-es5",
		"wikimedia/jquery"
	]
}
```

#### MediaWiki
Code that runs in MediaWiki can use this config. It enforces rules that are specific to the MediaWiki codebase (core and extensions), such as correct documentation of `mw.message` usage, and prohibiting self-closing tags in Vue templates.
`.eslintrc.json`:
```json
{
	"extends": [
		"wikimedia/client-es5",
		"wikimedia/mediawiki"
	]
}
```

### Adding a QUnit test suite
You can extend the above config by also adding a second `.eslintrc.json` file in your tests directory:

`tests/qunit/.eslintrc.json`:
```json
{
	"extends": [
		"../../.eslintrc.json",
		"wikimedia/qunit"
	]
}
```

### Adding a Mocha test suite
The following config will the Mocha environment and some Mocha related rules:

`tests/mocha/.eslintrc.json`:
```json
{
	"root": true,
	"extends": [
		"wikimedia/server",
		"wikimedia/mocha"
	]
}
```

### Adding a Selenium WDIO test suite
The following config will enable WDIO globals, as well as the Mocha and server configs:

`tests/selenium/.eslintrc.json`:
```json
{
	"root": true,
	"extends": [
		"wikimedia/selenium"
	]
}
```

### A typical Node project
This config allows ES2018 code and Node native functions (_i.e._, Node 10.x). It will complain about ES2019+ code and language features.

`.eslintrc.json`:
```json
{
	"extends": "wikimedia/server"
}
```

The `wikimedia/server` config consists of `wikimedia`, `wikimedia/node` and `wikimedia/language/es2018`. To use later versions of ES, for example ES2019, you can use the following config:
`.eslintrc.json`:
```json
{
	"extends": [
		"wikimedia",
		"wikimedia/node",
		"wikimedia/language/es2019"
	]
}
```

### A basic project
Please note that the basic project configuration does not specify any language or environmental defaults, and is unlikely to be suitable. However, if you wish to target clients with ES3 language support, or earlier versions of Node, this is a good place from which to start.

`.eslintrc.json`:
```json
{
	"extends": "wikimedia"
}
```

## Proposing changes

Major changes should be discussed [on mediawiki.org](https://www.mediawiki.org/wiki/Manual_talk:Coding_conventions/JavaScript) or on the [Wikitech mailing list](https://lists.wikimedia.org/mailman/listinfo/wikitech-l) beforehand.

## Semver policy

Same approach as in ESLint, see https://github.com/eslint/eslint#user-content-semantic-versioning-policy.
