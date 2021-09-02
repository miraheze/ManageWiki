<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use AssertionError;
use ast\Node;
use Error;
use Exception;
use Phan\AST\ASTReverter;
use Phan\AST\ContextNode;
use Phan\AST\UnionTypeVisitor;
use Phan\BlockAnalysisVisitor;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassElement;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\PassByReferenceVariable;
use Phan\Language\Element\Property;
use Phan\Language\Element\TypedElementInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\BranchScope;
use Phan\Language\Type;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\ClosureType;
use Phan\Language\Type\LiteralTypeInterface;
use Phan\Language\UnionType;
use Phan\Library\Set;

/**
 * Trait for the Tainedness visitor subclasses. Mostly contains
 * utility methods.
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
/**
 * @property-read Context $context
 * @property-read \Phan\CodeBase $code_base
 */
trait TaintednessBaseVisitor {
	/** @var null|string|bool|resource filehandle to output debug messages */
	private $debugOutput;

	/** @var Context|null Override the file/line number to emit issues */
	protected $overrideContext;

	/**
	 * Change taintedness of a function/method
	 *
	 * @param FunctionInterface $func
	 * @param FunctionTaintedness $taint
	 * @param bool $override Whether to merge taint or override
	 * @param string|Context|null $reason Either a reason or a context representing the line number
	 */
	protected function setFuncTaint(
		FunctionInterface $func,
		FunctionTaintedness $taint,
		bool $override = false,
		$reason = null
	) : void {
		if (
			$func instanceof Method &&
			(string)$func->getDefiningFQSEN() !== (string)$func->getFQSEN()
		) {
			$this->debug( __METHOD__, "Setting taint on function " . $func->getFQSEN() . " other than"
				. " its implementation " . $func->getDefiningFQSEN()
			);
			// FIXME we should maybe do something here.
			// As it stands, this case probably can't be reached.
		}

		$funcTaint = $this->getFuncTaint( $func );
		if ( $funcTaint !== null ) {
			$curTaint = $funcTaint;
		} elseif ( !$override ) {
			// If we are not overriding, and we don't know
			// current taint, figure it out.
			$curTaint = clone $this->getTaintOfFunction( $func, false );
		} else {
			$curTaint = new FunctionTaintedness( Taintedness::newUnknown() );
		}
		$newTaint = $override ? clone $taint : new FunctionTaintedness( Taintedness::newUnknown() );

		/**
		 * @param int|string $index
		 */
		$maybeAddTaintError = function (
			Taintedness $baseT,
			Taintedness $curT,
			$index
		) use ( $func, $reason ) : void {
			// Only copy error lines if we add some taint not
			// previously present.
			if ( !$baseT->withoutShaped( $curT )->isSafe() ) {
				if ( $index === 'overall' ) {
					$this->addTaintError( $baseT, $func, -1, $reason );
				} else {
					$this->addTaintError( $baseT, $func, $index, $reason );
				}
			}
		};
		$getTaintToAdd = function ( Taintedness $curT, Taintedness $baseT ) : Taintedness {
			if ( $curT->has( SecurityCheckPlugin::NO_OVERRIDE ) ) {
				// We have some hard coded taint (e.g. from
				// docblock) and do not want to override it
				// from stuff deduced from src code.
				return $curT;
			} else {
				// We also clear the UNKNOWN flag here, as
				// if we are explicitly setting it, it is no
				// longer unknown.
				$curTNoUnk = $curT->without( SecurityCheckPlugin::UNKNOWN_TAINT );
				return $curTNoUnk->asMergedWith( $baseT );
			}
		};

		$allParams = array_merge( $taint->getParamKeys(), $curTaint->getParamKeys() );
		foreach ( $allParams as $index ) {
			$baseT = $taint->getParamTaint( $index );
			$curT = $curTaint->getParamTaint( $index );
			if ( !$override ) {
				$newTaint->setParamTaint( $index, $getTaintToAdd( $curT, $baseT ) );
			}
			$maybeAddTaintError( $baseT, $curT, $index );
		}

		$baseOverall = $taint->getOverall();
		$curOverall = $curTaint->getOverall();
		if ( !$override ) {
			$newTaint->setOverall( $getTaintToAdd( $curOverall, $baseOverall ) );
		}
		$maybeAddTaintError( $baseOverall, $curOverall, 'overall' );

		// Note, it's important that we only use the real type here (e.g. from typehints) and NOT
		// the PHPDoc type, as it may be wrong.
		$mask = $this->getTaintMaskForType( $func->getRealReturnType() );
		$newTaint->map( function ( Taintedness $taint ) use ( $mask ) : void {
			$taint->keepOnly( $mask );
		} );

		$func->funcTaint = $newTaint;
	}

	/**
	 * Check whether $needle is subset of $haystack, regardless of the keys, and returns
	 * the starting index of the subset in the $haystack array. If the subset occurs multiple
	 * times, this will just find the first one.
	 *
	 * @param array[] $haystack
	 * @phan-param array<int,array{0:Taintedness,1:string}> $haystack
	 * @param array[] $needle
	 * @phan-param array<int,array{0:Taintedness,1:string}> $needle
	 * @return false|int False if not a subset, the starting index if it is.
	 * @note Use strict comparisons with the return value!
	 */
	private static function getArraySubsetIdx( array $haystack, array $needle ) {
		if ( !$needle ) {
			// For our needs, the empty array is not a subset of anything
			return false;
		}
		$curIdx = 0;
		$haystack = array_values( $haystack );
		$needleLength = count( $needle );
		foreach ( $haystack as $i => $el ) {
			if ( $el === $needle[ $curIdx ] ) {
				$curIdx++;
			} else {
				$curIdx = 0;
			}
			if ( $curIdx === $needleLength ) {
				return $i - ( $needleLength - 1 );
			}
		}
		return false;
	}

	/**
	 * Merge the caused-by lines of $new into $base. Note that this isn't a merge operation like
	 * array_merge. What this method does is:
	 * 1 - if $new is a subset of $base, return $base;
	 * 2 - update taintedness values in $base if the *lines* (not taint values) in $new
	 *   are a subset of the lines in $base;
	 * 3 - array_merge otherwise;
	 *
	 * Step 2 is very important, because otherwise, caused-by lines can grow exponentially if
	 * even a single taintedness value in $base changes.
	 *
	 * @param array[] $base
	 * @param array[] $new
	 * @return array[]
	 */
	public static function mergeCausedByLines( array $base, array $new ) : array {
		if ( self::getArraySubsetIdx( $base, $new ) !== false ) {
			return $base;
		}

		$subsIdx = self::getArraySubsetIdx( array_column( $base, 1 ), array_column( $new, 1 ) );
		if ( $subsIdx !== false ) {
			foreach ( $new as $i => $cur ) {
				$base[ $i + $subsIdx ][0]->add( $cur[0] );
			}
			return $base;
		}
		// HACK: Set a hard limit, or this may time out
		return array_slice( array_merge( $base, $new ), 0, 25 );
	}

	/**
	 * Get a copy of $func's taint, or null if not set.
	 *
	 * @param FunctionInterface $func
	 * @return FunctionTaintedness|null
	 */
	protected function getFuncTaint( FunctionInterface $func ) : ?FunctionTaintedness {
		return isset( $func->funcTaint )
			? clone $func->funcTaint
			: null;
	}

	/**
	 * Merge the info on original cause of taint to left variable
	 *
	 * If you have something like $left = $right, merge any information
	 * about what tainted $right into $left as $right's taint may now
	 * have tainted $left (Or may not if the assignment is in a branch
	 * or its not a local variable).
	 *
	 * @param TypedElementInterface $left (LHS-ish variable)
	 * @param TypedElementInterface|Node $right (RHS-ish variable)
	 * @param int $arg If $left is a Function, which arg
	 */
	protected function mergeTaintError( TypedElementInterface $left, $right, int $arg = -1 ) : void {
		assert( $arg === -1 || $left instanceof FunctionInterface );
		if ( $right instanceof Node ) {
			$phanObjs = $this->getPhanObjsForNode( $right, [ 'all' ] );
		} else {
			assert( $right instanceof TypedElementInterface );
			$phanObjs = [ $right ];
		}

		if ( $arg === -1 ) {
			if ( !property_exists( $left, 'taintedOriginalError' ) ) {
				$left->taintedOriginalError = [];
			}
			$newLeftError = $left->taintedOriginalError;
		} else {
			if ( !property_exists( $left, 'taintedOriginalErrorByArg' ) ) {
				$left->taintedOriginalErrorByArg = [];
			}
			$newLeftError = $left->taintedOriginalErrorByArg[$arg] ?? [];
		}

		foreach ( $phanObjs as $rightObj ) {
			// TODO: Possibly we would want to skip merging the errors,
			// if the merge did not result in any new taint being set.
			// However at this point, taint has already been merged so
			// we don't know if we should skip or not.
			// TODO: Does this make sense? If we are merging a function
			// to merge all its argument errors not just overall.
			$rightErrors = array_merge(
				property_exists( $rightObj, 'taintedOriginalError' ) ? [ $rightObj->taintedOriginalError ] : [],
				$rightObj->taintedOriginalErrorByArg ?? []
			);
			foreach ( $rightErrors as $rightError ) {
				if ( $newLeftError && self::getArraySubsetIdx( $rightError, $newLeftError ) !== false ) {
					$newLeftError = $rightError;
				} elseif ( $rightError && self::getArraySubsetIdx( $newLeftError, $rightError ) === false ) {
					$newLeftError = self::mergeCausedByLines( $newLeftError, $rightError );
				}
			}
		}

		if ( $arg === -1 ) {
			$left->taintedOriginalError = $newLeftError;
		} else {
			$left->taintedOriginalErrorByArg[$arg] = $newLeftError;
		}
	}

	/**
	 * Clears any previous error on the given element.
	 *
	 * @param TypedElementInterface $elem
	 */
	protected function clearTaintError( TypedElementInterface $elem ) : void {
		if ( property_exists( $elem, 'taintedOriginalError' ) ) {
			$elem->taintedOriginalError = [];
		}
	}

	/**
	 * Clears any taintedness links on this object
	 *
	 * @param TypedElementInterface $elem
	 */
	protected function clearTaintLinks( TypedElementInterface $elem ) : void {
		unset( $elem->taintedMethodLinks, $elem->taintedVarLinks );
	}

	/**
	 * Add the current context to taintedOriginalError book-keeping
	 *
	 * This allows us to show users what line caused an issue.
	 *
	 * @param Taintedness $taintedness
	 * @param TypedElementInterface $elem Where to put it
	 * @param int $arg [Optional] For functions, which argument
	 * @param string|Context|null $reason To override the caused by line
	 */
	protected function addTaintError(
		Taintedness $taintedness,
		TypedElementInterface $elem,
		int $arg = -1,
		$reason = null
	) : void {
		if ( !$taintedness->isExecTaint() && !$taintedness->isAllTaint() ) {
			// Don't add book-keeping if no actual taint was added.
			return;
		}

		assert( $arg === -1 || $elem instanceof FunctionInterface );

		if ( $arg === -1 ) {
			if ( !property_exists( $elem, 'taintedOriginalError' ) ) {
				$elem->taintedOriginalError = [];
			}
		} else {
			if ( !property_exists( $elem, 'taintedOriginalErrorByArg' ) ) {
				$elem->taintedOriginalErrorByArg = [];
			}
			if ( !isset( $elem->taintedOriginalErrorByArg[$arg] ) ) {
				$elem->taintedOriginalErrorByArg[$arg] = [];
			}
		}
		if ( !is_string( $reason ) ) {
			$newErrors = [ trim( $this->dbgInfo( $reason ?? $this->context ) ) ];
		} else {
			$newErrors = [ trim( $reason ) ];
		}
		if ( $this->overrideContext ) {
			$newErrors[] = trim( $this->dbgInfo( $this->overrideContext ) );
		}
		foreach ( $newErrors as $newError ) {
			if ( $arg === -1 ) {
				$newElement = [ clone $taintedness, $newError ];
				if ( self::getArraySubsetIdx( $elem->taintedOriginalError, [ $newElement ] ) === false ) {
					$elem->taintedOriginalError = self::mergeCausedByLines(
						$elem->taintedOriginalError,
						[ $newElement ]
					);
				}
			} else {
				$rawPart = $taintedness->withOnly( SecurityCheckPlugin::RAW_PARAM );
				$argErrTaint = $taintedness->asExecToYesTaint()->with( $rawPart );
				$newElement = [ $argErrTaint, $newError ];
				if ( self::getArraySubsetIdx( $elem->taintedOriginalErrorByArg[$arg], [ $newElement ] ) === false ) {
					$elem->taintedOriginalErrorByArg[$arg] = self::mergeCausedByLines(
						$elem->taintedOriginalErrorByArg[$arg],
						[ $newElement ]
					);
				}
			}
		}
	}

	/**
	 * @param TypedElementInterface $var
	 * @return Taintedness
	 */
	protected function getTaintednessReference( TypedElementInterface $var ) : Taintedness {
		if ( $var instanceof PassByReferenceVariable ) {
			throw new AssertionError( __METHOD__ . ' takes the element inside PassByRefs' );
		}
		return $var->taintednessRef ?? Taintedness::newSafe();
	}

	/**
	 * Given a PassByRef, recursively extract the argument it refers to.
	 *
	 * @param PassByReferenceVariable $obj
	 * @return TypedElementInterface
	 */
	protected function extractReferenceArgument(
		PassByReferenceVariable $obj
	) : TypedElementInterface {
		do {
			$obj = $obj->getElement();
		} while ( $obj instanceof PassByReferenceVariable );
		return $obj;
	}

	/**
	 * Whether the object is a reference argument of a hook.
	 *
	 * @param TypedElementInterface $obj
	 * @return bool
	 */
	protected function isHookRefArg( TypedElementInterface $obj ) : bool {
		return property_exists( $obj, 'isHookRefArg' );
	}

	/**
	 * @param TypedElementInterface $var
	 * @param Taintedness $taint
	 * @param bool $override
	 */
	protected function setRefTaintedness(
		TypedElementInterface $var,
		Taintedness $taint,
		bool $override
	) : void {
		if ( $var instanceof PassByReferenceVariable ) {
			throw new Error(
				__METHOD__ . ' not meant for PassByReferenceVariable objects, but for their element'
			);
		}

		if (
			$this->context->getScope() instanceof BranchScope ||
			$var instanceof Property ||
			$this->isHookRefArg( $var )
		) {
			$override = false;
		}
		if ( !property_exists( $var, 'taintednessRef' ) || $override ) {
			$var->taintednessRef = $taint;
		} else {
			// NOTE: Don't merge in-place here, same as doSetTaintedness
			$var->taintednessRef = $var->taintednessRef->with( $taint );
		}

		$this->addTaintError( $taint, $var );
	}

	/**
	 * TEMPORARY METHOD
	 * @param TypedElementInterface $variableObj
	 * @param Taintedness $taintedness
	 * @param bool $override
	 * @param bool $allowClearLHSData
	 * @param Taintedness|null $errorTaint
	 */
	protected function setTaintednessOld(
		TypedElementInterface $variableObj,
		Taintedness $taintedness,
		$override = true,
		bool $allowClearLHSData = false,
		Taintedness $errorTaint = null
	) : void {
		$this->setTaintedness( $variableObj, [], $taintedness, $override, $allowClearLHSData, $errorTaint );
	}

	/**
	 * Change the taintedness of a variable
	 *
	 * @param TypedElementInterface $variableObj The variable in question
	 * @param (Node|mixed)[] $resolvedOffsetsLhs List of possibly-resolved offsets at the LHS
	 * @param Taintedness $taintedness
	 * @param bool $override Override taintedness or just take max.
	 * @param bool $allowClearLHSData Whether we're allowed to clear taint error and links
	 *   from the LHS. This is only honored when the taint is being overridden.
	 * @param Taintedness|null $errorTaint The taintedness to use for adding the taint error. By default,
	 *   this is identical to $taintedness. This can be useful when the element is already tainted
	 *   (e.g. for assign ops like `.=`, so that `$tainted .= 'safe'` doesn't add a caused-by line),
	 *   but it should only be used when there's no actual taint being added (so e.g. don't use this
	 *   for `$tainted .= $anotherTainted`).
	 */
	protected function setTaintedness(
		TypedElementInterface $variableObj,
		array $resolvedOffsetsLhs,
		Taintedness $taintedness,
		$override = true,
		bool $allowClearLHSData = false,
		Taintedness $errorTaint = null
	) : void {
		// $this->debug( __METHOD__, "begin for \$" . $variableObj->getName()
		// . " <- $taintedness (override=$override) prev " . ( $variableObj->taintedness ?? 'unset' )
		// . ' Caller: ' . ( debug_backtrace()[1]['function'] ?? 'n/a' )
		// . ', ' . ( debug_backtrace()[2]['function'] ?? 'n/a' ) );

		$errorTaint = $errorTaint ?? $taintedness;

		if ( $variableObj instanceof FunctionInterface ) {
			// FIXME what about closures?
			throw new AssertionError( "Must use setFuncTaint for functions" );
		}

		if ( $variableObj instanceof PassByReferenceVariable ) {
			throw new AssertionError( 'Handle passbyrefs before calling this method' );
		}

		// $this->debug( __METHOD__, "\$" . $variableObj->getName() . " has outer scope - "
		// . get_class( $this->context->getScope() ) . "" );

		if ( $this->isGlobalVariableInLocalScope( $variableObj ) ) {
			$globalVar = $this->context->getScope()->getGlobalVariableByName( $variableObj->getName() );
			// Merge the taint on the "true" global object, too
			$this->doSetTaintedness( $globalVar, $resolvedOffsetsLhs, $taintedness, false, $errorTaint );
			$override = false;
		}
		if ( $this->isHookRefArg( $variableObj ) ) {
			// We do this in the general case as well. In doing so, we assume that a hook handler
			// is only used as a hook handler.
			$override = false;
		}
		if ( $resolvedOffsetsLhs ) {
			// Don't clear data if this is an array assignment (regardless of whether offsets were resolved)
			$allowClearLHSData = false;
		}

		if ( $override && $allowClearLHSData ) {
			// Clear any error and link before setting taintedness if we're overriding taint.
			// Checking for $override here already takes into account globals, props,
			// outer scope, and whatnot.
			$this->clearTaintError( $variableObj );
			$this->clearTaintLinks( $variableObj );
		}

		$this->doSetTaintedness( $variableObj, $resolvedOffsetsLhs, $taintedness, $override, $errorTaint );
	}

	/**
	 * Whether $var is a global variable in the *current* *local* scope.
	 * (More precisely, whether it was imported in this scope via the 'global' keyword)
	 *
	 * @param TypedElementInterface $var
	 * @return bool
	 */
	public function isGlobalVariableInLocalScope( TypedElementInterface $var ) : bool {
		return $var instanceof Variable
			&& property_exists( $this->context->getScope(), 'globalsInScope' )
			&& in_array( $var->getName(), $this->context->getScope()->globalsInScope, true );
	}

	/**
	 * Actually sets the taintedness on $variableObj. This should almost never be used.
	 *
	 * @see self::setTaintedness for param docs
	 *
	 * @param TypedElementInterface $variableObj
	 * @param (Node|mixed)[] $resolvedOffsetsLhs
	 * @param Taintedness $taintedness
	 * @param bool $override
	 * @param Taintedness $errorTaint
	 */
	private function doSetTaintedness(
		TypedElementInterface $variableObj,
		array $resolvedOffsetsLhs,
		Taintedness $taintedness,
		bool $override,
		Taintedness $errorTaint
	) : void {
		// NOTE: Do NOT merge in place here, as that would change the taintedness for all variable
		// objects of which $variableObj is a clone!
		/** @var Taintedness $curTaint */
		$curTaint = property_exists( $variableObj, 'taintedness' )
			? clone $variableObj->taintedness
			: Taintedness::newSafe();
		'@phan-var Taintedness $curTaint';

		if ( $resolvedOffsetsLhs ) {
			$offsetOverride = $override && $this->wereAllKeysResolved( $resolvedOffsetsLhs );
			$keysTaint = $this->getKeysTaintednessList( $resolvedOffsetsLhs );
			$curTaint->setTaintednessAtOffsetList( $resolvedOffsetsLhs, $keysTaint, $taintedness, $offsetOverride );
			foreach ( $keysTaint as $keyTaint ) {
				$errorTaint->addKeysTaintedness( $keyTaint->get() );
			}
		} else {
			$curTaint = $override ? $taintedness : $curTaint->asMergedWith( $taintedness );
		}
		$variableObj->taintedness = $curTaint;
		// $this->debug( __METHOD__, $variableObj->getName() . " now has taint " .
		// ( $variableObj->taintedness ?? 'unset' ) );
		$this->addTaintError( $errorTaint, $variableObj );
	}

	/**
	 * Given a list of resolved offsets, return the corresponding list of taintedness values
	 * @param (Node|mixed)[] $offsets
	 * @return Taintedness[]
	 */
	protected function getKeysTaintednessList( array $offsets ) : array {
		$ret = [];
		foreach ( $offsets as $offset ) {
			$ret[] = $this->getTaintedness( $offset );
		}
		return $ret;
	}

	/**
	 * Check whether we could *really* resolve (100% accuracy) all keys in $keys
	 *
	 * @param array $keys
	 * @phan-param list<Node|mixed> $keys
	 * @return bool
	 */
	private function wereAllKeysResolved( array $keys ) : bool {
		foreach ( $keys as $key ) {
			if ( $key === null || $key instanceof Node ) {
				// Null is for `$arr[] = 'foo'`. Phan doesn't infer real types here, nor will we.
				return false;
			}
		}
		return true;
	}

	/**
	 * Get the taint of a PHP builtin function/method
	 *
	 * Assume that anything not-hardcoded just passes its
	 * arguments into its return value
	 *
	 * @param FunctionInterface $func A builtin Function/Method
	 * @return FunctionTaintedness
	 */
	private function getTaintOfFunctionPHP( FunctionInterface $func ) : FunctionTaintedness {
		$taint = $this->getBuiltinFuncTaint( $func->getFQSEN() );
		if ( $taint !== null ) {
			return $taint;
		}

		// Assume that anything really dangerous we've already
		// hardcoded. So just preserve taint
		$taintFromReturnType = $this->getTaintByType( $func->getUnionType() );
		if ( $taintFromReturnType->isSafe() ) {
			return new FunctionTaintedness( Taintedness::newSafe() );
		}
		return new FunctionTaintedness( new Taintedness( SecurityCheckPlugin::PRESERVE_TAINT ) );
	}

	/**
	 * Given a func, get the defining func or null
	 *
	 * @param FunctionInterface $func
	 * @return null|FunctionInterface
	 */
	private function getDefiningFunc( FunctionInterface $func ) : ?FunctionInterface {
		if ( $func instanceof Method && $func->hasDefiningFQSEN() ) {
			// Our function has a parent, and potentially interface and traits.
			if ( (string)$func->getDefiningFQSEN() !== (string)$func->getFQSEN() ) {
				return $this->code_base->getMethodByFQSEN(
					$func->getDefiningFQSEN()
				);
			}
		}
		return null;
	}

	/**
	 * Get a list of places to look for function taint info
	 *
	 * @todo How to handle multiple function definitions (phan "alternates")
	 * @param FunctionInterface $func
	 * @return FunctionInterface[]
	 */
	private function getPossibleFuncDefinitions( FunctionInterface $func ) : array {
		$funcsToTry = [ $func ];

		// If we don't have a defining func, stay with the same func.
		// definingFunc is used later on during fallback processing.
		$definingFunc = $this->getDefiningFunc( $func );
		if ( $definingFunc ) {
			$funcsToTry[] = $definingFunc;
		}
		if ( $func instanceof Method ) {
			try {
				$class = $func->getClass( $this->code_base );
			} catch ( CodeBaseException $e ) {
				$this->debug( __METHOD__, "Class not found for func $func: " . $this->getDebugInfo( $e ) );
				return $funcsToTry;
			}
			$nonParents = $class->getNonParentAncestorFQSENList();

			foreach ( $nonParents as $nonParentFQSEN ) {
				if ( $this->code_base->hasClassWithFQSEN( $nonParentFQSEN ) ) {
					$nonParent = $this->code_base->getClassByFQSEN( $nonParentFQSEN );
					if ( $nonParent->hasMethodWithName( $this->code_base, $func->getName() ) ) {
						$funcsToTry[] = $nonParent->getMethodByName( $this->code_base, $func->getName() );
					}
				}
			}
		}
		return $funcsToTry;
	}

	/**
	 * This is also for methods and other function like things
	 *
	 * @param FunctionInterface $func What function/method to look up
	 * @param bool $clearOverride Include SecurityCheckPlugin::NO_OVERRIDE
	 * @return FunctionTaintedness
	 */
	protected function getTaintOfFunction( FunctionInterface $func, $clearOverride = true ) : FunctionTaintedness {
		// Fast case, either a builtin to php function or we already
		// know taint:
		if ( $func->isPHPInternal() ) {
			return $this->getTaintOfFunctionPHP( $func )->withMaybeClearNoOverride( $clearOverride );
		}

		$funcTaint = $this->getFuncTaint( $func );
		if ( $funcTaint !== null ) {
			return $funcTaint->withMaybeClearNoOverride( $clearOverride );
		}

		// Gather up

		$funcsToTry = $this->getPossibleFuncDefinitions( $func );
		foreach ( $funcsToTry as $trialFunc ) {
			$trialFuncName = $trialFunc->getFQSEN();
			$taint = $this->getDocBlockTaintOfFunc( $trialFunc );
			if ( $taint !== null ) {
				$this->setFuncTaint( $func, $taint, true, $trialFunc->getContext() );

				return $taint->withMaybeClearNoOverride( $clearOverride );
			}
			$taint = $this->getBuiltinFuncTaint( $trialFuncName );
			if ( $taint !== null ) {
				$this->setFuncTaint( $func, $taint, true, "Builtin-$trialFuncName" );
				return $taint->withMaybeClearNoOverride( $clearOverride );
			}
		}

		$definingFunc = $this->getDefiningFunc( $func ) ?: $func;
		// Ensure we don't indef loop.
		if (
			!$definingFunc->isPHPInternal() &&
			( !$this->context->isInFunctionLikeScope() ||
			$definingFunc->getFQSEN() !== $this->context->getFunctionLikeFQSEN() )
		) {
			$this->debug( __METHOD__, 'no taint info for func ' . $func->getName() );
			if ( $this->getFuncTaint( $definingFunc ) === null ) {
				// Optim: don't reanalyze if we already have taint data. This might rarely hide
				// some issues, see T203651#6046483.
				try {
					$this->analyzeFunc( $definingFunc );
				} catch ( Exception $e ) {
					$this->debug( __METHOD__, "FIXME: " . $this->getDebugInfo( $e ) );
				}
				$this->debug( __METHOD__, 'updated taint info for ' . $definingFunc->getName() );
			}

			$definingFuncTaint = $this->getFuncTaint( $definingFunc );
			// var_dump( $definingFuncTaint ?? "NO INFO" );
			if ( $definingFuncTaint !== null ) {
				return $definingFuncTaint->withMaybeClearNoOverride( $clearOverride );
			}
		}
		// TODO: Maybe look at __toString() if we are at __construct().
		// FIXME this could probably use a second look.

		// If we haven't seen this function before, first of all
		// check the return type. If it (e.g.) returns just an int,
		// its probably safe.
		$taint = new FunctionTaintedness( $this->getTaintByType( $func->getUnionType() ) );
		$this->setFuncTaint( $func, $taint, true );
		return $taint->withMaybeClearNoOverride( $clearOverride );
	}

	/**
	 * Analyze a function. This is very similar to Analyzable::analyze, but avoids several checks
	 * used by phan for performance. Phan doesn't know about taintedness, so it may decide to skip
	 * a re-analysis which we need.
	 * @todo This is a bit hacky.
	 * @todo We should implement our own perf checks, e.g. if the method as already called with
	 * the same taintedness, taint links, etc. for all params.
	 * @see \Phan\Analysis\Analyzable::analyze()
	 *
	 * @param FunctionInterface $func
	 */
	public function analyzeFunc( FunctionInterface $func ) : void {
		static $depth = 0;
		$node = $func->getNode();
		if ( !$node ) {
			return;
		}
		// @todo Tune the max depth. Raw benchmarking shows very little difference between e.g.
		// 5 and 10. However, while with higher values we can detect more issues and avoid more
		// false positives, it becomes harder to tell where an issue is coming from.
		// Thus, this value should be increased only when we'll have better error reporting.
		if ( $depth > 5 ) {
			$this->debug( __METHOD__, 'WARNING: aborting analysis earlier due to max depth' );
			return;
		}
		if ( $node->kind === \ast\AST_CLOSURE && isset( $node->children['uses'] ) ) {
			return;
		}
		$depth++;

		// Like Analyzable::analyze, clone the context to avoid overriding anything
		$context = clone $func->getContext();
		// @phan-suppress-next-line PhanUndeclaredMethod All implementations have it
		if ( $func->getRecursionDepth() !== 0 ) {
			// Add the arguments types to the internal scope of the function, see
			// https://github.com/phan/phan/issues/3848
			foreach ( $func->getParameterList() as $parameter ) {
				$context->addScopeVariable( $parameter->cloneAsNonVariadic() );
			}
		}
		try {
			( new BlockAnalysisVisitor( $this->code_base, $context ) )(
				$node
			);
		} finally {
			$depth--;
		}
	}

	/**
	 * Obtain taint information from a docblock comment.
	 *
	 * @param FunctionInterface $func The function to check
	 * @return FunctionTaintedness|null null for no info
	 */
	protected function getDocBlockTaintOfFunc( FunctionInterface $func ) : ?FunctionTaintedness {
		// Note that we're not using the hashed docblock for caching, because the same docblock
		// may have different meanings in different contexts. E.g. @return self
		$fqsen = (string)$func->getFQSEN();
		if ( isset( SecurityCheckPlugin::$docblockCache[ $fqsen ] ) ) {
			return clone SecurityCheckPlugin::$docblockCache[ $fqsen ];
		}
		// @phan-suppress-next-line PhanUndeclaredMethod All FunctionInterface implementations have it
		if ( !$func->hasNode() ) {
			// No docblock available
			return null;
		}
		// Assume that if some of the taint is specified, then
		// the person would specify all the dangerous taints, so
		// don't set the unknown flag if not taint annotation on
		// @return.
		$funcTaint = new FunctionTaintedness( Taintedness::newSafe() );
		$docBlock = $func->getDocComment();
		if ( $docBlock === null ) {
			return null;
		}
		$lines = explode( "\n", $docBlock );
		$validTaintEncountered = false;

		foreach ( $lines as $line ) {
			$m = [];
			if ( preg_match( SecurityCheckPlugin::PARAM_ANNOTATION_REGEX, $line, $m ) ) {
				$paramNumber = $this->getParamNumberGivenName( $func, $m['paramname'] );
				// TODO: Should we check the real signature, rather than relying on the annotation?
				// Probably yes, as currently we're 100% trusting the annotation, but it might be wrong.
				$isVariadic = $m['variadic'] !== '';
				if ( $paramNumber === null ) {
					continue;
				}
				$taint = SecurityCheckPlugin::parseTaintLine( $m['taint'] );
				if ( $taint !== null ) {
					$taint->add( $isVariadic ? SecurityCheckPlugin::VARIADIC_PARAM : SecurityCheckPlugin::NO_TAINT );
					$funcTaint->setParamTaint( $paramNumber, $taint );
					$validTaintEncountered = true;
					if ( $taint->has( SecurityCheckPlugin::ESCAPES_HTML, true ) ) {
						// Special case to auto-set anything that escapes html to detect double escaping.
						$funcTaint->setOverall( $funcTaint->getOverall()->with( SecurityCheckPlugin::ESCAPED_TAINT ) );
					}
				} else {
					$this->debug( __METHOD__, "Could not " .
						"understand taint line '" . $m['taint'] . "'" );
				}
			} elseif ( strpos( $line, '@return-taint' ) !== false ) {
				$taintLine = substr(
					$line,
					strpos( $line, '@return-taint' ) + strlen( '@return-taint' ) + 1
				);
				$taint = SecurityCheckPlugin::parseTaintLine( $taintLine );
				if ( $taint !== null ) {
					$funcTaint->setOverall( $taint );
					$validTaintEncountered = true;
				} else {
					$this->debug( __METHOD__, "Could not " .
						"understand return taint '$taintLine'" );
				}
			}
		}

		SecurityCheckPlugin::$docblockCache[ $fqsen ] = $validTaintEncountered ? clone $funcTaint : null;
		return SecurityCheckPlugin::$docblockCache[ $fqsen ];
	}

	/**
	 * @param FunctionInterface $func
	 * @param string $name The name of parameter, no $ or & prefixed
	 * @return null|int null on no such parameter
	 */
	private function getParamNumberGivenName( FunctionInterface $func, string $name ) : ?int {
		$parameters = $func->getParameterList();
		foreach ( $parameters as $i => $param ) {
			if ( $name === $param->getName() ) {
				return $i;
			}
		}
		$this->debug( __METHOD__, $func->getName() . " does not have param $name" );
		return null;
	}

	/**
	 * Given a type, determine what type of taint
	 *
	 * e.g. Integers are probably untainted since its hard to do evil
	 * with them, but mark strings as unknown since we don't know.
	 *
	 * Only use as a fallback
	 * @param UnionType $types The types
	 * @return Taintedness
	 */
	protected function getTaintByType( UnionType $types ) : Taintedness {
		$typelist = $types->getTypeSet();
		if ( count( $typelist ) === 0 ) {
			// $this->debug( __METHOD__, "Setting type unknown due to no type info." );
			return Taintedness::newUnknown();
		}

		$taint = Taintedness::newSafe();
		foreach ( $typelist as $type ) {
			if ( $type instanceof LiteralTypeInterface ) {
				// We're going to assume that literals aren't tainted...
				continue;
			}
			switch ( $type->getName() ) {
			case 'int':
			case 'non-zero-int':
			case 'float':
			case 'bool':
			case 'false':
			case 'true':
			case 'null':
			case 'void':
			case 'class-string':
			case 'callable-string':
			case 'callable-object':
			case 'callable-array':
				$taint->add( SecurityCheckPlugin::NO_TAINT );
				break;
			case 'string':
			case 'non-empty-string':
			case 'Closure':
			case 'callable':
			case 'array':
			case 'iterable':
			case 'object':
			case 'resource':
			case 'mixed':
			case 'non-empty-mixed':
				// $this->debug( __METHOD__, "Taint set unknown due to type '$type'." );
				$taint->add( SecurityCheckPlugin::UNKNOWN_TAINT );
				break;
			default:
				assert( $type instanceof Type );
				if ( $type->hasTemplateTypeRecursive() ) {
					// TODO Can we do better for template types?
					$taint->add( SecurityCheckPlugin::UNKNOWN_TAINT );
					break;
				}

				if ( !$type->isObjectWithKnownFQSEN() ) {
					// Likely some phan-specific types not included above
					$this->debug( __METHOD__, " $type not a class?" );
					$taint->add( SecurityCheckPlugin::UNKNOWN_TAINT );
					break;
				}

				// This means specific class, so look up __toString()
				$toStringFQSEN = FullyQualifiedMethodName::fromStringInContext(
					$type->asFQSEN() . '::__toString',
					$this->context
				);
				if ( !$this->code_base->hasMethodWithFQSEN( $toStringFQSEN ) ) {
					// This is common in a void context.
					// e.g. code like $this->foo() will reach this
					// check.
					$taint->add( SecurityCheckPlugin::UNKNOWN_TAINT );
					break;
				}
				$toString = $this->code_base->getMethodByFQSEN( $toStringFQSEN );
				$taint->add( $this->handleMethodCall( $toString, $toStringFQSEN, [] ) );
			}
		}
		return $taint;
	}

	/**
	 * Get what taint types are allowed on a typed element (i.e. use its type to rule out
	 * impossible taint types).
	 *
	 * @param TypedElementInterface $var
	 * @return Taintedness
	 */
	protected function getTaintMaskForTypedElement( TypedElementInterface $var ) : Taintedness {
		if (
			$var instanceof Property ||
			$this->isGlobalVariableInLocalScope( $var ) ||
			$this->context->isInGlobalScope()
		) {
			// TODO Improve handling of globals and props
			return Taintedness::newAll();
		}
		// Note, we must use the real union type because:
		// 1 - The non-real type might be wrong
		// 2 - The non-real type might be incomplete (e.g. when analysing a func without docblock
		// we still don't know all the possible types of the params).
		return $this->getTaintMaskForType( $var->getUnionType()->getRealUnionType() );
	}

	/**
	 * Get what taint types are allowed on an element with the given type.
	 *
	 * @param UnionType $type
	 * @return Taintedness
	 */
	protected function getTaintMaskForType( UnionType $type ) : Taintedness {
		$typeTaint = $this->getTaintByType( $type );

		if ( $typeTaint->has( SecurityCheckPlugin::UNKNOWN_TAINT ) ) {
			return Taintedness::newAll();
		}
		return $typeTaint;
	}

	/**
	 * Get what taint the element could have in the future. For instance, a func parameter may initially
	 * have no taint, but it may become tainted depending on the argument.
	 * @todo Ensure this won't miss any case (aside from when phan infers a wrong real type)
	 *
	 * @param TypedElementInterface $el
	 * @return Taintedness
	 */
	protected function getPossibleFutureTaintOfElement( TypedElementInterface $el ) : Taintedness {
		return $this->getTaintMaskForTypedElement( $el );
	}

	/**
	 * Get the built in taint of a function/method
	 *
	 * This is used for when people special case if a function is tainted.
	 *
	 * @param FullyQualifiedFunctionLikeName $fqsen Function to check
	 * @return FunctionTaintedness|null Null if no info
	 */
	protected function getBuiltinFuncTaint( FullyQualifiedFunctionLikeName $fqsen ) : ?FunctionTaintedness {
		return SecurityCheckPlugin::$pluginInstance->getBuiltinFuncTaint( $fqsen );
	}

	/**
	 * Get name of current method (for debugging purposes)
	 *
	 * @return string Name of method or "[no method]"
	 */
	protected function getCurrentMethod() : string {
		return $this->context->isInFunctionLikeScope() ?
			(string)$this->context->getFunctionLikeFQSEN() : '[no method]';
	}

	/**
	 * Get the taintedness of something from the AST tree.
	 *
	 * @warning This does not take into account preexisting taint
	 *  unless you provide it with a Phan object (Not an AST node).
	 *
	 * FIXME maybe it should try and turn into phan object.
	 * @param mixed $expr An expression from the AST tree.
	 * @return Taintedness
	 */
	protected function getTaintedness( $expr ) : Taintedness {
		$type = gettype( $expr );
		switch ( $type ) {
		case "string":
		case "boolean":
		case "integer":
		case "double":
		case "NULL":
			// simple literal
			return Taintedness::newSafe();
		case "object":
			if ( $expr instanceof Node ) {
				return $this->getTaintednessNode( $expr );
			}
			// fallthrough
		case "resource":
		case "unknown type":
		case "array":
		default:
			throw new AssertionError( __METHOD__ . " called with invalid type $type" );
		}
	}

	/**
	 * Give an AST node, find its taint. This always returns a copy.
	 *
	 * @param Node $node
	 * @return Taintedness
	 */
	protected function getTaintednessNode( Node $node ) : Taintedness {
		// Debug::printNode( $node );
		// Make sure to update the line number, or the same issue may be reported
		// more than once on different lines (see test 'multilineissue').
		$oldLine = $this->context->getLineNumberStart();
		$this->context->setLineNumberStart( $node->lineno );
		$ret = null;

		try {
			( new TaintednessVisitor( $this->code_base, $this->context, $ret ) )(
				$node
			);
			return clone $ret;
		} finally {
			$this->context->setLineNumberStart( $oldLine );
		}
	}

	/**
	 * Given a phan object (not method/function) find its taint. This always returns a copy
	 * for existing objects.
	 *
	 * @param TypedElementInterface $variableObj
	 * @return Taintedness
	 */
	protected function getTaintednessPhanObj( TypedElementInterface $variableObj ) : Taintedness {
		if ( $variableObj instanceof FunctionInterface ) {
			throw new AssertionError( "This method cannot be used with methods" );
		}
		if ( $variableObj instanceof PassByReferenceVariable ) {
			throw new AssertionError( 'Handle PassByRefs before calling this method' );
		}
		if ( property_exists( $variableObj, 'taintedness' ) ) {
			$mask = $this->getTaintMaskForTypedElement( $variableObj );
			$taintedness = $variableObj->taintedness->withOnly( $mask );
			// echo "$varName has taintedness $taintedness due to last time\n";
		} else {
			$type = $variableObj->getUnionType();
			$taintedness = $this->getTaintByType( $type );
			// $this->debug( " \$" . $variableObj->getName() . " first sight."
			// . " taintedness set to $taintedness due to type $type\n";
		}
		return $taintedness;
	}

	/**
	 * Shortcut to resolve array offsets, with a sanity check
	 *
	 * @param Node|mixed $rawOffset
	 * @return Node|mixed
	 */
	protected function resolveOffset( $rawOffset ) {
		// Null usually means an "implicit" dim like in `$a[] = $b`. Trying to resolve
		// it will likely create errors (anything added to implicit indexes is stored together).
		assert( $rawOffset !== null );
		return $this->resolveValue( $rawOffset );
	}

	/**
	 * Shortcut to try and turn an AST element (Node or already literal) into an equivalent PHP
	 * scalar value.
	 *
	 * @param Node|mixed $value A Node or a scalar value from the AST
	 * @return Node|mixed An equivalent scalar PHP value, or $value if it cannot be resolved
	 */
	protected function resolveValue( $value ) {
		return $value instanceof Node
			? $this->getCtxN( $value )->getEquivalentPHPScalarValue()
			: $value;
	}

	/**
	 * Quick wrapper to get the ContextNode for a node
	 *
	 * @param Node $node
	 * @return ContextNode
	 */
	protected function getCtxN( Node $node ) : ContextNode {
		return new ContextNode(
			$this->code_base,
			$this->context,
			$node
		);
	}

	/**
	 * Given a node, return the Phan variable objects that
	 * correspond to that node. Note, this will ignore
	 * things like method calls (for now at least).
	 *
	 * TODO: Maybe this should be a visitor class instead(?)
	 *
	 * This method is a little confused, because sometimes we only
	 * want the objects that materially contribute to taint, and
	 * other times we want all the objects.
	 * e.g. Should foo( $bar ) return the $bar variable object?
	 *  What about the foo function object?
	 *
	 * @param Node $node AST node in question
	 * @param string[] $options Change type of objects returned
	 *    * 'all' -> Given a method call, include the method and its args
	 *    * 'return' -> Given a method call, include objects in its return.
	 * @return TypedElementInterface[] Array of various phan objects corresponding to $node
	 */
	protected function getPhanObjsForNode( Node $node, $options = [] ) : array {
		$cn = $this->getCtxN( $node );

		switch ( $node->kind ) {
			case \ast\AST_PROP:
			case \ast\AST_STATIC_PROP:
				try {
					return [ $cn->getProperty( $node->kind === \ast\AST_STATIC_PROP ) ];
				} catch ( NodeException | IssueException | UnanalyzableException $e ) {
					// There won't be an expr for static prop.
					if ( isset( $node->children['expr'] ) && $node->children['expr'] instanceof Node ) {
						$cnClass = $this->getCtxN( $node->children['expr'] );
						if ( $cnClass->getVariableName() === 'row' ) {
							// Its probably a db row, so ignore.
							// FIXME, we should handle the
							// db row situation much better.
							return [];
						}
					}

					$this->debug( __METHOD__, "Cannot determine " .
						"property [3] (Maybe don't know what class) - " .
						$this->getDebugInfo( $e )
					);
					return [];
				}
			case \ast\AST_VAR:
			case \ast\AST_CLOSURE_VAR:
				if ( Variable::isHardcodedGlobalVariableWithName( $cn->getVariableName() ) ) {
					return [];
				} else {
					try {
						return [ $cn->getVariable() ];
					} catch ( NodeException | IssueException $e ) {
						$this->debug( __METHOD__, "variable not in scope?? " . $this->getDebugInfo( $e ) );
						return [];
					}
					// return [];
				}
			case \ast\AST_ENCAPS_LIST:
			case \ast\AST_ARRAY:
				$results = [];
				foreach ( $node->children as $child ) {
					if ( !is_object( $child ) ) {
						continue;
					}
					$results = array_merge( $this->getPhanObjsForNode( $child, $options ), $results );
				}
				return $results;
			case \ast\AST_ARRAY_ELEM:
				$results = [];
				if ( is_object( $node->children['key'] ) ) {
					$results = array_merge(
						$this->getPhanObjsForNode( $node->children['key'], $options ),
						$results
					);
				}
				if ( is_object( $node->children['value'] ) ) {
					$results = array_merge(
						$this->getPhanObjsForNode( $node->children['value'], $options ),
						$results
					);
				}
				return $results;
			case \ast\AST_CAST:
				// Future todo might be to ignore casts to ints, since
				// such things should be safe. Unclear if that makes
				// sense in all circumstances.
				if ( $node->children['expr'] instanceof Node ) {
					return $this->getPhanObjsForNode( $node->children['expr'], $options );
				}
				return [];
			case \ast\AST_DIM:
				if ( $node->children['expr'] instanceof Node ) {
					// For now just consider the outermost array.
					// FIXME. doesn't handle tainted array keys!
					return $this->getPhanObjsForNode( $node->children['expr'], $options );
				}
				return [];
			case \ast\AST_UNARY_OP:
				$var = $node->children['expr'];
				return $var instanceof Node ? $this->getPhanObjsForNode( $var, $options ) : [];
			case \ast\AST_BINARY_OP:
				$left = $node->children['left'];
				$right = $node->children['right'];
				$leftObj = $left instanceof Node ? $this->getPhanObjsForNode( $left, $options ) : [];
				$rightObj = $right instanceof Node ? $this->getPhanObjsForNode( $right, $options ) : [];
				return array_merge( $leftObj, $rightObj );
			case \ast\AST_CONDITIONAL:
				$t = $node->children['true'];
				$f = $node->children['false'];
				$tObj = $t instanceof Node ? $this->getPhanObjsForNode( $t, $options ) : [];
				$fObj = $f instanceof Node ? $this->getPhanObjsForNode( $f, $options ) : [];
				return array_merge( $tObj, $fObj );
			case \ast\AST_CONST:
			case \ast\AST_CLASS_CONST:
			case \ast\AST_CLASS_NAME:
			case \ast\AST_MAGIC_CONST:
			case \ast\AST_ISSET:
			case \ast\AST_NEW:
			// For now we don't do methods, only variables
			// Also don't do args to function calls.
			// Unclear if this makes sense.
				return [];
			case \ast\AST_CALL:
			case \ast\AST_STATIC_CALL:
			case \ast\AST_METHOD_CALL:
				if ( !array_intersect( $options, [ 'all', 'return' ] ) ) {
					return [];
				}

				$ctxNode = $this->getCtxN( $node );
				// @todo Future todo might be to still return arguments when catching an exception.
				if ( $node->kind === \ast\AST_CALL ) {
					if ( $node->children['expr']->kind !== \ast\AST_NAME ) {
						return [];
					}
					try {
						$func = $ctxNode->getFunction( $node->children['expr']->children['name'] );
					} catch ( IssueException | FQSENException $e ) {
						$this->debug( __METHOD__, "FIXME func not found: " . $this->getDebugInfo( $e ) );
						return [];
					}
				} else {
					$methodName = $node->children['method'];
					try {
						$func = $ctxNode->getMethod( $methodName, $node->kind === \ast\AST_STATIC_CALL );
					} catch ( NodeException | CodeBaseException | IssueException $e ) {
						$this->debug( __METHOD__, "FIXME method not found: " . $this->getDebugInfo( $e ) );
						return [];
					}
				}
				if ( in_array( 'return', $options ) ) {
					// intentionally resetting options to []
					// here to ensure we don't recurse beyond
					// a depth of 1.
					try {
						return $this->getReturnObjsOfFunc( $func );
					} catch ( Exception $e ) {
						$this->debug( __METHOD__, "FIXME: " . $this->getDebugInfo( $e ) );
						return [];
					}
				}
				$args = $node->children['args']->children;
				$pObjs = [ $func ];
				foreach ( $args as $arg ) {
					if ( !( $arg instanceof Node ) ) {
						continue;
					}
					$pObjs = array_merge(
						$pObjs,
						$this->getPhanObjsForNode( $arg, $options )
					);
				}
				return $pObjs;
			case \ast\AST_PRE_INC:
			case \ast\AST_PRE_DEC:
			case \ast\AST_POST_INC:
			case \ast\AST_POST_DEC:
				$children = $node->children;
				assert( count( $children ) === 1 );
				return $this->getPhanObjsForNode( reset( $children ) );
			default:
				// Debug::printNode( $node );
				// This should really be a visitor that recurses into
				// things.
				$this->debug( __METHOD__, "FIXME unhandled case"
					. Debug::nodeName( $node ) . "\n"
				);
				return [];
		}
	}

	/**
	 * Extract some useful debug data from an exception
	 * @param Exception $e
	 * @return string
	 */
	protected function getDebugInfo( Exception $e ) : string {
		return $e instanceof IssueException
			? $e->getIssueInstance()->__toString()
			: ( get_class( $e ) . " {$e->getMessage()}" );
	}

	/**
	 * Whether a variable can be considered a superglobal. Phan doesn't consider $argv and $argc
	 * as such, but for our use case, they should be.
	 * @param string $varName
	 * @return bool
	 */
	protected function isSuperGlobal( $varName ) : bool {
		return Variable::isSuperglobalVariableWithName( $varName ) ||
			$varName === 'argv' || $varName === 'argc';
	}

	/**
	 * Get the current filename and line.
	 *
	 * @param Context|null $context Override the context to make debug info for
	 * @return string path/to/file +linenumber
	 */
	protected function dbgInfo( Context $context = null ) : string {
		$ctx = $context ?: $this->context;
		// Using a + instead of : so that I can just copy and paste
		// into a vim command line.
		return ' ' . $ctx->getFile() . ' +' . $ctx->getLineNumberStart();
	}

	/**
	 * Link together a Method and its parameters
	 *
	 * The idea being if the method gets called with something evil
	 * later, we can traceback anything it might affect
	 *
	 * @param Variable $param The variable object for the parameter. This can also be
	 *  instance of Parameter (subclass of Variable).
	 * @param FunctionInterface $func The function/method in question
	 * @param int $i Which argument number is $param
	 */
	protected function linkParamAndFunc( Variable $param, FunctionInterface $func, int $i ) : void {
		// $this->debug( __METHOD__, "Linking '$param' to '$func' arg $i" );

		if ( !property_exists( $func, 'taintedVarLinks' ) ) {
			$func->taintedVarLinks = [];
		}
		if ( !isset( $func->taintedVarLinks[$i] ) ) {
			$func->taintedVarLinks[$i] = new Set;
		}
		if ( !property_exists( $param, 'taintedMethodLinks' ) ) {
			// This is a map of FunctionInterface -> int[]
			$param->taintedMethodLinks = new Set;
		}

		$func->taintedVarLinks[$i]->attach( $param );
		if ( $param->taintedMethodLinks->contains( $func ) ) {
			$data = $param->taintedMethodLinks[$func];
			$data[$i] = true;
			$param->taintedMethodLinks[$func] = $data;
		} else {
			$param->taintedMethodLinks[$func] = [ $i => true ];
		}
	}

	/**
	 * Given a LHS and RHS make all the methods that can set RHS also for LHS
	 *
	 * Given 2 variables (e.g. $lhs = $rhs ), see to it that any function/method
	 * which we marked as being able to set the value of rhs, is also marked
	 * as being able to set the value of lhs. We use this information to figure
	 * out what method parameter is causing the return statement to be tainted.
	 *
	 * @warning Be careful calling this function if lhs already has taint
	 *  or rhs side is a compound statement. This could result in misattribution
	 *  of where the taint is coming from.
	 *
	 * This also merges the information on what line caused the taint.
	 *
	 * @param TypedElementInterface $lhs Source of method list
	 * @param TypedElementInterface|Node $rhs Destination of merged method list
	 */
	protected function mergeTaintDependencies( TypedElementInterface $lhs, $rhs ) : void {
		if ( $rhs instanceof Node ) {
			// Recurse.
			$phanObjs = $this->getPhanObjsForNode( $rhs );
			foreach ( $phanObjs as $phanObj ) {
				if ( $phanObj instanceof PassByReferenceVariable ) {
					$phanObj = $this->extractReferenceArgument( $phanObj );
				}
				$this->mergeTaintDependencies( $lhs, $phanObj );
			}
			return;
		}
		assert( $rhs instanceof TypedElementInterface );

		if ( !property_exists( $rhs, 'taintedMethodLinks' ) ) {
			// $this->debug( __METHOD__, "FIXME no back links on preserved taint" );
			return;
		}

		if ( !property_exists( $lhs, 'taintedMethodLinks' ) ) {
			$lhs->taintedMethodLinks = new Set;
		}

		// So if we have $a = $b;
		// First we find out all the methods that can set $b
		// Then we add $a to the list of variables that those methods can set.
		// Last we add these methods to $a's list of all methods that can set it.
		foreach ( $rhs->taintedMethodLinks as $method ) {
			$paramInfo = $rhs->taintedMethodLinks[$method];
			foreach ( $paramInfo as $index => $_ ) {
				assert( property_exists( $method, 'taintedVarLinks' ) );
				assert( isset( $method->taintedVarLinks[$index] ) );
				assert( $method->taintedVarLinks[$index] instanceof Set );
				// $this->debug( __METHOD__, "During assignment, we link $lhs to $method($index)" );
				$method->taintedVarLinks[$index]->attach( $lhs );
			}
			if ( isset( $lhs->taintedMethodLinks[$method] ) ) {
				$lhs->taintedMethodLinks[$method] += $paramInfo;
			} else {
				$lhs->taintedMethodLinks[ $method ] = $paramInfo;
			}
		}
	}

	/**
	 * Mark any function setting a specific variable as EXEC taint
	 *
	 * If you do something like echo $this->foo;
	 * This method is called to make all things that set $this->foo
	 * as TAINT_EXEC.
	 *
	 * @note This might have annoying false positives with widely used properties
	 * that are used with different levels of escaping, which is not a good idea anyway.
	 *
	 * @param TypedElementInterface $var The variable in question
	 * @param Taintedness $taint What taint to mark them as.
	 * @param Node|TypedElementInterface|null $triggeringElm To propagate caused-by lines
	 */
	protected function markAllDependentMethodsExec(
		TypedElementInterface $var,
		Taintedness $taint,
		$triggeringElm = null
	) : void {
		// Ensure we only set exec bits, not normal taint bits.
		$taint = $taint->withOnly( SecurityCheckPlugin::BACKPROP_TAINTS );

		if ( $var instanceof PassByReferenceVariable ) {
			$var = $this->extractReferenceArgument( $var );
		}
		if (
			$taint->isSafe() ||
			$this->isIssueSuppressedOrFalsePositive( $taint ) ||
			!property_exists( $var, 'taintedMethodLinks' ) ||
			!count( $var->taintedMethodLinks )
		) {
			return;
		}

		$oldMem = memory_get_peak_usage();

		/** @var FunctionInterface $method */
		foreach ( $var->taintedMethodLinks as $method ) {
			$paramInfo = $var->taintedMethodLinks[$method];
			// Note, not forCaller, as that doesn't see variadic parameters
			/** @var Parameter[] $calleeParamList */
			$calleeParamList = $method->getParameterList();
			$paramTaint = new FunctionTaintedness( Taintedness::newSafe() );
			foreach ( $paramInfo as $i => $_ ) {
				if ( isset( $calleeParamList[$i] ) && $calleeParamList[$i]->isVariadic() ) {
					$taint = $taint->with( SecurityCheckPlugin::VARIADIC_PARAM );
				}
				$paramTaint->setParamTaint( $i, $taint );
				// $this->debug( __METHOD__, "Setting method $method" .
					// " arg $i as $taint due to dependency on $var" );
			}
			$this->setFuncTaint( $method, $paramTaint );
			// TODO: Ideally we would merge taint error per argument
			$this->mergeTaintError( $method, $var );
			if ( $triggeringElm ) {
				$this->mergeTaintError( $method, $triggeringElm );
			}
		}

		if ( $var instanceof Property || $this->isGlobalVariableInLocalScope( $var ) ) {
			// For local variables, don't set the taint: the taintedness set here should only be used
			// when examining a function call. Inside the function body, we'll already have all the
			// info we need, and actually, this extra taint would cause false positives with variable
			// names reuse.
			$curVarTaint = $this->getTaintednessPhanObj( $var );
			$newTaint = $curVarTaint->with( $taint );
			$this->setTaintednessOld( $var, $newTaint );
		}

		$newMem = memory_get_peak_usage();
		$diffMem = round( ( $newMem - $oldMem ) / ( 1024 * 1024 ) );
		if ( $diffMem > 2 ) {
			$this->debug( __METHOD__, "Memory spike $diffMem for variable " . $var->getName() );
		}
	}

	/**
	 * This happens when someone calls foo( $evilTaintedVar );
	 *
	 * It makes sure that any variable that the function foo() sets takes on
	 * the taint of the supplied argument.
	 *
	 * @param FunctionInterface $method The function or method in question
	 * @param int $i The number of the argument in question.
	 * @param Taintedness $taint The taint to apply.
	 * @param Node $arg The evil tainted argument (to propagate caused by lines)
	 */
	protected function markAllDependentVarsYes(
		FunctionInterface $method,
		int $i,
		Taintedness $taint,
		Node $arg
	) : void {
		$taintAdjusted = $taint->withOnly( SecurityCheckPlugin::ALL_TAINT );
		if ( $method->isPHPInternal() ) {
			return;
		}
		if (
			!property_exists( $method, 'taintedVarLinks' )
			|| !isset( $method->taintedVarLinks[$i] )
		) {
			$this->debug( __METHOD__, "returning early no backlinks" );
			return;
		}
		$oldMem = memory_get_peak_usage();
		// If we mark a class member as being tainted, we recheck all the
		// methods of the class, as the previous taint of the methods may
		// have assumed the class member was not tainted.
		$classesNeedRefresh = new Set;
		foreach ( $method->taintedVarLinks[$i] as $var ) {
			assert( $var instanceof TypedElementInterface );
			$curVarTaint = $this->getTaintednessPhanObj( $var );
			$newTaint = $curVarTaint->with( $taintAdjusted );
			// $this->debug( __METHOD__, "handling $var as dependent yes" .
			// " of $method($i). Prev=$curVarTaint; new=$newTaint" );
			$this->setTaintednessOld( $var, $newTaint );
			$this->mergeTaintError( $var, $arg );
			if (
				$taintAdjusted->without( $curVarTaint )->isAllTaint() &&
				$var instanceof ClassElement
			) {
				// TODO: This is subpar -
				// * Its inefficient, reanalyzing much more than needed.
				// * It doesn't handle parent classes properly
				// * For public class members, it wouldn't catch uses
				// outside of the member's own class.
				$classesNeedRefresh->attach( $var->getClass( $this->code_base ) );
			}
		}
		foreach ( $classesNeedRefresh as $class ) {
			foreach ( $class->getMethodMap( $this->code_base ) as $classMethod ) {
				$this->debug( __METHOD__, "reanalyze $classMethod" );
				$this->analyzeFunc( $classMethod );
			}
		}
		// Maybe delete links??
		$newMem = memory_get_peak_usage();
		$diffMem = round( ( $newMem - $oldMem ) / ( 1024 * 1024 ) );
		if ( $diffMem > 2 ) {
			$this->debug( __METHOD__, "Memory spike $diffMem for method {$method->getName()}" );
		}
	}

	/**
	 * Whether merging the rhs to lhs is an safe operation
	 *
	 * @param Taintedness $lhs Taint of left hand side
	 * @param Taintedness $rhs Taint of right hand side
	 * @return bool Is it safe
	 */
	protected function isSafeAssignment( Taintedness $lhs, Taintedness $rhs ) : bool {
		$adjustRHS = $rhs->asYesToExecTaint();

		// $this->debug( __METHOD__, "lhs=$lhs; rhs=$rhs, adjustRhs=$adjustRHS" );
		return $adjustRHS->withOnly( $lhs )->isSafe() && !(
			$lhs->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) &&
			$rhs->has( SecurityCheckPlugin::UNKNOWN_TAINT )
		);
	}

	/**
	 * Given an array of caused-by lines, return a truncated, stringified representation of it.
	 *
	 * @todo Perhaps this should include the first and last X lines, not the first 2X. However,
	 *   doing so would make phan emit a new issue for the same line whenever new caused-by
	 *   lines are added to the array.
	 *
	 * @param string[] $lines
	 * @return string
	 */
	private function stringifyCausedByLines( array $lines ) : string {
		$maxLines = 12;
		if ( count( $lines ) <= $maxLines ) {
			return implode( '; ', $lines );
		}
		return implode( '; ', array_slice( $lines, 0, $maxLines ) ) . '; ...';
	}

	/**
	 * Get the line number of the original cause of taint.
	 * @todo Keep per-offset caused-by lines
	 *
	 * @param TypedElementInterface|Node|mixed $element
	 * @param Taintedness|null $taintedness Only consider caused-by lines having (at least) these bits, null
	 *   to include all lines.
	 * @param int $arg [optional] For functions what arg. -1 for overall.
	 * @return string
	 */
	protected function getOriginalTaintLine( $element, ?Taintedness $taintedness, $arg = -1 ) : string {
		$lines = $this->getOriginalTaintArray( $element, $arg );
		$filteredLines = $this->extractInterestingCausedbyLines( $lines, $taintedness );
		if ( $filteredLines ) {
			return ' (Caused by: ' . $this->stringifyCausedByLines( $filteredLines ) . ')';
		} else {
			return '';
		}
	}

	/**
	 * Normalize a taintedness value for caused-by lookup
	 *
	 * @param Taintedness $taintedness
	 * @return Taintedness
	 */
	private function normalizeTaintForCausedBy( Taintedness $taintedness ) : Taintedness {
		// Convert EXEC to YES, but keep existing YES in place, and also RAW_PARAM
		// as that's used for error reporting.
		$normTaints = $taintedness->withOnly( SecurityCheckPlugin::ALL_TAINT | SecurityCheckPlugin::RAW_PARAM );
		$taintedness = $taintedness->asExecToYesTaint()->with( $normTaints );

		if ( $taintedness->has( SecurityCheckPlugin::SQL_NUMKEY_TAINT ) ) {
			// Special case: we assume the bad case, preferring false positives over false negatives
			$taintedness->add( SecurityCheckPlugin::SQL_TAINT );
		}

		return $taintedness;
	}

	/**
	 * @param array[] $allLines
	 * @phan-param array<int,array{0:Taintedness,1:string}> $allLines
	 * @param Taintedness|null $taintedness
	 * @return string[]
	 */
	private function extractInterestingCausedbyLines( array $allLines, ?Taintedness $taintedness ) : array {
		if ( $taintedness === null ) {
			return array_column( $allLines, 1 );
		}

		$taintedness = $this->normalizeTaintForCausedBy( $taintedness );
		$ret = [];
		foreach ( $allLines as [ $lineTaint, $lineText ] ) {
			// Don't check for equality, as that would fail with MultiTaint
			if ( $taintedness->has( $lineTaint->get() ) ) {
				$ret[] = $lineText;
			}
		}
		return $ret;
	}

	/**
	 * Get the line number of the original cause of taint without "Caused by" string.
	 *
	 * @param TypedElementInterface|Node|mixed $element
	 * @param int $arg [optional] For functions what arg. -1 for overall.
	 * @return array[]
	 * @phan-return array<int,array{0:Taintedness,1:string}>
	 */
	private function getOriginalTaintArray( $element, $arg = -1 ) : array {
		if ( !is_object( $element ) ) {
			return [];
		}

		$lines = [];
		if ( $element instanceof TypedElementInterface ) {
			if ( $arg === -1 ) {
				if ( $element instanceof PassByReferenceVariable ) {
					$element = $this->extractReferenceArgument( $element );
				}
				if ( property_exists( $element, 'taintedOriginalError' ) ) {
					$lines = self::mergeCausedByLines(
						$lines,
						$element->taintedOriginalError
					);
				}
				foreach ( $element->taintedOriginalErrorByArg ?? [] as $origArg ) {
					// FIXME is this right? In the generic
					// case should we include all arguments as
					// well?
					$lines = self::mergeCausedByLines( $lines, $origArg );
				}
			} else {
				assert( $element instanceof FunctionInterface );
				$argErr = $this->getTaintErrorByArg( $element, $arg );
				$overallFuncErr = $element->taintedOriginalError ?? [];
				if ( !$argErr || self::getArraySubsetIdx( $overallFuncErr, $argErr ) !== false ) {
					$lines = self::mergeCausedByLines( $lines, $overallFuncErr );
				} elseif ( !$overallFuncErr || self::getArraySubsetIdx( $argErr, $overallFuncErr ) !== false ) {
					$lines = self::mergeCausedByLines( $lines, $argErr );
				} else {
					$lines = self::mergeCausedByLines( self::mergeCausedByLines( $lines, $argErr ), $overallFuncErr );
				}
			}
		} elseif ( $element instanceof Node ) {
			$pobjs = $this->getPhanObjsForNode( $element, [ 'all' ] );
			foreach ( $pobjs as $elem ) {
				$lines = self::mergeCausedByLines( $lines, $this->getOriginalTaintArray( $elem ) );
			}
		} else {
			throw new AssertionError( $this->dbgInfo() . "invalid parameter " . get_class( $element ) );
		}

		return $lines;
	}

	/**
	 * @param FunctionInterface $element
	 * @param int $arg
	 * @return array
	 * @phan-return list<array{0:Taintedness,1:string}>
	 */
	private function getTaintErrorByArg( FunctionInterface $element, int $arg ) : array {
		if ( isset( $element->taintedOriginalErrorByArg[ $arg ] ) ) {
			return $element->taintedOriginalErrorByArg[ $arg ];
		}
		// Check the variadic case. TODO Ideally, we might store caused-by and taintedness close together
		$funcTaint = $element->funcTaint ?? null;
		if ( !$funcTaint ) {
			return [];
		}
		assert( $funcTaint instanceof FunctionTaintedness );
		if (
			$funcTaint->hasParam( $arg ) &&
			$funcTaint->getParamTaint( $arg )->has( SecurityCheckPlugin::VARIADIC_PARAM )
		) {
			$lastIdx = max( $funcTaint->getParamKeys() );
			return $arg >= $lastIdx ? $element->taintedOriginalErrorByArg[ $lastIdx ] : [];
		}
		return [];
	}

	/**
	 * Match an expressions taint to func arguments
	 *
	 * Given an ast expression (node, or literal value) try and figure
	 * out which of the current function's parameters its taint came
	 * from.
	 *
	 * @todo Do a better job in preserving offset taint
	 *
	 * @param mixed $node Either a Node or a string, int, etc. The expression
	 * @param Taintedness $taintedness
	 * @param FunctionInterface $curFunc The function/method we are in.
	 * @return FunctionTaintedness
	 */
	protected function matchTaintToParam(
		$node,
		Taintedness $taintedness,
		FunctionInterface $curFunc
	) : FunctionTaintedness {
		if ( !is_object( $node ) ) {
			assert( $taintedness->isSafe() );
			return new FunctionTaintedness( $taintedness );
		}

		// Try to match up the taintedness of the return expression
		// to which parameter caused the taint. This will only work
		// in relatively simple cases.
		// $taintRemaining is any taint we couldn't attribute.
		$taintRemaining = clone $taintedness;
		// $paramTaint is taint we attribute to each param
		$paramTaint = new FunctionTaintedness( Taintedness::newUnknown() );
		// $otherTaint is taint contributed by other things.
		$otherTaint = Taintedness::newSafe();

		$pobjs = $this->getPhanObjsForNode( $node );
		foreach ( $pobjs as $pobj ) {
			if ( $pobj instanceof PassByReferenceVariable ) {
				$pobj = $this->extractReferenceArgument( $pobj );
			}
			$pobjTaintContribution = $this->getTaintednessPhanObj( $pobj );
			// $this->debug( __METHOD__, "taint for $pobj is $pobjTaintContribution" );
			$links = $pobj->taintedMethodLinks ?? null;
			if ( !$links ) {
				// No method links.
				// $this->debug( __METHOD__, "no method links for $pobj in " . $curFunc->getFQSEN() );
				// If its a non-private property, try getting parent class
				if ( $pobj instanceof Property && !$pobj->isPrivate() ) {
					$this->debug( __METHOD__, "FIXME should check parent class of $pobj" );
				}
				$otherTaint->add( $pobjTaintContribution );
				$taintRemaining->remove( $pobjTaintContribution );
				continue;
			}

			/** @var Set $links Its not a normal array */
			foreach ( $links as $func ) {
				/** @var $paramInfo array Array of int -> true */
				$paramInfo = $links[$func];
				if ( (string)( $func->getFQSEN() ) === (string)( $curFunc->getFQSEN() ) ) {
					foreach ( $paramInfo as $i => $_ ) {
						$paramTaint->setParamTaint( $i, $pobjTaintContribution );
						$taintRemaining->remove( $pobjTaintContribution );
					}
				} else {
					$taintRemaining->remove( $pobjTaintContribution );
					$otherTaint->add( $pobjTaintContribution );
				}
			}
		}
		$paramTaint->setOverall( $otherTaint->asMergedWith( $taintRemaining )->withOnly( $taintedness ) );
		return $paramTaint;
	}

	/**
	 * Output a debug message to stdout.
	 *
	 * @param string $method __METHOD__ in question
	 * @param string $msg debug message
	 */
	public function debug( $method, $msg ) : void {
		if ( $this->debugOutput === null ) {
			$errorOutput = getenv( "SECCHECK_DEBUG" );
			if ( $errorOutput && $errorOutput !== '-' ) {
				$this->debugOutput = fopen( $errorOutput, "w" );
			} elseif ( $errorOutput === '-' ) {
				$this->debugOutput = '-';
			} else {
				$this->debugOutput = false;
			}
		}
		$line = $method . "\33[1m" . $this->dbgInfo() . " \33[0m" . $msg . "\n";
		if ( $this->debugOutput && $this->debugOutput !== '-' ) {
			fwrite(
				$this->debugOutput,
				$line
			);
		} elseif ( $this->debugOutput === '-' ) {
			echo $line;
		}
	}

	/**
	 * Given an AST node that's a callable, try and determine what it is
	 *
	 * This is intended for functions that register callbacks. It will
	 * only really work for callbacks that are basically literals.
	 *
	 * @note $node may not be the current node in $this->context.
	 *
	 * @param Node|mixed $node The thingy from AST expected to be a Callable
	 * @return FullyQualifiedMethodName|FullyQualifiedFunctionName|null The corresponding FQSEN
	 */
	protected function getFQSENFromCallable( $node ) {
		$callback = null;
		if ( is_string( $node ) ) {
			// Easy case, 'Foo::Bar'
			if ( strpos( $node, '::' ) === false ) {
				$callback = FullyQualifiedFunctionName::fromFullyQualifiedString(
					$node
				);
			} else {
				$callback = FullyQualifiedMethodName::fromFullyQualifiedString(
					$node
				);
			}
		} elseif ( $node instanceof Node && $node->kind === \ast\AST_CLOSURE ) {
			$method = (
				new ContextNode(
					$this->code_base,
					$this->context->withLineNumberStart(
						$node->lineno ?? 0
					),
					$node
				)
			)->getClosure();
			$callback = $method->getFQSEN();
		} elseif (
			$node instanceof Node
			&& $node->kind === \ast\AST_VAR
			&& is_string( $node->children['name'] )
		) {
			$cnode = $this->getCtxN( $node );
			$var = $cnode->getVariable();
			$types = $var->getUnionType()->getTypeSet();
			foreach ( $types as $type ) {
				if (
					( $type instanceof CallableType || $type instanceof ClosureType ) &&
					$type->asFQSEN() instanceof FullyQualifiedFunctionLikeName
				) {
					// @todo FIXME This doesn't work if the closure
					// is defined in a different function scope
					// then the one we are currently in. Perhaps
					// we could look up the closure in
					// $this->code_base to figure out what func
					// its defined on via its parent scope. Or
					// something.
					$callback = $type->asFQSEN();
					break;
				}
			}
		} elseif ( $node instanceof Node && $node->kind === \ast\AST_ARRAY ) {
			if ( count( $node->children ) !== 2 ) {
				return null;
			}
			if (
				$node->children[0]->children['key'] !== null ||
				$node->children[1]->children['key'] !== null ||
				!is_string( $node->children[1]->children['value'] )
			) {
				return null;
			}
			$methodName = $node->children[1]->children['value'];
			$classNode = $node->children[0]->children['value'];
			if ( is_string( $node->children[0]->children['value'] ) ) {
				$className = $classNode;
			} elseif ( $classNode instanceof Node ) {
				switch ( $classNode->kind ) {
				case \ast\AST_MAGIC_CONST:
					// Mostly a special case for MediaWiki
					// CoreParserFunctions.php
					if (
						( $classNode->flags & \ast\flags\MAGIC_CLASS ) !== 0
						&& $this->context->isInClassScope()
					) {
						$className = (string)$this->context->getClassFQSEN();
					} else {
						return null;
					}
					break;
				case \ast\AST_CLASS_NAME:
					if (
						$classNode->children['class']->kind === \ast\AST_NAME &&
						is_string( $classNode->children['class']->children['name'] )
					) {
						$className = $classNode->children['class']->children['name'];
					} else {
						return null;
					}
					break;
				case \ast\AST_CLASS_CONST:
					return null;
				case \ast\AST_VAR:
				case \ast\AST_PROP:
					$var = $classNode->kind === \ast\AST_VAR
						? $this->getCtxN( $classNode )->getVariable()
						: $this->getCtxN( $classNode )->getProperty( false );
					$type = $var->getUnionType();
					if ( $type->typeCount() !== 1 || $type->isScalar() ) {
						return null;
					}
					$cl = $type->asClassList(
						$this->code_base,
						$this->context
					);
					$clazz = false;
					foreach ( $cl as $item ) {
						$clazz = $item;
						break;
					}
					if ( !$clazz ) {
						return null;
					}
					$className = (string)$clazz->getFQSEN();
					break;
				default:
					return null;
				}

			} else {
				return null;
			}
			// Note, not from in context, since this goes to call_user_func.
			$callback = FullyQualifiedMethodName::fromFullyQualifiedString(
				$className . '::' . $methodName
			);
		} else {
			return null;
		}

		if (
			( $callback instanceof FullyQualifiedMethodName &&
			$this->code_base->hasMethodWithFQSEN( $callback ) )
			|| ( $callback instanceof FullyQualifiedFunctionName &&
			 $this->code_base->hasFunctionWithFQSEN( $callback ) )
		) {
			return $callback;
		} else {
			// @todo Should almost emit a non-security issue for this
			$this->debug( __METHOD__, "Missing Callable $callback" );
			return null;
		}
	}

	/**
	 * Get the issue name and severity given a taint
	 *
	 * @param Taintedness $combinedTaint The taint to warn for. I.e. The exec flags
	 *   from LHS shifted to non-exec bitwise AND'd with the rhs taint.
	 * @return array Issue type and severity
	 * @phan-return array{0:string,1:int}
	 */
	public function taintToIssueAndSeverity( Taintedness $combinedTaint ) : array {
		$severity = Issue::SEVERITY_NORMAL;

		switch ( $combinedTaint->get() ) {
			case SecurityCheckPlugin::HTML_TAINT:
				$issueType = 'SecurityCheck-XSS';
				break;
			case SecurityCheckPlugin::SQL_TAINT:
			case SecurityCheckPlugin::SQL_NUMKEY_TAINT:
			case SecurityCheckPlugin::SQL_TAINT | SecurityCheckPlugin::SQL_NUMKEY_TAINT:
				$issueType = 'SecurityCheck-SQLInjection';
				$severity = Issue::SEVERITY_CRITICAL;
				break;
			case SecurityCheckPlugin::SHELL_TAINT:
				$issueType = 'SecurityCheck-ShellInjection';
				$severity = Issue::SEVERITY_CRITICAL;
				break;
			case SecurityCheckPlugin::SERIALIZE_TAINT:
				$issueType = 'SecurityCheck-PHPSerializeInjection';
				// For now this is low because it seems to have a lot
				// of false positives.
				// $severity = 4;
				break;
			case SecurityCheckPlugin::ESCAPED_TAINT:
				$issueType = 'SecurityCheck-DoubleEscaped';
				break;
			case SecurityCheckPlugin::PATH_TAINT:
				$issueType = 'SecurityCheck-PathTraversal';
				break;
			case SecurityCheckPlugin::CODE_TAINT:
				$issueType = 'SecurityCheck-RCE';
				break;
			case SecurityCheckPlugin::REGEX_TAINT:
				$issueType = 'SecurityCheck-ReDoS';
				break;
			case SecurityCheckPlugin::CUSTOM1_TAINT:
				$issueType = 'SecurityCheck-CUSTOM1';
				break;
			case SecurityCheckPlugin::CUSTOM2_TAINT:
				$issueType = 'SecurityCheck-CUSTOM2';
				break;
			case SecurityCheckPlugin::MISC_TAINT:
				$issueType = 'SecurityCheck-OTHER';
				break;
			default:
				$issueType = 'SecurityCheckMulti';
				if ( $combinedTaint->has( SecurityCheckPlugin::SHELL_TAINT | SecurityCheckPlugin::SQL_TAINT ) ) {
					$severity = Issue::SEVERITY_CRITICAL;
				}
		}

		return [ $issueType, $severity ];
	}

	/**
	 * Simplified version of maybeEmitIssue which makes the following assumptions:
	 *  - The caller would compute the RHS taint only to feed it to maybeEmitIssue
	 *  - The message should be followed by caused-by lines
	 *  - These caused-by lines should be taken from the same object passed as RHS
	 *  - Only caused-by lines having the LHS taint should be included
	 * If these conditions hold true, then this method should be preferred.
	 *
	 * @warning DO NOT use this method if the caller already needs to compute the RHS
	 * taintedness! The taint would be computed twice!
	 *
	 * @param Taintedness $lhsTaint
	 * @param mixed $rhsElement
	 * @param string $msg
	 * @param array $params Additional parameters for the message template
	 * @phan-param list<string|FullyQualifiedFunctionLikeName> $params
	 * @throws Exception
	 */
	public function maybeEmitIssueSimplified(
		Taintedness $lhsTaint,
		$rhsElement,
		string $msg,
		array $params = []
	) : void {
		$this->maybeEmitIssue(
			$lhsTaint,
			$this->getTaintedness( $rhsElement ),
			$msg . '{DETAILS}',
			array_merge( $params, [ $this->getOriginalTaintLine( $rhsElement, $lhsTaint ) ] )
		);
	}

	/**
	 * Emit an issue using the appropriate issue type
	 *
	 * If $this->overrideContext is set, it will use that for the
	 * file/line number to report. This is meant as a hack, so that
	 * in MW we can force hook related issues to be in the extension
	 * instead of where the hook is called from in MW core.
	 *
	 * @param Taintedness $lhsTaint Taint of left hand side (or equivalent)
	 * @param Taintedness $rhsTaint Taint of right hand side (or equivalent)
	 * @param string $msg Issue description
	 * @param array $msgArgs Message arguments passed to emitIssue
	 * @phan-param list<string|FullyQualifiedFunctionLikeName> $msgArgs
	 */
	public function maybeEmitIssue(
		Taintedness $lhsTaint,
		Taintedness $rhsTaint,
		string $msg,
		array $msgArgs
	) : void {
		if ( $lhsTaint->has( SecurityCheckPlugin::RAW_PARAM ) ) {
			$msg .= ' (Param is raw)';
			$lhsTaint = $lhsTaint->without( SecurityCheckPlugin::RAW_PARAM )->asYesToExecTaint();
		}
		if ( $this->isSafeAssignment( $lhsTaint, $rhsTaint ) ) {
			return;
		}

		$adjustLHS = $lhsTaint->asExecToYesTaint();
		$combinedTaint = $rhsTaint->withOnly( $adjustLHS );
		if (
			( $combinedTaint->isSafe() &&
			$rhsTaint->has( SecurityCheckPlugin::UNKNOWN_TAINT ) ) ||
			SecurityCheckPlugin::$pluginInstance->isFalsePositive(
				$adjustLHS,
				$rhsTaint,
				$msg,
				// FIXME should this be $this->overrideContext ?
				$this->context,
				$this->code_base
			)
		) {
			$issueType = 'SecurityCheck-LikelyFalsePositive';
			$severity = Issue::SEVERITY_LOW;
		} else {
			list( $issueType, $severity ) = $this->taintToIssueAndSeverity(
				$combinedTaint
			);
		}

		// If we have multiple, include what types.
		if ( $issueType === 'SecurityCheckMulti' ) {
			$msg .= ' (' . SecurityCheckPlugin::taintToString( $lhsTaint->get() ) .
				' <- ' . SecurityCheckPlugin::taintToString( $rhsTaint->get() ) . ')';
		}

		$context = $this->context;
		if ( $this->overrideContext ) {
			// If we are overriding the file/line number,
			// report the original line number as well.
			$msg .= " (Originally at: $this->context)";
			$context = $this->overrideContext;
		}

		SecurityCheckPlugin::emitIssue(
			$this->code_base,
			$context,
			$issueType,
			$msg,
			$msgArgs,
			$severity
		);
	}

	/**
	 * Method to determine if a potential error isn't really real
	 *
	 * This is useful when a specific warning would have a side effect
	 * and we want to know whether we should suppress the side effect in
	 * addition to the warning.
	 *
	 * @param Taintedness $lhsTaint Must have at least one EXEC flag set
	 * @return bool
	 */
	public function isIssueSuppressedOrFalsePositive( Taintedness $lhsTaint ) : bool {
		assert( $lhsTaint->has( SecurityCheckPlugin::ALL_EXEC_TAINT ) );
		$context = $this->overrideContext ?: $this->context;
		$adjustLHS = $lhsTaint->asExecToYesTaint();
		list( $issueType ) = $this->taintToIssueAndSeverity( $adjustLHS );

		if ( $context->hasSuppressIssue( $this->code_base, $issueType ) ) {
			return true;
		}

		$msg = "[dummy msg for false positive check]";
		return SecurityCheckPlugin::$pluginInstance->isFalsePositive(
			$adjustLHS,
			$adjustLHS,
			$msg,
			// not using $this->overrideContext to be consistent with maybeEmitIssue()
			$this->context,
			$this->code_base
		);
	}

	/**
	 * Somebody invokes a method or function (or something similar)
	 *
	 * This has to figure out:
	 *  Is the return value of the call tainted
	 *  Are any of the arguments tainted
	 *  Does the function do anything scary with its arguments
	 * It also has to maintain quite a bit of book-keeping.
	 *
	 * @param FunctionInterface $func
	 * @param FullyQualifiedFunctionLikeName $funcName
	 * @param array $args Arguments to function/method
	 * @phan-param array<Node|mixed> $args
	 * @return Taintedness Taint The resulting taint of the expression
	 */
	public function handleMethodCall(
		FunctionInterface $func,
		FullyQualifiedFunctionLikeName $funcName,
		array $args
	) : Taintedness {
		$oldMem = memory_get_peak_usage();
		$taint = $this->getTaintOfFunction( $func );

		// We need to look at the taintedness of the arguments
		// we are passing to the method.
		$overallArgTaint = Taintedness::newSafe();
		foreach ( $args as $i => $argument ) {
			if ( !( $argument instanceof Node ) ) {
				// Literal value
				continue;
			}

			list( $curArgTaintedness, $effectiveArgTaintedness ) = $this->getArgTaint(
				$taint, $argument, $i, $funcName
			);
			// Add a hook in order to special case for codebases. This is primarily used as a hack so that in mediawiki
			// the Message class doesn't have double escape taint if method takes Message|string.
			// TODO This is quite hacky.
			$curArgTaintedness = SecurityCheckPlugin::$pluginInstance->modifyArgTaint(
				$curArgTaintedness,
				$argument,
				$i,
				$func,
				$taint,
				$this->context,
				$this->code_base
			);

			// If this is a call by reference parameter,
			// link the taintedness variables.
			$param = $func->getParameterForCaller( $i );
			// @todo Internal funcs that pass by reference. Should we
			// assume that their variables are tainted? Most common
			// example is probably preg_match, which may very well be
			// tainted much of the time.
			if ( $param && $param->isPassByReference() && !$func->isPHPInternal() ) {
				$this->handlePassByRef( $func, $param, $argument, $i );
			}

			// We are doing something like someFunc( $evilArg );
			// Propagate that any vars set by someFunc should now be
			// marked tainted.
			// FIXME: We also need to handle the case where
			// someFunc( $execArg ) for pass by reference where
			// the parameter is later executed outside the func.
			if ( $curArgTaintedness->isAllTaint() ) {
				// $this->debug( __METHOD__, "cur arg $i is YES taint " .
				// "($curArgTaintedness). Marking dependent $funcName" );
				// Mark all dependent vars as tainted.
				$this->markAllDependentVarsYes( $func, $i, $curArgTaintedness, $argument );
			}

			// We are doing something like evilMethod( $arg );
			// where $arg is a parameter to the current function.
			// So backpropagate that assigning to $arg can cause evilness.
			if ( $taint->hasParam( $i ) && $taint->getParamTaint( $i )->isExecTaint() ) {
				// $this->debug( __METHOD__, "cur param is EXEC. $funcName" );
				$phanObjs = $this->getPhanObjsForNode( $argument, [ 'return' ] );
				foreach ( $phanObjs as $phanObj ) {
					$this->markAllDependentMethodsExec(
						$phanObj,
						$taint->getParamTaint( $i ),
						$func
					);
				}
			}
			// Always include the ordinal (it helps for repeated arguments)
			$taintedArg = '#' . ( $i + 1 );
			$argStr = ASTReverter::toShortString( $argument );
			if ( !( $argStr instanceof Node ) && strlen( $argStr ) < 25 ) {
				// If we have a short representation of the arg, include it as well.
				$taintedArg .= " (`$argStr`)";
			}
			// We use curArgTaintedness here, as we aren't checking what taint
			// gets passed to return value, but which taint is EXECed.
			// $this->debug( __METHOD__, "Checking safe assign $funcName" .
				// " arg=$i paramTaint= " . ( $taint[$i] ?? "MISSING" ) .
				// " vs argTaint= $curArgTaintedness" );
			$containingMethod = $this->getCurrentMethod();
			$thisTaint = $taint->hasParam( $i ) ? $taint->getParamTaint( $i ) : Taintedness::newSafe();
			$this->maybeEmitIssue(
				$thisTaint,
				$curArgTaintedness,
				"Calling method {FUNCTIONLIKE}() in {FUNCTIONLIKE}" .
				" that outputs using tainted argument {CODE}.{DETAILS}{DETAILS}",
				[
					$funcName,
					$containingMethod,
					$taintedArg,
					$this->getOriginalTaintLine( $func, $thisTaint, $i ),
					$this->getOriginalTaintLine( $argument, $thisTaint )
				]
			);

			$overallArgTaint->mergeWith( $effectiveArgTaintedness );
		}

		$containingMethod = $this->getCurrentMethod();
		$overallTaint = $taint->getOverall();
		$this->maybeEmitIssue(
			$overallTaint,
			$overallTaint->asExecToYesTaint(),
			"Calling method {FUNCTIONLIKE}() in {FUNCTIONLIKE} that "
			. "is always unsafe.{DETAILS}",
			[
				$funcName,
				$containingMethod,
				$this->getOriginalTaintLine( $func, $overallTaint )
			]
		);

		$newMem = memory_get_peak_usage();
		$diffMem = round( ( $newMem - $oldMem ) / ( 1024 * 1024 ) );
		if ( $diffMem > 2 ) {
			$this->debug( __METHOD__, "Memory spike $diffMem $funcName" );
		}
		// The taint of the method call expression is the overall taint
		// of the method not counting the preserve flag plus any of the
		// taint from arguments of the right type.
		// With all the exec bits removed from args.
		$preserveOrExec = SecurityCheckPlugin::PRESERVE_TAINT |
			SecurityCheckPlugin::ALL_EXEC_TAINT;
		return $taint->getOverall()->without( $preserveOrExec )
			->with( $overallArgTaint->without( SecurityCheckPlugin::ALL_EXEC_TAINT ) );
	}

	/**
	 * Get current and effective taint of an argument when examining a func call
	 *
	 * @param FunctionTaintedness $funcTaint
	 * @param Node $argument
	 * @param int $i Position of the param
	 * @param FullyQualifiedFunctionLikeName $funcName
	 * @return Taintedness[] [ cur, effective ]
	 */
	private function getArgTaint(
		FunctionTaintedness $funcTaint,
		Node $argument,
		int $i,
		FullyQualifiedFunctionLikeName $funcName
	) : array {
		if (
			$funcTaint->hasParam( $i )
			&& ( $funcTaint->getParamTaint( $i )->has( SecurityCheckPlugin::ARRAY_OK ) )
			&& $this->nodeIsArray( $argument )
		) {
			// This function specifies that arrays are always ok
			// So treat as if untainted.
			return [ Taintedness::newSafe(), Taintedness::newSafe() ];
		}

		$curArgTaintedness = $this->getTaintednessNode( $argument );
		if ( $funcTaint->hasParam( $i ) ) {
			if (
				( $funcTaint->getParamTaint( $i )->has( SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT ) )
				&& ( $curArgTaintedness->has( SecurityCheckPlugin::SQL_TAINT ) )
				&& $this->nodeIsString( $argument )
			) {
				// Special case to make NUMKEY work right for non-array
				// values. Should consider if this is really best
				// approach.
				$curArgTaintedness->add( SecurityCheckPlugin::SQL_NUMKEY_TAINT );
			}
			$effectiveArgTaintedness = $curArgTaintedness->withOnly(
				$funcTaint->getParamTaint( $i )->with( $funcTaint->getParamTaint( $i )->asExecToYesTaint() )
			);
			$this->debug( __METHOD__, "effective $effectiveArgTaintedness"
				. " via arg $i $funcName" );
		} elseif (
			$funcTaint->getOverall()->has( SecurityCheckPlugin::PRESERVE_TAINT | SecurityCheckPlugin::UNKNOWN_TAINT )
		) {
			// No info for this specific parameter, but
			// the overall function either preserves taint
			// when unspecified or is unknown. So just
			// pass the taint through.
			// FIXME, could maybe check if type is safe like int.
			// TODO Currently we collapse because the array shape may mutate (e.g. implode, unset,
			//   array_shift, array_merge, etc.). This should be handled on a per-case basis.
			$effectiveArgTaintedness = $curArgTaintedness->asCollapsed();
			// $this->debug( __METHOD__, "effective $effectiveArgTaintedness"
			// . " via preserve or unknown $funcName" );
		} else {
			// This parameter has no taint info.
			// And overall this function doesn't depend on param
			// for taint and isn't unknown.
			// So we consider this argument untainted.
			$effectiveArgTaintedness = Taintedness::newSafe();
			// $this->debug( __METHOD__, "effective $effectiveArgTaintedness"
			// . " via no taint info $funcName" );
		}
		return [ $curArgTaintedness, $effectiveArgTaintedness ];
	}

	/**
	 * Handle pass-by-ref params when examining a function call. Phan handles passbyref by reanalyzing
	 * the method with PassByReferenceVariable objects instead of Parameters. These objects contain
	 * the info about the param, but proxy all calls to the underlying argument object. Our approach
	 * to passbyrefs takes advantage of that, and is described below.
	 *
	 * Whenever we find a PassByReferenceVariable, we first extract the argument from it.
	 * This means that we can set taintedness, links, caused-by, etc. all on the argument object,
	 * and without having to use dedicated code paths.
	 * However, methods are usually analyzed *before* the call, hence, if we modify the
	 * taintedness of the argument immediately, the effect of the method call will be reproduced
	 * twice. This would lead to weird bugs where a method escapes its (ref) parameter, and calling
	 * such a method with a non-tainted argument would result in a DoubleEscaped warning.
	 * To avoid that, we save taint data for passbyrefs inside another property (on the
	 * argument object), taintednessRef. Then, when the method call is found, the "ref" taintedness
	 * becomes actual, which is what this very method takes care of.
	 *
	 * @param FunctionInterface $func
	 * @param Parameter $param
	 * @param Node $argument
	 * @param int $i Position of the param
	 * @throws Exception
	 */
	private function handlePassByRef(
		FunctionInterface $func,
		Parameter $param,
		Node $argument,
		int $i
	) : void {
		if ( !$func->getInternalScope()->hasVariableWithName( $param->getName() ) ) {
			$this->debug( __METHOD__, "Missing variable in scope for arg $i \$" . $param->getName() );
			return;
		}
		$argObjs = $this->getPhanObjsForNode( $argument );
		if ( count( $argObjs ) !== 1 ) {
			$this->debug( __METHOD__, "Expected only one $param" );
		}
		foreach ( $argObjs as $argObj ) {
			$overrideTaint = true;
			if ( $argObj instanceof PassByReferenceVariable ) {
				// Watch out for nested references, and do not reset taint in that case, yet
				$argObj = $this->extractReferenceArgument( $argObj );
				$overrideTaint = false;
			}
			// Move the ref taintedness to the "actual" taintedness of the object
			$overrideTaint = $overrideTaint && !( $argObj instanceof Property );
			$this->setTaintednessOld( $argObj, $this->getTaintednessReference( $argObj ), $overrideTaint );
			if ( $overrideTaint ) {
				unset( $argObj->taintednessRef );
			}
		}
	}

	/**
	 * Given a binary operator, compute which taint will be preserved. Safe ops don't preserve
	 * any taint, whereas unsafe ops will preserve all taints. The taint of a binop is basically
	 * ( lhs_taint | rhs_taint ) & taint_mask
	 *
	 * @warning This method should avoid computing the taint of $lhs and $rhs, because it might be
	 * called in preorder, but it would trigger a postorder visit.
	 *
	 * @param Node $opNode
	 * @param Node|mixed $lhs Either a Node or a scalar
	 * @param Node|mixed $rhs Either a Node or a scalar
	 * @return int
	 */
	protected function getBinOpTaintMask( Node $opNode, $lhs, $rhs ) : int {
		static $safeBinOps = [
			\ast\flags\BINARY_BOOL_XOR,
			\ast\flags\BINARY_DIV,
			\ast\flags\BINARY_IS_EQUAL,
			\ast\flags\BINARY_IS_IDENTICAL,
			\ast\flags\BINARY_IS_NOT_EQUAL,
			\ast\flags\BINARY_IS_NOT_IDENTICAL,
			\ast\flags\BINARY_IS_SMALLER,
			\ast\flags\BINARY_IS_SMALLER_OR_EQUAL,
			\ast\flags\BINARY_MOD,
			\ast\flags\BINARY_MUL,
			\ast\flags\BINARY_POW,
			// BINARY_ADD handled below due to array addition.
			\ast\flags\BINARY_SUB,
			\ast\flags\BINARY_BOOL_AND,
			\ast\flags\BINARY_BOOL_OR,
			\ast\flags\BINARY_IS_GREATER,
			\ast\flags\BINARY_IS_GREATER_OR_EQUAL,
			\ast\flags\BINARY_SHIFT_LEFT,
			\ast\flags\BINARY_SHIFT_RIGHT,
			\ast\flags\BINARY_SPACESHIP,
		];

		// This list is mostly used for debugging purposes
		static $knownUnsafeOps = [
			\ast\flags\BINARY_ADD,
			\ast\flags\BINARY_CONCAT,
			\ast\flags\BINARY_COALESCE,
			// The result of bitwise ops can be a string, so we err on the side of caution.
			\ast\flags\BINARY_BITWISE_AND,
			\ast\flags\BINARY_BITWISE_OR,
			\ast\flags\BINARY_BITWISE_XOR,
		];

		if ( in_array( $opNode->flags, $safeBinOps, true ) ) {
			return SecurityCheckPlugin::NO_TAINT;
		}
		if (
			$opNode->flags === \ast\flags\BINARY_ADD &&
			( !$this->nodeCanBeArray( $lhs ) || !$this->nodeCanBeArray( $rhs ) )
		) {
			// Array addition is the only way `+` can preserve taintedness; if at least one operand
			// is definitely NOT an array, then the result will be an integer, or a fatal error will
			// occurr (depending on the other operand). Note that if we cannot be 100% sure that the
			// node cannot be an array (e.g. if it has mixed type), we err on the side of caution and
			// consider it potentially tainted.
			return SecurityCheckPlugin::NO_TAINT;
		}

		if ( !in_array( $opNode->flags, $knownUnsafeOps, true ) ) {
			$this->debug(
				__METHOD__,
				'Unhandled binop ' . Debug::astFlagDescription( $opNode->flags, $opNode->kind )
			);
		}

		return SecurityCheckPlugin::ALL_TAINT_FLAGS;
	}

	/**
	 * Get the possible UnionType of a node, without emitting issues.
	 *
	 * @param Node $node
	 * @return UnionType|null
	 */
	protected function getNodeType( Node $node ) : ?UnionType {
		try {
			return UnionTypeVisitor::unionTypeFromNode(
				$this->code_base,
				$this->context,
				$node,
				// Don't check types, as this might be called e.g. on the LHS (see T249647)
				false
			);
		} catch ( IssueException $e ) {
			$this->debug( __METHOD__, "Got error " . $this->getDebugInfo( $e ) );
			return null;
		}
	}

	/**
	 * Given a Node, is it an array? (And definitely not a string)
	 *
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it an array?
	 */
	protected function nodeIsArray( $node ) : bool {
		if ( !( $node instanceof Node ) ) {
			// simple literal
			return false;
		}
		if ( $node->kind === \ast\AST_ARRAY ) {
			// Exit early in the simple case.
			return true;
		}
		$type = $this->getNodeType( $node );
		return $type && $type->hasArrayLike() && !$type->hasMixedType() && !$type->hasStringType();
	}

	/**
	 * Can $node potentially be an array?
	 *
	 * @param Node|mixed $node
	 * @return bool
	 */
	protected function nodeCanBeArray( $node ) : bool {
		if ( !( $node instanceof Node ) ) {
			return is_array( $node );
		}
		$type = $this->getNodeType( $node );
		if ( !$type ) {
			return true;
		}
		$type = $type->getRealUnionType();
		return $type->hasArrayLike() || $type->hasMixedType() || $type->isEmpty();
	}

	/**
	 * Given a Node, is it a string?
	 *
	 * @todo Unclear if this should return true for things that can
	 *   autocast to a string (e.g. ints)
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it a string?
	 */
	protected function nodeIsString( $node ) : bool {
		if ( !( $node instanceof Node ) ) {
			// simple literal
			return is_string( $node );
		}
		$type = $this->getNodeType( $node );
		// @todo Should having mixed type result in returning false here?
		return $type && $type->hasStringType();
	}

	/**
	 * Given a Node, is it definitely an int (and nothing else)
	 *
	 * Floats are not considered ints here.
	 *
	 * @param Node|mixed $node A node object or simple value from AST tree
	 * @return bool Is it an int?
	 */
	protected function nodeIsInt( $node ) : bool {
		if ( !( $node instanceof Node ) ) {
			// simple literal
			return is_int( $node );
		}
		$type = $this->getNodeType( $node );
		return $type && $type->hasIntType() && $type->typeCount() === 1;
	}

	/**
	 * Get the phan objects from the return line of a Func/Method
	 *
	 * This is primarily used to handle the case where a method
	 * returns a member (e.g. return $this->foo), and then something
	 * else does something evil with it - e.g. echo $someObj->getFoo().
	 * This allows keeping track that $this->foo is outputted, so if
	 * somewhere else in the code someone calls $someObj->setFoo( $unsafe )
	 * we can trigger a warning.
	 *
	 * This of course will only work in simple cases. It may also potentially
	 * have false positives if one instance is used solely for escaped stuff
	 * and a different instance is used for unsafe values that are later
	 * escaped, as all the different instances are treated the same.
	 *
	 * It needs the return statement to be trivial (e.g. return $this->foo;). It
	 * will not work even with something as simple as $a = $this->foo; return $a;
	 * However, this code path will only happen if the plugin encounters the
	 * code to output the value prior to reading the code that sets the value to
	 * something evil. The other code path where the set happens first is much
	 * more robust and hopefully the more common code path.
	 *
	 * @param FunctionInterface $func The function/method. Must use Analyzable trait
	 * @return TypedElementInterface[] An array of phan objects
	 */
	public function getReturnObjsOfFunc( FunctionInterface $func ) : array {
		if ( !property_exists( $func, 'retObjs' ) ) {
			if (
				$this->context->isInFunctionLikeScope() &&
				$func->getFQSEN() === $this->context->getFunctionLikeFQSEN()
			) {
				// Prevent infinite recursion
				return [];
			}
			// We still have to see the function. Analyze it now.
			$this->analyzeFunc( $func );
			if ( !property_exists( $func, 'retObjs' ) ) {
				// If it still doesn't exist, perhaps we reached the recursion limit, or it might be
				// a kind of function that we can't handle.
				return [];
			}
		}

		// Note that if a function is recursively calling itself, this list might be incomplete.
		// This could be remediated with another dynamic property (e.g. retObjsCollected), initialized
		// inside visitMethod in preorder, and set to true inside visitMethod in postorder.
		// It would be pointless, though, as returning a partial list is better than returning no list.
		return $func->retObjs;
	}

	/**
	 * Shorthand to check if $child is subclass of $parent.
	 *
	 * @param FullyQualifiedClassName $child
	 * @param FullyQualifiedClassName $parent
	 * @param CodeBase $codeBase
	 * @return bool
	 */
	public static function isSubclassOf(
		FullyQualifiedClassName $child,
		FullyQualifiedClassName $parent,
		CodeBase $codeBase
	) : bool {
		$childTypes = $child->asType()->asExpandedTypes( $codeBase )->getTypeSet();
		$parentType = $parent->asType();
		return in_array( $parentType, $childTypes, true );
	}
}
