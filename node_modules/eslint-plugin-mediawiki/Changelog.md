# eslint-plugin-mediawiki release history

## v0.2.7
* Upgrade eslint-plugin-vue to ^7.7.0 to match eslint-config-wikimedia (James D. Forrester)
* build: Upgrade eslint-config-wikimedia from 0.17.0 to 0.18.1 (James D. Forrester)
* build: Upgrade codecov from ^3.7.2 to ^3.8.1 (James D. Forrester)
* build: Upgrade outdent from ^0.7.1 to ^0.8.0 (James D. Forrester)

## v0.2.6
* Rule fix: `msg-doc`/`class-doc`: Fix behavior in var statements (Roan Kattouw)
* Build: Update devDependencies (Ed Sanders)
* Build: Add codecov reporting (Ed Sanders)

## v0.2.5
* Rule fix: `valid-package-file-require`: Deal with backslashes in Windows paths (Roan Kattouw)
* Code: Use upath for Windows path normalization, add Windows to tests (Ed Sanders)
* Docs: Move docs to docs/rules (Ed Sanders)
* Docs: Documentation cleanup (Ed Sanders)
* Docs: Use eslint-docgen (Ed Sanders)
* Tests: Move test rules to tests/rules (Ed Sanders)
* Tests: Simplify error message string assertions (Ed Sanders)
* Tests: Use outdent for multi-line test cases (Ed Sanders)
* Build: Introduce eslint-plugin-eslint-plugin (Ed Sanders)
* Build: Update devDependencies and remove explicit eslint dependency (Ed Sanders)
* Build: Update ESLint to 7.0.0 (Ed Sanders)
* Build: Increase ESLint peerDependency from 2.3.0 to 5.0.0 (Ed Sanders)

## v0.2.4
* Rule fix: `valid-package-file-require`: Allow ./ prefix when going up the dir tree (Jakob Warkotsch)
* Rule fix: `valid-package-file-require`: Check if require() arg looks like a path (Roan Kattouw)
* Rule fix: `valid-package-file-require`: Make fixable (Roan Kattouw)
* Rule fix: `valid-package-file-require`: Report correct file path in error message (Roan Kattouw)
* Rule fix: Link to documentation for `class-doc` & `msg-doc` rules (Ed Sanders)
* Build: Add code coverage report and set threshold to 100% (Ed Sanders)
* Build: Update devDependencies (Ed Sanders)
* Code: Add rule types (Ed Sanders)
* Code: Move rules and index.js to src (Ed Sanders)

## v0.2.3
* New rule: `no-vue-dynamic-i18n` (Roan Kattouw)

## v0.2.2
* New rule: `class-doc` (Ed Sanders)
* Rule fix: Support passing arrays of literals to addClass (Ed Sanders)
* Rule fix: `class-doc` – Catch OOUI classes (Ed Sanders)
* Rule fix: `class-doc`: Also match DOM class changes (Ed Sanders)
* README: Direct users to eslint-config-wikimedia (Ed Sanders)
* Release: Update devDependencies (Ed Sanders)

## v0.2.1
* Fix `valid-package-file-require` file path in index.js (Jakob Warkotsch)

## v0.2.0
* New rule: `valid-package-file-require` (Jakob Warkotsch)

## v0.1.0
* Initial release.
* New rule: `msg-doc` (Ed Sanders)
