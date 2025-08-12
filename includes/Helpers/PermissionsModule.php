<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\IModule;
use stdClass;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\Platform\ISQLPlatform;
use function array_diff;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function implode;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function sort;

class PermissionsModule implements IModule {

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $deleteGroups = [];
	private array $renameGroups = [];
	private array $livePermissions = [];
	private array $messageFields = [];

	private ?string $log = null;

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ITextFormatter $textFormatter,
		private readonly string $dbname
	) {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		$perms = $dbr->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $perms as $perm ) {
			if ( !$perm instanceof stdClass ) {
				// Skip unexpected row
				continue;
			}

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
	 * Retrieves data for a specific group
	 * @param string $group Group wanted
	 * @return array Group configuration
	 */
	public function list( string $group ): array {
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
	 * @return array<string, array>
	 */
	public function listAll(): array {
		return $this->livePermissions;
	}

	/**
	 * @return list<string>
	 */
	public function listGroups(): array {
		return array_keys( $this->listAll() );
	}

	/**
	 * Get all groups that have the specified permission
	 *
	 * @param string $permission The permission to look for
	 * @return string[] List of group names that have the permission
	 * @phan-return associative-array<int, string>
	 */
	public function getGroupsWithPermission( string $permission ): array {
		$groups = [];
		foreach ( $this->listAll() as $group => $data ) {
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
					$new = array_values( array_unique( $new ) );
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

	public function addMessageFields( string $group ): void {
		$this->messageFields[] = $group;
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

		foreach ( $this->listGroups() as $name ) {
			$this->modify( $name, [
				'addgroups' => [
					'remove' => [ $group ],
				],
				'removegroups' => [
					'remove' => [ $group ],
				],
				'addself' => [
					'remove' => [ $group ],
				],
				'removeself' => [
					'remove' => [ $group ],
				],
			] );
		}

		// We will handle all processing in final stages
		unset( $this->livePermissions[$group] );

		// Push to a deletion queue
		$this->deleteGroups[] = $group;
	}

	public function rename( string $group, string $newName ): void {
		if ( $group === $newName ) {
			return;
		}

		$this->changes[$group] = [
			'oldname' => $group,
			'newname' => $newName,
		];

		foreach ( $this->listGroups() as $name ) {
			$data = $this->list( $name );
			if ( in_array( $group, $data['addgroups'] ?? [], true ) ) {
				$this->modify( $name, [
					'addgroups' => [
						'add' => [ $newName ],
						'remove' => [ $group ],
					],
				] );
			}

			if ( in_array( $group, $data['removegroups'] ?? [], true ) ) {
				$this->modify( $name, [
					'removegroups' => [
						'add' => [ $newName ],
						'remove' => [ $group ],
					],
				] );
			}

			if ( in_array( $group, $data['addself'] ?? [], true ) ) {
				$this->modify( $name, [
					'addself' => [
						'add' => [ $newName ],
						'remove' => [ $group ],
					],
				] );
			}

			if ( in_array( $group, $data['removeself'] ?? [], true ) ) {
				$this->modify( $name, [
					'removeself' => [
						'add' => [ $newName ],
						'remove' => [ $group ],
					],
				] );
			}
		}

		// Push to a rename queue
		$this->renameGroups[$group] = $newName;
	}

	public function isDeleting( string $group ): bool {
		return in_array( $group, $this->deleteGroups, true );
	}

	public function isRenaming( string $group ): bool {
		return isset( $this->renameGroups[$group] );
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function hasChanges(): bool {
		return $this->changes || $this->messageFields;
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

		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		foreach ( array_keys( $this->changes ) as $group ) {
			if ( $this->isDeleting( $group ) ) {
				$this->log = 'delete-group';
				$dbw->newDeleteQueryBuilder()
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

			if ( $this->isRenaming( $group ) ) {
				$this->log = 'rename-group';
				$this->logParams = [
					'5::oldname' => $group,
					'6::newname' => $this->renameGroups[$group],
				];

				$dbw->newUpdateQueryBuilder()
					->update( 'mw_permissions' )
					->set( [ 'perm_group' => $this->renameGroups[$group] ] )
					->where( [
						'perm_dbname' => $this->dbname,
						'perm_group' => $group,
					] )
					->caller( __METHOD__ )
					->execute();

				$this->moveUsersFromGroup( $group );
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

			$dbw->newInsertQueryBuilder()
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

			if ( $this->log !== null ) {
				// If we already have a log type we don't need to change it
				continue;
			}

			$logAP = ( $this->changes[$group]['autopromote'] ?? false ) ? 'htmlform-yes' : 'htmlform-no';
			$logNULL = $this->textFormatter->format( MessageValue::new( 'rightsnone' ) );

			/**
			 * Convert a list of permission/group changes into a comma-separated string.
			 * Used for logging. Falls back to $logNULL when the list is empty.
			 * Keeps the logic out of the main logParams block for readability and reusability.
			 */
			$logValue = static fn ( ?array $value ): string =>
				$value ? implode( ', ', $value ) : $logNULL;

			$this->logParams = [
				'4::ar' => $logValue( $this->changes[$group]['permissions']['add'] ?? null ),
				'5::rr' => $logValue( $this->changes[$group]['permissions']['remove'] ?? null ),
				'6::aag' => $logValue( $this->changes[$group]['addgroups']['add'] ?? null ),
				'7::rag' => $logValue( $this->changes[$group]['addgroups']['remove'] ?? null ),
				'8::arg' => $logValue( $this->changes[$group]['removegroups']['add'] ?? null ),
				'9::rrg' => $logValue( $this->changes[$group]['removegroups']['remove'] ?? null ),
				'10::aags' => $logValue( $this->changes[$group]['addself']['add'] ?? null ),
				'11::rags' => $logValue( $this->changes[$group]['addself']['remove'] ?? null ),
				'12::args' => $logValue( $this->changes[$group]['removeself']['add'] ?? null ),
				'13::rrgs' => $logValue( $this->changes[$group]['removeself']['remove'] ?? null ),
				'14::ap' => mb_strtolower( $this->textFormatter->format( MessageValue::new( $logAP ) ) ),
			];
		}

		if ( $this->dbname !== ModuleFactory::DEFAULT_DBNAME ) {
			$data = $this->dataFactory->newInstance( $this->dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}

	private function deleteUsersFromGroup( string $group ): void {
		if ( $this->dbname === ModuleFactory::DEFAULT_DBNAME ) {
			// Not a valid database to remove users from groups
			return;
		}

		$userGroupManager = $this->userGroupManagerFactory->getUserGroupManager( $this->dbname );
		$userIdentityLookup = $this->actorStoreFactory->getUserIdentityLookup( $this->dbname );

		$dbr = $this->databaseUtils->getRemoteWikiReplicaDB( $this->dbname );
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

	private function moveUsersFromGroup( string $group ): void {
		if ( $this->dbname === ModuleFactory::DEFAULT_DBNAME ) {
			// Not a valid database to move users from groups
			return;
		}

		$dbw = $this->databaseUtils->getRemoteWikiPrimaryDB( $this->dbname );

		$dbw->newUpdateQueryBuilder()
			->update( 'user_groups' )
			->set( [ 'ug_group' => $this->renameGroups[$group] ] )
			->where( [ 'ug_group' => $group ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newUpdateQueryBuilder()
			->update( 'user_former_groups' )
			->set( [ 'ufg_group' => $this->renameGroups[$group] ] )
			->where( [ 'ufg_group' => $group ] )
			->caller( __METHOD__ )
			->execute();
	}
}
