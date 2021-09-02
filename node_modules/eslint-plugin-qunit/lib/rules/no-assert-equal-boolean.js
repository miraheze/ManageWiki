"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const assert = require("assert"),
    utils = require("../utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

const EQUALITY_ASSERTIONS = new Set(["equal", "deepEqual", "strictEqual"]);

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "require use of boolean assertions",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-assert-equal-boolean.md"
        },
        fixable: "code",
        messages: {
            useAssertTrueOrFalse: "Use `assert.true or `assert.false` for boolean assertions."
        },
        schema: []
    },

    create: function (context) {
        // Declare a test stack in case of nested test cases (not currently supported by QUnit).
        const testStack = [];

        function getCurrentAssertContextVariable() {
            assert(testStack.length, "Test stack should not be empty");

            return testStack[testStack.length - 1].assertVar;
        }

        // Check for something like `equal(...)` without assert parameter.
        function isGlobalEqualityAssertion(calleeNode) {
            return calleeNode &&
                calleeNode.type === "Identifier" &&
                EQUALITY_ASSERTIONS.has(calleeNode.name);
        }

        // Check for something like `assert.equal(...)`.
        function isAssertEquality(calleeNode) {
            return calleeNode &&
                calleeNode.type === "MemberExpression" &&
                calleeNode.property.type === "Identifier" &&
                EQUALITY_ASSERTIONS.has(calleeNode.property.name) &&
                calleeNode.object.type === "Identifier" &&
                calleeNode.object.name === getCurrentAssertContextVariable();
        }

        // Check for something like `equal(...)` or `assert.equal(...)`.
        function isEqualityAssertion(calleeNode) {
            return isGlobalEqualityAssertion(calleeNode) ||
                isAssertEquality(calleeNode);
        }

        // Finds the first boolean argument of a CallExpression if one exists.
        function getBooleanArgument(node) {
            return node.arguments.length >= 2 &&
                [node.arguments[0], node.arguments[1]]
                    .find(arg => arg.type === "Literal" && (arg.value === true || arg.value === false));
        }

        function reportError(node) {
            context.report({
                node: node,
                messageId: "useAssertTrueOrFalse",
                fix(fixer) {
                    const booleanArgument = getBooleanArgument(node);
                    const newAssertionFunctionName = booleanArgument.value ? "true" : "false";

                    const sourceCode = context.getSourceCode();
                    const newArgsTextArray = node.arguments.filter(arg => arg !== booleanArgument).map(arg => sourceCode.getText(arg));
                    const newArgsTextJoined = newArgsTextArray.join(", ");

                    const assertVariablePrefix = node.callee.type === "Identifier" ? "" : `${getCurrentAssertContextVariable()}.`;

                    return fixer.replaceText(node, `${assertVariablePrefix}${newAssertionFunctionName}(${newArgsTextJoined})`);
                }
            });
        }

        return {
            "CallExpression": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (utils.isTest(node.callee) || utils.isAsyncTest(node.callee)) {
                    testStack.push({
                        assertVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0 && isEqualityAssertion(node.callee) && getBooleanArgument(node)) {
                    reportError(node);
                }
            },
            "CallExpression:exit": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (utils.isTest(node.callee) || utils.isAsyncTest(node.callee)) {
                    testStack.pop();
                }
            }
        };
    }
};
