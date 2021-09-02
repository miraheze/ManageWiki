<?php

/**
 * Custom report format for Gerrit robot comments.
 *
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

namespace MediaWiki\Reports;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Reports\Report;
use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;

// ignore phan complaining about unused parameters that must be declared to inheritance
// @phan-file-suppress PhanUnusedPublicMethodParameter

/**
 * Custom report format for Gerrit robot comments. Outputs a JSON array of Gerrit
 * RobotCommentInput entities.
 * Relies on the following environment variables:
 * - PHPCS_ROOT_DIR: the repo root compared to which Gerrit expects file paths
 * - GERRIT_ROBOT_ID, GERRIT_ROBOT_RUN_ID, GERRIT_URL: Gerrit robot comment parameters.
 *   Typically these would be the robot username, Jenkins build number and Jenkins
 *   console output URL, respectively.
 *
 * @see https://gerrit.wikimedia.org/r/Documentation/config-robot-comments.html
 * @see https://gerrit.wikimedia.org/r/Documentation/rest-api-changes.html#set-review
 * @see https://gerrit-review.googlesource.com/Documentation/rest-api-changes.html#robot-comment-input
 */
class GerritRobotComments implements Report {

	private const TYPE_ERROR = 'ERROR';
	// private const TYPE_WARNING = 'WARNING';

	/** @var array[] */
	private $robotComments = [];

	/** @var string[] */
	private $diffs = [];

	/**
	 * Generate a partial report for a single processed file.
	 *
	 * Function should return TRUE if it printed or stored data about the file
	 * and FALSE if it ignored the file. Returning TRUE indicates that the file and
	 * its data should be counted in the grand totals.
	 *
	 * @param array $report Prepared report data.
	 * @param File $phpcsFile The file being reported on.
	 * @param bool $showSources Show sources?
	 * @param int $width Maximum allowed line width.
	 *
	 * @return bool
	 */
	public function generateFileReport( $report, File $phpcsFile, $showSources = false, $width = 80 ) {
		if ( $report['errors'] === 0 && $report['warnings'] === 0 ) {
			// Nothing to print.
			return false;
		}

		$path = $phpcsFile->path;
		$pathPrefix = getenv( 'PHPCS_ROOT_DIR' ) ?: '';
		$pathPrefix = rtrim( $pathPrefix, '/' ) . '/';
		if ( $pathPrefix && strpos( $path, $pathPrefix ) === 0 ) {
			$path = substr( $path, strlen( $pathPrefix ) );
		}

		foreach ( $report['messages'] as $line => $lineIssues ) {
			foreach ( $lineIssues as $columnIssues ) {
				foreach ( $columnIssues as $issue ) {
					$this->processIssue( $path, $line, $issue['message'], $issue['source'], $issue['type'] );
				}
			}
		}

		// Based on the Diff report.
		if ( $phpcsFile->getFixableCount() ) {
			$phpcsFile->disableCaching();
			$tokens = $phpcsFile->getTokens();
			if ( !$tokens === true ) {
				$phpcsFile->parse();
				$phpcsFile->fixer->startFile( $phpcsFile );
			}
			$fixed = $phpcsFile->fixer->fixFile();
			if ( $fixed ) {
				$diff = $phpcsFile->fixer->generateDiff( null, false );
				// PHPCS generates diffs via a temporary file so we end up with a weird filename. Fix that.
				$diff = preg_replace( '/^\+\+\+ PHP_CodeSniffer/m', "+++ $path", $diff );
				$this->diffs[$path] = $diff;
			}
		}

		return true;
	}

	/**
	 * Generate the actual report.
	 *
	 * @param string $cachedData Any partial report data that was returned from
	 *   generateFileReport during the run.
	 * @param int $totalFiles Total number of files processed during the run.
	 * @param int $totalErrors Total number of errors found during the run.
	 * @param int $totalWarnings Total number of warnings found during the run.
	 * @param int $totalFixable Total number of problems that can be fixed.
	 * @param bool $showSources Show sources?
	 * @param int $width Maximum allowed line width.
	 * @param bool $interactive Are we running in interactive mode?
	 * @param bool $toScreen Is the report being printed to screen?
	 * @return void
	 */
	public function generate(
		$cachedData,
		$totalFiles,
		$totalErrors,
		$totalWarnings,
		$totalFixable,
		$showSources = false,
		$width = 80,
		$interactive = false,
		$toScreen = true
	) {
		foreach ( $this->diffs as $file => $diff ) {
			$fixComment = $this->makeRobotComment( 0, 'Click "SHOW FIX" to automatically fix all issues in this file'
				. ' (click "APPLY FIX" then "PUBLISH EDIT" afterwards).', self::TYPE_ERROR );
			$fixComment['fix_suggestions'][] = $this->makeFixSuggestionInfoFromDiff( $file, $diff );
			$this->robotComments[$file][] = $fixComment;
		}

		if ( !$this->robotComments ) {
			return;
		}
		echo json_encode( $this->robotComments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
	}

	/**
	 * @param string $file
	 * @param int $line
	 * @param string $message Human-readable error message
	 * @param string $source Sniff name
	 * @param string $type One of the TYPE_* constants
	 * @return void
	 */
	protected function processIssue(
		string $file,
		int $line,
		string $message,
		string $source,
		string $type
	) {
		$finalMessage = "$message\n($source)";
		$this->robotComments[$file][] = $this->makeRobotComment( $line, $finalMessage, $type );
	}

	/**
	 * Create a RobotCommentInfo, a data structure used by the Gerrit API to create a robot
	 * comment describing a CI error, optionally with an attached fix.
	 * @param int $line
	 * @param string $message
	 * @param string $type
	 * @return array A JSON-compatible data structure.
	 * @see https://gerrit.wikimedia.org/r/Documentation/rest-api-changes.html#robot-comment-input
	 */
	protected function makeRobotComment(
		int $line,
		string $message,
		string $type
	) {
		return [
			'robot_id' => getenv( 'GERRIT_ROBOT_ID' ) ?: 'mediawiki-codesniffer',
			'robot_run_id' => getenv( 'GERRIT_ROBOT_RUN_ID' ) ?: 'unspecified',
			'url' => getenv( 'GERRIT_URL' )
				?: 'https://gerrit.wikimedia.org/r/plugins/gitiles/mediawiki/tools/codesniffer/',
			'line' => $line,
			'message' => $message,
			'unresolved' => $type === self::TYPE_ERROR,
		];
	}

	/**
	 * Create a FixSuggestionInfo, a data structure used by the Gerrit API to create a robot
	 * comment describing a CI error, optionally with an attached fix.
	 * @param string $file Path to file
	 * @param string $diffString Changes in unified diff format
	 * @return array A JSON-compatible data structure.
	 * @see https://gerrit.wikimedia.org/r/Documentation/rest-api-changes.html#fix-suggestion-info
	 */
	protected function makeFixSuggestionInfoFromDiff(
		string $file,
		string $diffString
	) {
		$info = [
			'description' => 'PHPCS fixes',
			'replacements' => [],
		];

		$diffParser = new Parser();
		$diffs = $diffParser->parse( $diffString );

		foreach ( $diffs as $diff ) {
			foreach ( $diff->getChunks() as $chunk ) {
				$started = false;
				$startLine = $endLine = $chunk->getStart();
				$replacement = [];
				foreach ( $chunk->getLines() as $line ) {
					if ( $line->getType() === Line::ADDED ) {
						$started = true;
						$replacement[] = $line->getContent();
					} elseif ( $line->getType() === Line::REMOVED ) {
						$started = true;
						$endLine++;
					} elseif ( !$started ) {
						$startLine++;
						$endLine++;
					}
				}
				$info['replacements'][] = [
					'path' => $file,
					'range' => [
						'start_line' => $startLine,
						'start_character' => 0,
						'end_line' => $endLine,
						'end_character' => 0,
					],
					'replacement' => implode( PHP_EOL, $replacement ) . PHP_EOL,
				];
			}
		}

		return $info;
	}

}
