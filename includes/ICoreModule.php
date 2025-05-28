<?php

namespace Miraheze\ManageWiki;

interface ICoreModule extends IModule {

	public function getSitename(): string;

	public function setSitename( string $sitename ): void;

	public function getLanguage(): string;

	public function setLanguage( string $lang ): void;

	public function isInactive(): bool;

	public function markInactive(): void;

	public function markActive(): void;

	public function isInactiveExempt(): bool;

	public function markExempt(): void;

	public function unExempt(): void;

	public function setInactiveExemptReason( string $reason ): void;

	public function getInactiveExemptReason(): ?string;

	public function isPrivate(): bool;

	public function markPrivate(): void;

	public function markPublic(): void;

	public function isClosed(): bool;

	public function markClosed(): void;

	public function isDeleted(): bool;

	public function delete(): void;

	public function undelete(): void;

	public function isLocked(): bool;

	public function lock(): void;

	public function unlock(): void;

	public function getCategory(): string;

	public function setCategory( string $category ): void;

	public function getServerName(): string;

	public function setServerName( string $server ): void;

	public function getDBCluster(): string;

	public function setDBCluster( string $dbcluster ): void;

	public function isExperimental(): bool;

	public function markExperimental(): void;

	public function unMarkExperimental(): void;

	/**
	 * Needed for hooks
	 */
	public function getExtraFieldData( string $field, mixed $default ): mixed;

	public function setExtraFieldData( string $field, mixed $value, mixed $default ): void;

	public function trackChange( string $field, mixed $oldValue, mixed $newValue ): void;

	/**
	 * Used by providers to control some form displays
	 */
	public function isEnabled( string $feature ): bool;

	public function getCategoryOptions(): array;

	public function getDatabaseClusters(): array;

	public function getDatabaseClustersInactive(): array;

	public function getInactiveExemptReasonOptions(): array;
}
