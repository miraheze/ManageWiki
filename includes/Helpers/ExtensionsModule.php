<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\IModule;
use Psr\Log\LoggerInterface;

class ExtensionsModule implements IModule {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::Extensions,
	];

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $liveExtensions = [];
	private array $removedExtensions = [];
	private array $scripts = [];

	private ?string $log = null;

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly LoggerInterface $logger,
		private readonly ServiceOptions $options,
		private readonly string $dbname
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		$extensions = $dbr->newSelectQueryBuilder()
			->select( 's_extensions' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchField();

		// To simplify clean up and to reduce the need to constantly refer back to many different variables, we now
		// populate extension lists with config associated with them.
		$config = $this->options->get( ConfigNames::Extensions );
		foreach ( json_decode( $extensions ?: '[]', true ) as $extension ) {
			if ( !isset( $config[$extension] ) ) {
				$this->logger->error( '{extension} is not set in {config}', [
					'config' => ConfigNames::Extensions,
					'extension' => $extension,
				] );

				continue;
			}

			$this->liveExtensions[$extension] = $config[$extension];
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
		$config = $this->options->get( ConfigNames::Extensions );
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			$this->liveExtensions[$ext] = $config[$ext];
			$this->changes[$ext] = [
				'old' => 0,
				'new' => 1,
			];
		}
	}

	/**
	 * Removes an extension from the 'enabled' list
	 * @param string[] $extensions Array of extensions to disable
	 * @param bool $force Force removing extension in the event it is removed from config
	 */
	public function remove( array $extensions, bool $force ): void {
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
		foreach ( $this->options->get( ConfigNames::Extensions ) as $ext => $_ ) {
			// TODO: do we still need this?
			if ( !is_string( $ext ) ) {
				continue;
			}

			if ( in_array( $ext, $extensions, true ) && !in_array( $ext, $overwrittenExts, true ) ) {
				$this->add( [ $ext ] );
				continue;
			}

			if ( !in_array( $ext, $extensions, true ) && in_array( $ext, $overwrittenExts, true ) ) {
				$this->remove( [ $ext ], force: false );
			}
		}
	}

	private function getName( string $extension ): string {
		return $this->options->get( ConfigNames::Extensions )[$extension]['name'] ?? '';
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

		foreach ( $this->liveExtensions as $name => $config ) {
			// Check if we have a conflict first
			if ( in_array( $config['conflicts'], $enabling, true ) ) {
				$this->errors[] = [
					'managewiki-error-conflict' => [
						$this->getName( $config['conflicts'] ),
						$config['name'],
					],
				];

				// We have a conflict, we have nothing else to do for this extension.
				continue;
			}

			$requirements = $config['requires'] ?? [];
			// If we aren't making any changes to this extension,
			// we don't need to check for permissions.
			if ( !isset( $this->changes[$name] ) ) {
				unset( $requirements['permissions'] );
			}

			// Now we need to check if we fulfill the requirements to enable this extension.
			$requirementsCheck = ManageWikiRequirements::process( $requirements, $this->list() );

			if ( !$requirementsCheck ) {
				if ( !isset( $this->changes[$name] ) ) {
					// If we are not changing this extension but we are changing (i.e. disabling)
					// an extension that this extension depends on.
					foreach ( $requirements['extensions'] ?? [] as $exts ) {
						foreach ( (array)$exts as $ext ) {
							if ( isset( $this->changes[$ext] ) ) {
								$this->errors[] = [
									'managewiki-error-dependency' => [
										$this->getName( $ext ),
										$config['name'],
									],
								];
								// Continue the parent loop
								continue 3;
							}
						}
					}
				}

				// If we are changing this extension or there is no extension requirements.
				$this->errors[] = [
					'managewiki-error-requirements-enabled' => [ $config['name'] ],
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
			if ( isset( $config['install'] ) ) {
				if ( isset( $config['install']['mwscript'] ) ) {
					$this->scripts[] = $config['install']['mwscript'];
					unset( $config['install']['mwscript'] );
				}

				$installResult = ManageWikiInstaller::process(
					$this->dbname, $config['install'],
					install: true
				);
			}

			if ( !$installResult ) {
				$this->errors[] = [
					'managewiki-error-install' => [ $config['name'] ],
				];
			}
		}

		// Early exit if we already have errors
		if ( $this->getErrors() ) {
			// If we have errors we don't want to save anything
			return;
		}

		foreach ( $this->removedExtensions as $name => $config ) {
			$requirementsCheck = true;
			$permissionRequirements = $config['requires']['permissions'] ?? [];
			if ( $permissionRequirements ) {
				$requirementsCheck = ManageWikiRequirements::process(
					// We only need to check for permissions when an
					// extension is being disabled.
					actions: [ 'permissions' => $permissionRequirements ],
					// We don't need this since it's not used for permissions,
					// which is the only thing we need to check here.
					extList: []
				);
			}

			if ( !$requirementsCheck ) {
				$this->errors[] = [
					'managewiki-error-requirements-disabled' => [ $config['name'] ],
				];

				// Requirements failed, we have nothing else to do for this extension.
				continue;
			}

			if ( $this->getErrors() ) {
				// If we have errors we don't want to save anything
				continue;
			}

			// Unlike installing, we are not too fussed about whether this fails, let us just do it.
			if ( isset( $config['remove'] ) ) {
				ManageWikiInstaller::process(
					$this->dbname, $config['remove'],
					install: false
				);
			}
		}

		if ( $this->getErrors() ) {
			// If we have errors we don't want to save anything
			return;
		}

		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbw->newInsertQueryBuilder()
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

		$data = $this->dataFactory->newInstance( $this->dbname );
		$data->resetWikiData( isNewChanges: true );

		// We need to run mwscript steps after the extension is already loaded
		if ( $this->scripts ) {
			$installResult = ManageWikiInstaller::process(
				dbname: $this->dbname,
				actions: [ 'mwscript' => $this->scripts ],
				install: true
			);
		}

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
