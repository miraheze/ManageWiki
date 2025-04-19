<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler class for managing settings
 */
class ManageWikiSettings implements IConfigModule {

	private Config $config;
	private IDatabase $dbw;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $scripts = [];
	private array $liveSettings;
	private array $settingsConfig;

	private string $dbname;
	private ?string $log = null;

	public function __construct( string $dbname ) {
		$this->dbname = $dbname;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$this->settingsConfig = $this->config->get( ConfigNames::Settings );

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$this->dbw = $databaseUtils->getGlobalPrimaryDB();

		$settings = $this->dbw->selectRow(
			'mw_settings',
			's_settings',
			[
				's_dbname' => $dbname,
			],
			__METHOD__
		)->s_settings ?? '[]';

		$this->liveSettings = (array)json_decode( $settings, true );
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
		// We will handle all processing in final stages
		foreach ( $settings as $var => $value ) {
			if ( $value !== ( $this->liveSettings[$var] ?? $this->settingsConfig[$var]['overridedefault'] ?? $default ) ) {
				$this->changes[$var] = [
					'old' => $this->liveSettings[$var] ?? $this->settingsConfig[$var]['overridedefault'] ?? $default,
					'new' => $value,
				];

				$this->liveSettings[$var] = $value;

				if ( isset( $this->settingsConfig[$var]['script'] ) ) {
					foreach ( $this->settingsConfig[$var]['script'] as $script => $opts ) {
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
		// We allow removing of a single variable or many variables
		// We will handle all processing in final stages
		foreach ( $settings as $var ) {
			if ( !isset( $this->liveSettings[$var] ) ) {
				continue;
			}

			$this->changes[$var] = [
				'old' => $this->liveSettings[$var],
				'new' => $this->settingsConfig[$var]['overridedefault'] ?? $default,
			];

			unset( $this->liveSettings[$var] );
		}
	}

	/**
	 * Allows multiples settings to be changed at once
	 * @param array $settings Settings to change
	 * @param bool $remove Whether to remove settings if they do not exist
	 */
	public function overwriteAll(
		array $settings,
		bool $remove = true
	): void {
		$overwrittenSettings = $this->list( var: null );

		foreach ( $this->settingsConfig as $var => $setConfig ) {
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
		$this->dbw->upsert(
			'mw_settings',
			[
				's_dbname' => $this->dbname,
				's_settings' => json_encode( $this->liveSettings ),
			],
			[ [ 's_dbname' ] ],
			[
				's_settings' => json_encode( $this->liveSettings ),
			],
			__METHOD__
		);

		if ( $this->scripts ) {
			ManageWikiInstaller::process( $this->dbname, [ 'mwscript' => $this->scripts ] );
		}

		$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
		$data = $dataFactory->newInstance( $this->dbname );
		$data->resetWikiData( isNewChanges: true );

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
