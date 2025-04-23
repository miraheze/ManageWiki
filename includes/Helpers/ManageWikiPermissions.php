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

		$perms = $this->dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchResultSet();

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
		return isset( $this->livePermissions[$group] );
	}

	/**
	 * Lists either all groups or a specific one
	 * @param ?string $group Group wanted (null for all)
	 * @return array Group configuration
	 */
	public function list( ?string $group ): array {
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
	 * Get all groups that have the specified permission
	 *
	 * @param string $permission The permission to look for
	 * @return array List of group names that have the permission
	 */
	public function getGroupsWithPermission( string $permission ): array {
		$groups = [];
		foreach ( $this->livePermissions as $group => $data ) {
			if ( in_array( $permission, $data['permissions'] ?? [], true ) ) {
				$groups[] = $group;
			}
		}

		return array_unique( $groups );
	}

	/**
	 * Modify a group handler
	 * @param string $group Group name
	 * @param array $data Merging information about the group
	 */
	public function modify( string $group, array $data ): void {
		if ( is_array( $data['permissions']['remove'] ?? null ) ) {
			$groupsWithPermission = $this->getGroupsWithPermission( 'managewiki-permissions' );
			$isRemovingPermission = in_array(
				'managewiki-permissions', $data['permissions']['remove'], true
			);

			if ( $isRemovingPermission && $groupsWithPermission === [ $group ] ) {
				$this->errors[] = [
					'managewiki-error-missingpermission' => [],
				];
				return;
			}
		}

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
		$groupsWithPermission = $this->getGroupsWithPermission( 'managewiki-permissions' );
		if ( $groupsWithPermission === [ $group ] ) {
			$this->errors[] = [
				'managewiki-error-missingpermission' => [],
			];
			return;
		}

		// Utilize changes differently in this case
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

	public function isDeleting( string $group ): bool {
		return in_array( $group, $this->deleteGroups, true );
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
		if ( $this->getErrors() ) {
			// Don't save anything if we have errors
			return;
		}

		foreach ( array_keys( $this->changes ) as $group ) {
			if ( $this->isDeleting( $group ) ) {
				$this->log = 'delete-group';

				$this->dbw->newDeleteQueryBuilder()
					->deleteFrom( 'mw_permissions' )
					->where( [
						'perm_dbname' => $this->dbname,
						'perm_group' => $group,
					] )
					->caller( __METHOD__ )
					->execute();

				$this->deleteUsersFromGroup( $group );
				continue;
			}

			$live = $this->livePermissions[$group];
			if ( empty( $live['permissions'] ) ) {
				$this->errors[] = [
					'managewiki-error-emptygroup' => [],
				];
				continue;
			}

			$builtTable = [
				'perm_permissions' => json_encode( $live['permissions'] ),
				'perm_addgroups' => json_encode( $live['addgroups'] ),
				'perm_removegroups' => json_encode( $live['removegroups'] ),
				'perm_addgroupstoself' => json_encode( $live['addself'] ),
				'perm_removegroupsfromself' => json_encode( $live['removeself'] ),
				'perm_autopromote' => $live['autopromote'] === null
					? null : json_encode( $live['autopromote'] ?? '' ),
			];

			$this->dbw->newInsertQueryBuilder()
				->insertInto( 'mw_permissions' )
				->row( [
					'perm_dbname' => $this->dbname,
					'perm_group' => $group,
				] + $builtTable )
				->onDuplicateKeyUpdate()
				->uniqueIndexFields( [
					'perm_dbname',
					'perm_group',
				] )
				->set( $builtTable )
				->caller( __METHOD__ )
				->execute();

			$logAP = ( $this->changes[$group]['autopromote'] ?? false ) ? 'htmlform-yes' : 'htmlform-no';
			$logNULL = wfMessage( 'rightsnone' )->inContentLanguage()->text();

			/**
			 * Convert a list of permission/group changes into a comma-separated string.
			 * Used for logging. Falls back to $logNULL when the list is empty.
			 * Keeps the logic out of the main logParams block for readability and reusability.
			 */
			$logValue = static fn ( ?array $value ): string =>
				!empty( $value ) ? implode( ', ', $value ) : $logNULL;

			$this->logParams = [
				'4::ar'   => $logValue( $this->changes[$group]['permissions']['add'] ?? null ),
				'5::rr'   => $logValue( $this->changes[$group]['permissions']['remove'] ?? null ),
				'6::aag'  => $logValue( $this->changes[$group]['addgroups']['add'] ?? null ),
				'7::rag'  => $logValue( $this->changes[$group]['addgroups']['remove'] ?? null ),
				'8::arg'  => $logValue( $this->changes[$group]['removegroups']['add'] ?? null ),
				'9::rrg'  => $logValue( $this->changes[$group]['removegroups']['remove'] ?? null ),
				'10::aags' => $logValue( $this->changes[$group]['addself']['add'] ?? null ),
				'11::rags' => $logValue( $this->changes[$group]['addself']['remove'] ?? null ),
				'12::args' => $logValue( $this->changes[$group]['removeself']['add'] ?? null ),
				'13::rrgs' => $logValue( $this->changes[$group]['removeself']['remove'] ?? null ),
				'14::ap'   => strtolower( wfMessage( $logAP )->inContentLanguage()->text() ),
			];
		}

		if ( $this->dbname !== 'default' ) {
			$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $this->dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}

	private function deleteUsersFromGroup( string $group ): void {
		if ( $this->dbname === 'default' ) {
			// Not a valid wiki to remove users from groups
			return;
		}

		$userGroupManagerFactory = MediaWikiServices::getInstance()->getUserGroupManagerFactory();
		$userGroupManager = $userGroupManagerFactory->getUserGroupManager( $this->dbname );

		$actorStoreFactory = MediaWikiServices::getInstance()->getActorStoreFactory();
		$userIdentityLookup = $actorStoreFactory->getUserIdentityLookup( $this->dbname );

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$dbr = $databaseUtils->getRemoteWikiReplicaDB( $this->dbname );

		$userIds = $dbr->newSelectQueryBuilder()
			->select( 'ug_user' )
			->from( 'user_groups' )
			->where( [ 'ug_group' => $group ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		foreach ( $userIds as $userId ) {
			$remoteUser = $userIdentityLookup->getUserIdentityByUserId( $userId );
			if ( $remoteUser ) {
				$userGroupManager->removeUserFromGroup( $remoteUser, $group );
			}
		}
	}
}
