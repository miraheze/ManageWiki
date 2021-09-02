/**
 * @fileoverview Require the use of `expect` when using `assert` inside of a
 * block or when passing `assert` to a function.
 * @author Mitch Lloyd
 */
"use strict";

const utils = require("../utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "enforce that `expect` is called",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/require-expect.md"
        },
        messages: {
            expectRequired: "Test is missing `{{expect}}()` call.",
            expectForbidden: "Unexpected use of `{{expect}}()` call.",
            expectRequiredComplexTest: "Should use `{{expect}}()` when using assertions outside of the top-level test callback."
        },
        schema: [
            {
                "enum": ["always", "except-simple", "never", "never-except-zero"]
            }
        ]
    },

    create: function (context) {
        let currentTest = false;

        function isGlobalExpectCall(callee) {
            return callee.type === "Identifier" && callee.name === "expect";
        }

        function isAssertExpectCall(callee) {
            return callee.object &&
                   callee.object.type === "Identifier" &&
                   callee.object.name === currentTest.assertName &&
                   callee.property.name === "expect";
        }

        function isExpectCall(callee) {
            return isGlobalExpectCall(callee) || isAssertExpectCall(callee);
        }

        function isNonZeroExpectCall(node) {
            return isExpectCall(node.callee) && !(
                node.arguments.length === 1 &&
                node.arguments[0].type === "Literal" &&
                node.arguments[0].raw === "0"
            );
        }

        function isTopLevelExpectCall(callee) {
            return isExpectCall(callee) && currentTest.blockDepth === 1;
        }

        function isUsingAssertInNestedBlock(node) {
            return currentTest.blockDepth > 1 && utils.isAssertion(node.callee, currentTest.assertName);
        }

        function isPassingAssertAsArgument(node) {
            if (!currentTest.assertName) {
                return false;
            }

            for (let i = 0; i < node.arguments.length; i++) {
                if (node.arguments[i].name === currentTest.assertName) {
                    return true;
                }
            }
            return false;
        }

        function isViolatingExceptSimpleRule(node) {
            return !currentTest.isExpectUsed &&
                   (isUsingAssertInNestedBlock(node) || isPassingAssertAsArgument(node));
        }

        function captureTestContext(node) {
            currentTest = {
                assertName: utils.getAssertContextNameForTest(node.arguments),
                node: node,
                blockDepth: 0,
                isExpectUsed: false,
                didReport: false
            };
        }

        function releaseTestContext() {
            currentTest = false;
        }

        function assertionMessageData() {
            return {
                expect: currentTest.assertName ? `${currentTest.assertName}.expect` : "expect"
            };
        }

        const ExceptSimpleStrategy = {
            "CallExpression": function (node) {
                if (currentTest && !currentTest.didReport) {
                    if (isTopLevelExpectCall(node.callee)) {
                        currentTest.isExpectUsed = true;
                    } else if (isViolatingExceptSimpleRule(node)) {
                        context.report({
                            node: currentTest.node,
                            messageId: "expectRequiredComplexTest",
                            data: assertionMessageData()
                        });
                        currentTest.didReport = true;
                    }
                } else if (utils.isTest(node.callee)) {
                    captureTestContext(node);
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    releaseTestContext();
                }
            },

            "BlockStatement, ArrowFunctionExpression[body.type!='BlockStatement]": function () {
                if (currentTest) {
                    currentTest.blockDepth++;
                }
            },

            "BlockStatement, ArrowFunctionExpression[body.type!='BlockStatement]:exit": function () {
                if (currentTest) {
                    currentTest.blockDepth--;
                }
            }
        };

        const AlwaysStrategy = {
            "CallExpression": function (node) {
                if (currentTest && isExpectCall(node.callee)) {
                    currentTest.isExpectUsed = true;
                } else if (utils.isTest(node.callee)) {
                    captureTestContext(node);
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    if (!currentTest.isExpectUsed) {
                        context.report({
                            node: currentTest.node,
                            messageId: "expectRequired",
                            data: assertionMessageData()
                        });
                    }

                    releaseTestContext();
                }
            }
        };

        const NeverStrategy = {
            "CallExpression": function (node) {
                if (currentTest && isExpectCall(node.callee)) {
                    currentTest.isExpectUsed = true;
                } else if (utils.isTest(node.callee)) {
                    captureTestContext(node);
                }
            },
            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    if (currentTest.isExpectUsed) {
                        context.report({
                            node: currentTest.node,
                            messageId: "expectForbidden",
                            data: assertionMessageData()
                        });
                    }
                    releaseTestContext();
                }
            }
        };

        const NeverExceptZeroStrategy = {
            "CallExpression": function (node) {
                if (currentTest && isNonZeroExpectCall(node)) {
                    currentTest.isNonZeroExpectUsed = true;
                } else if (utils.isTest(node.callee)) {
                    captureTestContext(node);
                }
            },
            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    if (currentTest.isNonZeroExpectUsed) {
                        context.report({
                            node: currentTest.node,
                            messageId: "expectForbidden",
                            data: assertionMessageData()
                        });
                    }
                    releaseTestContext();
                }
            }
        };

        return {
            "always": AlwaysStrategy,
            "except-simple": ExceptSimpleStrategy,
            "never": NeverStrategy,
            "never-except-zero": NeverExceptZeroStrategy
        }[context.options[0]] || ExceptSimpleStrategy;
    }
};
