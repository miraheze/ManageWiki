/**
 * @fileoverview Utility functions used by one or more rules.
 * @author Kevin Partington
 */
"use strict";

const assert = require("assert");

const SUPPORTED_TEST_IDENTIFIERS = new Set(["test", "asyncTest", "only"]);

const OLD_MODULE_HOOK_IDENTIFIERS = ["setup", "teardown"];
const NEW_MODULE_HOOK_IDENTIFIERS = ["beforeEach", "afterEach"];
const ALL_MODULE_HOOK_IDENTIFIERS = new Set(OLD_MODULE_HOOK_IDENTIFIERS.concat(NEW_MODULE_HOOK_IDENTIFIERS));

const ASSERTION_METADATA = {
    deepEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    equal: {
        allowedArities: [2],
        compareActualFirst: true
    },
    false: {
        allowedArities: [1]
    },
    notDeepEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    notEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    notOk: {
        allowedArities: [1]
    },
    notPropEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    notStrictEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    ok: {
        allowedArities: [1]
    },
    propEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    strictEqual: {
        allowedArities: [2],
        compareActualFirst: true
    },
    raises: {
        allowedArities: [1, 2]
    },
    throws: {
        allowedArities: [1, 2]
    },
    true: {
        allowedArities: [1]
    }
};

function getAssertionNames() {
    return Object.keys(ASSERTION_METADATA);
}

exports.getAssertionNames = getAssertionNames;

function getAssertionMetadata(calleeNode, assertVar) {
    if (calleeNode.type === "MemberExpression") {
        return calleeNode.object &&
            calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === assertVar &&
            calleeNode.property &&
            Object.hasOwnProperty.call(ASSERTION_METADATA, calleeNode.property.name) &&
            ASSERTION_METADATA[calleeNode.property.name];
    } else if (calleeNode.type === "Identifier") {
        return Object.hasOwnProperty.call(ASSERTION_METADATA, calleeNode.name) &&
            ASSERTION_METADATA[calleeNode.name];
    }

    return null;
}

exports.isAsyncCallExpression = function (callExpressionNode, assertVar) {
    if (!assertVar) {
        assertVar = "assert";
    }

    return callExpressionNode &&
        callExpressionNode.type === "CallExpression" &&
        callExpressionNode.callee.type === "MemberExpression" &&
        callExpressionNode.callee.object.type === "Identifier" &&
        callExpressionNode.callee.object.name === assertVar &&
        callExpressionNode.callee.property.type === "Identifier" &&
        callExpressionNode.callee.property.name === "async";
};

exports.isStop = function (calleeNode) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        result = calleeNode.name === "stop";
    } else if (calleeNode.type === "MemberExpression") {
        result = calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === "QUnit" &&
            calleeNode.property.type === "Identifier" &&
            calleeNode.property.name === "stop";
    }

    return result;
};

exports.isStart = function (calleeNode) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        result = calleeNode.name === "start";
    } else if (calleeNode.type === "MemberExpression") {
        result = calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === "QUnit" &&
            calleeNode.property.type === "Identifier" &&
            calleeNode.property.name === "start";
    }

    return result;
};

exports.isTest = function (calleeNode) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        result = SUPPORTED_TEST_IDENTIFIERS.has(calleeNode.name);
    } else if (calleeNode.type === "MemberExpression") {
        result = calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === "QUnit" &&
            calleeNode.property.type === "Identifier" &&
            SUPPORTED_TEST_IDENTIFIERS.has(calleeNode.property.name);
    }

    return result;
};

exports.isModule = function (calleeNode) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        result = calleeNode.name === "module";
    } else if (calleeNode.type === "MemberExpression") {
        result = calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === "QUnit" &&
            calleeNode.property.type === "Identifier" &&
            calleeNode.property.name === "module";
    }

    return result;
};

exports.isModuleHookPropertyKey = function (identifierNode) {
    return identifierNode &&
        identifierNode.type === "Identifier" &&
        ALL_MODULE_HOOK_IDENTIFIERS.has(identifierNode.name);
};

exports.isAsyncTest = function (calleeNode) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        result = calleeNode.name === "asyncTest";
    } else if (calleeNode.type === "MemberExpression") {
        result = calleeNode.object.type === "Identifier" &&
            calleeNode.object.name === "QUnit" &&
            calleeNode.property.type === "Identifier" &&
            calleeNode.property.name === "asyncTest";
    }

    return result;
};

function isQUnitMethod(calleeNode, qunitMethod) {
    let result = false;

    /* istanbul ignore else: will correctly return false */
    if (calleeNode.type === "Identifier") {
        // <qunitMethod>()
        result = calleeNode.name === qunitMethod;
    } else if (calleeNode.type === "MemberExpression" && calleeNode.property.type === "Identifier" && calleeNode.property.name === qunitMethod) {
        if (calleeNode.object.type === "Identifier") {
            // QUnit.<qunitMethod>() or module.<qunitMethod>()
            result = calleeNode.object.name === "QUnit" || calleeNode.object.name === "module";
        } else if (calleeNode.object.type === "MemberExpression") {
            // QUnit.*.<qunitMethod>()
            result = calleeNode.object.object.type === "Identifier" &&
                calleeNode.object.object.name === "QUnit";
        }
    }

    return result;
}

exports.isOnly = function (calleeNode) {
    return isQUnitMethod(calleeNode, "only");
};

exports.isSkip = function (calleeNode) {
    return isQUnitMethod(calleeNode, "skip");
};

exports.getAssertContextNameForTest = function (argumentsNodes) {
    const functionExpr = argumentsNodes.find(function (argNode) {
        return argNode.type === "FunctionExpression";
    });

    return this.getAssertContextName(functionExpr);
};

exports.getAssertContextName = function (functionExpr) {
    let result;

    if (functionExpr && functionExpr.params && functionExpr.params.length > 0) {
        result = functionExpr.params[0].name;
    }

    return result;
};

exports.isAssertion = function (calleeNode, assertVar) {
    return !!getAssertionMetadata(calleeNode, assertVar);
};

exports.getAllowedArities = function (calleeNode, assertVar) {
    const assertionMetadata = getAssertionMetadata(calleeNode, assertVar);

    return assertionMetadata && assertionMetadata.allowedArities || /* istanbul ignore next */ [];
};

exports.isComparativeAssertion = function (calleeNode, assertVar) {
    const assertionMetadata = getAssertionMetadata(calleeNode, assertVar);

    return Object.hasOwnProperty.call(assertionMetadata, "compareActualFirst");
};

exports.shouldCompareActualFirst = function (calleeNode, assertVar) {
    const assertionMetadata = getAssertionMetadata(calleeNode, assertVar);

    return assertionMetadata && assertionMetadata.compareActualFirst;
};

exports.createAssertionCheck = function (assertions, errorMessageConfig) {
    return function (context) {
        // Declare a test stack in case of nested test cases (not currently
        // supported by QUnit).
        const testStack = [];

        function isGlobalAssertion(calleeNode) {
            return calleeNode &&
                calleeNode.type === "Identifier" &&
                assertions.includes(calleeNode.name);
        }

        function getCurrentAssertContextVariable() {
            assert(testStack.length, "Test stack should not be empty");

            return testStack[testStack.length - 1].assertVar;
        }

        function isMethodCalledOnLocalAssertObject(calleeNode) {
            return calleeNode &&
                calleeNode.type === "MemberExpression" &&
                calleeNode.property.type === "Identifier" &&
                assertions.includes(calleeNode.property.name) &&
                calleeNode.object.type === "Identifier" &&
                calleeNode.object.name === getCurrentAssertContextVariable();
        }

        function isExpectedAssertion(calleeNode) {
            return isGlobalAssertion(calleeNode) ||
                isMethodCalledOnLocalAssertObject(calleeNode);
        }

        function reportError(node) {
            const assertVar = getCurrentAssertContextVariable();
            const isGlobal = isGlobalAssertion(node.callee);
            const assertion = isGlobal ? node.callee.name : node.callee.property.name;

            const reportErrorObject = {
                node,
                data: {
                    assertVar,
                    assertion
                }
            };
            const errorMessageConfigForAssertion = errorMessageConfig[assertion];
            if (errorMessageConfigForAssertion.unexpectedGlobalAssertionMessageId && errorMessageConfigForAssertion.unexpectedLocalAssertionMessageId) {
                reportErrorObject.messageId = isGlobal ? errorMessageConfigForAssertion.unexpectedGlobalAssertionMessageId : errorMessageConfigForAssertion.unexpectedLocalAssertionMessageId;
            } else {
                reportErrorObject.message = isGlobal ? errorMessageConfigForAssertion.unexpectedGlobalAssertionMessage : errorMessageConfigForAssertion.unexpectedLocalAssertionMessage;
            }

            context.report(reportErrorObject);
        }

        return {
            "CallExpression": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (exports.isTest(node.callee) || exports.isAsyncTest(node.callee)) {
                    testStack.push({
                        assertVar: exports.getAssertContextNameForTest(node.arguments)
                    });
                } else if (testStack.length > 0 && isExpectedAssertion(node.callee)) {
                    reportError(node);
                }
            },
            "CallExpression:exit": function (node) {
                /* istanbul ignore else: correctly does nothing */
                if (exports.isTest(node.callee) || exports.isAsyncTest(node.callee)) {
                    testStack.pop();
                }
            }
        };
    };
};
