<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler class for managing settings
 */
class ManageWikiSettings {

	private Config $config;
	private IDatabase $dbw;

	private array $liveSettings;
	private array $settingsConfig;
	private array $scripts = [];

	private string $wiki;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];

	private string $log = 'settings';

	public function __construct( string $wiki ) {
		$this->wiki = $wiki;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$this->settingsConfig = $this->config->get( 'ManageWikiSettings' );

		$this->dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		$settings = $this->dbw->selectRow(
			'mw_settings',
			's_settings',
			[
				's_dbname' => $wiki,
			],
			__METHOD__
		)->s_settings ?? '[]';

		// Bring json_decoded values to class scope
		$this->liveSettings = (array)json_decode( $settings, true );
	}

	/**
	 * Lists either all settings or the value of a specific one
	 * @param ?string $setting Setting to retrieve value of
	 * @return mixed Value or all settings, null if no value
	 */
	public function list( ?string $setting = null ): mixed {
		if ( $setting === null ) {
			return $this->liveSettings;
		}

		return $this->liveSettings[$setting] ?? null;
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
		$overwrittenSettings = $this->list();

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
		$this->dbw->upsert(
			'mw_settings',
			[
				's_dbname' => $this->wiki,
				's_settings' => json_encode( $this->liveSettings ),
			],
			[ [ 's_dbname' ] ],
			[
				's_settings' => json_encode( $this->liveSettings ),
			],
			__METHOD__
		);

		if ( $this->scripts ) {
			ManageWikiInstaller::process( $this->wiki, [ 'mwscript' => $this->scripts ] );
		}

		$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
		$data = $dataFactory->newInstance( $this->wiki );
		$data->resetWikiData( isNewChanges: true );

		$this->logParams = [
			'5::changes' => implode( ', ', array_keys( $this->changes ) ),
		];
	}
}
