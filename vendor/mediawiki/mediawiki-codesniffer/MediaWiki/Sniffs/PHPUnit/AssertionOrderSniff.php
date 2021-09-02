<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Sniffs\PHPUnit;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Fix uses of assertEquals or assertSame with the actual value before the expected
 * Currently, only catches assertions where the actual value is a variable, and the expected is
 * a literal value (string, boolean, null, number)
 *
 * @author DannyS712
 */
class AssertionOrderSniff implements Sniff {
	use PHPUnitTestTrait;

	private const ASSERTIONS = [
		'assertEquals' => true,
		'assertSame' => true,
	];

	/**
	 * @inheritDoc
	 */
	public function register(): array {
		return [ T_STRING ];
	}

	/**
	 * @param File $phpcsFile
	 * @param int $stackPtr
	 *
	 * @return void|int
	 */
	public function process( File $phpcsFile, $stackPtr ) {
		if ( !$this->isTestFile( $phpcsFile, $stackPtr ) ) {
			return $phpcsFile->numTokens;
		}

		$tokens = $phpcsFile->getTokens();
		if ( $tokens[$stackPtr]['level'] < 2 ) {
			// Needs to be in a method in a class
			return;
		}

		$assertion = $tokens[$stackPtr]['content'];
		if ( !isset( self::ASSERTIONS[$assertion] ) ) {
			// Don't care about this string
			return;
		}

		$opener = $phpcsFile->findNext( T_WHITESPACE, $stackPtr + 1, null, true );
		if ( !isset( $tokens[$opener]['parenthesis_closer'] ) ) {
			// Needs to be a method call
			return $opener;
		}

		$varToken = $phpcsFile->findNext( T_WHITESPACE, $opener + 1, null, true );
		if ( $tokens[$varToken]['code'] !== T_VARIABLE ) {
			// First parameter isn't a variable
			return;
		}

		$commaToken = $phpcsFile->findNext( T_WHITESPACE, $varToken + 1, null, true );
		if ( $tokens[$commaToken]['code'] !== T_COMMA ) {
			// Not followed by a comma, something more complex is going on
			return;
		}

		$expectedToken = $phpcsFile->findNext( T_WHITESPACE, $commaToken + 1, null, true );
		$codesToReplace = [ T_NULL, T_FALSE, T_TRUE, T_LNUMBER, T_DNUMBER, T_CONSTANT_ENCAPSED_STRING ];
		if ( !in_array( $tokens[$expectedToken]['code'], $codesToReplace ) ) {
			// Not a comparison to one of the allowed literals
			return;
		}

		$nextToken = $phpcsFile->findNext( T_WHITESPACE, $expectedToken + 1, null, true );
		if ( !in_array( $tokens[$nextToken]['code'], [ T_COMMA, T_CLOSE_PARENTHESIS ] ) ) {
			// Not followed by a comma and a third parameter, or a closing parenthesis
			// something more complex is going on
			return;
		}

		$fix = $phpcsFile->addFixableWarning(
			'The expected value goes before the actual value in assertions',
			$stackPtr,
			'WrongOrder'
		);
		if ( $fix ) {
			$expectedVal = $tokens[$expectedToken]['content'];
			$actualVal = $tokens[$varToken]['content'];
			$phpcsFile->fixer->replaceToken( $varToken, $expectedVal );
			$phpcsFile->fixer->replaceToken( $expectedToken, $actualVal );
		}

		// There is no way the next assertion can be closer than this
		return $tokens[$opener]['parenthesis_closer'] + 4;
	}

}
