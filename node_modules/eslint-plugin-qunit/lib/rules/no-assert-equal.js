/**
 * @fileoverview Forbid the use of assert.equal and suggest other assertions.
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const assert = require("assert"),
    utils = require("../utils"),
    { ReferenceTracker } = require("eslint-utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow the use of assert.equal",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-assert-equal.md"
        },
        messages: {
            unexpectedGlobalEqual: "Unexpected equal. Use strictEqual, deepEqual, or propEqual.",
            unexpectedAssertEqual: "Unexpected {{assertVar}}.equal. Use {{assertVar}}.strictEqual, {{assertVar}}.deepEqual, or {{assertVar}}.propEqual.",
            switchToDeepEqual: "Switch to deepEqual.",
            switchToPropEqual: "Switch to propEqual.",
            switchToStrictEqual: "Switch to strictEqual."
        },
        schema: [],
        hasSuggestions: true
    },

    create: function (context) {
        // Declare a test stack in case of nested test cases (not currently
        // supported by QUnit).
        const testStack = [];

        // We check upfront to find all the references to global equal(),
        // and then report them if they end up being inside test contexts.
        const globalEqualCallNodes = new Set();

        function getCurrentAssertContextVariable() {
            assert(testStack.length, "Test stack should not be empty");

            return testStack[testStack.length - 1].assertVar;
        }

        function isAssertEqual(calleeNode) {
            return calleeNode &&
                calleeNode.type === "MemberExpression" &&
                calleeNode.property.type === "Identifier" &&
                calleeNode.property.name === "equal" &&
                calleeNode.object.type === "Identifier" &&
                calleeNode.object.name === getCurrentAssertContextVariable();
        }

        function reportError(node, isGlobal) {
            context.report({
                node: node,
                messageId: isGlobal ? "unexpectedGlobalEqual" : "unexpectedAssertEqual",
                data: {
                    assertVar: isGlobal ? null : getCurrentAssertContextVariable()
                },
                suggest: [
                    {
                        messageId: "switchToDeepEqual",
                        fix(fixer) {
                            return fixer.replaceText(isGlobal ? node.callee : node.callee.property, "deepEqual");
                        }
                    },
                    {
                        messageId: "switchToPropEqual",
                        fix(fixer) {
                            return fixer.replaceText(isGlobal ? node.callee : node.callee.property, "propEqual");
                        }
                    },
                    {
                        messageId: "switchToStrictEqual",
                        fix(fixer) {
                            return fixer.replaceText(isGlobal ? node.callee : node.callee.property, "strictEqual");
                        }
                    }
                ]

            });
        }

        return {
            "CallExpression": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (utils.isTest(node.callee) || utils.isAsyncTest(node.callee)) {
                    testStack.push({
                        assertVar: utils.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0) {
                    if (isAssertEqual(node.callee)) {
                        reportError(node, false);
                    } else if (globalEqualCallNodes.has(node)) {
                        reportError(node, true);
                    }
                }
            },
            "CallExpression:exit": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (utils.isTest(node.callee) || utils.isAsyncTest(node.callee)) {
                    testStack.pop();
                }
            },
            "Program": function () {
                // Gather all calls to global `equal()`.

                const tracker = new ReferenceTracker(context.getScope());
                const traceMap = { equal: { [ReferenceTracker.CALL]: true } };

                for (const { node } of tracker.iterateGlobalReferences(traceMap)) {
                    globalEqualCallNodes.add(node);
                }
            }
        };
    }
};
