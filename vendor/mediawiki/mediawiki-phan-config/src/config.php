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

use MediaWikiPhanConfig\ConfigBuilder;

// TODO: Use \Phan\Config::projectPath()
$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../..';

$VP = getenv( 'MW_VENDOR_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_VENDOR_PATH' ) )
	: $IP;

// Replace \\ by / for windows users to let exclude work correctly
$DIR = str_replace( '\\', '/', __DIR__ );

// TODO: Do we need to explicitly set these? If so, move to ConfigBuilder. Remove otherwise.
$baseOptions = [
	'backward_compatibility_checks' => false,

	'parent_constructor_required' => [
	],

	'quick_mode' => false,
	'analyze_signature_compatibility' => true,
	'ignore_undeclared_variables_in_global_scope' => false,
	'read_type_annotations' => true,
	'disable_suppression' => false,
	'dump_ast' => false,
	'dump_signatures_file' => null,
	'processes' => 1,
	'whitelist_issue_types' => [],
	'markdown_issue_messages' => false,
	'generic_types_enabled' => true,
	'plugins' => [
		'PregRegexCheckerPlugin',
		'UnusedSuppressionPlugin',
		'DuplicateExpressionPlugin',
	],
	'plugin_config' => [],
	// BC for repos not checking whether these are set
	'file_list' => [],
	'exclude_file_list' => [],
];

$baseCfg = new ConfigBuilder( $IP, $baseOptions );

if ( !defined( 'MSG_EOR' ) ) {
	$baseCfg->addFiles( $DIR . '/stubs/sockets.windows.php' );
}

/**
 * Internal helper used to filter dirs. This is used so that we can include commonly-used dir
 * names without phan complaining about "directory not found". It should NOT be used in
 * repo-specific config files.
 */
$filterDirs = function ( array $dirs ) : array {
	return array_filter( $dirs, 'file_exists' );
};

$baseCfg = $baseCfg
	->setDirectoryList( $filterDirs( [
		'includes/',
		'src/',
		'maintenance/',
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
	] ) )
	->setExcludedDirectoryList( [
		'.phan/stubs/',
		$IP . '/includes',
		$IP . '/languages',
		$IP . '/maintenance',
		$IP . '/.phan/stubs/',
		$VP . '/vendor',
		$DIR . '/stubs',
	] )
	->setExcludeFileRegex(
		'@vendor/(' .
		// Exclude known dev dependencies
		'(' . implode( '|', [
			'composer/installers',
			'jakub-onderka/php-console-color',
			'jakub-onderka/php-console-highlighter',
			'jakub-onderka/php-parallel-lint',
			'mediawiki/mediawiki-codesniffer',
			'microsoft/tolerant-php-parser',
			'phan/phan',
			'phpunit/php-code-coverage',
			'squizlabs/php_codesniffer',
		] ) . ')' .
		'|' .
		// Also exclude tests folder from dependencies
		'.*/[Tt]ests?' .
		')/@'
	)
	->setMinimumSeverity( 0 )
	->allowMissingProperties( false )
	->allowNullCastsAsAnyType( false )
	->allowScalarImplicitCasts( false )
	->enableDeadCodeDetection( false )
	->shouldDeadCodeDetectionPreferFalseNegatives( true )
	// TODO Enable by default
	->setProgressBarMode( ConfigBuilder::PROGRESS_BAR_DISABLED )
	->setSuppressedIssuesList( [
		'PhanDeprecatedFunction',
		'PhanDeprecatedClass',
		'PhanDeprecatedClassConstant',
		'PhanDeprecatedFunctionInternal',
		'PhanDeprecatedInterface',
		'PhanDeprecatedProperty',
		'PhanDeprecatedTrait',
		'PhanUnreferencedUseNormal',

		// https://github.com/phan/phan/issues/3420
		'PhanAccessClassConstantInternal',
		'PhanAccessClassInternal',
		'PhanAccessConstantInternal',
		'PhanAccessMethodInternal',
		'PhanAccessPropertyInternal',

		// These are quite PHP8-specific
		'PhanParamNameIndicatingUnused',
		'PhanParamNameIndicatingUnusedInClosure',
		'PhanProvidingUnusedParameter',
	] )
	->readClassAliases( true )
	->enableRedundantConditionDetection( true )
	->addGlobalsWithTypes( [
		'wgContLang' => '\\Language',
		'wgParser' => '\\Parser',
		'wgTitle' => '\\Title',
		'wgMemc' => '\\BagOStuff',
		'wgUser' => '\\User',
		'wgConf' => '\\SiteConfiguration',
		'wgLang' => '\\Language',
		'wgOut' => '\\OutputPage',
		'wgRequest' => '\\WebRequest',
	] );

// Hacky variable to quickly disable taint-check if something explodes.
// @note This is **NOT** a stable feature. It's only for BC and could be removed or changed
// without prior notice.
$baseCfg->makeTaintCheckAdjustments( !isset( $disableTaintCheck ), $DIR, $IP );

// BC: We're not ready to use the ConfigBuilder everywhere
return $baseCfg->make();
