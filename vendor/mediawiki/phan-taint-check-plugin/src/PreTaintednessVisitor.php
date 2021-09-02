<?php

namespace SecurityCheckPlugin;

use ast\Node;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\PluginV3\PluginAwarePreAnalysisVisitor;

/**
 * Class for visiting any nodes we want to handle in pre-order.
 *
 * Unlike TaintednessVisitor, this is solely used to set taint
 * on variable objects, and not to determine the taint of the
 * current node, so this class does not return anything.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
class PreTaintednessVisitor extends PluginAwarePreAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * @see visitMethod
	 * @param Node $node
	 */
	public function visitFuncDecl( Node $node ) : void {
		$this->visitMethod( $node );
	}

	/**
	 * @see visitMethod
	 * @param Node $node
	 */
	public function visitClosure( Node $node ) : void {
		$this->visitMethod( $node );
	}

	/**
	 * Set the taintedness of parameters to method/function.
	 *
	 * Parameters that are ints (etc) are clearly safe so
	 * this marks them as such. For other parameters, it
	 * creates a map between the function object and the
	 * parameter object so if anyone later calls the method
	 * with a dangerous argument we can determine if we need
	 * to output a warning.
	 *
	 * Also handles FuncDecl and Closure
	 * @param Node $node
	 */
	public function visitMethod( Node $node ) : void {
		// var_dump( __METHOD__ ); Debug::printNode( $node );
		$method = $this->context->getFunctionLikeInScope( $this->code_base );

		$params = $node->children['params']->children;
		foreach ( $params as $i => $param ) {
			$scope = $this->context->getScope();
			if ( !$scope->hasVariableWithName( $param->children['name'] ) ) {
				// Well uh-oh.
				$this->debug( __METHOD__, "Missing variable for param \$" . $param->children['name'] );
				continue;
			}
			$varObj = $scope->getVariableByName( $param->children['name'] );

			if ( $varObj instanceof PassByReferenceVariable ) {
				$this->addTaintError(
					Taintedness::newSafe(),
					$this->extractReferenceArgument( $varObj )
				);
				continue;
			}

			$paramTypeTaint = $this->getTaintByType( $varObj->getUnionType() );
			// Initially, the variable starts off with no taint.
			$this->setTaintednessOld( $varObj, Taintedness::newSafe() );

			if ( !$paramTypeTaint->isSafe() ) {
				// If the param is not an integer or something, link it to the func
				$this->linkParamAndFunc( $varObj, $method, $i );
			}
		}
	}

	/**
	 * Determine whether this operation is safe, based on the operand types. This needs to be done
	 * in preorder because phan infers types from operators, e.g. from `$a += $b` phan will infer
	 * that they're both numbers. We need to use the types of the operands *before* inferring
	 * types from the operator.
	 *
	 * @param Node $node
	 */
	public function visitAssignOp( Node $node ) : void {
		$lhs = $node->children['var'];
		$rhs = $node->children['expr'];
		$node->assignTaintMask = $this->getBinOpTaintMask( $node, $lhs, $rhs );
	}
}
