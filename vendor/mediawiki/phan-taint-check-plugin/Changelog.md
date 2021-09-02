# MediaWiki Security Check Plugin changelog

## v3.2.1
### Bug fixes
* Fixed a crash observed when using the polyfill parser
* Fixed two crashes introduced with the 3.2.0 release

### Internal changes
* Bumped phan/phan to 3.2.6

## v3.2.0
### New features
* Variadic parameters are now properly handled
* Array keys are now tracked separately from values
* (MW) Properly track taintedness of array keys in some HTMLForm specifiers
* Created new taint types and issues for RCE and path traversal: `SecurityCheck-RCE` and `SecurityCheck-PathTraversal`
* Added detection for ReDoS vulnerabilities. New issue: `SecurityCheck-ReDoS`
* The plugin can now properly analyze assignments with an array at the LHS
* Array shapes are now tracked more precisely when a key that cannot be determined statically is found

## v3.1.1
### Internal changes
* Allow installing the plugin in PHP 8. Analyzing code with new PHP 8 features is not supported yet (T269263)

## v3.1.0
### New features
* Increased the length limit for caused-by lines. The new limit is at 12 entries, rather than fixed at 255 characters (it was roughly doubled)
* Caused-by lines are now stored together with a taintedness value, which allows filtering taintedness depending on the sink type
* Handle a few more edge cases in foreach loops. Notably, class properties used as key or value are now
  properly analyzed, and caused-by lines now include sources of taintedness outside the loop.
* The plugin now filters taintedness based on the (real) type of variables using `if` conditions,
  parameters and return type declarations.
* Binops are now properly analysed, removing taintedness if the operation is safe.
* Caused-by lines for function calls now include a code snippet with the argument, together with its ordinal.
* Added an annotation to print the taintedness of a variable (use it with `'@phan-debug-var-taintedness $varname'`)
* Added taint data for a bunch of built-in functions

### Bug fixes
* (MW) Fixed a crash observed when using `$this` as hook handler
* Fixed an edge case that made the plugin crash when attempting to use an undeclared variable as a
  callable
* Fixed a bug that caused the same issue to be reported on multiple lines, hence creating redundant
  warnings, making it difficult to suppress them all.
* (MW) Avoid crash when Hooks::run has no arguments array
* Fixed an edge case where literal integers/strings weren't recognized as integers/strings; this brings improved tracking of SQL_NUMKEY.
* (MW) Fixed incorrect taint data for Sanitizer::removeHTMLtags (T268353)
* Slightly improved performance for recursive methods (analysis is not attempted, rather than letting it reach the recursion limit of 5)

### Internal changes
* Taintedness is now stored in a value object, rather than a plain integer.
* Function taintedness is now stored in a value object, rather than an array of integers.
* Issue descriptions now use phan templates, which notably adds support for selective colorizing.
* (MW) The plugin no longer forces types for MW globals in non-standalone mode. This is now done by mediawiki-phan-config.
* Plugin classes were moved to the `SecurityCheckPlugin` namespace.
* Bumped phan/phan to 3.2.4

## v3.0.4
### New features
* Added explicit taint info for `LinkRenderer::makeBrokenLink`
* Added explicit taint info for `shell_exec` and friends
* The plugin is now able to properly analyze conditionals, and merge the possible taints of each branch
* The plugin can now analyze pass by reference variables better
* Added support for analyzing each array element on its own

### Bug fixes
* Fixed several plugin crashes observed when analyzing weird syntax
* Fixed a crash observed with non-literal keys in getQueryInfo methods (T268055)

### Internal changes
* Bumped phan/phan to 3.0.3
* The plugin is now using PluginV3
* Objects returned by methods are now tracked in-place, and `GetReturnObjVisitor` was deleted

## v3.0.3
* Remove reference to `AST_LIST` (Daimona Eaytoy)
* Avoid shelling out to run tests (Daimona Eaytoy)
* Move hooks-related methods to a new class (Daimona Eaytoy)
* composer: Add Daimona as an author (James D. Forrester)
* Split a long method (Daimona Eaytoy)
* Expand docs for "manual" mode (Daimona Eaytoy)
* Assert that the config options we need are enabled (Daimona Eaytoy)
* Avoid conflating `stdClass` instances (Daimona Eaytoy)
* Cleanup: Various improvements suggested by PHPStorm (Daimona Eaytoy)
* Cleanup: Add return type hints where applicable (Daimona Eaytoy)
* Exclude invalid PHP files from analysis (Daimona Eaytoy)

## v3.0.2
* Fix `PhanTypeComparisonFromArray` edge cases (Daimona Eaytoy)
* Don't check type validity in `nodeIs(String|Int)` (Daimona Eaytoy)
* Optim: Don't reanalyze functions if we already have data (Daimona Eaytoy)
* Fix edge cases with `getOriginalScope` (Daimona Eaytoy)
* Make `handleMethodCall` always require a `FunctionInterface` and a function FQSEN (Daimona Eaytoy)
* Fix bad interaction with phan, part 3 (Daimona Eaytoy)
* Cleanup: Remove unnecessary `try/catch` constructs (Daimona Eaytoy)
* Cleanup: Add method to extract data from exceptions (Daimona Eaytoy)
* Cleanup: Change `taintToIssueAndSeverity` to use a switch (Daimona Eaytoy)
* Fix another edge case interaction with phan (Daimona Eaytoy)
* Fix edge case with prop access confusing other parts of phan (Daimona Eaytoy)

## v3.0.1
* build: Upgrade minus-x from 0.3.2 to 1.1.0 (James D. Forrester)
* Upgrade phan to latest version (Daimona Eaytoy)
* Properly handle `list()` assignments (Daimona Eaytoy)
* Upgrade phan to 2.4.0 (Daimona Eaytoy)

## v3.0.0
* Fix phan crash when analyzing MediaWiki core (Daimona Eaytoy)
* Add `RAW_PARAM` taint type (Daimona Eaytoy)
* build: Upgrade mediawiki-codesniffer from v29.0.0 to v30.0.0 (James D. Forrester)
* Remove outdated config settings (Daimona Eaytoy)
* Add `UnusedSuppressionPlugin` limited to our warnings (Daimona Eaytoy)
* Actually handle binary addition (Daimona Eaytoy)
* Update PHPUnit to 8.5 (Umherirrender)
* build: Upgrade mediawiki-codesniffer to v29.0.0 (James D. Forrester)
* build: Updating composer dependencies (Umherirrender)
* Upgrade phan to 2.2.13 (Daimona Eaytoy)
* Remove hack for OOUI constructors (Daimona Eaytoy)
* Upgrade to phan 2.2.5 (Daimona Eaytoy)
* Further improvements for same var reassignments (Daimona Eaytoy)
* Better handling of reassignments of the same var (Daimona Eaytoy)
* Don't fail hard when core methods cannot be found (Daimona Eaytoy)
* Shrink config files even more (Daimona Eaytoy)
* Remove explicit dependency on `ext-ast` (Daimona Eaytoy)
* Cleanup parent var linking code (Daimona Eaytoy)
* Remove awful hack for var context (Daimona Eaytoy)
* Upgrade to PHPUnit 8.4 (Daimona Eaytoy)
* build: Upgrade MW phpcs to 28.0.0 (Daimona Eaytoy)
* Replace `EXEC_TAINT` with `ALL_EXEC_TAINT` where latter was meant (Brian Wolff)
* Upgrade phan to 2.0.0, ast to 1.0.1 and require PHP72+ (Daimona Eaytoy)

## v2.1.0
* Improve caused-by lines (Daimona Eaytoy)
* Add debug for reaching max analysis depth (Daimona Eaytoy)
* Add some unhandled node kinds (Daimona Eaytoy)
* Visit `AST_EMPTY` (Daimona Eaytoy)
* Further improvements (Daimona Eaytoy)
* Handle closure vars (Daimona Eaytoy)
* Handle closures (Daimona Eaytoy)
* Make CI run phpunit tests (Daimona Eaytoy)
* Make CI run phpunit tests (Daimona Eaytoy)

## v2.0.2
* Fix a crash with the literal '`class`' (Daimona Eaytoy)
* Handle pre/post-increment/decrement operators (Daimona Eaytoy)
* Various code quality improvements (Daimona Eaytoy)
* Restore `TypedElementInterface` typehints (Daimona Eaytoy)
* Fix some FIXMEs (Daimona Eaytoy)
* Fix some issues with CI (Daimona Eaytoy)
* Add missing slashes to `MW_INSTALL_PATH` (Daimona Eaytoy)
* Re-fix failing test (Daimona Eaytoy)
* Fix a failing test (Daimona Eaytoy)

## v2.0.1
* Fix some issues with CI (Daimona Eaytoy)
* Add missing slashes to `MW_INSTALL_PATH` (Daimona Eaytoy)
* Re-fix failing test (Daimona Eaytoy)
* Fix a failing test (Daimona Eaytoy)
* Remove a duplicated method (Daimona Eaytoy)
* When suppressing a warning, also suppress side effects (Brian Wolff)
* Mark `IDatabase::buildLike` as something that escapes SQL (Brian Wolff)
* Special handling for `Linker::makeExternalLink` (Brian Wolff)
* When in MW mode, consider XSS in the maintenance directory to be false positives (Brian Wolff)
* Prevent an `EXEC` variable from tainting itself (Brian Wolff)

## v2.0.0
* Remove wrong `EXEC` bits from MW functions (Daimona Eaytoy)
* Take into account implicit BranchScopes (Daimona Eaytoy)
* Update readme (Daimona Eaytoy)
* Add a file with base config (Daimona Eaytoy)
* Temporarily lower ast requirement (Daimona Eaytoy)
* Hotfix for OOUI exclusion (Daimona Eaytoy)
* Handle nested calls (Daimona Eaytoy)
* Set taintedness to `NO_TAINT` for `class-string` and `callable-string` (Daimona Eaytoy)
* Update integration tests (Daimona Eaytoy)
* Fix global variable handling (Daimona Eaytoy)
* Add checks for `ClosureType` (Daimona Eaytoy)
* Transfer the taintedness from objects to props (Daimona Eaytoy)
* Prevent class props from sending taintedness too far (Daimona Eaytoy)
* Restore code bit for linking var to parentvar (Daimona Eaytoy)
* Make `nodeIsString` work again (Daimona Eaytoy)
* Unbreak `passByReference` parameters handling (Daimona Eaytoy)
* Hack: exclude OOUI constructors from DoubleEscape reporting (Daimona Eaytoy)
* Unbreak handling of `$argc` and `$argv` (Daimona Eaytoy)
* Unbreak docblock parsing (Daimona Eaytoy)
* Fix phan issues (Daimona Eaytoy)
* Upgrade phan to 1.3.2 and php-ast to 1.0.1 (Daimona Eaytoy)
* When suppressing a warning, also suppress side effects (Brian Wolff)
* Mark `IDatabase::buildLike` as something that escapes SQL (Brian Wolff)
* Special handling for `Linker::makeExternalLink` (Brian Wolff)
* When in MW mode, consider XSS in the maintenance directory to be false positives (Brian Wolff)
* Prevent an `EXEC` variable from tainting itself (Brian Wolff)
* Upgrade phan to 1.2.6 (Daimona Eaytoy)
* Minor fixes (Daimona Eaytoy)
* Upgrade phan to 1.0.0 (Daimona Eaytoy)
* Upgrade to PluginV2 (Daimona Eaytoy)
* Turn `TaintednessBaseVisitor` into a trait (Daimona Eaytoy)
* Change inheritance for MW analyzer (Daimona Eaytoy)
* Upgrade phan to 0.9.6 (Daimona Eaytoy)
* Upgrade phan to 0.8.13 (Daimona Eaytoy)
* Move regression test to PHPUnit (Daimona Eaytoy)
* Upgrade phan to 0.8.6 (Daimona Eaytoy)
* Minor fixes (Daimona Eaytoy)
* Remove phpcs bootstrap. (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 24.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 23.0.0 (libraryupgrader)
* Add another test case related to batch insert. (Brian Wolff)

## v1.5.1
* Fix fatal when using global keyword with indirect variable (Brian Wolff)
* Clarify `SECURITY_CHECK_EXT_PATH` documentation (Kunal Mehta)

## v1.5.0
* Avoid false positive related to `getQueryInfo()` methods. (Brian Wolff)
* Include syntax errors in the output of plugin. (Brian Wolff)
* Fix a fatal during a misdetected `HTMLForm` specifier with empty class (Brian Wolff)
* Fix IN list case for db conds when doing `$conds['field'][] = $tainted` (Brian Wolff)
* Fix some confusion over which group of taints to mask out in various places (Brian Wolff)
* Treat `htmlform type=info`'s 'rawrow' option like 'raw' (Brian Wolff)
* Disable `htmlform` detection inside `AuthenticationRequest` (Brian Wolff)
* Better handling of `HTMLForm $options` (Brian Wolff)
* Support custom checking for `IDatabase::makeList` (Brian Wolff)
* Update README expand limitation section (Brian Wolff)
* Link to docker image instructions in README.md (Brian Wolff)
* Make parser hooks work properly even without type hints (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 22.0.0 (libraryupgrader)
* Fix bug in how taint propagation works (Brian Wolff)

## v1.4.0
* Make `seccheck-mwext` and `seccheck-fast-mwext` work with skins (Brian Wolff)
* Make `onlysafefor_html` not mark things as `exec_escaped`. (Brian Wolff)
* Mark `base64_encode` as escaping taint. (Brian Wolff)
* Fix error in argument handling in test script (Brian Wolff)
* Add an indirect test case to taghook test (Brian Wolff)
* Move builtin taints for `Parser` & `ParserOutput` into inline annotations (Brian Wolff)
* Prevent `NO_OVERRIDE` flag from being propagated during assignment (Brian Wolff)
* Add support for reading skin.json in addition to extension.json (Brian Wolff)

## v1.3.1
* Ignore tests/ in mwext-fast (Kunal Mehta)
* Fix markdown syntax in README (Umherirrender)

## v1.3.0
* Refactor docblock taint annotation to support docblocks on interfaces (Brian Wolff)
* Improve tracking of outputting class members (Brian Wolff)
* Standardize casing in error as "Calling method..." (method is lowercase) (Kunal Mehta)
* Fix bug when argument both normal taint and execute taint (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 21.0.0 (libraryupgrader)
* Fix bug where pass by ref causing func to be treated as unknown (Brian Wolff)
* rm the hardcoded OOUI taints. They were wrong. (Brian Wolff)
* Add code to force type for MW globals (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 20.0.0 (libraryupgrader)

## v1.2.0
* Add support for docblock taint annotations (Brian Wolff)
* Fix phan tests (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 18.0.0 (libraryupgrader)
* build: Updating mediawiki/mediawiki-codesniffer to 17.0.0 (libraryupgrader)
* build: Updating jakub-onderka/php-parallel-lint to 1.0.0 (libraryupgrader)
* Add support for checking `HTMLForm` specifiers (Brian Wolff)
* Use SPDX 3.0 license identifier (Umherirrender)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.1 (libraryupgrader)
* build: Adding MinusX (Umherirrender)
* Don't mark `\Xml::encodeJsVar` and `encodeJsCall` as double escaping (Brian Wolff)
* Fix missing initial `\` in class name list (Brian Wolff)
* build: Updating mediawiki/mediawiki-codesniffer to 16.0.0 (libraryupgrader)
* Add support for looking at `__toString()` when object in string context (Brian Wolff)
* Improve some of the double escaping checks. (Brian Wolff)
* Depend upon phan/phan instead of deprecated etsy/phan (Kunal Mehta)
* build: Updating mediawiki/mediawiki-codesniffer to 15.0.0 (Kunal Mehta)
* Add `Hooks::runWithoutAbort` support (Phantom42)
* Add double escaping detection (Albert221)
* Appearently this doesn't work with php-ast 0.1.5 (Brian Wolff)

## v1.1.0
* Html escaping functions shouldn't clear non-html taint (Brian Wolff)
* Finish rename to mediawiki/phan-taint-check-plugin (Brian Wolff)
* Add .gitattributes file (Brian Wolff)
* Fix some typos (Kunal Mehta)
* Fix indentation in .phpcs.xml (Kunal Mehta)
* Replace `SecurityCheckPlugin::` with `self::` where possible (Brian Wolff)
* Add a test script for people whose php bin is not 7 (Brian Wolff)
* Disable progress bar in composer test, as ugly on jenkins (Brian Wolff)
* Rename plugin to mediawiki/phan-taint-check-plugin (Brian Wolff)
* Add a note about how it can't validate certain types of SQL (Brian Wolff)
* Version should be php 7.0 (7.1 not supported due to dependency) (Brian Wolff)
* Rename to "mediawiki/phan-security-plugin" (Kunal Mehta)
* Fix test that didn't pass lint (Brian Wolff)
* Follow-up on Ie9106c80 (MarcoAurelio)
* build: update composer.json (MarcoAurelio)
* Add .gitreview (MarcoAurelio)
* Add GPL license headers (Brian Wolff)
* Make README prettier (Bryan Davis)

## v1.0.0
* Update composer.json (Brian Wolff)
* Support installing via composer. (Brian Wolff)
* Update README (Brian Wolff)
* Move plugin entry points to root directory (Brian Wolff)
* Fix some false positives discovered while testing with MW (Brian Wolff)
* Fix various false positives found when testing with MW (Brian Wolff)
* Add a test for `list()` support (Brian Wolff)
* Add test for array addition with `SQL_NUMKEY` (Brian Wolff)
* Minor fixes discovered during testing (Brian Wolff)
* Ensure that errors related to function are per param (Brian Wolff)
* Minor fixes to the eval case (Brian Wolff)
* Some debugging fixes (Brian Wolff)
* Support checking `getQueryInfo()` return; Process `$options` & `$join_conds` (Brian Wolff)
* Fix handling of `IN(...)` lists in db `select` wrapper (Brian Wolff)
* Add support for `IDatabase::select` style arguments (Brian Wolff)
* Fix bug where non-local variables are treated like local (Brian Wolff)
* Add `ARRAY_OK` flag for functions that are safe with arrays (Brian Wolff)
* Make unit tests for extension.json always work (Brian Wolff)
* Make error messages from hooks be in extension instead of core (Brian Wolff)
* Avoid duplication in output (Brian Wolff)
* Fix some minor issues (Brian Wolff)
* Handle dispatching of hooks on `Hooks::run()` (Brian Wolff)
* Support loading hook information from extension.json (Brian Wolff)
* Make more clear error messages, distinguishing different issue types (Brian Wolff)
* Support recognizing `$wgHooks/$_GLOBALS['wgHooks']` (Brian Wolff)
* Keep track of hook registrations (Brian Wolff)
* Add support for parser tag hooks (Brian Wolff)
* Support `ParserFunctions`, and start of work for hooks in general (Brian Wolff)
* Add taint for db related function. Fix handling of subclasses (Brian Wolff)
* Mention phan version requirements (Brian Wolff)
* Fix remaining tests (mostly phpcs) (Brian Wolff)
* Fix various tests (Brian Wolff)
* Add composer and phpcs. (Brian Wolff)
* Use the normal GPL v2 (Kunal Mehta)
* Do not ouput very noisy debug by default (Brian Wolff)
* Initial commit. (Brian Wolff)
