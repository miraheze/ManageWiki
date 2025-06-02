<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\InstallerFactory;
use Miraheze\ManageWiki\Helpers\Factories\RequirementsFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\IModule;
use Psr\Log\LoggerInterface;
use function array_column;
use function array_filter;
use function array_keys;
use function array_merge;
use function implode;
use function in_array;
use function is_string;
use function json_decode;
use function json_encode;
use const MW_ENTRY_POINT;

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
		private readonly InstallerFactory $installerFactory,
		private readonly LoggerInterface $logger,
		private readonly RequirementsFactory $requirementsFactory,
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

		// Populate extension list with associated config to simplify cleanup
		$config = $this->options->get( ConfigNames::Extensions );
		foreach ( json_decode( $extensions ?: '[]', true ) as $extension ) {
			// Use config if available; otherwise, default to empty array
			$this->liveExtensions[$extension] = $config[$extension] ?? [];

			// Skip logging in CLI to avoid excessive noise from scripts like ToggleExtension
			if ( !isset( $config[$extension] ) && MW_ENTRY_POINT !== 'cli' ) {
				$this->logger->error( '{extension} is not set in {config}', [
					'config' => ConfigNames::Extensions,
					'extension' => $extension,
				] );
			}
		}
	}

	/**
	 * Lists an array of all extensions currently 'enabled'
	 * @return string[] Array of extensions enabled
	 *
	 * Phan warns list<int>|list<string> due to array_keys possibly returning int[]; however,
	 * $this->liveExtensions is always defined with string keys only, making this safe.
	 * @suppress PhanPartialTypeMismatchReturn
	 */
	public function list(): array {
		return array_keys( $this->liveExtensions );
	}

	/**
	 * Lists names of all currently enabled extensions.
	 * @return string[] Array of ExtensionRegistry names
	 */
	public function listNames(): array {
		return array_column( $this->liveExtensions, 'name' );
	}

	/**
	 * Adds an extension to the 'enabled' list
	 * @param string[] $extensions Array of extensions to enable
	 */
	public function add( array $extensions = [] ): void {
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
	 */
	public function remove( array $extensions ): void {
		// We will handle all processing in final stages
		foreach ( $extensions as $ext ) {
			if ( !isset( $this->liveExtensions[$ext] ) ) {
				continue;
			}

			$this->removedExtensions[$ext] = $this->liveExtensions[$ext];
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
				$this->remove( [ $ext ] );
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

		$mwInstaller = $this->installerFactory->getInstaller( $this->dbname );
		$mwRequirements = $this->requirementsFactory->getRequirements( $this->dbname );

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
			$requirementsCheck = $mwRequirements->check( $requirements, $this->list() );

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
					$this->scripts = array_merge(
						$this->scripts,
						$config['install']['mwscript']
					);

					unset( $config['install']['mwscript'] );
				}

				$installResult = $mwInstaller->execute(
					actions: $config['install'],
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

		foreach ( $this->removedExtensions as $config ) {
			$requirementsCheck = true;
			$permissionRequirements = $config['requires']['permissions'] ?? [];
			if ( $permissionRequirements !== [] ) {
				$requirementsCheck = $mwRequirements->check(
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
				$mwInstaller->execute(
					actions: $config['remove'],
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
			$mwInstaller->execute(
				actions: [ 'mwscript' => $this->scripts ],
				install: true
			);
		}

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
