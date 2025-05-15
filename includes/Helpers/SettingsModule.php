<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\InstallerFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\IModule;

class SettingsModule implements IModule {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::Settings,
	];

	private array $changes = [];
	private array $logParams = [];
	private array $liveSettings = [];
	private array $scripts = [];

	private ?string $log = null;

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly InstallerFactory $installerFactory,
		private readonly ServiceOptions $options,
		private readonly string $dbname
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		$settings = $dbr->newSelectQueryBuilder()
			->select( 's_settings' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->liveSettings = (array)json_decode( $settings ?: '[]', true );
	}

	/**
	 * Retrieves the value of a specific setting
	 * @param string $var Setting variable to retrieve value of
	 * @return mixed Value, null if no value
	 */
	public function list( string $var ): mixed {
		return $this->liveSettings[$var] ?? null;
	}

	public function listAll(): array {
		return $this->liveSettings;
	}

	/**
	 * Adds or changes a setting
	 * @param array $settings Setting to change with value
	 * @param mixed $default Default to use if none can be found
	 */
	public function modify( array $settings, mixed $default ): void {
		$config = $this->options->get( ConfigNames::Settings );
		// We will handle all processing in final stages
		foreach ( $settings as $var => $value ) {
			$live = $this->liveSettings[$var] ?? null;
			$override = $config[$var]['overridedefault'] ?? null;
			$current = $live ?? $override ?? $default;

			if ( $value !== $current ) {
				$this->changes[$var] = [
					'old' => $this->liveSettings[$var] ?? $config[$var]['overridedefault'] ?? $default,
					'new' => $value,
				];

				$this->liveSettings[$var] = $value;

				if ( isset( $config[$var]['script'] ) ) {
					foreach ( $config[$var]['script'] as $script => $opts ) {
						$this->scripts[$script] = $opts;
					}
				}
			}
		}
	}

	/**
	 * Removes a setting
	 * @param string[] $settings Settings to remove
	 * @param mixed $default Default to use if none can be found
	 */
	public function remove( array $settings, mixed $default ): void {
		$config = $this->options->get( ConfigNames::Settings );
		// We will handle all processing in final stages
		foreach ( $settings as $var ) {
			if ( !isset( $this->liveSettings[$var] ) ) {
				continue;
			}

			$this->changes[$var] = [
				'old' => $this->liveSettings[$var],
				'new' => $config[$var]['overridedefault'] ?? $default,
			];

			unset( $this->liveSettings[$var] );
		}
	}

	/**
	 * Allows multiples settings to be changed at once
	 * @param array $settings Settings to change
	 * @param bool $remove Whether to remove settings if they do not exist
	 */
	public function overwriteAll( array $settings, bool $remove ): void {
		$overwrittenSettings = $this->listAll();
		foreach ( $this->options->get( ConfigNames::Settings ) as $var => $_ ) {
			if ( !array_key_exists( $var, $settings ) && array_key_exists( $var, $overwrittenSettings ) && $remove ) {
				$this->remove( [ $var ], default: null );
				continue;
			}

			if ( ( $settings[$var] ?? null ) !== null ) {
				$this->modify( [ $var => $settings[$var] ], default: null );
			}
		}
	}

	public function getErrors(): array {
		// This class doesn't produce errors, but the method
		// may be called by consumers, so return an empty array.
		return [];
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
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'mw_settings' )
			->row( [
				's_dbname' => $this->dbname,
				's_settings' => json_encode( $this->listAll() ),
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 's_dbname' ] )
			->set( [ 's_settings' => json_encode( $this->listAll() ) ] )
			->caller( __METHOD__ )
			->execute();

		$data = $this->dataFactory->newInstance( $this->dbname );
		$data->resetWikiData( isNewChanges: true );

		if ( $this->scripts ) {
			$installer = $this->installerFactory->getInstaller( $this->dbname );
			$installer->process(
				actions: [ 'mwscript' => $this->scripts ],
				install: true
			);
		}

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
