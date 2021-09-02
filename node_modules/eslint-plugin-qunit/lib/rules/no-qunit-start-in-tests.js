/**
 * @fileoverview forbid QUnit.start() within tests or test hooks
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
            description: "disallow QUnit.start() within tests or test hooks",
            category: "Possible Errors",
            recommended: false,
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-qunit-start-in-tests.md"
        },
        fixable: null,
        messages: {
            noQUnitStartInTests: "Do not use QUnit.start() inside a {{context}}."
        },
        schema: []
    },

    create: function (context) {
        const contextStack = [];

        //----------------------------------------------------------------------
        // Helpers
        //----------------------------------------------------------------------

        function isQUnitStart(calleeNode) {
            return calleeNode.type === "MemberExpression" &&
                utils.isStart(calleeNode);
        }

        function isInModule(propertyNode) {
            return propertyNode &&
                propertyNode.parent &&          // ObjectExpression
                propertyNode.parent.parent &&   // CallExpression?
                propertyNode.parent.parent.type === "CallExpression" &&
                utils.isModule(propertyNode.parent.parent.callee);
        }

        //----------------------------------------------------------------------
        // Public
        //----------------------------------------------------------------------

        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    contextStack.push("test");
                } else if (contextStack.length > 0 && isQUnitStart(node.callee)) {
                    const currentContext = contextStack[contextStack.length - 1];

                    context.report({
                        node: node,
                        messageId: "noQUnitStartInTests",
                        data: {
                            context: currentContext
                        }
                    });
                }
            },

            "Property": function (node) {
                if (utils.isModuleHookPropertyKey(node.key) && isInModule(node)) {
                    contextStack.push(`${node.key.name} hook`);
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isTest(node.callee)) {
                    contextStack.pop();
                }
            },

            "Property:exit": function (node) {
                if (utils.isModuleHookPropertyKey(node.key) && isInModule(node)) {
                    contextStack.pop();
                }
            }
        };
    }
};
