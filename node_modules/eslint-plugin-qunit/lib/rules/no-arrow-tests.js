/**
 * @fileoverview Forbid arrow functions as QUnit test/module callbacks.
 * @author Kevin Partington
 * @copyright 2016 Kevin Partington. All rights reserved.
 * See LICENSE file in root directory for full license.
 */
"use strict";

//------------------------------------------------------------------------------
// Requirements
//------------------------------------------------------------------------------

const utils = require("../utils.js");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "problem",
        docs: {
            description: "disallow arrow functions as QUnit test/module callbacks",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-arrow-tests.md"
        },
        fixable: "code",
        messages: {
            noArrowFunction: "Arrow function should not be used as test callback."
        },
        schema: []
    },

    create: function (context) {
        //--------------------------------------------------------------------------
        // Helpers
        //--------------------------------------------------------------------------

        // Fixer adapted from https://github.com/lo1tuma/eslint-plugin-mocha (MIT)
        const sourceCode = context.getSourceCode();

        function extractSourceTextByRange(start, end) {
            return sourceCode.text.slice(start, end).trim();
        }

        function formatFunctionHead(fn) {
            const arrow = sourceCode.getTokenBefore(fn.body);
            const beforeArrowToken = sourceCode.getTokenBefore(arrow);
            let firstToken = sourceCode.getFirstToken(fn);

            let functionKeyword = "function";
            let params = extractSourceTextByRange(firstToken.range[0], beforeArrowToken.range[1]);
            if (fn.async) {
                // When 'async' specified strip the token from the params text
                // and prepend it to the function keyword
                params = params.slice(firstToken.range[1] - firstToken.range[0]).trim();
                functionKeyword = "async function";

                // Advance firstToken pointer
                firstToken = sourceCode.getTokenAfter(firstToken);
            }

            const beforeArrowComment = extractSourceTextByRange(beforeArrowToken.range[1], arrow.range[0]);
            const afterArrowComment = extractSourceTextByRange(arrow.range[1], fn.body.range[0]);
            const paramsFullText = firstToken.type !== "Punctuator" ?
                `(${params}${beforeArrowComment})${afterArrowComment}` :
                `${params}${beforeArrowComment}${afterArrowComment}`;

            return `${functionKeyword}${paramsFullText} `;
        }

        function fixArrowFunction(fixer, fn) {
            if (fn.body.type === "BlockStatement") {
                // When it((...) => { ... }),
                // simply replace '(...) => ' with 'function () '
                return fixer.replaceTextRange(
                    [fn.range[0], fn.body.range[0]],
                    formatFunctionHead(fn)
                );
            }

            const bodyText = sourceCode.text.slice(fn.body.range[0], fn.body.range[1]);
            return fixer.replaceTextRange(
                [fn.range[0], fn.range[1]],
                `${formatFunctionHead(fn)}{ return ${bodyText}; }`
            );
        }

        function checkCallback(fn) {
            if (fn && fn.type === "ArrowFunctionExpression") {
                context.report({
                    node: fn,
                    messageId: "noArrowFunction",
                    fix: fixer => fixArrowFunction(fixer, fn)
                });
            }
        }

        function isPropertyInModule(propertyNode) {
            return propertyNode &&
                propertyNode.parent &&
                propertyNode.parent.type === "ObjectExpression" &&
                propertyNode.parent.parent &&
                propertyNode.parent.parent.type === "CallExpression" &&
                utils.isModule(propertyNode.parent.parent.callee);
        }

        function isModuleProperty(propertyNode) {
            return isPropertyInModule(propertyNode) &&
                utils.isModuleHookPropertyKey(propertyNode.key);
        }

        //--------------------------------------------------------------------------
        // Public
        //--------------------------------------------------------------------------

        return {
            "CallExpression": function (node) {
                if (utils.isTest(node.callee) && node.arguments && node.arguments.length > 1) {
                    checkCallback(node.arguments[1]);
                }
            },

            "Property": function (node) {
                if (isModuleProperty(node)) {
                    checkCallback(node.value);
                }
            }
        };
    }
};
