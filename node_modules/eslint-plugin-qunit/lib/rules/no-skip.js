/**
 * @fileoverview Forbid the use of QUnit.skip
 * @author Steve Calvert
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
            description: "disallow QUnit.skip",
            category: "Best Practices",
            recommended: false,
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-skip.md"
        },
        messages: {
            noQUnitSkip: "Unexpected skip() call."
        },
        schema: []
    },

    create: function (context) {
        return {
            "CallExpression": function (node) {
                if (utils.isSkip(node.callee)) {
                    context.report({
                        node: node,
                        messageId: "noQUnitSkip",
                        data: {
                            callee: node.callee.name
                        }
                    });
                }
            }
        };
    }
};
