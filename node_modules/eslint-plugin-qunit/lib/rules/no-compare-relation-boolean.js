/**
 * @fileoverview forbid comparing relational expression to boolean in assertions
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const assert = require("assert"),
    utils = require("../utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow comparing relational expressions to booleans in assertions",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-compare-relation-boolean.md"
        },
        fixable: "code",
        messages: {
            redundantComparison: "Redundant comparison of relational expression to boolean literal."
        },
        schema: [
            {
                type: "object",
                properties: {
                    fixToNotOk: {
                        type: "boolean",
                        default: true
                    }
                },
                additionalProperties: false
            }
        ]
    },

    create: function (context) {
        const fixToNotOk = !context.options[0] || context.options[0].fixToNotOk;

        const testStack = [],
            RELATIONAL_OPS = new Set([
                "==", "!=", "===", "!==", "<", "<=", ">", ">=",
                "in", "instanceof"
            ]);

        function shouldCheckArguments(calleeNode) {
            assert.ok(testStack.length);

            const assertContextVar = testStack[testStack.length - 1].assertContextVar;

            return utils.isAssertion(calleeNode, assertContextVar) && utils.isComparativeAssertion(calleeNode, assertContextVar);
        }

        function sortLiteralFirst(a, b) {
            if (a.type === "Literal" && b.type !== "Literal") {
                return -1;      // Literal is first and should remain first
            }

            if (a.type !== "Literal" && b.type === "Literal") {
                return 1;       // Literal is second and should be first
            }

            return 0;
        }

        function checkAndReport(callExprNode, literalNode, binaryExprNode) {
            if (RELATIONAL_OPS.has(binaryExprNode.operator) && typeof literalNode.value === "boolean") {
                context.report({
                    node: callExprNode,
                    messageId: "redundantComparison",
                    fix(fixer) {
                        const sourceCode = context.getSourceCode();
                        const assertionVariableName = callExprNode.callee.object.name;

                        // Decide which assertion function to use based on how many negations we have.
                        let countNegations = 0;
                        if (callExprNode.callee.property.name.startsWith("not")) {
                            countNegations++;
                        }
                        if (!literalNode.value) {
                            countNegations++;
                        }
                        const newAssertionFunctionName = countNegations % 2 === 0 ? "ok" : "notOk";

                        if (newAssertionFunctionName === "notOk" && !fixToNotOk) {
                            // No autofix in this situation if the rule option is off.
                            return null;
                        }

                        const newArgsTextArray = [binaryExprNode, ...callExprNode.arguments.slice(2)].map(arg => sourceCode.getText(arg));
                        const newArgsTextJoined = newArgsTextArray.join(", ");
                        return fixer.replaceText(callExprNode, `${assertionVariableName}.${newAssertionFunctionName}(${newArgsTextJoined})`);
                    }
                });
            }
        }

        function checkAssertArguments(callExprNode) {
            const args = [...callExprNode.arguments];

            const firstTwoArgsSorted = args.slice(0, 2).sort(sortLiteralFirst);

            if (firstTwoArgsSorted[0].type === "Literal" && firstTwoArgsSorted[1].type === "BinaryExpression") {
                checkAndReport(callExprNode, firstTwoArgsSorted[0], firstTwoArgsSorted[1]);
            }
        }

        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    testStack.push({
                        assertContextVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0 && shouldCheckArguments(node.callee)) {
                    checkAssertArguments(node);
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
