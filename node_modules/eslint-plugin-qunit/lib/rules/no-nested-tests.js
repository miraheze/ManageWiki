/**
 * @fileoverview Forbid usage of nested QUnit.test()
 * @author Aliaksandr Yermalayeu
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
        type: "problem",
        docs: {
            description: "disallow nested QUnit.test() calls",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-nested-tests.md"
        },
        messages: {
            noNestedTests: "Using QUnit.test inside of another QUnit.test is not allowed."
        },
        schema: []
    },

    create: function (context) {
        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee)) {
                    let currentNode = node;
                    while (currentNode.parent) {
                        const { parent } = currentNode;
                        if (parent.type === "CallExpression" && utils.isTest(parent.callee)) {
                            context.report({
                                node,
                                messageId: "noNestedTests"
                            });
                            return;
                        }
                        currentNode = parent;
                    }
                }
            }
        };
    }
};
