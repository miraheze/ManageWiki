<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;
use Miraheze\ManageWiki\ICoreModule;
use function array_keys;
use function implode;

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

	/** @inheritDoc */
	public function getSitename(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgSitename' ) ?? '';
	}

	/** @inheritDoc */
	public function setSitename( string $sitename ): void {
		$this->trackChange(
			field: 'sitename',
			oldValue: $this->getSitename(),
			newValue: $sitename
		);
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgSitename' => $sitename ], default: '' );
	}

	/** @inheritDoc */
	public function getLanguage(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgLanguageCode' ) ?? 'en';
	}

	/** @inheritDoc */
	public function setLanguage( string $lang ): void {
		$this->trackChange(
			field: 'language',
			oldValue: $this->getLanguage(),
			newValue: $lang
		);
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgLanguageCode' => $lang ], default: 'en' );
	}

	/** @inheritDoc */
	public function isInactive(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function markInactive(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function markActive(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function isInactiveExempt(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function markExempt(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function unExempt(): void {
		// Not implemented
	}

	/**
	 * @inheritDoc
	 * @param string $reason @phan-unused-param
	 */
	public function setInactiveExemptReason( string $reason ): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function getInactiveExemptReason(): ?string {
		// Not implemented
		return null;
	}

	/** @inheritDoc */
	public function isPrivate(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function markPrivate(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function markPublic(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function isClosed(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function markClosed(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function isDeleted(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function delete(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function undelete(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function isLocked(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function lock(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function unlock(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function getCategory(): string {
		// Not implemented
		return '';
	}

	/**
	 * @inheritDoc
	 * @param string $category @phan-unused-param
	 */
	public function setCategory( string $category ): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function getServerName(): string {
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		return $mwSettings->list( 'wgServer' ) ?? '';
	}

	/** @inheritDoc */
	public function setServerName( string $server ): void {
		$server = $server === '' ? false : $server;
		$this->trackChange(
			field: 'servername',
			oldValue: $this->getServerName(),
			newValue: $server
		);
		$mwSettings = $this->settingsFactory->getInstance( $this->dbname );
		$mwSettings->modify( [ 'wgServer' => $server ], default: false );
	}

	/** @inheritDoc */
	public function getDBCluster(): string {
		// Not implemented
		return '';
	}

	/**
	 * @inheritDoc
	 * @param string $dbcluster @phan-unused-param
	 */
	public function setDBCluster( string $dbcluster ): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function isExperimental(): bool {
		// Not implemented
		return false;
	}

	/** @inheritDoc */
	public function markExperimental(): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function unMarkExperimental(): void {
		// Not implemented
	}

	/**
	 * @inheritDoc
	 * @param string $field @phan-unused-param
	 */
	public function getExtraFieldData( string $field, mixed $default ): mixed {
		// Not implemented
		return $default;
	}

	/**
	 * @inheritDoc
	 * @param string $field @phan-unused-param
	 * @param mixed $value @phan-unused-param
	 * @param mixed $default @phan-unused-param
	 */
	public function setExtraFieldData( string $field, mixed $value, mixed $default ): void {
		// Not implemented
	}

	/** @inheritDoc */
	public function trackChange( string $field, mixed $oldValue, mixed $newValue ): void {
		$this->changes[$field] = [
			'old' => $oldValue,
			'new' => $newValue,
		];
	}

	/** @inheritDoc */
	public function isEnabled( string $feature ): bool {
		$enabled = [
			'server' => $this->options->get( ConfigNames::UseCustomDomains ),
			'language' => true,
			'sitename' => true,
		];
		return $enabled[$feature] ?? false;
	}

	/** @inheritDoc */
	public function getCategoryOptions(): array {
		// Not implemented
		return [];
	}

	/** @inheritDoc */
	public function getDatabaseClusters(): array {
		// Not implemented
		return [];
	}

	/** @inheritDoc */
	public function getDatabaseClustersInactive(): array {
		// Not implemented
		return [];
	}

	/** @inheritDoc */
	public function getInactiveExemptReasonOptions(): array {
		// Not implemented
		return [];
	}

	/** @inheritDoc */
	public function getErrors(): array {
		// Not implemented
		return [];
	}

	/** @inheritDoc */
	public function hasChanges(): bool {
		return (bool)$this->changes;
	}

	/** @inheritDoc */
	public function setLogAction( string $action ): void {
		$this->log = $action;
	}

	/** @inheritDoc */
	public function getLogAction(): string {
		return $this->log ?? 'settings';
	}

	/** @inheritDoc */
	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	/** @inheritDoc */
	public function getLogParams(): array {
		return $this->logParams;
	}

	/** @inheritDoc */
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
