/**
 * @fileoverview Check the location of literals in arguments to QUnit's assertion functions.
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

function swapFirstTwoNodesInList(sourceCode, fixer, list) {
    const node0Text = sourceCode.getText(list[0]);
    const node1Text = sourceCode.getText(list[1]);
    return [
        fixer.replaceText(list[0], node1Text),
        fixer.replaceText(list[1], node0Text)
    ];
}

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "enforce comparison assertions have arguments in the right order",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/literal-compare-order.md"
        },
        fixable: "code",
        messages: {
            actualFirst: "Expected value {{expected}} should be specified after actual value {{actual}}.",
            expectedFirst: "Actual value {{actual}} should be specified after expected value {{expected}}."
        },
        schema: []
    },

    create: function (context) {
        const testStack = [],
            sourceCode = context.getSourceCode();

        function getAssertContext() {
            assert.ok(testStack.length);

            return testStack[testStack.length - 1].assertContextVar;
        }

        function checkLiteralCompareOrder(args, compareActualFirst) {
            if (args.length < 2) {
                return;
            }

            if (compareActualFirst && args[0].type === "Literal" && args[1].type !== "Literal") {
                context.report({
                    node: args[0],
                    messageId: "actualFirst",
                    data: {
                        expected: sourceCode.getText(args[0]),
                        actual: sourceCode.getText(args[1])
                    },
                    fix(fixer) {
                        return swapFirstTwoNodesInList(sourceCode, fixer, args);
                    }
                });
            } else /* istanbul ignore next: no assertions compare expected first */ if (!compareActualFirst && args[0].type !== "Literal" && args[1].type === "Literal") {
                context.report({
                    node: args[0],
                    messageId: "expectedFirst",
                    data: {
                        expected: sourceCode.getText(args[0]),
                        actual: sourceCode.getText(args[1])
                    },
                    fix(fixer) {
                        return swapFirstTwoNodesInList(sourceCode, fixer, args);
                    }
                });
            }
        }

        function processAssertion(node, assertVar) {
            /* istanbul ignore else: correctly does nothing */
            if (utils.isComparativeAssertion(node.callee, assertVar)) {
                const compareActualFirst = utils.shouldCompareActualFirst(node.callee, assertVar);
                checkLiteralCompareOrder(node.arguments, compareActualFirst);
            }
        }

        return {
            "CallExpression": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (utils.isTest(node.callee)) {
                    testStack.push({
                        assertContextVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0 && utils.isAssertion(node.callee, getAssertContext())) {
                    processAssertion(node, getAssertContext());
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
