/**
 * @fileoverview Check the number of arguments to QUnit's assertion functions.
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
        type: "problem",
        docs: {
            description: "enforce that the correct number of assert arguments are used",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/assert-args.md"
        },
        messages: {
            unexpectedArgCount: "Unexpected call to {{callee}} with {{argCount}} arguments.",
            unexpectedArgCountNoMessage: "Unexpected call to {{callee}} with {{argCount}} arguments and no error message."
        },
        schema: []
    },

    create: function (context) {
        const testStack = [],
            sourceCode = context.getSourceCode();

        function isPossibleMessage(argNode) {
            // For now, we will allow all nodes. Hoping to allow user-driven
            // configuration later.
            // E.g., to allow string literals only:
            // return lastArg.type === "Literal" && typeof lastArg.value === "string";

            // For now, allowing all nodes to be possible messages.
            return argNode;
        }

        function getAssertContext() {
            assert.ok(testStack.length);

            return testStack[testStack.length - 1].assertContextVar;
        }

        function checkAssertArity(callExpressionNode) {
            const allowedArities = utils.getAllowedArities(callExpressionNode.callee, getAssertContext()),
                assertArgs = callExpressionNode.arguments,
                lastArg = assertArgs[assertArgs.length - 1],
                mayHaveMessage = lastArg && isPossibleMessage(lastArg);

            const definitelyTooFewArgs = allowedArities.every(function (arity) {
                return assertArgs.length < arity;
            });

            if (mayHaveMessage && allowedArities.includes(assertArgs.length - 1)) {
                return;
            } else if (allowedArities.includes(assertArgs.length)) {
                return;
            }

            context.report({
                node: callExpressionNode,
                messageId: mayHaveMessage && !definitelyTooFewArgs ? "unexpectedArgCount" : "unexpectedArgCountNoMessage",
                data: {
                    callee: sourceCode.getText(callExpressionNode.callee),
                    argCount: assertArgs.length
                }
            });
        }

        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    testStack.push({
                        assertContextVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0 && utils.isAssertion(node.callee, getAssertContext())) {
                    checkAssertArity(node);
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
