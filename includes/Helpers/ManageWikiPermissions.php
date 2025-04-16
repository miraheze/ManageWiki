<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\IConfigModule;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler for interacting with Permissions
 */
class ManageWikiPermissions implements IConfigModule {

	private Config $config;
	private IDatabase $dbw;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $deleteGroups = [];
	private array $livePermissions = [];

	private string $dbname;
	private ?string $log = null;

	public function __construct( string $dbname ) {
		$this->dbname = $dbname;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$this->dbw = $databaseUtils->getGlobalPrimaryDB();

		$perms = $this->dbw->select(
			'mw_permissions',
			'*',
			[
				'perm_dbname' => $dbname,
			],
			__METHOD__
		);

		foreach ( $perms as $perm ) {
			$this->livePermissions[$perm->perm_group] = [
				'permissions' => json_decode( $perm->perm_permissions, true ),
				'addgroups' => json_decode( $perm->perm_addgroups, true ),
				'removegroups' => json_decode( $perm->perm_removegroups, true ),
				'addself' => json_decode( $perm->perm_addgroupstoself, true ),
				'removeself' => json_decode( $perm->perm_removegroupsfromself, true ),
				'autopromote' => json_decode( $perm->perm_autopromote ?? '', true ),
			];
		}
	}

	/**
	 * Checks whether or not the specified group exists
	 * @param string $group Group to check
	 * @return bool Whether or not the group exists
	 */
	public function exists( string $group ): bool {
		return array_key_exists( $group, $this->livePermissions );
	}

	/**
	 * Lists either all groups or a specific one
	 * @param ?string $group Group wanted (null for all)
	 * @return array Group configuration
	 */
	public function list( ?string $group = null ): array {
		if ( $group === null ) {
			return $this->livePermissions;
		}

		return $this->livePermissions[$group] ?? [
			'permissions' => [],
			'addgroups' => [],
			'removegroups' => [],
			'addself' => [],
			'removeself' => [],
			'autopromote' => null,
		];
	}

	/**
	 * Modify a group handler
	 * @param string $group Group name
	 * @param array $data Merging information about the group
	 */
	public function modify( string $group, array $data ): void {
		// We will handle all processing in final stages
		$permData = [
			'permissions' => $this->livePermissions[$group]['permissions'] ?? [],
			'addgroups' => $this->livePermissions[$group]['addgroups'] ?? [],
			'removegroups' => $this->livePermissions[$group]['removegroups'] ?? [],
			'addself' => $this->livePermissions[$group]['addself'] ?? [],
			'removeself' => $this->livePermissions[$group]['removeself'] ?? [],
			'autopromote' => $this->livePermissions[$group]['autopromote'] ?? null,
		];

		// Overwrite the defaults above with our new modified values
		foreach ( $data as $name => $array ) {
			if ( $name !== 'autopromote' ) {
				foreach ( $array as $type => $value ) {
					$original = array_values( $permData[$name] ?? [] );
					$new = $type === 'add' ?
						array_merge( $permData[$name] ?? [], $value ) :
						array_diff( $permData[$name] ?? [], $value );

					// Make sure it is ordered properly to ensure we can compare
					// the values and check for changes properly.
					$new = array_values( $new );
					sort( $original );
					sort( $new );

					if ( $original !== $new ) {
						$permData[$name] = $new;
						$this->changes[$group][$name][$type] = $value;
					}
				}
				continue;
			}

			if ( $permData['autopromote'] !== $data['autopromote'] ) {
				$permData['autopromote'] = $data['autopromote'];
				$this->changes[$group]['autopromote'] = true;
			}
		}

		$this->livePermissions[$group] = $permData;
	}

	/**
	 * Remove a group
	 * @param string $group Group name
	 */
	public function remove( string $group ): void {
		// Utilise changes differently in this case
		foreach ( $this->livePermissions[$group] as $name => $value ) {
			$this->changes[$group][$name] = [
				'add' => null,
				'remove' => $value,
			];
		}

		// We will handle all processing in final stages
		unset( $this->livePermissions[$group] );

		// Push to a deletion queue
		$this->deleteGroups[] = $group;
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
		return $this->log ?? 'rights';
	}

	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	public function getLogParams(): array {
		return $this->logParams;
	}

	public function commit(): void {
		$logNULL = wfMessage( 'rightsnone' )->inContentLanguage()->text();

		foreach ( array_keys( $this->changes ) as $group ) {
			if ( in_array( $group, $this->deleteGroups ) ) {
				$this->log = 'delete-group';

				$this->dbw->delete(
					'mw_permissions',
					[
						'perm_dbname' => $this->dbname,
						'perm_group' => $group,
					],
					__METHOD__
				);

				$this->deleteUsersFromGroup( $group );
				continue;
			}

			if ( empty( $this->livePermissions[$group]['permissions'] ) ) {
				$this->errors[] = [
					'managewiki-error-emptygroup' => [],
				];
				continue;
			}

			$builtTable = [
				'perm_permissions' => json_encode( $this->livePermissions[$group]['permissions'] ),
				'perm_addgroups' => json_encode( $this->livePermissions[$group]['addgroups'] ),
				'perm_removegroups' => json_encode( $this->livePermissions[$group]['removegroups'] ),
				'perm_addgroupstoself' => json_encode( $this->livePermissions[$group]['addself'] ),
				'perm_removegroupsfromself' => json_encode( $this->livePermissions[$group]['removeself'] ),
				'perm_autopromote' => $this->livePermissions[$group]['autopromote'] === null ? null : json_encode( $this->livePermissions[$group]['autopromote'] ?? '' ),
			];

			$this->dbw->upsert(
				'mw_permissions',
				[
					'perm_dbname' => $this->dbname,
					'perm_group' => $group,
				] + $builtTable,
				[
					[
						'perm_dbname',
						'perm_group',
					],
				],
				$builtTable,
				__METHOD__
			);

			$logAP = ( $this->changes[$group]['autopromote'] ?? false ) ? 'htmlform-yes' : 'htmlform-no';
			$this->logParams = [
				'4::ar' => !empty( $this->changes[$group]['permissions']['add'] ) ? implode( ', ', $this->changes[$group]['permissions']['add'] ) : $logNULL,
				'5::rr' => !empty( $this->changes[$group]['permissions']['remove'] ) ? implode( ', ', $this->changes[$group]['permissions']['remove'] ) : $logNULL,
				'6::aag' => !empty( $this->changes[$group]['addgroups']['add'] ) ? implode( ', ', $this->changes[$group]['addgroups']['add'] ) : $logNULL,
				'7::rag' => !empty( $this->changes[$group]['addgroups']['remove'] ) ? implode( ', ', $this->changes[$group]['addgroups']['remove'] ) : $logNULL,
				'8::arg' => !empty( $this->changes[$group]['removegroups']['add'] ) ? implode( ', ', $this->changes[$group]['removegroups']['add'] ) : $logNULL,
				'9::rrg' => !empty( $this->changes[$group]['removegroups']['remove'] ) ? implode( ', ', $this->changes[$group]['removegroups']['remove'] ) : $logNULL,
				'10::aags' => !empty( $this->changes[$group]['addself']['add'] ) ? implode( ', ', $this->changes[$group]['addself']['add'] ) : $logNULL,
				'11::rags' => !empty( $this->changes[$group]['addself']['remove'] ) ? implode( ', ', $this->changes[$group]['addself']['remove'] ) : $logNULL,
				'12::args' => !empty( $this->changes[$group]['removeself']['add'] ) ? implode( ', ', $this->changes[$group]['removeself']['add'] ) : $logNULL,
				'13::rrgs' => !empty( $this->changes[$group]['removeself']['remove'] ) ? implode( ', ', $this->changes[$group]['removeself']['remove'] ) : $logNULL,
				'14::ap' => strtolower( wfMessage( $logAP )->inContentLanguage()->text() ),
			];
		}

		if ( $this->dbname !== 'default' ) {
			$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $this->dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}

	private function deleteUsersFromGroup( string $group ): void {
		$groupManager = MediaWikiServices::getInstance()->getUserGroupManager();
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$dbr = $databaseUtils->getRemoteWikiReplicaDB( $this->dbname );

		$res = $dbr->select(
			'user_groups',
			'ug_user',
			[
				'ug_group' => $group,
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$groupManager->removeUserFromGroup( $userFactory->newFromId( $row->ug_user ), $group );
		}
	}
}
