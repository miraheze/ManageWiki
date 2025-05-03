<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\SettingsFactory;
use Miraheze\ManageWiki\ICoreModule;

class CoreModule implements ICoreModule {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::UseCustomDomains,
	];

	private array $changes = [];

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
		return;
	}

	public function markActive(): void {
		// Not implemented
		return;
	}

	public function isInactiveExempt(): bool {
		// Not implemented
		return false;
	}

	public function markExempt(): void {
		// Not implemented
		return;
	}

	public function unExempt(): void {
		// Not implemented
		return;
	}

	public function setInactiveExemptReason( string $reason ): void {
		// Not implemented
		return;
	}

	public function getInactiveExemptReason(): ?string {
		return null;
	}

	public function isPrivate(): bool {
		// Not implemented
		return false;
	}

	public function markPrivate(): void {
		// Not implemented
		return;
	}

	public function markPublic(): void {
		// Not implemented
		return;
	}

	public function isClosed(): bool {
		// Not implemented
		return false;
	}

	public function markClosed(): void {
		// Not implemented
		return;
	}

	public function isDeleted(): bool {
		// Not implemented
		return false;
	}

	public function delete(): void {
		// Not implemented
		return;
	}

	public function undelete(): void {
		// Not implemented
		return;
	}

	public function isLocked(): bool {
		// Not implemented
		return false;
	}

	public function lock(): void {
		// Not implemented
		return;
	}

	public function unlock(): void {
		// Not implemented
		return;
	}

	public function getCategory(): string {
		// Not implemented
		return '';
	}

	public function setCategory( string $category ): void {
		// Not implemented
		return;
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
		return;
	}

	public function isExperimental(): bool {
		// Not implemented
		return false;
	}

	public function markExperimental(): void {
		// Not implemented
		return;
	}

	public function unMarkExperimental(): void {
		// Not implemented
		return;
	}

	public function getExtraFieldData( string $field, mixed $default ): mixed {
		// Not implemented
		return $default;
	}

	public function setExtraFieldData( string $field, mixed $value, mixed $default ): void {
		// Not implemented
		return;
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
		if ( !$this->hasChanges() ) {
			return;
		}

		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->commit();
	}
}
