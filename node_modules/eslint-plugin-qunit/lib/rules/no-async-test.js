/**
 * @fileoverview Forbid the use of asyncTest or QUnit.asyncTest.
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const utils = require("../utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow the use of asyncTest or QUnit.asyncTest",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-async-test.md"
        },
        messages: {
            unexpectedAsyncTest: "Unexpected asynchronous test. Use assert.async() instead."
        },
        schema: []
    },

    create: function (context) {
        return {
            "CallExpression": function (node) {
                if (utils.isAsyncTest(node.callee)) {
                    context.report({
                        node: node,
                        messageId: "unexpectedAsyncTest"
                    });
                }
            }
        };
    }
};
