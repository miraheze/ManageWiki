<?php

use MediaWiki\MediaWikiServices;

/**
 * Handler for interacting with Permissions
 */
class ManageWikiPermissions {
	/** @var bool Whether changes are committed to the database */
	private $committed = false;
	/** @var Config Configuration object */
	private $config;
	/** @var MaintainableDBConnRef Database connection */
	private $dbw;
	/** @var array Deletion queue */
	private $deleteGroups = [];
	/** @var array Permissions configuration */
	private $livePermissions = [];
	/** @var string WikiID */
	private $wiki;

	/** @var array Changes to be committed */
	public $changes = [];
	/** @var array Errors */
	public $errors = [];

	/**
	 * ManageWikiNamespaces constructor.
	 * @param string $wiki WikiID
	 */
	public function __construct( string $wiki ) {
		$this->wiki = $wiki;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
		$this->dbw = wfGetDB( DB_MASTER, [], $this->config->get( 'CreateWikiDatabase' ) );

		$perms = $this->dbw->select(
			'mw_permissions',
			'*',
			[
				'perm_dbname' => $wiki
			]
		);

		// Bring database values to class scope
		foreach ( $perms as $perm ) {
			$this->livePermissions[$perm->perm_group] = [
				'permissions' => json_decode( $perm->perm_permissions, true ),
				'addgroups' => json_decode( $perm->perm_addgroups, true ),
				'removegroups' => json_decode( $perm->perm_removegroups, true ),
				'addself' => json_decode( $perm->perm_addgroupstoself, true ),
				'removeself' => json_decode( $perm->perm_removegroupsfromself, true ),
				'autopromote' => json_decode( $perm->perm_autopromote, true )
			];
		}
	}

	/**
	 * Lists either all groups or a specific one
	 * @param string|null Group wanted (null for all)
	 * @return array Group configuration
	 */
	public function list( string $group = null ) {
		if ( is_null( $group ) ) {
			return $this->livePermissions;
		} else {
			return $this->livePermissions[$group] ?? [
					'permissions' => [],
					'addgroups' => [],
					'removegroups' => [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => []
				];
		}
	}

	/**
	 * Modify a group handler
	 * @param string $group Group name
	 * @param array $data Merging information about the group
	 */
	public function modify( string $group, array $data ) {
		// We will handle all processing in final stages
		$permData = [
			'permissions' => $this->livePermissions[$group]['permissions'] ?? [],
			'addgroups' => $this->livePermissions[$group]['addgroups'] ?? [],
			'removegroups' => $this->livePermissions[$group]['removegroups'] ?? [],
			'addself' => $this->livePermissions[$group]['addself'] ?? [],
			'removeself' => $this->livePermissions[$group]['removeself'] ?? [],
			'autopromote' => $this->livePermissions[$group]['autopromote'] ?? null
		];

		// Overwrite the defaults above with our new modified values
		foreach ( $data as $name => $array ) {
			if ( $name != 'autopromote' ) {
				foreach ( $array as $type => $value ) {
					$permData[$name] = ( $type == 'add' ) ? array_merge( $permData[$name], $value ?? [] ) : array_diff( $permData[$name], $value ?? [] );

					$this->changes[$group][$name][$type] = $value;
				}
			} elseif ( $permData['autopromote'] != $data['autopromote'] ) {
				$permData['autopromote'] = $data['autopromote'];

				$this->changes[$group]['autopromote'] = true;
			}
		}

		$this->livePermissions[$group] = $permData;
	}

	/**
	 * Remove a group
	 * @param string Group name
	 */
	public function remove( string $group ) {
		// Utilise changes differently in this case
		foreach ( $this->livePermissions[$group] as $name => $value ) {
			$this->changes[$group][$name] = [
				'add' => null,
				'remove' => $value
			];
		}

		// We will handle all processing in final stages
		unset( $this->livePermissions[$group] );

		// Push to a deletion queue
		$this->deleteGroups[] = $group;
	}

	/**
	 * Commits all changes to database
	 */
	public function commit() {
		foreach ( array_keys( $this->changes ) as $group ) {
			if ( in_array( $group, $this->deleteGroups ) ) {
				$this->dbw->delete(
					'mw_permissions',
					[
						'perm_dbname' => $this->wiki,
						'perm_group' => $group
					]
				);
			} else {
				$builtTable = [
					'perm_permissions' => json_encode( $this->livePermissions[$group]['permissions'] ),
					'perm_addgroups' => json_encode( $this->livePermissions[$group]['addgroups'] ),
					'perm_removegroups' => json_encode( $this->livePermissions[$group]['removegroups'] ),
					'perm_addgroupstoself' => json_encode( $this->livePermissions[$group]['addself'] ),
					'perm_removegroupsfromself' => json_encode( $this->livePermissions[$group]['removeself'] ),
					'perm_autopromote' => is_null( $this->livePermissions[$group]['autopromote'] ) ? null : json_encode( $this->livePermissions[$group]['autopromote'] )
				];

				$this->dbw->upsert(
					'mw_permissions',
					[
						'perm_dbname' => $this->wiki,
						'perm_group' => $group
					] + $builtTable,
					[
						'perm_dbname',
						'perm_group'
					],
					$builtTable
				);
			}
		}

		if ( $this->wiki != 'default' ) {
			$cWJ = new CreateWikiJson( $this->wiki );
			$cWJ->resetWiki();
		}
		$this->committed = true;
	}

	/**
	 * Checks if changes are committed to the database or not
	 */
	public function __destruct() {
		if ( !$this->committed && !empty( $this->changes ) ) {
			print 'Changes have not been committed to the database!';
		}
	}
}

