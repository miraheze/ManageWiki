/**
 * @fileoverview Forbid the use of global QUnit assertions.
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const { getAssertionNames } = require("../utils");
const { ReferenceTracker } = require("eslint-utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow global QUnit assertions",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-global-assertions.md"
        },
        messages: {
            unexpectedGlobalAssertion: "Unexpected global `{{ assertion }}` assertion."
        },
        schema: []
    },

    create: function (context) {
        return {
            "Program": function () {
                const tracker = new ReferenceTracker(context.getScope());
                const traceMap = {};
                for (const assertionName of getAssertionNames()) {
                    traceMap[assertionName] = { [ReferenceTracker.CALL]: true };
                }

                for (const { node } of tracker.iterateGlobalReferences(traceMap)) {
                    context.report({
                        node: node,
                        messageId: "unexpectedGlobalAssertion",
                        data: {
                            assertion: node.callee.name
                        }
                    });
                }
            }
        };
    }
};
