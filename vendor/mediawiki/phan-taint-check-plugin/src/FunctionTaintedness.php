<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use Closure;
use Error;
use LogicException;

/**
 * Value object used to store taintedness of functions.
 * The $overall prop specifies what taint the function returns
 *   irrespective of its arguments.
 * The numeric keys in $paramTaints are how each individual argument affects taint.
 *
 *   For 'overall': the EXEC flags mean a call does evil regardless of args
 *                  the TAINT flags are what taint the output has
 *   For numeric keys: EXEC flags for what taints are unsafe here
 *                     TAINT flags for what taint gets passed through func.
 * As a special case, if the overall key has self::PRESERVE_TAINT
 * then any unspecified keys behave like they are self::YES_TAINT
 *
 * If func has an arg that is missing from $paramTaints, then it should be
 * treated as NO_TAINT if its a number or bool, and YES_TAINT otherwise.
 * The $overall taintedness must always be set.
 */
class FunctionTaintedness {
	/** @var Taintedness Overall taintedness of the func */
	private $overall;
	/** @var Taintedness[] Taintedness for each param */
	public $paramTaints = [];

	/**
	 * @param Taintedness $overall
	 */
	public function __construct( Taintedness $overall ) {
		$this->overall = $overall;
	}

	/**
	 * Construct a FunctionTaintedness from an old-style array.
	 * @note This should only be used by SecurityCheckPlugin::getBuiltinFuncTaint!
	 *
	 * @param Taintedness[] $taint
	 * @return self
	 */
	public static function newFromArray( array $taint ) : self {
		self::assertWellFormedArray( $taint );
		$ret = new self( $taint['overall'] );
		unset( $taint['overall'] );
		$ret->paramTaints = $taint;
		return $ret;
	}

	/**
	 * Assert that a taintednes array is well formed, and fail hard if it isn't.
	 *
	 * @param Taintedness[] $taint
	 */
	private static function assertWellFormedArray( array $taint ) : void {
		if ( !isset( $taint['overall'] ) ) {
			throw new Error( 'Overall taint must be set' );
		}

		foreach ( $taint as $i => $t ) {
			if ( !is_int( $i ) && $i !== 'overall' ) {
				throw new Error( "Taint indexes must be int or 'overall', got '$i'" );
			}
			if ( !$t instanceof Taintedness ) {
				throw new Error( "Wrong taint index $i, got: " . var_export( $t, true ) );
			}
		}
	}

	/**
	 * @param Taintedness $val
	 */
	public function setOverall( Taintedness $val ) : void {
		$this->overall = $val;
	}

	/**
	 * Get a copy of the overall taint
	 *
	 * @return Taintedness
	 */
	public function getOverall() : Taintedness {
		if ( $this->overall === null ) {
			throw new LogicException( 'Found null overall' );
		}
		return clone $this->overall;
	}

	/**
	 * Set the taint for a given param
	 *
	 * @param int $param
	 * @param Taintedness $taint
	 */
	public function setParamTaint( int $param, Taintedness $taint ) : void {
		$this->paramTaints[$param] = $taint;
	}

	/**
	 * Get a clone of the taintedness of the given param, and NO_TAINT if not set.
	 *
	 * @param int $param
	 * @return Taintedness
	 */
	public function getParamTaint( int $param ) : Taintedness {
		if ( !$this->hasParam( $param ) ) {
			return Taintedness::newSafe();
		}
		// TODO: array_key_last once we support PHP 7.3+
		$idx = min( $param, max( array_keys( $this->paramTaints ) ) );
		return clone $this->paramTaints[$idx];
	}

	/**
	 * Get the *keys* of the params for which we have data
	 *
	 * @return int[]
	 */
	public function getParamKeys() : array {
		return array_keys( $this->paramTaints );
	}

	/**
	 * Check whether we have taint data for the given param
	 *
	 * @param int $param
	 * @return bool
	 */
	public function hasParam( int $param ) : bool {
		if ( isset( $this->paramTaints[$param] ) ) {
			return true;
		}
		if ( !$this->paramTaints ) {
			return false;
		}
		// TODO: array_key_last once we support PHP 7.3+
		$lastKey = max( array_keys( $this->paramTaints ) );
		$lastEl = $this->paramTaints[$lastKey];
		return $param >= $lastKey && $lastEl->has( SecurityCheckPlugin::VARIADIC_PARAM );
	}

	/**
	 * Apply a callback to all taint values (in-place)
	 * @param Closure $fn
	 * @phan-param Closure( Taintedness ):void $fn
	 */
	public function map( Closure $fn ) : void {
		foreach ( $this->paramTaints as $taint ) {
			$fn( $taint );
		}
		$fn( $this->overall );
	}

	/**
	 * Sometimes we don't want NO_OVERRIDE. This is primarily used to ensure that NO_OVERRIDE
	 * doesn't propagate into other variables.
	 *
	 * Note that this always creates a clone of $this.
	 *
	 * @param bool $clear Whether to clear it or not
	 * @return $this
	 */
	public function withMaybeClearNoOverride( bool $clear ) : self {
		$ret = clone $this;
		if ( !$clear ) {
			return $ret;
		}
		$ret->overall->remove( SecurityCheckPlugin::NO_OVERRIDE );
		foreach ( $ret->paramTaints as $t ) {
			$t->remove( SecurityCheckPlugin::NO_OVERRIDE );
		}
		return $ret;
	}

	/**
	 * Make sure to clone properties when cloning the instance
	 */
	public function __clone() {
		$this->overall = clone $this->overall;
		foreach ( $this->paramTaints as $k => $e ) {
			$this->paramTaints[$k] = clone $e;
		}
	}

	/**
	 * @return string
	 */
	public function __toString() : string {
		$str = "[\n\toverall: " . $this->overall->toString( '    ' ) . ",\n";
		foreach ( $this->paramTaints as $par => $taint ) {
			$str .= "\t$par: " . $taint->toString() . ",\n";
		}
		return "$str]";
	}
}
