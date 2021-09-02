<?php declare( strict_types=1 );

/**
 * Base class for SecurityCheckPlugin. Extend if you want to customize.
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

namespace SecurityCheckPlugin;

use AssertionError;
use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Builder;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\Scope;
use Phan\Library\Set;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeLiteralStatementCapability;
use Phan\PluginV3\BeforeLoopBodyAnalysisCapability;
use Phan\PluginV3\MergeVariableInfoCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\PreAnalyzeNodeCapability;

/**
 * Base class used by the Generic and MediaWiki flavours of the plugin.
 */
abstract class SecurityCheckPlugin extends PluginV3 implements
	PostAnalyzeNodeCapability,
	PreAnalyzeNodeCapability,
	BeforeLoopBodyAnalysisCapability,
	MergeVariableInfoCapability,
	AnalyzeLiteralStatementCapability
{

	// Various taint flags. The _EXEC_ varieties mean
	// that it is unsafe to assign that type of taint
	// to the variable in question.

	public const NO_TAINT = 0;

	// For declaration type things. Given a special value for
	// debugging purposes, but inapplicable taint should not
	// actually show up anywhere.
	public const INAPPLICABLE_TAINT = 1 << 0;

	// Flag to denote that we don't know
	public const UNKNOWN_TAINT = 1 << 1;

	// Flag for function parameters and the like, where it
	// preserves whatever taint the function is given.
	public const PRESERVE_TAINT = 1 << 2;

	// In future might separate out different types of html quoting.
	// e.g. "<div data-foo='" . htmlspecialchars( $bar ) . "'>";
	// is unsafe.
	public const HTML_TAINT = 1 << 3;
	public const HTML_EXEC_TAINT = 1 << 4;

	public const SQL_TAINT = 1 << 5;
	public const SQL_EXEC_TAINT = 1 << 6;

	public const SHELL_TAINT = 1 << 7;
	public const SHELL_EXEC_TAINT = 1 << 8;

	public const SERIALIZE_TAINT = 1 << 9;
	public const SERIALIZE_EXEC_TAINT = 1 << 10;

	// Tainted paths, as input to include(), require() and some FS functions (path traversal)
	public const PATH_TAINT = 1 << 11;
	public const PATH_EXEC_TAINT = 1 << 12;

	// User-controlled code, for RCE
	public const CODE_TAINT = 1 << 13;
	public const CODE_EXEC_TAINT = 1 << 14;

	// User-controlled regular expressions, for ReDoS
	public const REGEX_TAINT = 1 << 15;
	public const REGEX_EXEC_TAINT = 1 << 16;

	// For stuff that doesn't fit another category
	public const MISC_TAINT = 1 << 17;
	public const MISC_EXEC_TAINT = 1 << 18;

	// To allow people to add other application specific taints.
	public const CUSTOM1_TAINT = 1 << 19;
	public const CUSTOM1_EXEC_TAINT = 1 << 20;
	public const CUSTOM2_TAINT = 1 << 21;
	public const CUSTOM2_EXEC_TAINT = 1 << 22;

	// Special purpose for supporting MediaWiki's IDatabase::select
	// and friends. Like SQL_TAINT, but only applies to the numeric
	// keys of an array. Note: These are not included in YES_TAINT/EXEC_TAINT.
	// e.g. given $f = [ $_GET['foo'] ]; $f would have the flag, but
	// $g = $_GET['foo']; or $h = [ 's' => $_GET['foo'] ] would not.
	// The associative keys also have this flag if they are tainted.
	// It is also assumed anything with this flag will also have
	// the SQL_TAINT flag set.
	public const SQL_NUMKEY_TAINT = 1 << 23;
	public const SQL_NUMKEY_EXEC_TAINT = 1 << 24;

	// For double escaped variables
	public const ESCAPED_TAINT = 1 << 25;
	public const ESCAPED_EXEC_TAINT = 1 << 26;

	// Special purpose flags (Starting at 2^28)
	// Cancel's out all EXEC flags on a function arg if arg is array.
	public const ARRAY_OK = 1 << 28;

	// Do not allow autodetected taint info override given taint.
	public const NO_OVERRIDE = 1 << 29;

	// Represents a parameter expecting a raw value, for which escaping should have already
	// taken place. E.g. in MW this happens for Message::rawParams. In practice, this turns
	// the func taint into EXEC, but without propagation.
	public const RAW_PARAM = 1 << 30;

	public const VARIADIC_PARAM = 1 << 31;

	// Combination flags.

	// YES_TAINT denotes all taint a user controlled variable would have
	public const YES_TAINT = self::HTML_TAINT | self::SQL_TAINT | self::SHELL_TAINT | self::SERIALIZE_TAINT |
		self::PATH_TAINT | self::CODE_TAINT | self::REGEX_TAINT | self::CUSTOM1_TAINT | self::CUSTOM2_TAINT |
		self::MISC_TAINT;
	public const EXEC_TAINT = self::YES_TAINT << 1;
	public const YES_EXEC_TAINT = self::YES_TAINT | self::EXEC_TAINT;

	// ALL taint is YES + special purpose taints, but not including special flags.
	public const ALL_TAINT = self::YES_TAINT | self::SQL_NUMKEY_TAINT | self::ESCAPED_TAINT;
	public const ALL_EXEC_TAINT =
		self::EXEC_TAINT | self::SQL_NUMKEY_EXEC_TAINT | self::ESCAPED_EXEC_TAINT;
	public const ALL_YES_EXEC_TAINT = self::ALL_TAINT | self::ALL_EXEC_TAINT;

	// Taints that support backpropagation. Does not include numkey
	// due to special array handling.
	public const BACKPROP_TAINTS = self::ALL_EXEC_TAINT & ~self::SQL_NUMKEY_EXEC_TAINT;

	public const ESCAPES_HTML = ( self::YES_TAINT & ~self::HTML_TAINT ) | self::ESCAPED_EXEC_TAINT;

	// As the name would suggest, this must include *ALL* possible taint flags.
	public const ALL_TAINT_FLAGS = self::ALL_YES_EXEC_TAINT | self::ARRAY_OK | self::RAW_PARAM |
		self::NO_OVERRIDE | self::INAPPLICABLE_TAINT | self::UNKNOWN_TAINT | self::PRESERVE_TAINT |
		self::VARIADIC_PARAM;

	/**
	 * Used to print taint debug data, see BlockAnalysisVisitor::PHAN_DEBUG_VAR_REGEX
	 */
	private const DEBUG_TAINTEDNESS_REGEXP =
		'/@phan-debug-var-taintedness\s+\$(' . Builder::WORD_REGEX . '(,\s*\$' . Builder::WORD_REGEX . ')*)/';
	// @phan-suppress-previous-line PhanAccessClassConstantInternal It's just perfect for use here

	public const PARAM_ANNOTATION_REGEX = '/@param-taint &?(?P<variadic>\.\.\.)?\$(?P<paramname>\S+)\s+(?P<taint>.*)$/';

	/**
	 * @var self Passed to the visitor for context
	 */
	public static $pluginInstance;

	/**
	 * @var FunctionTaintedness[] Cache of parsed docblocks. This is declared here (as opposed to
	 *  the BaseVisitor) so that PHPUnit can snapshot and restore it.
	 */
	public static $docblockCache = [];

	/** @var FunctionTaintedness[] Cache of taintedness of builtin functions */
	private static $builtinFuncTaintCache = [];

	/**
	 * Save the subclass instance to make it accessible from the visitor
	 */
	public function __construct() {
		$this->assertRequiredConfig();
		self::$pluginInstance = $this;
	}

	/**
	 * Ensure that the options we need are enabled.
	 */
	private function assertRequiredConfig() : void {
		if ( Config::get_quick_mode() ) {
			throw new AssertionError( 'Quick mode must be disabled to run taint-check' );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getMergeVariableInfoClosure() : Closure {
		/**
		 * For branches that are not guaranteed to be executed, merge taint info for any involved
		 * variable across all branches.
		 * @param Variable $variable
		 * @param Scope[] $scopeList
		 * @param bool $varExistsInAllScopes @phan-unused-param
		 * @suppress PhanUnreferencedClosure
		 */
		return static function ( Variable $variable, array $scopeList, bool $varExistsInAllScopes ) {
			$varName = $variable->getName();

			$methodLinks = new Set();
			$error = [];
			$taintedness = Taintedness::newSafe();

			foreach ( $scopeList as $scope ) {
				$localVar = $scope->getVariableByNameOrNull( $varName );
				if ( !$localVar ) {
					continue;
				}

				if ( property_exists( $localVar, 'taintedness' ) ) {
					$taintedness->mergeWith( $localVar->taintedness );
				}

				$variableObjLinks = $localVar->taintedMethodLinks ?? new Set;
				$methodLinks->addAll( $variableObjLinks );

				$varError = $localVar->taintedOriginalError ?? [];
				$error = TaintednessBaseVisitor::mergeCausedByLines( $error, $varError );
			}

			$variable->taintedness = $taintedness;
			$variable->taintedMethodLinks = $methodLinks;
			$variable->taintedOriginalError = $error;
		};
	}

	/**
	 * Print the taintedness of a variable, when requested
	 * @see BlockAnalysisVisitor::analyzeSubstituteVarAssert()
	 * @inheritDoc
	 */
	public function analyzeStringLiteralStatement( CodeBase $codeBase, Context $context, string $statement ): bool {
		$found = false;
		if ( preg_match_all( self::DEBUG_TAINTEDNESS_REGEXP, $statement, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $group ) {
				foreach ( explode( ',', $group[1] ) as $rawVar ) {
					$varName = ltrim( trim( $rawVar ), '$' );
					if ( $context->getScope()->hasVariableWithName( $varName ) ) {
						$var = $context->getScope()->getVariableByName( $varName );
						$taint = property_exists( $var, 'taintedness' )
							? $var->taintedness->toShortString()
							: 'unset';
						$msg = "Variable {CODE} has taintedness: {DETAILS}";
						$params = [ "\$$varName", $taint ];
					} else {
						$msg = "Variable {CODE} doesn't exist in scope";
						$params = [ "\$$varName" ];
					}
					self::emitIssue(
						$codeBase,
						$context,
						'SecurityCheckDebugTaintedness',
						$msg,
						$params
					);
					$found = true;
				}
			}
		}
		return $found;
	}

	/**
	 * Get a string representation of a taint integer
	 *
	 * The prefix ~ means all input taints except the letter given.
	 * The prefix * means the EXEC version of the taint.
	 *
	 * @param int $taint
	 * @return string
	 */
	public static function taintToString( int $taint ) : string {
		if ( $taint === self::NO_TAINT ) {
			return 'NONE';
		}

		// Note, order matters here.
		static $mapping = [
			self::YES_TAINT => 'YES',
			self::YES_TAINT &
			( ~self::HTML_TAINT ) => '~HTML',
			self::YES_TAINT &
			( ~self::SQL_TAINT ) => '~SQL',
			self::YES_TAINT &
			( ~self::SHELL_TAINT ) => '~SHELL',
			self::YES_TAINT &
			( ~self::SERIALIZE_TAINT ) => '~SERIALIZE',
			self::YES_TAINT &
			( ~self::CUSTOM1_TAINT ) => '~CUSTOM1',
			self::YES_TAINT &
			( ~self::CUSTOM2_TAINT ) => '~CUSTOM2',
			// We skip ~ versions of flags which shouldn't be possible.
			self::HTML_TAINT => 'HTML',
			self::SQL_TAINT => 'SQL',
			self::SHELL_TAINT => 'SHELL',
			self::ESCAPED_TAINT => 'ESCAPED',
			self::SERIALIZE_TAINT => 'SERIALIZE',
			self::CUSTOM1_TAINT => 'CUSTOM1',
			self::CUSTOM2_TAINT => 'CUSTOM2',
			self::CODE_TAINT => 'CODE',
			self::PATH_TAINT => 'PATH',
			self::REGEX_TAINT => 'REGEX',
			self::MISC_TAINT => 'MISC',
			self::SQL_NUMKEY_TAINT => 'SQL_NUMKEY',
			self::ARRAY_OK => 'ARRAY_OK',
			self::HTML_EXEC_TAINT => '*HTML',
			self::SQL_EXEC_TAINT => '*SQL',
			self::SHELL_EXEC_TAINT => '*SHELL',
			self::ESCAPED_EXEC_TAINT => '*ESCAPED',
			self::SERIALIZE_EXEC_TAINT => '*SERIALIZE',
			self::CUSTOM1_EXEC_TAINT => '*CUSTOM1',
			self::CUSTOM2_EXEC_TAINT => '*CUSTOM2',
			self::CODE_EXEC_TAINT => '*CODE',
			self::PATH_EXEC_TAINT => '*PATH',
			self::REGEX_EXEC_TAINT => '*REGEX',
			self::MISC_EXEC_TAINT => '*MISC',
			self::SQL_NUMKEY_EXEC_TAINT => '*SQL_NUMKEY',
		];

		$types = [];
		foreach ( $mapping as $bitmap => $val ) {
			if ( ( $bitmap & $taint ) === $bitmap ) {
				$types[] = $val;
				$taint &= ~$bitmap;
			}
		}
		// Catch-all flags
		if ( ( $taint & self::ALL_EXEC_TAINT ) !== 0 ) {
			$types[] = '*ALL';
			$taint &= ~self::ALL_EXEC_TAINT;
		}
		if ( ( $taint & self::ALL_TAINT ) !== 0 ) {
			$types[] = 'ALL';
		}
		$taintTypes = implode( ', ', $types );
		$flags = [];
		if ( ( $taint & self::RAW_PARAM ) === self::RAW_PARAM ) {
			$flags[] = 'raw param';
		}
		if ( ( $taint & self::VARIADIC_PARAM ) === self::VARIADIC_PARAM ) {
			$flags[] = 'variadic param';
		}
		if ( $flags ) {
			$taintTypes .= ' (' . implode( ', ', $flags ) . ')';
		}
		return $taintTypes;
	}

	/**
	 * Get the taintedness of a function
	 *
	 * This allows overriding the default taint of a function
	 *
	 * If you want to provide custom taint hints for your application,
	 * override the getCustomFuncTaints()
	 *
	 * @param FullyQualifiedFunctionLikeName $fqsen The function/method in question
	 * @return FunctionTaintedness|null Null to autodetect taintedness
	 */
	public function getBuiltinFuncTaint( FullyQualifiedFunctionLikeName $fqsen ) : ?FunctionTaintedness {
		$name = (string)$fqsen;

		if ( isset( self::$builtinFuncTaintCache[$name] ) ) {
			return clone self::$builtinFuncTaintCache[$name];
		}

		static $funcTaints = null;
		if ( $funcTaints === null ) {
			$funcTaints = $this->getCustomFuncTaints() + $this->getPHPFuncTaints();
		}

		if ( isset( $funcTaints[$name] ) ) {
			$intTaint = $funcTaints[$name];
			$taint = [];
			foreach ( $intTaint as $i => $val ) {
				$objVal = new Taintedness( $val );
				// For backcompat, make self::NO_OVERRIDE always be set.
				$objVal->add( self::NO_OVERRIDE );
				$taint[$i] = $objVal;
			}
			self::$builtinFuncTaintCache[$name] = FunctionTaintedness::newFromArray( $taint );
			return clone self::$builtinFuncTaintCache[$name];
		}
		return null;
	}

	/**
	 * Get an array of function taints custom for the application
	 *
	 * @return int[][] Array of function taints with 'overall' string key and numeric
	 *   keys for parameters. This is the same format
	 *   as FunctionTaintedness objects, except in array form.
	 *
	 *   For example: [ self::YES_TAINT, 'overall' => self::NO_TAINT ]
	 *   means that the taint of the return value is the same as the taint
	 *   of the the first arg, and all other args are ignored.
	 *   [ self::HTML_EXEC_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the first arg is output in an html context (e.g. like echo)
	 *   [ self::YES_TAINT & ~self::HTML_TAINT, 'overall' => self::NO_TAINT ]
	 *   Means that the function removes html taint (escapes) e.g. htmlspecialchars
	 *   [ 'overall' => self::YES_TAINT ]
	 *   Means that it returns a tainted value (e.g. return $_POST['foo']; )
	 * @see FunctionTaintedness for more details
	 * @phan-return array<string,int[]>
	 */
	abstract protected function getCustomFuncTaints() : array;

	/**
	 * Can be used to force specific issues to be marked false positives
	 *
	 * For example, a specific application might be able to recognize
	 * that we are in a CLI context, and thus the XSS is really a false positive.
	 *
	 * @note The $lhsTaint parameter uses the self::*_TAINT constants,
	 *   NOT the *_EXEC_TAINT constants.
	 * @param Taintedness $lhsTaint The dangerous taints to be output (e.g. LHS of assignment)
	 * @param Taintedness $rhsTaint The taint of the expression
	 * @param string &$msg Issue description (so plugin can modify to state why false)
	 * @param Context $context
	 * @param CodeBase $code_base
	 * @return bool Is this a false positive?
	 * @suppress PhanUnusedPublicMethodParameter No param is used
	 */
	public function isFalsePositive(
		Taintedness $lhsTaint,
		Taintedness $rhsTaint,
		string &$msg,
		Context $context,
		CodeBase $code_base
	) : bool {
		return false;
	}

	/**
	 * Given a param description line, extract taint
	 *
	 * This is to allow putting taint information in method docblocks.
	 * If a function has a docblock comment like:
	 *  *  @param-taint $foo escapes_html
	 * This converts that line into:
	 *   ( self::YES_TAINT & ~self::SQL_TAINT )
	 * Multiple taint types are separated by commas
	 * (which are interpreted as bitwise OR ( "|" ). Future versions
	 * might support more complex bitwise operators, but for now it
	 * doesn't seem needed.
	 *
	 * The following keywords are supported where {type} can be
	 * html, sql, shell, serialize, custom1, custom2, misc, sql_numkey,
	 * escaped.
	 *  * {type} - just set the flag. 99% you should only use 'none' or 'tainted'
	 *  * exec_{type} - sets the exec flag.
	 *  * escapes_{type} - self::YES_TAINT & ~self::{type}_TAINT.
	 *     Note: escapes_html adds the exec_escaped flag, use
	 *     escapes_htmlnoent if the value is safe to double encode.
	 *  * onlysafefor_{type}
	 *     Same as above, intended for return type declarations.
	 *     Only difference is that onlysafefor_html sets ESCAPED_TAINT instead
	 *     of ESCAPED_EXEC_TAINT
	 *  * none - self::NO_TAINT
	 *  * tainted - self::YES_TAINT
	 *  * array_ok - sets self::ARRAY_OK
	 *  * allow_override - Allow autodetected taints to override annotation
	 *
	 * @todo Should UNKNOWN_TAINT be in here? What about ~ operator?
	 * @note The special casing to have escapes_html always add exec_escaped
	 *   (and having htmlnoent exist) is "experimental" and may change in
	 *   future versions (Maybe all types should set exec_escaped. Maybe it
	 *   should be explicit)
	 * @param string $line A line from the docblock
	 * @return Taintedness|null null on no info
	 */
	public static function parseTaintLine( string $line ) : ?Taintedness {
		$types = '(?P<type>htmlnoent|html|sql|shell|serialize|custom1|'
			. 'custom2|misc|code|path|regex|sql_numkey|escaped|none|tainted)';
		$prefixes = '(?P<prefix>escapes|onlysafefor|exec)';
		$taintExpr = "/^(?P<taint>(?:${prefixes}_)?$types|array_ok|allow_override|raw_param)$/";

		$taints = explode( ',', strtolower( $line ) );
		$taints = array_map( 'trim', $taints );

		$overallTaint = new Taintedness( self::NO_OVERRIDE );
		$numberOfTaintsProcessed = 0;
		foreach ( $taints as $taint ) {
			$taintParts = [];
			if ( !preg_match( $taintExpr, $taint, $taintParts ) ) {
				continue;
			}
			$numberOfTaintsProcessed++;
			if ( $taintParts['taint'] === 'array_ok' ) {
				$overallTaint->add( self::ARRAY_OK );
				continue;
			}
			if ( $taintParts['taint'] === 'allow_override' ) {
				$overallTaint->remove( self::NO_OVERRIDE );
				continue;
			}
			if ( $taintParts['taint'] === 'raw_param' ) {
				$overallTaint->add( self::RAW_PARAM );
				continue;
			}
			$taintAsInt = new Taintedness( self::convertTaintNameToConstant( $taintParts['type'] ) );
			switch ( $taintParts['prefix'] ) {
				case '':
					$overallTaint->add( $taintAsInt );
					break;
				case 'exec':
					$overallTaint->add( $taintAsInt->asYesToExecTaint() );
					break;
				case 'escapes':
				case 'onlysafefor':
					$overallTaint->add( Taintedness::newTainted()->without( $taintAsInt ) );
					if ( $taintParts['type'] === 'html' ) {
						if ( $taintParts['prefix'] === 'escapes' ) {
							$overallTaint->add( self::ESCAPED_EXEC_TAINT );
						} else {
							$overallTaint->add( self::ESCAPED_TAINT );
						}
					}
					break;
			}
		}
		if ( $numberOfTaintsProcessed === 0 ) {
			return null;
		}
		return $overallTaint;
	}

	/**
	 * Hook to override how taint of an argument to method call is calculated
	 *
	 * @param Taintedness $curArgTaintedness
	 * @param Node $argument Note: This hook is not called on literals
	 * @param int $argIndex Which argument number is this
	 * @param FunctionInterface $func The function/method being called
	 * @param FunctionTaintedness $funcTaint Taint of method formal parameters
	 * @param Context $context Context object
	 * @param CodeBase $code_base CodeBase object
	 * @return Taintedness The taint to use for actual parameter
	 * @suppress PhanUnusedPublicMethodParameter
	 */
	public function modifyArgTaint(
		Taintedness $curArgTaintedness,
		Node $argument,
		int $argIndex,
		FunctionInterface $func,
		FunctionTaintedness $funcTaint,
		Context $context,
		CodeBase $code_base
	) : Taintedness {
		// no-op
		return $curArgTaintedness;
	}

	/**
	 * Convert a string like 'html' to self::HTML_TAINT.
	 *
	 * @note htmlnoent treated like self::HTML_TAINT.
	 * @param string $name one of:
	 *   html, sql, shell, serialize, custom1, custom2, code, path, regex, misc, sql_numkey,
	 *   escaped, none (= self::NO_TAINT), tainted (= self::YES_TAINT)
	 * @return int One of the TAINT constants
	 */
	public static function convertTaintNameToConstant( string $name ) : int {
		switch ( $name ) {
			case 'html':
			case 'htmlnoent':
				return self::HTML_TAINT;
			case 'sql':
				return self::SQL_TAINT;
			case 'shell':
				return self::SHELL_TAINT;
			case 'serialize':
				return self::SERIALIZE_TAINT;
			case 'custom1':
				return self::CUSTOM1_TAINT;
			case 'custom2':
				return self::CUSTOM2_TAINT;
			case 'code':
				return self::CODE_TAINT;
			case 'path':
				return self::PATH_TAINT;
			case 'regex':
				return self::REGEX_TAINT;
			case 'misc':
				return self::MISC_TAINT;
			case 'sql_numkey':
				return self::SQL_NUMKEY_TAINT;
			case 'escaped':
				return self::ESCAPED_TAINT;
			case 'tainted':
				return self::YES_TAINT;
			case 'none':
				return self::NO_TAINT;
			default:
				throw new AssertionError( "$name not valid taint" );
		}
	}

	/**
	 * Taints for builtin php functions
	 *
	 * @return int[][] List of func taints (See getBuiltinFuncTaint())
	 * @phan-return array<string,int[]>
	 */
	protected function getPHPFuncTaints() : array {
		$pregMatchTaint = [
			self::REGEX_EXEC_TAINT,
			self::YES_TAINT,
			self::NO_TAINT, // TODO Possibly unsafe pass-by-ref,
			self::NO_TAINT,
			self::NO_TAINT,
			'overall' => self::NO_TAINT,
		];
		$pregReplaceTaint = [
			self::REGEX_EXEC_TAINT,
			self::YES_TAINT, // TODO This is used for strings (in preg_replace) and callbacks (in preg_replace_callback)
			self::YES_TAINT,
			self::NO_TAINT,
			self::NO_TAINT,
			'overall' => self::NO_TAINT
		];
		return [
			'\htmlentities' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\htmlspecialchars' => [
				self::ESCAPES_HTML,
				'overall' => self::ESCAPED_TAINT
			],
			'\escapeshellarg' => [
				~self::SHELL_TAINT & self::YES_TAINT,
				'overall' => self::NO_TAINT
			],
			// TODO Perhaps we should distinguish arguments escape vs command escape
			'\escapeshellcmd' => [
				~self::SHELL_TAINT & self::YES_TAINT,
				'overall' => self::NO_TAINT
			],
			'\shell_exec' => [
				self::SHELL_EXEC_TAINT,
				'overall' => self::YES_TAINT
			],
			'\passthru' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\exec' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT, // TODO: This is an unsafe passbyref
				self::NO_TAINT,
				'overall' => self::YES_TAINT
			],
			'\system' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::YES_TAINT
			],
			'\proc_open' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT, // TODO Unsafe passbyref
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT  // TODO Perhaps not so safe
			],
			'\popen' => [
				self::SHELL_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT  // TODO Perhaps not so safe
			],
			// Or any time the serialized data comes from a trusted source.
			'\serialize' => [
				'overall' => self::YES_TAINT & ~self::SERIALIZE_TAINT,
			],
			'\unserialize' => [
				self::SERIALIZE_EXEC_TAINT,
				'overall' => self::NO_TAINT,
			],
			'\mysql_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli_real_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\mysqli::real_query' => [
				self::SQL_EXEC_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\sqlite_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			'\sqlite_single_query' => [
				self::NO_TAINT,
				self::SQL_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::UNKNOWN_TAINT
			],
			// Note: addslashes, addcslashes etc. intentionally omitted because they're not
			// enough to avoid SQLi.
			'\mysqli_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli_real_escape_string' => [
				self::NO_TAINT,
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\mysqli::real_escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\sqlite_escape_string' => [
				self::YES_TAINT & ~self::SQL_TAINT,
				'overall' => self::NO_TAINT
			],
			'\base64_encode' => [
				self::YES_TAINT & ~self::HTML_TAINT,
				'overall' => self::NO_TAINT
			],
			'\file_put_contents' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			// TODO What about file_get_contents() and file() ?
			'\fopen' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT // TODO Perhaps not so safe
			],
			'\opendir' => [
				self::PATH_EXEC_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT // TODO Perhaps not so safe
			],
			'\rawurlencode' => [
				self::YES_TAINT & ~self::PATH_TAINT,
				'overall' => self::NO_TAINT
			],
			'\urlencode' => [
				self::YES_TAINT & ~self::PATH_TAINT,
				'overall' => self::NO_TAINT
			],
			'\printf' => [
				self::HTML_EXEC_TAINT,
				// TODO We could check if the respective specifiers are safe
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				self::HTML_EXEC_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_filter' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_grep' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_match_all' => $pregMatchTaint,
			'\preg_match' => $pregMatchTaint,
			'\preg_quote' => [
				self::YES_TAINT & ~self::REGEX_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_replace' => $pregReplaceTaint,
			'\preg_replace_callback' => $pregReplaceTaint,
			'\preg_replace_callback_array' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			],
			'\preg_split' => [
				self::REGEX_EXEC_TAINT,
				self::YES_TAINT,
				self::NO_TAINT,
				self::NO_TAINT,
				'overall' => self::NO_TAINT
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function getBeforeLoopBodyAnalysisVisitorClassName() : string {
		return TaintednessLoopVisitor::class;
	}
}
