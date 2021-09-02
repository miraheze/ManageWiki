# eslint-plugin-qunit

[![NPM version](https://img.shields.io/npm/v/eslint-plugin-qunit.svg?style=flat)](https://npmjs.org/package/eslint-plugin-qunit)
![CI](https://github.com/platinumazure/eslint-plugin-qunit/workflows/CI/badge.svg)
[![Coverage Status](https://coveralls.io/repos/platinumazure/eslint-plugin-qunit/badge.svg?branch=master&service=github)](https://coveralls.io/github/platinumazure/eslint-plugin-qunit?branch=master)
[![devDependency Status](https://david-dm.org/platinumazure/eslint-plugin-qunit/dev-status.svg)](https://david-dm.org/platinumazure/eslint-plugin-qunit#info=devDependencies)
[![Join the chat at https://gitter.im/platinumazure/eslint-plugin-qunit](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/platinumazure/eslint-plugin-qunit?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

ESLint plugin containing rules useful for QUnit tests.

## Configurations

You can extend from a configuration in order to simplify manual configuration of plugin rules in your project.

For more details on how to extend your configuration from a plugin configuration, please see the [ESLint plugin configuration documentation](http://eslint.org/docs/user-guide/configuring#using-the-configuration-from-a-plugin).

|     | Name | Description |
| :-- | :--- | :---------- |
| :white_check_mark: | recommended | This configuration includes rules which I recommend to avoid QUnit runtime errors or incorrect behavior, some of which can be difficult to debug. Some of these rules also encourage best practices that help QUnit work better for you. You can use this configuration by extending from `"plugin:qunit/recommended"` in your configuration file. |

## Rules

Each rule has emojis denoting:

* What configuration it belongs to
* :wrench: if some problems reported by the rule are automatically fixable by the `--fix` [command line](https://eslint.org/docs/user-guide/command-line-interface#fixing-problems) option
* 💡 if some problems reported by the rule are manually fixable by editor [suggestions](https://eslint.org/docs/developer-guide/working-with-rules#providing-suggestions)

<!--RULES_TABLE_START-->

| Name | Description | :white_check_mark: | :wrench: | 💡 |
|:--------|:--------|:---|:---|:---|
| [assert-args](./docs/rules/assert-args.md) | enforce that the correct number of assert arguments are used | :white_check_mark: |  |  |
| [literal-compare-order](./docs/rules/literal-compare-order.md) | enforce comparison assertions have arguments in the right order | :white_check_mark: | :wrench: |  |
| [no-arrow-tests](./docs/rules/no-arrow-tests.md) | disallow arrow functions as QUnit test/module callbacks | :white_check_mark: | :wrench: |  |
| [no-assert-equal](./docs/rules/no-assert-equal.md) | disallow the use of assert.equal |  |  | 💡 |
| [no-assert-equal-boolean](./docs/rules/no-assert-equal-boolean.md) | require use of boolean assertions | :white_check_mark: | :wrench: |  |
| [no-assert-logical-expression](./docs/rules/no-assert-logical-expression.md) | disallow binary logical expressions in assert arguments | :white_check_mark: |  |  |
| [no-assert-ok](./docs/rules/no-assert-ok.md) | disallow the use of assert.ok/assert.notOk |  |  |  |
| [no-async-in-loops](./docs/rules/no-async-in-loops.md) | disallow async calls in loops | :white_check_mark: |  |  |
| [no-async-module-callbacks](./docs/rules/no-async-module-callbacks.md) | disallow async module callbacks | :white_check_mark: |  |  |
| [no-async-test](./docs/rules/no-async-test.md) | disallow the use of asyncTest or QUnit.asyncTest | :white_check_mark: |  |  |
| [no-commented-tests](./docs/rules/no-commented-tests.md) | disallow commented tests | :white_check_mark: |  |  |
| [no-compare-relation-boolean](./docs/rules/no-compare-relation-boolean.md) | disallow comparing relational expressions to booleans in assertions | :white_check_mark: | :wrench: |  |
| [no-conditional-assertions](./docs/rules/no-conditional-assertions.md) | disallow assertions within if statements or conditional expressions | :white_check_mark: |  |  |
| [no-early-return](./docs/rules/no-early-return.md) | disallow early return in tests | :white_check_mark: |  |  |
| [no-global-assertions](./docs/rules/no-global-assertions.md) | disallow global QUnit assertions | :white_check_mark: |  |  |
| [no-global-expect](./docs/rules/no-global-expect.md) | disallow global expect | :white_check_mark: |  |  |
| [no-global-module-test](./docs/rules/no-global-module-test.md) | disallow global module/test/asyncTest | :white_check_mark: |  |  |
| [no-global-stop-start](./docs/rules/no-global-stop-start.md) | disallow global stop/start | :white_check_mark: |  |  |
| [no-hooks-from-ancestor-modules](./docs/rules/no-hooks-from-ancestor-modules.md) | disallow the use of hooks from ancestor modules | :white_check_mark: |  |  |
| [no-identical-names](./docs/rules/no-identical-names.md) | disallow identical test and module names | :white_check_mark: |  |  |
| [no-init](./docs/rules/no-init.md) | disallow use of QUnit.init | :white_check_mark: |  |  |
| [no-jsdump](./docs/rules/no-jsdump.md) | disallow use of QUnit.jsDump | :white_check_mark: |  |  |
| [no-loose-assertions](./docs/rules/no-loose-assertions.md) | disallow the use of assert.equal/assert.ok/assert.notEqual/assert.notOk |  |  |  |
| [no-negated-ok](./docs/rules/no-negated-ok.md) | disallow negation in assert.ok/assert.notOk | :white_check_mark: | :wrench: |  |
| [no-nested-tests](./docs/rules/no-nested-tests.md) | disallow nested QUnit.test() calls | :white_check_mark: |  |  |
| [no-ok-equality](./docs/rules/no-ok-equality.md) | disallow equality comparisons in assert.ok/assert.notOk | :white_check_mark: | :wrench: |  |
| [no-only](./docs/rules/no-only.md) | disallow QUnit.only | :white_check_mark: |  |  |
| [no-qunit-push](./docs/rules/no-qunit-push.md) | disallow QUnit.push | :white_check_mark: |  |  |
| [no-qunit-start-in-tests](./docs/rules/no-qunit-start-in-tests.md) | disallow QUnit.start() within tests or test hooks | :white_check_mark: |  |  |
| [no-qunit-stop](./docs/rules/no-qunit-stop.md) | disallow QUnit.stop | :white_check_mark: |  |  |
| [no-reassign-log-callbacks](./docs/rules/no-reassign-log-callbacks.md) | disallow overwriting of QUnit logging callbacks | :white_check_mark: |  |  |
| [no-reset](./docs/rules/no-reset.md) | disallow QUnit.reset | :white_check_mark: |  |  |
| [no-setup-teardown](./docs/rules/no-setup-teardown.md) | disallow setup/teardown module hooks | :white_check_mark: | :wrench: |  |
| [no-skip](./docs/rules/no-skip.md) | disallow QUnit.skip |  |  |  |
| [no-test-expect-argument](./docs/rules/no-test-expect-argument.md) | disallow the expect argument in QUnit.test | :white_check_mark: |  |  |
| [no-throws-string](./docs/rules/no-throws-string.md) | disallow assert.throws() with block, string, and message args | :white_check_mark: |  |  |
| [require-expect](./docs/rules/require-expect.md) | enforce that `expect` is called | :white_check_mark: |  |  |
| [require-object-in-propequal](./docs/rules/require-object-in-propequal.md) | enforce use of objects as expected value in `assert.propEqual` | :white_check_mark: |  |  |
| [resolve-async](./docs/rules/resolve-async.md) | require that async calls are resolved | :white_check_mark: |  |  |

<!--RULES_TABLE_END-->

## Contributors

Thanks goes to these wonderful people ([emoji key](https://github.com/kentcdodds/all-contributors#emoji-key)):

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
<!-- prettier-ignore-start -->
<!-- markdownlint-disable -->
<table>
  <tr>
    <td align="center"><a href="http://github.com/platinumazure"><img src="https://avatars.githubusercontent.com/u/284282?v=3?s=100" width="100px;" alt=""/><br /><sub><b>Kevin Partington</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=platinumazure" title="Code">💻</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=platinumazure" title="Documentation">📖</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=platinumazure" title="Tests">⚠️</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Aplatinumazure" title="Bug reports">🐛</a> <a href="#example-platinumazure" title="Examples">💡</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/pulls?q=is%3Apr+reviewed-by%3Aplatinumazure" title="Reviewed Pull Requests">👀</a></td>
    <td align="center"><a href="https://jordaneldredge.com"><img src="https://avatars.githubusercontent.com/u/162735?v=3?s=100" width="100px;" alt=""/><br /><sub><b>Jordan Eldredge</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=captbaritone" title="Tests">⚠️</a> <a href="#question-captbaritone" title="Answering Questions">💬</a></td>
    <td align="center"><a href="https://github.com/mitchlloyd"><img src="https://avatars.githubusercontent.com/u/15169?v=3?s=100" width="100px;" alt=""/><br /><sub><b>Mitch Lloyd</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=mitchlloyd" title="Code">💻</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=mitchlloyd" title="Documentation">📖</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=mitchlloyd" title="Tests">⚠️</a></td>
    <td align="center"><a href="https://github.com/jmainz"><img src="https://avatars.githubusercontent.com/u/6665906?v=3?s=100" width="100px;" alt=""/><br /><sub><b>John Mainz</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=jmainz" title="Tests">⚠️</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Ajmainz" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://github.com/Turbo87"><img src="https://avatars1.githubusercontent.com/u/141300?v=3?s=100" width="100px;" alt=""/><br /><sub><b>Tobias Bieniek</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=Turbo87" title="Code">💻</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=Turbo87" title="Documentation">📖</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=Turbo87" title="Tests">⚠️</a></td>
    <td align="center"><a href="https://twitter.com/netweb"><img src="https://avatars1.githubusercontent.com/u/1016458?v=3?s=100" width="100px;" alt=""/><br /><sub><b>Stephen Edgar</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Antwb" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://timotijhof.net"><img src="https://avatars3.githubusercontent.com/u/156867?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Timo Tijhof</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=Krinkle" title="Documentation">📖</a> <a href="#ideas-Krinkle" title="Ideas, Planning, & Feedback">🤔</a></td>
  </tr>
  <tr>
    <td align="center"><a href="https://github.com/edg2s"><img src="https://avatars3.githubusercontent.com/u/180672?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Ed S</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Aedg2s" title="Bug reports">🐛</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=edg2s" title="Code">💻</a></td>
    <td align="center"><a href="https://github.com/Techn1x"><img src="https://avatars1.githubusercontent.com/u/1049837?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Brad Overton</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3ATechn1x" title="Bug reports">🐛</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=Techn1x" title="Code">💻</a></td>
    <td align="center"><a href="http://sha.nemart.in"><img src="https://avatars3.githubusercontent.com/u/95600?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Shane Martin</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Ashamrt" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://github.com/ventuno"><img src="https://avatars3.githubusercontent.com/u/5890858?v=4?s=100" width="100px;" alt=""/><br /><sub><b>ventuno</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=ventuno" title="Code">💻</a></td>
    <td align="center"><a href="https://github.com/Krysthalia"><img src="https://avatars0.githubusercontent.com/u/38167520?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Anne-Gaëlle Schall</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3AKrysthalia" title="Bug reports">🐛</a></td>
    <td align="center"><a href="http://www.linkedin.com"><img src="https://avatars2.githubusercontent.com/u/180990?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Steve Calvert</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=scalvert" title="Code">💻</a></td>
    <td align="center"><a href="https://github.com/aliaksandr-yermalayeu"><img src="https://avatars3.githubusercontent.com/u/14282348?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Aliaksandr Yermalayeu</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=aliaksandr-yermalayeu" title="Code">💻</a></td>
  </tr>
  <tr>
    <td align="center"><a href="http://www.linkedin.com/in/bmish"><img src="https://avatars3.githubusercontent.com/u/698306?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Bryan Mishkin</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Abmish" title="Bug reports">🐛</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=bmish" title="Code">💻</a> <a href="https://github.com/platinumazure/eslint-plugin-qunit/commits?author=bmish" title="Documentation">📖</a> <a href="#ideas-bmish" title="Ideas, Planning, & Feedback">🤔</a></td>
    <td align="center"><a href="https://xhmikosr.io/"><img src="https://avatars2.githubusercontent.com/u/349621?v=4?s=100" width="100px;" alt=""/><br /><sub><b>XhmikosR</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3AXhmikosR" title="Bug reports">🐛</a></td>
    <td align="center"><a href="https://github.com/andreyfel"><img src="https://avatars2.githubusercontent.com/u/9370878?v=4?s=100" width="100px;" alt=""/><br /><sub><b>Andrey Fel</b></sub></a><br /><a href="https://github.com/platinumazure/eslint-plugin-qunit/issues?q=author%3Aandreyfel" title="Bug reports">🐛</a></td>
  </tr>
</table>

<!-- markdownlint-restore -->
<!-- prettier-ignore-end -->
<!-- ALL-CONTRIBUTORS-LIST:END -->

<!-- markdownlint-disable line-length -->

This project follows the [all-contributors](https://github.com/kentcdodds/all-contributors) specification. Contributions of any kind welcome!

## Semantic Versioning Policy

Like ESLint itself, this ESLint plugin follows [semantic versioning](http://semver.org). However, due to the nature of ESLint as a code quality tool, it's not always clear when a minor or major version bump occurs. To help clarify this for everyone, we've defined the following semantic versioning policy, based on the policy used by ESLint:

* Patch release (intended not to break your lint build)
  * A bug fix in a plugin rule that results in ESLint reporting fewer errors.
  * Improvements to documentation.
  * Non-user-facing changes such as refactoring code; adding, deleting, or modifying tests; and increasing test coverage.
  * Re-releasing after a failed release (i.e., after having published a release that doesn't work for anyone).
* Minor release (might break your lint build)
  * A bug fix in a rule that results in ESLint reporting more errors.
  * A new rule is created (without being added to plugin configuration).
  * A new option to an existing rule is created (without any default options changing).
  * A new plugin configuration is created.
  * An existing rule is deprecated.
* Major release (likely to break your lint build)
  * An existing plugin configuration is changed in any way, including but not limited to:
    * A new rule is added to the configuration.
    * A rule is removed from the configuration.
    * The options used in configuration for a rule are changed
  * An existing rule is removed.
  * A backward-incompatible change is made to the options of a rule.
