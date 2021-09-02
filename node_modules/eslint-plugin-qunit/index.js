/**
 * @fileoverview Entry point for eslint-plugin-qunit. Exports rules and configs.
 * @author Kevin Partington
 */

/* eslint sort-keys: "error" */

"use strict";

const requireIndex = require("requireindex");

module.exports = {
    rules: requireIndex(`${__dirname}/lib/rules`),

    // eslint-disable-next-line sort-keys
    configs: {
        recommended: {
            plugins: ["qunit"],
            rules: {
                "qunit/assert-args": "error",
                "qunit/literal-compare-order": "error",
                "qunit/no-arrow-tests": "error",
                "qunit/no-assert-equal-boolean": "error",
                "qunit/no-assert-logical-expression": "error",
                "qunit/no-async-in-loops": "error",
                "qunit/no-async-module-callbacks": "error",
                "qunit/no-async-test": "error",
                "qunit/no-commented-tests": "error",
                "qunit/no-compare-relation-boolean": "error",
                "qunit/no-conditional-assertions": "error",
                "qunit/no-early-return": "error",
                "qunit/no-global-assertions": "error",
                "qunit/no-global-expect": "error",
                "qunit/no-global-module-test": "error",
                "qunit/no-global-stop-start": "error",
                "qunit/no-hooks-from-ancestor-modules": "error",
                "qunit/no-identical-names": "error",
                "qunit/no-init": "error",
                "qunit/no-jsdump": "error",
                "qunit/no-negated-ok": "error",
                "qunit/no-nested-tests": "error",
                "qunit/no-ok-equality": "error",
                "qunit/no-only": "error",
                "qunit/no-qunit-push": "error",
                "qunit/no-qunit-start-in-tests": "error",
                "qunit/no-qunit-stop": "error",
                "qunit/no-reassign-log-callbacks": "error",
                "qunit/no-reset": "error",
                "qunit/no-setup-teardown": "error",
                "qunit/no-test-expect-argument": "error",
                "qunit/no-throws-string": "error",
                "qunit/require-expect": "error",
                "qunit/require-object-in-propequal": "error",
                "qunit/resolve-async": "error"
            }
        }
    }
};
