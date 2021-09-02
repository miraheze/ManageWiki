/**
 * @fileoverview prevent early return in a QUnit test
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
            description: "disallow early return in tests",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-early-return.md"
        },
        messages: {
            noEarlyReturn: "Do not return early from a QUnit test."
        },
        schema: []
    },

    create: function (context) {
        let assertContextVar = null;
        const functionScopes = [];

        function pushFunction() {
            if (assertContextVar !== null) {
                functionScopes.push({
                    returnAndAssertNodes: []
                });
            }
        }

        function popFunction() {
            if (assertContextVar !== null) {
                const lastScope = functionScopes.pop();

                let lastAssert = null,
                    i;

                for (i = lastScope.returnAndAssertNodes.length - 1; i >= 0; --i) {
                    if (lastScope.returnAndAssertNodes[i].type === "CallExpression") {
                        lastAssert = i;
                        break;
                    }
                }

                if (lastAssert !== null && lastScope.returnAndAssertNodes.length > 0) {
                    for (const node of lastScope.returnAndAssertNodes.slice(0, lastAssert)) {
                        if (node.type === "ReturnStatement") {
                            context.report({
                                node: node,
                                messageId: "noEarlyReturn"
                            });
                        }
                    }
                }
            }
        }

        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    assertContextVar = utils.getAssertContextNameForTest(node.arguments);
                } else if (utils.isAssertion(node.callee, assertContextVar) && functionScopes.length > 0) {
                    functionScopes[functionScopes.length - 1].returnAndAssertNodes.push(node);
                }
            },

            "FunctionDeclaration": pushFunction,
            "FunctionExpression": pushFunction,
            "ArrowFunctionExpression": pushFunction,

            "ReturnStatement": function (node) {
                if (functionScopes.length > 0) {
                    functionScopes[functionScopes.length - 1].returnAndAssertNodes.push(node);
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    assertContextVar = null;
                }
            },

            "FunctionDeclaration:exit": popFunction,
            "FunctionExpression:exit": popFunction,
            "ArrowFunctionExpression:exit": popFunction
        };
    }
};
