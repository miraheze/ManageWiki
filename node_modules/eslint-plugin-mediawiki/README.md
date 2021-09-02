# eslint-plugin-mediawiki
MediaWiki-specific linting rules, for use in MediaWiki core and extensions.

## Usage

__If you are developing in MediaWiki you should be using [eslint-config-wikimedia](https://github.com/wikimedia/eslint-config-wikimedia) which includes this plugin.__

Add `mediawiki` to the plugins section of your `.eslintrc` configuration file, then enable the required rules.

```json
{
  "plugins": [
    "mediawiki"
  ],
  "rules": {
    "mediawiki/msg-doc": "error"
  }
}
```

## Rules
* [`mediawiki/class-doc`](docs/rules/class-doc.md)
* [`mediawiki/msg-doc`](docs/rules/msg-doc.md)
* [`mediawiki/valid-package-file-require`](docs/rules/valid-package-file-require.md)
* [`mediawiki/no-vue-dynamic-i18n`](docs/rules/no-vue-dynamic-i18n.md)

## Development

```sh
npm install
npm test
```

## License

Distributed under the MIT license. See LICENSE for details.
