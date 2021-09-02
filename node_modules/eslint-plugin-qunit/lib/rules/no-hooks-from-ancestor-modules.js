/**
 * @fileoverview disallow the use of hooks from ancestor modules
 * @author Raymond Cohen
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const utils = require("../utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

const NESTABLE_HOOK_NAMES = new Set(["afterEach", "beforeEach"]);

module.exports = {
    meta: {
        type: "problem",
        docs: {
            description: "disallow the use of hooks from ancestor modules",
            category: "Possible Errors",
            recommended: false,
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-hooks-from-ancestor-modules.md"
        },
        fixable: null,
        messages: {
            "noHooksFromAncestorModules": "Do not call {{usedHooksIdentifierName}}.{{hookName}} from an ancestor module."
        },
        schema: []
    },

    create: function (context) {
        const moduleStack = [];

        //----------------------------------------------------------------------
        // Helpers
        //----------------------------------------------------------------------

        function isInModuleCallbackBody(callExpressionNode) {
            return callExpressionNode &&
                callExpressionNode.parent &&
                callExpressionNode.parent.type === "ExpressionStatement" &&
                callExpressionNode.parent.parent &&
                callExpressionNode.parent.parent.type === "BlockStatement" &&
                callExpressionNode.parent.parent.parent &&
                callExpressionNode.parent.parent.parent.type === "FunctionExpression" &&
                callExpressionNode.parent.parent.parent.parent &&
                callExpressionNode.parent.parent.parent.parent.type === "CallExpression" &&
                utils.isModule(callExpressionNode.parent.parent.parent.parent.callee);
        }

        function isHookInvocation(node) {
            return node.callee.type === "MemberExpression" &&
                node.callee.object.type === "Identifier" &&
                NESTABLE_HOOK_NAMES.has(node.callee.property.name) &&
                isInModuleCallbackBody(node);
        }

        function getHooksIdentifierFromParams(params) {
            // In TypeScript, `this` can be passed as the first function parameter to add a type to it,
            // and we want to ignore that parameter since we're looking for the `hooks` variable.
            return params.find(p => p.type === "Identifier" && p.name !== "this");
        }

        //----------------------------------------------------------------------
        // Public
        //----------------------------------------------------------------------

        return {
            "CallExpression": function (node) {
                if (utils.isModule(node.callee)) {
                    const moduleStackInfo = {
                        callExpression: node,
                        description: node.arguments[0].value
                    };

                    const callback = node.arguments[1];
                    const hooksParam = callback && callback.type === "FunctionExpression" ? getHooksIdentifierFromParams(callback.params) : null;
                    moduleStackInfo.hookIdentifierName = hooksParam ? hooksParam.name : null;
                    moduleStack.push(moduleStackInfo);
                } else if (isHookInvocation(node)) {
                    const containingModuleInfo = moduleStack[moduleStack.length - 1];
                    const expectedHooksIdentifierName = containingModuleInfo.hookIdentifierName;
                    const usedHooksIdentifierName = node.callee.object.name;
                    const invokedMethodName = node.callee.property.name;

                    if (expectedHooksIdentifierName !== usedHooksIdentifierName) {
                        context.report({
                            node: node.callee,
                            messageId: "noHooksFromAncestorModules",
                            data: {
                                invokedMethodName,
                                usedHooksIdentifierName
                            }
                        });
                    }
                }
            },

            "CallExpression:exit": function (node) {
                if (utils.isModule(node.callee)) {
                    moduleStack.pop();
                }
            }
        };
    }
};
