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

	/**
	 * Constructs a ManageWikiSettings instance with required dependencies.
	 *
	 * Asserts that all required configuration options are present.
	 */
	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Initializes the instance for a specific wiki database and loads its current settings.
	 *
	 * Resets internal state and retrieves the live settings for the given database from the `mw_settings` table.
	 *
	 * @param string $dbname The database name of the wiki to manage.
	 * @return self The initialized instance.
	 */
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
	 * Updates one or more wiki settings and records any changes.
	 *
	 * For each provided setting, updates its value if it differs from the current or default, tracks the change, and collects any associated scripts for later processing.
	 *
	 * @param array $settings Associative array of setting names and their new values.
	 * @param mixed $default Value to use if no current or override default exists for a setting.
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
	 * Removes specified settings from the current live settings.
	 *
	 * For each setting in the provided list, removes it from the live settings if present and records the change with the old value and the new default or override default.
	 *
	 * @param string[] $settings List of setting keys to remove.
	 * @param mixed $default Value to use as the new default if no override default is defined.
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
	 * Applies a batch update to all settings, modifying or removing them as specified.
	 *
	 * For each configured setting, updates its value if provided in the input array, or removes it if not present and removal is enabled.
	 *
	 * @param array $settings Associative array of settings to apply.
	 * @param bool $remove If true, removes settings not present in the input array.
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

	/**
	 * Returns the current parameters set for logging actions.
	 *
	 * @return array Associative array of log parameters and their values.
	 */
	public function getLogParams(): array {
		return $this->logParams;
	}

	/**
	 * Commits all pending settings changes to the database for the current wiki.
	 *
	 * Updates the `mw_settings` table with the current settings, processes any associated scripts, resets wiki data, and updates log parameters to reflect the changes.
	 */
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
