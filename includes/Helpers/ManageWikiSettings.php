<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;

/**
 * Handler class for managing settings
 */
class ManageWikiSettings implements IConfigModule {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::Settings,
	];

	private array $changes = [];
	private array $logParams = [];
	private array $liveSettings = [];
	private array $scripts = [];

	private string $dbname;
	private ?string $log = null;

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function newInstance( string $dbname ): self {
		$this->dbname = $dbname;

		// Reset properties
		$this->changes = [];
		$this->logParams = [];
		$this->liveSettings = [];
		$this->scripts = [];
		$this->log = null;

		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		$settings = $dbr->newSelectQueryBuilder()
			->select( 's_settings' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchField();

		$this->liveSettings = (array)json_decode( $settings ?: '[]', true );

		return $this;
	}

	/**
	 * Lists either all settings or the value of a specific one
	 * @param ?string $var Setting variable to retrieve value of
	 * @return mixed Value or all settings, null if no value
	 */
	public function list( ?string $var ): mixed {
		if ( $var === null ) {
			return $this->liveSettings;
		}

		return $this->liveSettings[$var] ?? null;
	}

	/**
	 * Adds or changes a setting
	 * @param array $settings Setting to change with value
	 * @param mixed $default Default to use if none can be found
	 */
	public function modify( array $settings, mixed $default = null ): void {
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
	public function remove( array $settings, mixed $default = null ): void {
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
		$overwrittenSettings = $this->list( var: null );
		foreach ( $this->options->get( ConfigNames::Settings ) as $var => $_ ) {
			if ( !array_key_exists( $var, $settings ) && array_key_exists( $var, $overwrittenSettings ) && $remove ) {
				$this->remove( [ $var ] );
				continue;
			}

			if ( ( $settings[$var] ?? null ) !== null ) {
				$this->modify( [ $var => $settings[$var] ] );
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
				's_settings' => json_encode( $this->liveSettings ),
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 's_dbname' ] )
			->set( [ 's_settings' => json_encode( $this->liveSettings ) ] )
			->caller( __METHOD__ )
			->execute();

		if ( $this->scripts ) {
			ManageWikiInstaller::process(
				$this->dbname,
				[ 'mwscript' => $this->scripts ],
				install: true
			);
		}

		$data = $this->dataFactory->newInstance( $this->dbname );
		$data->resetWikiData( isNewChanges: true );

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
