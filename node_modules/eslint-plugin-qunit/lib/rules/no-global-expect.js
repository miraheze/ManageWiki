/**
 * @fileoverview Forbid the use of global expect.
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
            description: "disallow global expect",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-global-expect.md"
        },
        messages: {
            unexpectedGlobalExpect: "Unexpected global expect."
        },
        schema: []
    },

    create: function (context) {
        return {
            "Program": function () {
                const tracker = new ReferenceTracker(context.getScope());
                const traceMap = { expect: { [ReferenceTracker.CALL]: true } };

                for (const { node } of tracker.iterateGlobalReferences(traceMap)) {
                    context.report({
                        node: node,
                        messageId: "unexpectedGlobalExpect"
                    });
                }
            }
        };
    }
};
