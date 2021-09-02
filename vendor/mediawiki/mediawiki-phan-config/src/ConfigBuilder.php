<?php
/** @noinspection PhpUnused */

namespace MediaWikiPhanConfig;

use InvalidArgumentException;

class ConfigBuilder {
	public const PROGRESS_BAR_DISABLED = 0;
	public const PROGRESS_BAR_STANDARD = 1;
	public const PROGRESS_BAR_LONG = 2;

	/** @var array */
	private $options;
	/** @var string */
	private $installPath;

	/**
	 * @param string $installPath
	 * @param array $baseOptions Options to start with, if any. This should only be used in edge
	 *   cases, or complicated config files (e.g. the base file in this repo).
	 */
	public function __construct( string $installPath, array $baseOptions = [] ) {
		$this->installPath = rtrim( $installPath, '/' );
		$this->options = $baseOptions;
	}

	/**
	 * @return array
	 */
	public function make() : array {
		return $this->options;
	}

	/**
	 * @param array $list
	 * @return $this
	 */
	public function setFileList( array $list ) : self {
		$this->options['file_list'] = $list;
		return $this;
	}

	/**
	 * @param string ...$files
	 * @return $this
	 */
	public function addFiles( string ...$files ) : self {
		$this->options['file_list'] = array_merge(
			$this->options['file_list'] ?? [],
			$files
		);
		return $this;
	}

	/**
	 * @param array $list
	 * @return $this
	 */
	public function setExcludedFileList( array $list ) : self {
		$this->options['exclude_file_list'] = $list;
		return $this;
	}

	/**
	 * @param string ...$files
	 * @return $this
	 */
	public function excludeFiles( string ...$files ) : self {
		$this->options['exclude_file_list'] = array_merge(
			$this->options['exclude_file_list'] ?? [],
			$files
		);
		return $this;
	}

	/**
	 * @param string $regex
	 * @return $this
	 */
	public function setExcludeFileRegex( string $regex ) : self {
		$this->options['exclude_file_regex'] = $regex;
		return $this;
	}

	/**
	 * @param array $list
	 * @return $this
	 */
	public function setDirectoryList( array $list ) : self {
		$this->options['directory_list'] = $list;
		return $this;
	}

	/**
	 * @param string ...$dirs
	 * @return $this
	 */
	public function addDirectories( string ...$dirs ) : self {
		$this->options['directory_list'] = array_merge(
			$this->options['directory_list'] ?? [],
			$dirs
		);
		return $this;
	}

	/**
	 * @param array $list
	 * @return $this
	 */
	public function setExcludedDirectoryList( array $list ) : self {
		$this->options['exclude_analysis_directory_list'] = $list;
		return $this;
	}

	/**
	 * @param string ...$dirs
	 * @return $this
	 */
	public function excludeDirectories( string ...$dirs ) : self {
		$this->options['exclude_analysis_directory_list'] = array_merge(
			$this->options['exclude_analysis_directory_list'] ?? [],
			$dirs
		);
		return $this;
	}

	/**
	 * @param int $minSev
	 * @return $this
	 */
	public function setMinimumSeverity( int $minSev ) : self {
		$this->options['minimum_severity'] = $minSev;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function allowMissingProperties( bool $yn ) : self {
		$this->options['allow_missing_properties'] = $yn;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function allowScalarImplicitCasts( bool $yn ) : self {
		$this->options['scalar_implicit_cast'] = $yn;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function allowNullCastsAsAnyType( bool $yn ) : self {
		$this->options['null_casts_as_any_type'] = $yn;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function enableDeadCodeDetection( bool $yn ) : self {
		$this->options['dead_code_detection'] = $yn;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function shouldDeadCodeDetectionPreferFalseNegatives( bool $yn ) : self {
		$this->options['dead_code_detection_prefer_false_negative'] = $yn;
		return $this;
	}

	/**
	 * @param int $mode One of the PROGRESS_BAR_* constants
	 * @return $this
	 */
	public function setProgressBarMode( int $mode ) : self {
		switch ( $mode ) {
			case self::PROGRESS_BAR_DISABLED:
				$this->options['progress_bar'] = false;
				break;
			case self::PROGRESS_BAR_STANDARD:
				$this->options['progress_bar'] = true;
				break;
			case self::PROGRESS_BAR_LONG:
				$this->options['progress_bar'] = true;
				$this->options['long_progress_bar'] = true;
				break;
			default:
				throw new InvalidArgumentException( "Invalid $mode" );
		}
		return $this;
	}

	/**
	 * @param array $list
	 * @return $this
	 */
	public function setSuppressedIssuesList( array $list ) : self {
		$this->options['suppress_issue_types'] = $list;
		return $this;
	}

	/**
	 * @param string ...$types
	 * @return $this
	 */
	public function suppressIssueTypes( string ...$types ) : self {
		$this->options['suppress_issue_types'] = array_merge(
			$this->options['suppress_issue_types'] ?? [],
			$types
		);
		return $this;
	}

	/**
	 * @param array $globals [ 'global_name' => 'union_type' ]
	 * @return $this
	 */
	public function addGlobalsWithTypes( array $globals ) : self {
		$this->options['globals_type_map'] = array_merge(
			$this->options['globals_type_map'] ?? [],
			$globals
		);
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function readClassAliases( bool $yn ) : self {
		$this->options['enable_class_alias_support'] = $yn;
		return $this;
	}

	/**
	 * @param bool $yn
	 * @return $this
	 */
	public function enableRedundantConditionDetection( bool $yn ) : self {
		$this->options['redundant_condition_detection'] = $yn;
		return $this;
	}

	/**
	 * @param string[] $names
	 * @param string $type 'extension' or 'skin'
	 * @return string[]
	 */
	private function getDependenciesPaths( array $names, string $type ) : array {
		return array_map(
			function ( string $name ) use ( $type ) : string {
				$dir = $type === 'extension' ? 'extensions' : 'skins';
				return $this->installPath . "/$dir/$name";
			},
			$names
		);
	}

	/**
	 * @todo Exclude multiple vendor directories
	 * @param string ...$extensions
	 * @return $this
	 */
	public function addExtensionDependencies( string ...$extensions ) : self {
		$extDirs = $this->getDependenciesPaths( $extensions, 'extension' );
		$this->addDirectories( ...$extDirs );
		$this->excludeDirectories( ...$extDirs );
		return $this;
	}

	/**
	 * @todo Exclude multiple vendor directories
	 * @param string ...$skins
	 * @return $this
	 */
	public function addSkinDependencies( string ...$skins ) : self {
		$skinDirs = $this->getDependenciesPaths( $skins, 'skin' );
		$this->addDirectories( ...$skinDirs );
		$this->excludeDirectories( ...$skinDirs );
		return $this;
	}

	/**
	 * @internal
	 * Temporary method to adjust taint-check-related settings, while preserving B/C.
	 * This should only be used by the config file in this repo.
	 *
	 * @param bool $enabled Whether taint-check should be enabled
	 * @param string $curDir
	 * @param string $vendorPath
	 * @return $this
	 */
	public function makeTaintCheckAdjustments(
		bool $enabled,
		string $curDir,
		string $vendorPath
	) : self {
		if ( $enabled ) {
			$taintCheckPath = $curDir . "/../../phan-taint-check-plugin/MediaWikiSecurityCheckPlugin.php";
			if ( !file_exists( $taintCheckPath ) ) {
				$taintCheckPath =
					"$vendorPath/vendor/mediawiki/phan-taint-check-plugin/MediaWikiSecurityCheckPlugin.php";
			}
			$this->options['plugins'][] = $taintCheckPath;
			// Taint-check specific settings. NOTE: don't remove these lines, even if they duplicate some of
			// the settings above. taint-check may fail hard if one of these settings goes missing.
			$this->options['quick_mode'] = false;
			$this->options['record_variable_context_and_scope'] = true;
			$this->options['suppress_issue_types'] = array_merge(
				$this->options['suppress_issue_types'],
				[
					// We obviously don't want to report false positives
					'SecurityCheck-LikelyFalsePositive',
					// This one still has a lot of false positives
					'SecurityCheck-PHPSerializeInjection',
				]
			);
		} else {
			// BC code to avoid failures in case of emergency when taint-check is disabled.
			$this->options['plugin_config']['unused_suppression_ignore_list'] = [
				'SecurityCheck-DoubleEscaped',
				'SecurityCheck-OTHER',
				'SecurityCheck-SQLInjection',
				'SecurityCheck-XSS',
			];
		}
		return $this;
	}
}
