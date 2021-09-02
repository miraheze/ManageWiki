v0.9.0 / 2020-04-06
==================

* [BREAKING CHANGE] Drop support for node 6 (James D. Forrester)
* build: Bump each devDependency to latest (James D. Forrester)
* CI: Replace Travis with GitHub Actions (James D. Forrester)

v0.8.1 / 2019-08-27
==================

* Fix uncaught error for translated message not defined in source (Alex Monk)
* build: Upgrade eslint-config-wikimedia from 0.13.1 to 0.14.0 (James D. Forrester)
* build(deps): bump eslint-utils from 1.4.0 to 1.4.2 (dependabot[bot])

v0.8.0 / 2019-08-20
==================

* Allow ignoring missing translations of blank source messages (Roan Kattouw)
* Allow requiring each parameter to be used in translations (James D. Forrester)
* Fix "languageMesages" typo (James D. Forrester)
* Make "lacks documentation" message more specific (Thiemo Kreuz)
* code: Commafy chained consts and fix directories (James D. Forrester)
* build: Upgrade eslint-config-wikimedia to 0.13.1 (James D. Forrester)
* build: Upgrade nyc from 13.3.0 to 14.1.1 (James D. Forrester)
* build: Use template literals (James D. Forrester)

v0.7.0 / 2019-01-08
==================

* Add check for message key case validity (James D. Forrester)
* Add option to require a message key prefix (James D. Forrester)
* build: Replace jshint and jscs with eslint (James D. Forrester)

v0.6.0 / 2017-03-01
==================

* Allow skipping keys that don't have message documentation (Kunal Mehta)

v0.5.0 / 2016-03-18
==================

* Don't crash when encountering file names that contain '.json' in the middle (Roan Kattouw)
* Extract the regex for a JSON filename (James Forrester)
* build: Bump various devDependencies to latest (paladox)

v0.4.0 / 2015-10-06
==================

* Make disallowUnusedTranslations default to false (Ed Sanders)
* Make disallowDuplicateTranslations default to false (Ed Sanders)
* build: Remove use of global grunt-cli (Timo Tijhof)
* build: Add Node.js v0.12 and v4.0 (Timo Tijhof)
* tests: Migrate Travis to container-based infrastructure (James D. Forrester)
* readme: Add line break between images and h1 (Timo Tijhof)

v0.3.0 / 2015-09-01
==================

* Fail if the target directory doesn't exist (James D. Forrester)
* Allow individual checks to be disabled in config (James D. Forrester)
* Be able to require complete translations, or specific messages in all translations (James D. Forrester)
* build: Bump grunt-jscs to latest version (James D. Forrester)
* Enforce disallowBlankTranslations, disallowDuplicateTranslations and disallowUnusedTranslations (James D. Forrester)

v0.2.2 / 2015-06-05
==================

* Fix off-by-one error in counting the number of messages (Kunal Mehta)
* build: Bump devDep grunt-contrib-jshint to 0.11.2 (James D. Forrester)
* build: Bump grunt-jscs to latest version (James D. Forrester)
* readme: Improved (Sébastien Santoro)

v0.2.1 / 2015-03-27
==================

* Fix catastrophic logic error (James D. Forrester)
* build: Owner has moved from me to Wikimedia (James D. Forrester)
* build: Change Travis-CI output channel (James D. Forrester)
* build: Bump devDependencies to latest (James D. Forrester)

v0.2.0 / 2014-08-31
==================

* task: Fail if a documentation message is blank or whitespace-only (James D. Forrester)
* task: Fail if a documentation message has no matching source message (James D. Forrester)
* readme: Documentation was slightly improved (James D. Forrester)
* build: Code is now tested automatically using Travis CI (James D. Forrester)

v0.1.0 / 2014-04-04
==================

* Initial release (James D. Forrester)
