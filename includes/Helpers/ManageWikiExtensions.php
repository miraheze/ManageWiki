<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\RequestContext;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler for all interactions with Extension changes within ManageWiki
 */
class ManageWikiExtensions implements IConfigModule {

	private Config $config;
	private IDatabase $dbw;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $liveExtensions = [];
	private array $removedExtensions = [];
	private array $extensionsConfig;

	private string $dbname;
	private ?string $log = null;

	public function __construct( string $dbname ) {
		$this->dbname = $dbname;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$this->extensionsConfig = $this->config->get( ConfigNames::Extensions );

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$this->dbw = $databaseUtils->getGlobalPrimaryDB();

		$extensions = $this->dbw->newSelectQueryBuilder()
			->select( 's_extensions' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchField();

		$logger = LoggerFactory::getInstance( 'ManageWiki' );

		// To simplify clean up and to reduce the need to constantly refer back to many different variables, we now
		// populate extension lists with config associated with them.
		foreach ( json_decode( $extensions ?: '[]', true ) as $extension ) {
			if ( !isset( $this->extensionsConfig[$extension] ) ) {
				$logger->error( 'Extension/Skin {extension} not set in {config}', [
					'config' => ConfigNames::Extensions,
					'extension' => $extension,
				] );

				continue;
			}

			$this->liveExtensions[$extension] = $this->extensionsConfig[$extension];
		}
	}

	/**
	 * Lists an array of all extensions currently 'enabled'
	 * @return array 1D array of extensions enabled
	 */
	public function list(): array {
		return array_keys( $this->liveExtensions );
	}

	/**
	 * Adds an extension to the 'enabled' list
	 * @param string[] $extensions Array of extensions to enable
	 */
	public function add( array $extensions ): void {
		// We allow adding either one extension (string) or many (array)
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			$this->liveExtensions[$ext] = $this->extensionsConfig[$ext];
			$this->changes[$ext] = [
				'old' => 0,
				'new' => 1,
			];
		}
	}

	/**
	 * Removes an extension from the 'enabled' list
	 * @param string[] $extensions Array of extensions to disable
	 * @param bool $force Force removing extension incase it is removed from config
	 */
	public function remove(
		array $extensions,
		bool $force = false
	): void {
		// We allow remove either one extension (string) or many (array)
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			if ( !isset( $this->liveExtensions[$ext] ) && !$force ) {
				continue;
			}

			$this->removedExtensions[$ext] = $this->liveExtensions[$ext] ?? [];
			unset( $this->liveExtensions[$ext] );

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

		foreach ( $this->extensionsConfig as $ext => $extensionsConfig ) {
			if ( !is_string( $ext ) ) {
				continue;
			}

			if ( in_array( $ext, $extensions, true ) && !in_array( $ext, $overwrittenExts, true ) ) {
				$this->add( [ $ext ] );
				continue;
			}

			if ( !in_array( $ext, $extensions, true ) && in_array( $ext, $overwrittenExts, true ) ) {
				$this->remove( [ $ext ] );
			}
		}
	}

	private function getName( string $extension ): string {
		return $this->extensionsConfig[$extension]['name'] ?? '';
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

	public function getLogAction(): string {
		return $this->log ?? 'settings';
	}

	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	public function getLogParams(): array {
		return $this->logParams;
	}

	public function commit(): void {
		// We use this to check for conflicts only for
		// extensions we are currently enabling.
		// TODO: can we just use liveExtensions for this?
		$enabling = array_keys(
			array_filter(
				$this->changes,
				static fn ( array $change ): bool => ( $change['new'] ?? 0 ) === 1
			)
		);

		foreach ( $this->liveExtensions as $name => $extensionConfig ) {
			// Check if we have a conflict first
			if ( in_array( $extensionConfig['conflicts'], $enabling, true ) ) {
				$this->errors[] = [
					'managewiki-error-conflict' => [
						$this->getName( $extensionConfig['conflicts'] ),
						$extensionConfig['name'],
					],
				];

				// We have a conflict, we have nothing else to do for this extension.
				continue;
			}

			$requirements = $extensionConfig['requires'] ?? [];

			// If we aren't making any changes to this extension,
			// we don't need to check for permissions.
			if ( !isset( $this->changes[$name] ) ) {
				$requirements = array_diff_key(
					$requirements,
					[ 'permissions' => true ]
				);
			}

			// Now we need to check if we fulfill the requirements to enable this extension.
			$requirementsCheck = ManageWikiRequirements::process( $requirements, $this->list() );

			if ( !$requirementsCheck ) {
				$language = RequestContext::getMain()-getLanguage();
				$this->errors[] = [
					'managewiki-error-requirements' => [
						//$extensionConfig['name'],
						$language->listToText( $requirements )
					],
				];

				// Requirements failed, we have nothing else to do for this extension.
				continue;
			}

			// If we aren't making any changes to this extension,
			// we are done with it.
			if ( !isset( $this->changes[$name] ) ) {
				continue;
			}

			if ( $this->getErrors() ) {
				// If we have errors we don't want to save anything
				continue;
			}

			// Requirements passed, proceed to installer
			$installResult = true;
			if ( isset( $extensionConfig['install'] ) ) {
				$installResult = ManageWikiInstaller::process(
					$this->dbname,
					$extensionConfig['install'],
					install: true
				);
			}

			if ( !$installResult ) {
				$this->errors[] = [
					'managewiki-error-install' => [
						$extensionConfig['name'],
					],
				];
			}
		}

		// Early exit if we already have errors
		if ( $this->getErrors() ) {
			// If we have errors we don't want to save anything
			return;
		}

		foreach ( $this->removedExtensions as $name => $extensionConfig ) {
			$requirementsCheck = ManageWikiRequirements::process(
				// We only need to check for permissions when an
				// extension is being disabled.
				actions: array_intersect_key(
					$extensionConfig['requires'] ?? [],
					[ 'permissions' => true ]
				),
				// We don't need this since it's not used for permissions,
				// which is the only thing we need to check here.
				extList: []
			);

			if ( !$requirementsCheck ) {
				$this->errors[] = [
					'managewiki-error-requirements' => [
						$extensionConfig['name'],
					],
				];

				continue;
			}

			if ( $this->getErrors() ) {
				// If we have errors we don't want to save anything
				continue;
			}

			// Unlike installing, we are not too fussed about whether this fails, let us just do it.
			if ( isset( $extensionConfig['remove'] ) ) {
				ManageWikiInstaller::process(
					$this->dbname,
					$extensionConfig['remove'],
					install: false
				);
			}
		}

		if ( $this->getErrors() ) {
			// If we have errors we don't want to save anything
			return;
		}

		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'mw_settings' )
			->row( [
				's_dbname' => $this->dbname,
				's_extensions' => json_encode( $this->list() ),
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 's_dbname' ] )
			->set( [ 's_extensions' => json_encode( $this->list() ) ] )
			->caller( __METHOD__ )
			->execute();

		$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
		$data = $dataFactory->newInstance( $this->dbname );
		$data->resetWikiData( isNewChanges: true );

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
