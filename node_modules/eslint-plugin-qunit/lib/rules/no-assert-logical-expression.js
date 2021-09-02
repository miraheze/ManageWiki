/**
 * @fileoverview forbid binary logical expressions in assert arguments
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
            description: "disallow binary logical expressions in assert arguments",
            category: "Best Practices",
            recommended: false,
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-assert-logical-expression.md"
        },
        fixable: null,
        messages: {
            noLogicalOperator: "Do not use '{{operator}}' in assertion arguments."
        },
        schema: []
    },

    create: function (context) {
        const testStack = [];

        //----------------------------------------------------------------------
        // Helpers
        //----------------------------------------------------------------------

        function checkAndReport(argNodes) {
            for (const arg of argNodes) {
                if (arg.type === "LogicalExpression") {
                    context.report({
                        node: arg,
                        messageId: "noLogicalOperator",
                        data: {
                            operator: arg.operator
                        }
                    });
                }
            }
        }

        function getAssertVar() {
            let result = null;

            if (testStack.length > 0) {
                result = testStack[testStack.length - 1].assertContextVar;
            }

            return result;
        }

        //----------------------------------------------------------------------
        // Public
        //----------------------------------------------------------------------

        return {

            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    testStack.push({
                        assertContextVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (utils.isAssertion(node.callee, getAssertVar())) {
                    const countNonMessageArgs = Math.max(...utils.getAllowedArities(node.callee, getAssertVar()));
                    checkAndReport(node.arguments.slice(0, countNonMessageArgs));
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    testStack.pop();
                }
            }

        };
    }
};
