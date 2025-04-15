<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler for all interactions with Extension changes within ManageWiki
 */
class ManageWikiExtensions {

	private Config $config;
	private IDatabase $dbw;

	private array $extConfig;
	private array $liveExts = [];
	private array $removedExts = [];

	private string $wiki;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];

	private string $log = 'settings';

	public function __construct( string $wiki ) {
		$this->wiki = $wiki;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$this->extConfig = $this->config->get( 'ManageWikiExtensions' );

		$this->dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		$exts = $this->dbw->selectRow(
			'mw_settings',
			's_extensions',
			[
				's_dbname' => $wiki,
			],
			__METHOD__
		)->s_extensions ?? '[]';

		$logger = LoggerFactory::getInstance( 'ManageWiki' );

		// To simplify clean up and to reduce the need to constantly refer back to many different variables, we now
		// populate extension lists with config associated with them.
		foreach ( json_decode( $exts, true ) as $ext ) {
			if ( !isset( $this->extConfig[$ext] ) ) {
				$logger->error( 'Extension/Skin {ext} not set in wgManageWikiExtensions', [
					'ext' => $ext,
				] );

				continue;
			}

			$this->liveExts[$ext] = $this->extConfig[$ext];
		}
	}

	/**
	 * Lists an array of all extensions currently 'enabled'
	 * @return array 1D array of extensions enabled
	 */
	public function list(): array {
		return array_keys( $this->liveExts );
	}

	/**
	 * Adds an extension to the 'enabled' list
	 * @param string[] $extensions an array of extensions to enable
	 */
	public function add( array $extensions ): void {
		// We allow adding either one extension (string) or many (array)
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			$this->liveExts[$ext] = $this->extConfig[$ext];
			$this->changes[$ext] = [
				'old' => 0,
				'new' => 1,
			];
		}
	}

	/**
	 * Removes an extension from the 'enabled' list
	 * @param string[] $extensions an array of extensions to disable
	 * @param bool $forceRemove Force removing extension incase it is removed from config
	 */
	public function remove(
		array $extensions,
		bool $forceRemove = false
	): void {
		// We allow remove either one extension (string) or many (array)
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			if ( !isset( $this->liveExts[$ext] ) && !$forceRemove ) {
				continue;
			}

			$this->removedExts[$ext] = $this->liveExts[$ext] ?? [];
			unset( $this->liveExts[$ext] );

			$this->changes[$ext] = [
				'old' => 1,
				'new' => 0,
			];
		}
	}

	/**
	 * Allows multiples extensions to be either enabled or disabled
	 * @param array $extensions Array of extensions that should be enabled, absolute
	 */
	public function overwriteAll( array $extensions ): void {
		$overwrittenExts = $this->list();

		foreach ( $this->extConfig as $ext => $extConfig ) {
			if ( !is_string( $ext ) ) {
				continue;
			}

			if ( in_array( $ext, $extensions ) && !in_array( $ext, $overwrittenExts ) ) {
				$this->add( [ $ext ] );
				continue;
			}

			if ( !in_array( $ext, $extensions ) && in_array( $ext, $overwrittenExts ) ) {
				$this->remove( [ $ext ] );
			}
		}
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function hasChanges(): bool {
		return (bool)$this->changes;
	}

	public function setLogAction( string $action ): void {
		$this->log = $action;
	}

	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	public function getLogAction(): ?string {
		return $this->log;
	}

	public function getLogParams(): array {
		return $this->logParams;
	}

	public function commit(): void {
		$remoteWikiFactory = MediaWikiServices::getInstance()->get( 'RemoteWikiFactory' );
		$remoteWiki = $remoteWikiFactory->newInstance( $this->wiki );

		foreach ( $this->liveExts as $name => $extConfig ) {
			// Check if we have a conflict first
			if ( in_array( $extConfig['conflicts'] ?? [], $this->list() ) ) {
				unset( $this->liveExts[$name] );
				unset( $this->changes[$name] );
				$this->errors[] = [
					'managewiki-error-conflict' => [
						$extConfig['name'],
						$extConfig['conflicts'],
					],
				];

				// We have a conflict and we have unset it. Therefore we have nothing else to do for this extension
				continue;
			}

			// Define a 'current' extension as one with no changes entry
			$enabledExt = !isset( $this->changes[$name] );
			// Now we need to check if we fulfil the requirements to enable this extension
			$requirementsCheck = ManageWikiRequirements::process( $extConfig['requires'] ?? [], $this->list(), $enabledExt, $remoteWiki );

			if ( $requirementsCheck ) {
				$installResult = ( !isset( $extConfig['install'] ) || $enabledExt ) ? true : ManageWikiInstaller::process( $this->wiki, $extConfig['install'] );

				if ( !$installResult ) {
					unset( $this->liveExts[$name] );
					unset( $this->changes[$name] );
					$this->errors[] = [
						'managewiki-error-install' => [
							$extConfig['name'],
						],
					];
				}

				continue;
			}

			unset( $this->liveExts[$name] );
			unset( $this->changes[$name] );
			$this->errors[] = [
				'managewiki-error-requirements' => [
					$extConfig['name'],
				],
			];
		}

		foreach ( $this->removedExts as $name => $extConfig ) {
			// Unlike installing, we are not too fussed about whether this fails, let us just do it
			if ( isset( $extConfig['remove'] ) ) {
				ManageWikiInstaller::process( $this->wiki, $extConfig['remove'], false );
			}
		}

		$this->write();

		$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
		$data = $dataFactory->newInstance( $this->wiki );
		$data->resetWikiData( isNewChanges: true );

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}

	private function write(): void {
		$this->dbw->upsert(
			'mw_settings',
			[
				's_dbname' => $this->wiki,
				's_extensions' => json_encode( $this->list() ),
			],
			[ [ 's_dbname' ] ],
			[
				's_extensions' => json_encode( $this->list() ),
			],
			__METHOD__
		);
	}
}
