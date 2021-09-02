/**
 * @fileoverview Forbid the use of QUnit.push.
 * @author Kevin Partington
 */
"use strict";

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow QUnit.push",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-qunit-push.md"
        },
        messages: {
            noQUnitPush: "Do not use QUnit.push()."
        },
        schema: []
    },

    create: function (context) {
        //--------------------------------------------------------------------------
        // Public
        //--------------------------------------------------------------------------

        return {
            "CallExpression[callee.object.name='QUnit'][callee.property.name='push']": function (node) {
                context.report({
                    node: node,
                    messageId: "noQUnitPush"
                });
            }
        };
    }
};
