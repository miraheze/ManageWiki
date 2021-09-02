/**
 * @fileoverview forbid assertions within if statements or conditional expressions
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const utils = require("../utils");

//------------------------------------------------------------------------------
// Constants
//------------------------------------------------------------------------------

const CONDITIONAL_NODE_TYPES = new Set(["IfStatement", "ConditionalExpression"]);

const STOP_NODE_TYPES = new Set([
    "FunctionExpression",
    "FunctionDeclaration",
    "ArrowFunctionExpression"
]);

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow assertions within if statements or conditional expressions",
            category: "Best Practices",
            recommended: false,
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-conditional-assertions.md"
        },
        fixable: null,  // or "code" or "whitespace"
        messages: {
            noAssertionInsideConditional: "Do not place an assertion inside a conditional."
        },
        schema: []
    },

    create: function (context) {
        const testStack = [];

        //----------------------------------------------------------------------
        // Helper functions
        //----------------------------------------------------------------------

        function isConditionalNode(node) {
            return CONDITIONAL_NODE_TYPES.has(node.type);
        }

        function isStopNode(node) {
            return STOP_NODE_TYPES.has(node.type);
        }

        function checkAndReport(assertNode) {
            let currentNode = assertNode;

            while (currentNode && !isStopNode(currentNode) && !isConditionalNode(currentNode)) {
                currentNode = currentNode.parent;
            }

            if (CONDITIONAL_NODE_TYPES.has(currentNode.type)) {
                context.report({
                    node: assertNode,
                    messageId: "noAssertionInsideConditional"
                });
            }
        }

        function isAssertion(calleeNode) {
            const assertContextVar = testStack[testStack.length - 1].assertContextVar;
            return utils.isAssertion(calleeNode, assertContextVar);
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
                } else if (testStack.length > 0 && isAssertion(node.callee)) {
                    checkAndReport(node);
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
