/**
 * @fileoverview Forbid the use of global module/test/asyncTest.
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const { ReferenceTracker } = require("eslint-utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow global module/test/asyncTest",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-global-module-test.md"
        },
        messages: {
            unexpectedGlobalModuleTest: "Unexpected global `{{ callee }}`."
        },
        schema: []
    },

    create: function (context) {
        return {
            "Program": function () {
                const tracker = new ReferenceTracker(context.getScope());
                const traceMap = {
                    asyncTest: { [ReferenceTracker.CALL]: true },
                    module: { [ReferenceTracker.CALL]: true },
                    test: { [ReferenceTracker.CALL]: true }
                };

                for (const { node } of tracker.iterateGlobalReferences(traceMap)) {
                    context.report({
                        node: node,
                        messageId: "unexpectedGlobalModuleTest",
                        data: {
                            callee: node.callee.name
                        }
                    });
                }
            }
        };
    }
};
