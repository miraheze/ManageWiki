[![NPM version](https://badge.fury.io/js/grunt-banana-checker.svg)](http://badge.fury.io/js/grunt-banana-checker) [![Build Status](https://travis-ci.org/wikimedia/banana-checker.svg?branch=master)](https://travis-ci.org/wikimedia/banana-checker)

# banana-checker

> Checker for the 'Banana' JSON-file format for interface messages, as used by MediaWiki and jQuery.i18n.

By default, Banana checker asserts the following:

* The source and documentation files must exist and contain valid JSON.
* Both files include a `@metadata` object.
* Each defined source message is documentated.
* Each defined documentation entry has a matching source message.

For all available options, see the [**Options** section](#options).

You can use Banana checker [standalone](#command-line-interface), or as [Grunt plugin](#getting-started-grunt-plugin).

## Getting started (Grunt plugin)

To use this plugin, add it as a development dependency to your project:

<pre lang=shell>
npm install grunt-banana-checker --save-dev
</pre>

Ensure your project has a Gruntfile.js file ([example file](http://gruntjs.com/sample-gruntfile)). Then, in Gruntfile.js, add the line:

<pre lang=js>
grunt.loadNpmTasks( 'grunt-banana-checker' );
</pre>

### Configure the Grunt plugin

In Gruntfile.js, add a configuation key for `banana` and set it to an empty object.

We will use this object to declare which directory contains the interface messages. For example, to enable grunt-banana-checker for a single directory only, configure it like so:

<pre lang=js>
grunt.initConfig( {
	banana: {
	    all: 'i18n/'
	}
} );
</pre>

You can also configure multiple directories, like so:

<pre lang=js>
grunt.initConfig( {
	banana: {
	    core: 'languages/i18n/',
	    installer: 'includes/installer/i18n/'
	}
} );
</pre>

You can also use globbing patterns and/or arrays of directories, like so:

<pre lang=js>
grunt.initConfig( {
	banana: {
	    all: 'modules/ve-{mw,wmf}/i18n/'
	}
} );
</pre>

For a full list of supported ways of defining the target directory of a Grunt plugin, see [Configuring tasks](https://gruntjs.com/configuring-tasks) on gruntjs.com.

To customise [the **options** for Banana checker](#Options), define your target directory as an object instead of a string, with `src` and `options` properties, like so:

<pre lang=js>
grunt.initConfig( {
	banana: {
		all: {
			src: 'i18n/',
			options: {
				sourceFile: 'messages.json',
				documentationFile: 'documentation.json'
			}
		}
	}
} );
</pre>

For all available options, see the [**Options** section](#Options).

## Command-line

The Banana checker also offers a command-line interface.

<pre lang=shell>
npm install grunt-banana-checker --save-dev
</pre>

To use Banana checker as part of your test run, refer to the `banana-checker`
program from the `scripts.test` property in your `package.json` file.

<pre lang=js>
{
	"scripts": {
		"test": "banana-checker i18n/"
	}
}
</pre>

To set custom options, pass parameters as `--key=value` pairs. For example:

<pre lang=shell>
npx banana-checker --requireKeyPrefix="x-" i18n/
</pre>

* For boolean options, use the valus `0`, `1`, `true`, or `false`.
* Quotes are allowed, but not required.
* For options that allow multiple values, separate values by comma. Like `--key=one,two`.

## Options

For edge cases, you can set some path options:

#### sourceFile
Type: `string`
Default value: `"en.json"`

The JSON file providing the primary messages.

#### documentationFile
Type: `string`
Default value: `"qqq.json"`

The JSON file providing the documentation messages.

#### requireMetadata
Type: `boolean`
Default value: `true`

Whether to fail if message files don't have a `@metadata` meta-data key.

#### requireCompleteMessageDocumentation
Type: `boolean`
Default value: `true`

Whether to fail if any message is in the primary file but not documented.

#### disallowEmptyDocumentation
Type: `boolean`
Default value: `true`

Whether to fail if any message is in the primary file but documented as a blank string.

#### requireLowerCase
Type: `boolean` or `"initial"`
Default value: `true`

Whether to fail if any message key is not lower case. If set to `"initial"`, fail only if the first
character is not lower case.

#### requireKeyPrefix
Type: `string` or `string[]`
Default value: `[]`

Whether to fail if any message key is not prefixed by the given prefix, or if multiple, one of the
given prefices.

#### disallowUnusedDocumentation
Type: `boolean`
Default value: `true`

Whether to fail if any documented message isn't in the primary file.

#### disallowBlankTranslations
Type: `boolean`
Default value: `true`

Whether to fail if any message is translated as a blank string.

#### disallowDuplicateTranslations
Type: `boolean`
Default value: `false`

Whether to fail if any message is translated as identical to the original string.

#### disallowUnusedTranslations
Type: `boolean`
Default value: `false`

Whether to fail if any translated message isn't in the primary file.

#### requireCompletelyUsedParameters
Type: `boolean`
Default value: `false`

Whether to fail if any translated message fails to use a parameter used in the primary message.

#### requireCompleteTranslationLanguages
Type: `string[]`
Default value: `[]`
Example value: `[ 'fr', 'es' ]`

Languages on which to fail if any message in the primary file is missing.

#### requireCompleteTranslationMessages
Type: `string[]`
Default value: `[]`
Example value: `[ 'first-message-key', 'third-message-key' ]`

Messages on which to fail if missing in any provided language.

### ignoreMissingBlankTranslations
Type: `boolean`
Default value: `true`

Whether to ignore missing translations whose original string is blank.
