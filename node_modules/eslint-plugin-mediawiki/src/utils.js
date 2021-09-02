'use strict';

function countListItems( sourceCode, node, countedLines, onlyBefore ) {
	const comments = ( onlyBefore ? [] : sourceCode.getCommentsInside( node ) )
		.concat( sourceCode.getCommentsBefore( node ) );
	return comments.reduce(
		function ( acc, line ) {
			if ( line.type === 'Block' ) {
				return acc;
			}
			let matches;
			if ( !countedLines.has( line.value ) ) {
				matches = line.value.match( /^ *\* ?[a-z]./gi );
				countedLines.add( line.value );
			}
			return acc + ( matches ? matches.length : 0 );
		}, 0
	);
}

function isOfLiterals( node ) {
	switch ( node.type ) {
		case 'Literal':
			// Literals: 'foo'
			return true;
		case 'ConditionalExpression':
			// Ternaries: cond ? 'foo' : 'bar'
			return isOfLiterals( node.consequent ) && isOfLiterals( node.alternate );
		case 'ArrayExpression':
			// Arrays of literals
			return node.elements.every( isOfLiterals );
	}
	return false;
}

function requiresCommentList( context, node ) {
	if ( isOfLiterals( node ) ) {
		return false;
	}

	const sourceCode = context.getSourceCode();
	// Don't modify `node` so the correct error source is highlighted
	let checkNode = node,
		prevNode = node,
		listItems = 0;
	const countedLines = new Set();
	while (
		checkNode &&
		checkNode.type !== 'ExpressionStatement' &&
		checkNode.type !== 'VariableDeclaration'
	) {
		listItems += countListItems( sourceCode, checkNode, countedLines );

		if ( listItems > 1 ) {
			// Comments found, return
			return false;
		}

		// Allow documentation to be on or in parent nodes
		prevNode = checkNode;
		checkNode = checkNode.parent;
	}

	// Allow documentation for the first VariableDeclarator in a VariableDeclaration to be
	// above the VariableDeclaration. But don't look inside the VariableDeclaration, because that
	// would allow the documentation for a different variable to be counted.
	if ( checkNode.type === 'VariableDeclaration' && checkNode.declarations[ 0 ] === prevNode ) {
		listItems += countListItems( sourceCode, checkNode, countedLines, true );
		if ( listItems > 1 ) {
			return false;
		}
	}

	return true;
}

module.exports = {
	requiresCommentList: requiresCommentList
};
