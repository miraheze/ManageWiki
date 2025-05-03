<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;
use Miraheze\ManageWiki\ICoreModule;

class CoreModule implements ICoreModule {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::UseCustomDomains,
	];

	private array $changes = [];
	private array $logParams = [];

	private ?string $log = null;

	public function __construct(
		private readonly SettingsFactory $settingsFactory,
		private readonly ServiceOptions $options,
		private readonly string $dbname
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function getSitename(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgSitename' ) ?? '';
	}

	public function setSitename( string $sitename ): void {
		$this->trackChange( 'sitename', $this->getSitename(), $sitename );
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgSitename' => $sitename ], default: '' );
	}

	public function getLanguage(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgLanguageCode' ) ?? 'en';
	}

	public function setLanguage( string $lang ): void {
		$this->trackChange( 'language', $this->getLanguage(), $lang );
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgLanguageCode' => $lang ], default: 'en' );
	}

	public function isInactive(): bool {
		// Not implemented
		return false;
	}

	public function markInactive(): void {
		// Not implemented
	}

	public function markActive(): void {
		// Not implemented
	}

	public function isInactiveExempt(): bool {
		// Not implemented
		return false;
	}

	public function markExempt(): void {
		// Not implemented
	}

	public function unExempt(): void {
		// Not implemented
	}

	public function setInactiveExemptReason( string $reason ): void {
		// Not implemented
	}

	public function getInactiveExemptReason(): ?string {
		// Not implemented
		return null;
	}

	public function isPrivate(): bool {
		// Not implemented
		return false;
	}

	public function markPrivate(): void {
		// Not implemented
	}

	public function markPublic(): void {
		// Not implemented
	}

	public function isClosed(): bool {
		// Not implemented
		return false;
	}

	public function markClosed(): void {
		// Not implemented
	}

	public function isDeleted(): bool {
		// Not implemented
		return false;
	}

	public function delete(): void {
		// Not implemented
	}

	public function undelete(): void {
		// Not implemented
	}

	public function isLocked(): bool {
		// Not implemented
		return false;
	}

	public function lock(): void {
		// Not implemented
	}

	public function unlock(): void {
		// Not implemented
	}

	public function getCategory(): string {
		// Not implemented
		return '';
	}

	public function setCategory( string $category ): void {
		// Not implemented
	}

	public function getServerName(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgServer' ) ?? '';
	}

	public function setServerName( string $server ): void {
		$server = $server === '' ? false : $server;
		$this->trackChange( 'servername', $this->getServerName(), $server );
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgServer' => $server ], default: false );
	}

	public function getDBCluster(): string {
		// Not implemented
		return '';
	}

	public function setDBCluster( string $dbcluster ): void {
		// Not implemented
	}

	public function isExperimental(): bool {
		// Not implemented
		return false;
	}

	public function markExperimental(): void {
		// Not implemented
	}

	public function unMarkExperimental(): void {
		// Not implemented
	}

	public function getExtraFieldData( string $field, mixed $default ): mixed {
		// Not implemented
		return $default;
	}

	public function setExtraFieldData( string $field, mixed $value, mixed $default ): void {
		// Not implemented
	}

	public function trackChange( string $field, mixed $oldValue, mixed $newValue ): void {
		$this->changes[$field] = [
			'old' => $oldValue,
			'new' => $newValue,
		];
	}

	public function isEnabled( string $feature ): bool {
		$enabled = [
			'server' => $this->options->get( ConfigNames::UseCustomDomains ),
			'language' => true,
			'sitename' => true,
		];
		return $enabled[$feature] ?? false;
	}

	public function getCategoryOptions(): array {
		// Not implemented
		return [];
	}

	public function getDatabaseClusters(): array {
		// Not implemented
		return [];
	}

	public function getErrors(): array {
		// Not implemented
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
		if ( !$this->hasChanges() ) {
			return;
		}

		if ( $this->log === null ) {
			$this->log = 'settings';
			$this->logParams = [
				'5::changes' => implode( ', ', array_keys( $this->changes ) ),
			];
		}

		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->commit();
	}
}
