<?php declare( strict_types=1 );

namespace SecurityCheckPlugin;

use ast\Node;
use Closure;

/**
 * Value object used to store taintedness. This should always be used to manipulate taintedness values,
 * instead of directly using taint constants directly (except for comparisons etc.).
 *
 * @todo (Some) methods accepting self|int should be changed to accept int only, and the callers should
 *   be migrated to use methods preserving the shape (e.g. add -> mergeWith, with -> asMergedWith)
 *
 * Note that this class should be used as copy-on-write (like phan's UnionType), so in-place
 * manipulation should never be done on phan objects.
 */
class Taintedness {
	/** @var int Combination of the class constants */
	private $flags;

	/** @var self[] Taintedness for each possible array element */
	private $dimTaint = [];

	/** @var int Taintedness of the array keys */
	private $keysTaint = SecurityCheckPlugin::NO_TAINT;

	/**
	 * @var self|null Taintedness for array elements that we couldn't attribute to any key
	 * @todo Can we store this under a bogus key in self::$dimTaint ?
	 */
	private $unknownDimsTaint;

	/**
	 * @var int Shortcut for all numkey flags. We remove these recursively from the keys, because
	 * numkey should only refer to the outer array.
	 */
	private const ALL_NUMKEY = SecurityCheckPlugin::SQL_NUMKEY_TAINT | SecurityCheckPlugin::SQL_NUMKEY_EXEC_TAINT;

	/**
	 * @param int $val One of the class constants
	 */
	public function __construct( int $val ) {
		$this->flags = $val;
	}

	// Common creation shortcuts

	/**
	 * @return self
	 */
	public static function newSafe() : self {
		return new self( SecurityCheckPlugin::NO_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newInapplicable() : self {
		return new self( SecurityCheckPlugin::INAPPLICABLE_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newUnknown() : self {
		return new self( SecurityCheckPlugin::UNKNOWN_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newTainted() : self {
		return new self( SecurityCheckPlugin::YES_TAINT );
	}

	/**
	 * @return self
	 */
	public static function newAll() : self {
		return new self( SecurityCheckPlugin::ALL_TAINT_FLAGS );
	}

	/**
	 * Get a numeric representation of the taint stored in this object. This includes own taint,
	 * array keys and whatnot.
	 * @note This should almost NEVER be used outside of this class! Use accessors as much as possible!
	 *
	 * @return int
	 */
	public function get() : int {
		$ret = $this->flags | $this->getAllKeysTaint() | $this->keysTaint;
		$ret = $this->unknownDimsTaint ? ( $ret | $this->unknownDimsTaint->get() ) : $ret;
		return $ret;
	}

	/**
	 * Get a flattened version of this object, with any taint from keys etc. collapsed into flags
	 * @return $this
	 */
	public function asCollapsed() : self {
		return new self( $this->get() );
	}

	/**
	 * Recursively extract the taintedness from each key.
	 * @note This removes numkey flags from the returned value!
	 *
	 * @return int
	 */
	private function getAllKeysTaint() : int {
		$ret = SecurityCheckPlugin::NO_TAINT;
		foreach ( $this->dimTaint as $val ) {
			$ret |= $val->without( self::ALL_NUMKEY )->get();
		}
		return $ret;
	}

	// Value manipulation

	/**
	 * Add the given taint to this object's flags, *without* creating a clone
	 * @see Taintedness::with() if you need a clone
	 * @see Taintedness::mergeWith() if you want to preserve the whole shape
	 *
	 * @param self|int $taint
	 */
	public function add( $taint ) : void {
		$addedTaint = $taint instanceof self ? $taint->flags : $taint;
		// TODO: Should this clear UNKNOWN_TAINT if its present
		// only in one of the args?
		$this->flags |= $addedTaint;
	}

	/**
	 * Returns a copy of this object, with the bits in $other added to flags.
	 * @see Taintedness::add() for the in-place version
	 * @see Taintedness::asMergedWith() if you want to preserve the whole shape
	 *
	 * @param self|int $other
	 * @return $this
	 */
	public function with( $other ) : self {
		$ret = clone $this;
		$taint = $other instanceof self ? $other->get() : $other;
		$ret->add( $taint );
		return $ret;
	}

	/**
	 * Recursively remove the given taint from this object, *without* creating a clone
	 * @see Taintedness::without() if you need a clone
	 *
	 * @param self|int $other
	 */
	public function remove( $other ) : void {
		$taint = $other instanceof self ? $other->flags : $other;
		$this->keepOnly( ~$taint );
	}

	/**
	 * Returns a copy of this object, with the bits in $other removed recursively.
	 * @see Taintedness::remove() for the in-place version
	 * @todo This should probably do what withoutShaped does.
	 *
	 * @param self|int $other
	 * @return $this
	 */
	public function without( $other ) : self {
		$ret = clone $this;
		$taint = $other instanceof self ? $other->get() : $other;
		$ret->remove( $taint );
		return $ret;
	}

	/**
	 * Similar to self::without, but acts on the shape
	 * @see Taintedness::remove() for the in-place version
	 *
	 * @param self $other
	 * @return $this
	 */
	public function withoutShaped( self $other ) : self {
		$ret = clone $this;
		$ret->flags &= ~$other->flags;
		$ret->keysTaint &= ~$other->keysTaint;
		// Don't change unknown keys.
		foreach ( $ret->dimTaint as $k => &$child ) {
			if ( isset( $other->dimTaint[$k] ) ) {
				$child = $child->withoutShaped( $other->dimTaint[$k] );
			}
		}
		unset( $child );
		return $ret;
	}

	/**
	 * Check whether this object has the given flag, recursively.
	 *
	 * @param int $taint
	 * @param bool $all For composite flags, this determines whether we should check for
	 *  a subset of $taint, or all of the flags in $taint should also be in $this->taintedness.
	 * @return bool
	 */
	public function has( int $taint, bool $all = false ) : bool {
		return $all
			? ( $this->get() & $taint ) === $taint
			: ( $this->get() & $taint ) !== SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Check whether this object does NOT have any of the given flags, recursively.
	 *
	 * @param int $taint
	 * @return bool
	 */
	public function lacks( int $taint ) : bool {
		return ( $this->get() & $taint ) === SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Keep only the taint in $taint, recursively, preserving the shape and without creating a copy.
	 * @see Taintedness::withOnly if you need a clone
	 *
	 * @param self|int $other
	 */
	public function keepOnly( $other ) : void {
		$taint = $other instanceof self ? $other->flags : $other;
		$this->flags &= $taint;
		if ( $this->unknownDimsTaint ) {
			$this->unknownDimsTaint->keepOnly( $taint );
		}
		$this->keysTaint &= $taint;
		foreach ( $this->dimTaint as $val ) {
			$val->keepOnly( $taint );
		}
	}

	/**
	 * Returns a copy of this object, with only the taint in $taint kept (recursively, preserving the shape)
	 * @see Taintedness::keepOnly() for the in-place version
	 *
	 * @param self|int $other
	 * @return $this
	 */
	public function withOnly( $other ) : self {
		$ret = clone $this;
		$taint = $other instanceof self ? $other->get() : $other;
		$ret->keepOnly( $taint );
		return $ret;
	}

	/**
	 * Merge this object with $other, recursively and without creating a copy.
	 * @see Taintedness::asMergedWith() if you need a copy
	 *
	 * @param Taintedness $other
	 */
	public function mergeWith( self $other ) : void {
		$this->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$this->unknownDimsTaint ) {
			$this->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		$this->keysTaint |= $other->keysTaint;
		foreach ( $other->dimTaint as $key => $val ) {
			if ( !array_key_exists( $key, $this->dimTaint ) ) {
				$this->dimTaint[$key] = clone $val;
			} else {
				$this->dimTaint[$key]->mergeWith( $val );
			}
		}
	}

	/**
	 * Merge this object with $other, recursively, creating a copy.
	 * @see Taintedness::mergeWith() for in-place merge
	 *
	 * @param Taintedness $other
	 * @return $this
	 */
	public function asMergedWith( self $other ) : self {
		$ret = clone $this;
		$ret->mergeWith( $other );
		return $ret;
	}

	// Offsets taintedness

	/**
	 * Set the taintedness for $offset to $value, in place
	 *
	 * @param Node|mixed $offset Node or a scalar value, already resolved
	 * @param Taintedness $value
	 */
	public function setOffsetTaintedness( $offset, self $value ) : void {
		if ( is_scalar( $offset ) ) {
			$this->dimTaint[$offset] = $value;
		} else {
			$this->unknownDimsTaint = $this->unknownDimsTaint ?? self::newSafe();
			$this->unknownDimsTaint->mergeWith( $value );
		}
	}

	/**
	 * Adds the bits in $value to the taintedness of the keys
	 * @param int $value
	 */
	public function addKeysTaintedness( int $value ) : void {
		$this->keysTaint |= $value;
	}

	/**
	 * Apply the given closure to the final element at the offset list given by $offset. If the
	 * element cannot be found because $offsets contain an unknown index, the taint of $rhs is
	 * applied to the closest index.
	 *
	 * @param array $offsets
	 * @phan-param array<int,Node|mixed> $offsets
	 * @param Taintedness[] $offsetsTaint Taintedness for each offset in $offsets
	 * @param Closure $cb First parameter is the base element for the last key, and second parameter
	 * is the last key. The closure should return the new value.
	 * @phan-param Closure(self,mixed):self $cb
	 */
	private function applyClosureAtOffsetList( array $offsets, array $offsetsTaint, Closure $cb ) : void {
		assert( count( $offsets ) >= 1 );
		$base = $this;
		// Just in case keys are not consecutive
		$offsets = array_values( $offsets );
		$lastIdx = count( $offsets ) - 1;
		foreach ( $offsets as $i => $offset ) {
			$isLast = $i === $lastIdx;
			if ( !is_scalar( $offset ) ) {
				// Note, if the offset is scalar its taint is NO_TAINT
				$base->keysTaint |= $offsetsTaint[$i]->get();

				// NOTE: This is intendedly done for Nodes AND null. We assume that null here means
				// "implicit" dim (`$a[] = 'b'`), aka unknown dim.
				if ( !$base->unknownDimsTaint ) {
					$base->unknownDimsTaint = self::newSafe();
				}
				if ( $isLast ) {
					$base->unknownDimsTaint = $cb( $base, $offset );
					return;
				}

				$base = $base->unknownDimsTaint;
				continue;
			}

			if ( $isLast ) {
				// Mission accomplished!
				$base->dimTaint[$offset] = $cb( $base, $offset );
				return;
			}

			if ( !array_key_exists( $offset, $base->dimTaint ) ) {
				// Create the element as safe and move on
				$base->dimTaint[$offset] = self::newSafe();
			}
			$base = $base->dimTaint[$offset];
		}
	}

	/**
	 * Set the taintedness of $val after the list of offsets given by $offsets, with or without override.
	 *
	 * @param array $offsets This is an integer-keyed, ordered list of offsets. E.g. the list
	 *  [ 'a', 'b', 'c' ] means assigning to $var['a']['b']['c']. This must NOT be empty.
	 * @phan-param non-empty-list<mixed> $offsets
	 * @param Taintedness[] $offsetsTaint Taintedness for each offset in $offsets
	 * @param Taintedness $val
	 * @param bool $override
	 */
	public function setTaintednessAtOffsetList(
		array $offsets,
		array $offsetsTaint,
		self $val,
		bool $override
	) : void {
		/**
		 * @param mixed $lastOffset
		 */
		$setCb = static function ( self $base, $lastOffset ) use ( $val, $override ) : self {
			if ( !is_scalar( $lastOffset ) ) {
				return ( !$base->unknownDimsTaint || $override )
					? $val
					: $base->unknownDimsTaint->asMergedWith( $val );
			}
			return ( !isset( $base->dimTaint[$lastOffset] ) || $override )
				? $val
				: $base->dimTaint[$lastOffset]->with( $val );
		};
		$this->applyClosureAtOffsetList( $offsets, $offsetsTaint, $setCb );
	}

	/**
	 * Apply an array addition with $other
	 *
	 * @param Taintedness $other
	 */
	public function arrayPlus( self $other ) : void {
		$this->flags |= $other->flags;
		if ( $other->unknownDimsTaint && !$this->unknownDimsTaint ) {
			$this->unknownDimsTaint = $other->unknownDimsTaint;
		} elseif ( $other->unknownDimsTaint ) {
			$this->unknownDimsTaint->mergeWith( $other->unknownDimsTaint );
		}
		$this->keysTaint |= $other->keysTaint;
		// This is not recursive because array addition isn't
		$this->dimTaint += $other->dimTaint;
	}

	/**
	 * Apply the effect of array addition and return a clone of $this
	 *
	 * @param Taintedness $other
	 * @return $this
	 */
	public function asArrayPlusWith( self $other ) : self {
		$ret = clone $this;
		$ret->arrayPlus( $other );
		return $ret;
	}

	/**
	 * Get the taintedness for the given offset, if set. If $offset could not be resolved, this
	 * will return the whole object, with taint from unknown keys added. If the offset is not known,
	 * it will return a new Taintedness object without the original shape, and with taint from
	 * unknown keys added.
	 *
	 * @param Node|string|int|bool|float $offset
	 * @return self Always a copy
	 */
	public function getTaintednessForOffsetOrWhole( $offset ) : self {
		if ( $offset instanceof Node ) {
			return $this->asValueFirstLevel();
		}
		if ( isset( $this->dimTaint[$offset] ) ) {
			return $this->dimTaint[$offset]->asMergedWith( $this->unknownDimsTaint ?? self::newSafe() );
		}

		$ret = $this->unknownDimsTaint ? clone $this->unknownDimsTaint : self::newSafe();
		$ret->add( $this->flags );
		return $ret;
	}

	/**
	 * Get a representation of this taint at the first depth level. For instance, this can be used in a foreach
	 * assignment for the value. Own taint and unknown keys taint are preserved, and then we merge in recursively
	 * all the current keys.
	 *
	 * @return $this
	 */
	public function asValueFirstLevel() : self {
		$ret = new self( $this->flags );
		$ret->unknownDimsTaint = $this->unknownDimsTaint;
		foreach ( $this->dimTaint as $val ) {
			$ret->mergeWith( $val );
			if ( $ret->unknownDimsTaint ) {
				$ret->unknownDimsTaint->add( $val->flags );
			} else {
				$ret->unknownDimsTaint = new self( $val->flags );
			}
		}
		return $ret;
	}

	/**
	 * Get a representation of this taint to be used in a foreach assignment for the key
	 *
	 * @return $this
	 */
	public function asKeyForForeach() : self {
		return new self( $this->keysTaint | $this->flags );
	}

	// Conversion/checks shortcuts

	/**
	 * Does the taint have one of EXEC flags set
	 *
	 * @return bool If the variable has any exec taint
	 */
	public function isExecTaint() : bool {
		return $this->has( SecurityCheckPlugin::ALL_EXEC_TAINT );
	}

	/**
	 * Are any of the positive (i.e HTML_TAINT) taint flags set
	 *
	 * @return bool If the variable has known (non-execute taint)
	 */
	public function isAllTaint() : bool {
		return $this->has( SecurityCheckPlugin::ALL_TAINT );
	}

	/**
	 * Check whether this object has no taintedness.
	 *
	 * @return bool
	 */
	public function isSafe() : bool {
		return $this->get() === SecurityCheckPlugin::NO_TAINT;
	}

	/**
	 * Convert exec to yes taint recursively. Special flags like UNKNOWN or INAPPLICABLE are discarded.
	 * Any YES flags are also discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @return self
	 */
	public function asExecToYesTaint() : self {
		$ret = clone $this;
		$ret->flags = ( $ret->flags & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
		if ( $ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asExecToYesTaint();
		}
		$ret->keysTaint = ( $ret->keysTaint & SecurityCheckPlugin::ALL_EXEC_TAINT ) >> 1;
		foreach ( $ret->dimTaint as &$val ) {
			$val = $val->asExecToYesTaint();
		}
		return $ret;
	}

	/**
	 * Convert the yes taint bits to corresponding exec taint bits recursively.
	 * Any UNKNOWN_TAINT or INAPPLICABLE_TAINT is discarded. Note that this returns a copy of the
	 * original object. The shape is preserved.
	 *
	 * @return self
	 */
	public function asYesToExecTaint() : self {
		$ret = clone $this;
		$ret->flags = ( $ret->flags & SecurityCheckPlugin::ALL_TAINT ) << 1;
		if ( $ret->unknownDimsTaint ) {
			$ret->unknownDimsTaint = $ret->unknownDimsTaint->asYesToExecTaint();
		}
		$ret->keysTaint = ( $ret->keysTaint & SecurityCheckPlugin::ALL_TAINT ) << 1;
		foreach ( $ret->dimTaint as &$val ) {
			$val = $val->asYesToExecTaint();
		}
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness, useful for debugging etc.
	 *
	 * @param string $indent
	 * @return string
	 */
	public function toString( $indent = '' ) : string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$keys = SecurityCheckPlugin::taintToString( $this->keysTaint );
		$ret = <<<EOT
{
$indent    Own taint: $flags
$indent    Keys: $keys
$indent    Elements: {
EOT;

		$kIndent = "$indent    ";
		$first = "\n";
		$last = '';
		foreach ( $this->dimTaint as $key => $taint ) {
			$ret .= "$first$kIndent    $key => " . $taint->toString( "$kIndent    " ) . "\n";
			$first = '';
			$last = $kIndent;
		}
		if ( $this->unknownDimsTaint ) {
			$ret .= "$first$kIndent    UNKNOWN => " . $this->unknownDimsTaint->toString( "$kIndent    " ) . "\n";
			$last = $kIndent;
		}
		$ret .= "$last}\n$indent}";
		return $ret;
	}

	/**
	 * Get a stringified representation of this taintedness suitable for the debug annotation
	 *
	 * @return string
	 */
	public function toShortString() : string {
		$flags = SecurityCheckPlugin::taintToString( $this->flags );
		$keys = SecurityCheckPlugin::taintToString( $this->keysTaint );
		$ret = "{Own: $flags; Keys: $keys";
		$keyParts = [];
		if ( $this->dimTaint ) {
			foreach ( $this->dimTaint as $key => $taint ) {
				$keyParts[] = "$key => " . $taint->toShortString();
			}
		}
		if ( $this->unknownDimsTaint ) {
			$keyParts[] = 'UNKNOWN => ' . $this->unknownDimsTaint->toShortString();
		}
		if ( $keyParts ) {
			$ret .= '; Elements: {' . implode( '; ', $keyParts ) . '}';
		}
		$ret .= '}';
		return $ret;
	}

	/**
	 * Make sure to clone member variables, too.
	 */
	public function __clone() {
		if ( $this->unknownDimsTaint ) {
			$this->unknownDimsTaint = clone $this->unknownDimsTaint;
		}
		foreach ( $this->dimTaint as $k => $v ) {
			$this->dimTaint[$k] = clone $v;
		}
	}

	/**
	 * @return string
	 */
	public function __toString() : string {
		return $this->toString();
	}
}
