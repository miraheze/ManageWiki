/**
 * @fileoverview Ensure that no unit test is commented out.
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
            description: "disallow commented tests",
            category: "Best Practices",
            url: "https://github.com/platinumazure/eslint-plugin-qunit/blob/master/docs/rules/no-commented-tests.md"
        },
        messages: {
            unexpectedTestInComment: "Unexpected \"{{callee}}\" in comment. Use QUnit.skip outside of a comment."
        },
        schema: []
    },

    create: function (context) {
        const sourceCode = context.getSourceCode(),
            newlineRegExp = /\r\n|\r|\n/g,
            warningRegExp = /\b(QUnit\.test|QUnit\.asyncTest|QUnit\.skip|test|asyncTest)\s*\(/g;

        function getNewlineIndexes(text) {
            const indexes = [];
            let result;

            while ((result = newlineRegExp.exec(text)) !== null) {
                indexes.push(result.index + result[0].length);
            }

            return indexes;
        }

        function reportWarning(node, warning) {
            context.report({
                node: node,
                loc: warning.loc,
                messageId: "unexpectedTestInComment",
                data: {
                    callee: warning.term
                }
            });
        }

        function checkComment(node) {
            const warnings = [],
                text = sourceCode.getText(node),
                loc = node.loc.start,
                newlineIndexes = getNewlineIndexes(text);

            let lineOffset = 0,
                currentNewlineIndex,
                result;

            while ((result = warningRegExp.exec(text)) !== null) {
                while (newlineIndexes.length > 0 && result.index >= newlineIndexes[0]) {
                    ++lineOffset;
                    currentNewlineIndex = newlineIndexes.shift();
                }

                warnings.push({
                    term: result[1],
                    loc: {
                        line: loc.line + lineOffset,
                        column: lineOffset ? result.index - currentNewlineIndex : loc.column + result.index
                    }
                });
            }

            for (const warning of warnings) {
                reportWarning(node, warning);
            }
        }

        return {
            Program: function () {
                const comments = sourceCode.getAllComments()
                    .filter(comment => comment.type !== "Shebang");
                for (const comment of comments) {
                    checkComment(comment);
                }
            }
        };
    }
};
