const espree = require('espree');
const evk = require('eslint-visitor-keys');

function parseJson(code, options) {
    // Use a wrapper to create valid code out of JSON.
    const validCode = `(${code});`;

    try {
        const ast = espree.parse(validCode, options);

        fixProgram(ast);
        fixTokens(ast);
        fixNodes(ast);

        return {
            ast,
            services: {},
            scopeManager: null,
            visitorKeys: null
        };
    } catch (astParseError) {
        // Ups, the AST parser did not like the code.
        // To get a better error message, we need to parse it as JSON.
        let error = astParseError;

        try {
            JSON.parse(code);
        } catch (jsonParseError) {
            // Override the AST parser syntax error with the JSON parser message.
            error.message = jsonParseError.message;
        }

        throw error;
    }
}

function fixProgram(ast) {
    // The wrapper uses three tokens: '(', ')' and ';'
    ast.end = ast.end - 3;
    ast.range[1] = ast.end

    // Remove the wrapper (Expression Statement) and move the JSON (e.g. ObjectExpression) into body
    ast.body[0] = ast.body[0].expression;
}

function fixTokens(ast) {
    // Remove the first '(' and the last two tokens ')', ';'
    ast.tokens = ast.tokens.slice(1, ast.tokens.length - 2);

    // Fix the location of the tokens
    ast.tokens.forEach(fixLocation);
}

function fixNodes(ast) {
    traverseAst(ast.body[0], node => fixLocation(node));
}

function traverseAst(node, callback) {
    callback(node);

    const keys = evk.KEYS[node.type];

    for(const key of keys) {
        const next = node[key];

        if (Array.isArray(next)) {
            for(const child of next) {
                traverseAst(child, callback);
            }
        } else if (next.type) {
            traverseAst(next, callback);
        }
    }
}

function fixPosition(loc) {
    if (loc.line === 1 && !loc.fixed) {
        // After the wrapper was removed we need to fix the column of the first line.
        loc.column--;

        // Position objects can be shared between
        // ast.tokens & ast.nodes, so mark as fixed
        loc.fixed = true;
    }
}

function fixLocation(node) {
    if (node.start) {
        node.start = node.start - 1;
        node.range[0] = node.start;
    }

    if (node.end) {
        node.end = node.end - 1;
        node.range[1] = node.end;
    }

    if (node.loc) {
        fixPosition(node.loc.start);
        fixPosition(node.loc.end);
    }
}

module.exports = {parseJson};
