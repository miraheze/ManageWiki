/**
 * @fileoverview Forbid use of global stop()/start().
 * @author Kevin Partington
 * @copyright 2016 Kevin Partington. All rights reserved.
 * See LICENSE file in root directory for full license.
 */
"use strict";

const { ReferenceTracker } = require("eslint-utils");

//------------------------------------------------------------------------------
// Rule Definition
//------------------------------------------------------------------------------

module.exports = {
    meta: {
        type: "suggestion",
        docs: {
            description: "disallow global stop/start",
            category: "Possible Errors",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-global-stop-start.md"
        },
        messages: {
            unexpectedGlobalStopStart: "Unexpected global {{callee}}() call."
        },
        schema: []
    },

    create: function (context) {
        //--------------------------------------------------------------------------
        // Public
        //--------------------------------------------------------------------------

        return {
            "Program": function () {
                const tracker = new ReferenceTracker(context.getScope());
                const traceMap = {
                    start: { [ReferenceTracker.CALL]: true },
                    stop: { [ReferenceTracker.CALL]: true }
                };

                for (const { node } of tracker.iterateGlobalReferences(traceMap)) {
                    context.report({
                        node: node,
                        messageId: "unexpectedGlobalStopStart",
                        data: {
                            callee: node.callee.name
                        }
                    });
                }
            }
        };
    }
};
