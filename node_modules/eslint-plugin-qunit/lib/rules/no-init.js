/**
 * @fileoverview Forbids use of QUnit.init.
 * @author Kevin Partington
 * @copyright 2016 Kevin Partington. All rights reserved.
 * See LICENSE file in root directory for full license.
 */
"use strict";

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow use of QUnit.init",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-init.md"
        },
        messages: {
            noInit: "Do not use QUnit.init()."
        },
        schema: []
    },

    create: function (context) {
        //--------------------------------------------------------------------------
        // Public
        //--------------------------------------------------------------------------

        return {
            "CallExpression[callee.object.name='QUnit'][callee.property.name='init']": function (node) {
                context.report({
                    node: node,
                    messageId: "noInit"
                });
            }
        };
    }
};
