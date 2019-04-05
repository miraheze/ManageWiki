<?php

class ManageWikiPermissions {
	public static function availableGroups( string $wiki = null ) {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbName = $wiki ?? $wgDBname;

		$dbr = wfGetDB( DB_SLAVE, [], $wgCreateWikiDatabase );

		$res = $dbr->select(
			'mw_permissions',
			'perm_group',
			[
				'perm_dbname' => $dbName
			]
		);

		$groups = [];

		foreach ( $res as $row ) {
			$groups[] = $row->perm_group;
		}

		return $groups;
	}

	public static function groupPermissions( string $group, string $wiki = null ) {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbName = $wiki ?? $wgDBname;

		$dbr = wfGetDB( DB_SLAVE, [], $wgCreateWikiDatabase );

		$row = $dbr->selectRow(
			'mw_permissions',
			'*',
			[
				'perm_dbname' => $dbName,
				'perm_group' => $group
			]
		);

		if ( $row ) {
			$groupAssigns = [
				'wgAddGroups' => json_decode( $row->perm_addgroups, true ),
				'wgRemoveGroups' => json_decode( $row->perm_removegroups, true )
			];

			$data = [
				'permissions' => json_decode( $row->perm_permissions, true ),
				'matrix' => ManageWiki::handleMatrix( json_encode( $groupAssigns ), 'php' ),
			];
		} else {
			$data = [
				'permissions' => [],
				'matrix' => []
			];
		}

		return $data;
	}

	public static function groupAssignBuilder( string $group, string $wiki = null ) {
		global $wgDBname, $wgManageWikiPermissionsBlacklistRights, $wgManageWikiPermissionsBlacklistGroups;

		$dbName = $wiki ?? $wgDBname;

		$groupData = self::groupPermissions( $group, $dbName );

		return [
			'allPermissions' => array_diff( User::getAllRights(), ( isset( $wgManageWikiPermissionsBlacklistRights[$group] ) ) ? array_merge( $wgManageWikiPermissionsBlacklistRights[$group], $wgManageWikiPermissionsBlacklistRights['any'] ) : $wgManageWikiPermissionsBlacklistRights['any'] ),
			'assignedPermissions' => $groupData['permissions'],
			'allGroups' => array_diff( self::availableGroups( $dbName ), $wgManageWikiPermissionsBlacklistGroups, User::getImplicitGroups() ),
			'groupMatrix' => $groupData['matrix'],
		];
	}

	public static function modifyPermissions( string $group, array $addp = [], array $removep = [], array $addag = [], array $removeag = [], array $addrg = [], array $removerg = [], string $wiki = null ) {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbName = $wiki ?? $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$existingGroup = in_array( $group, self::availableGroups( $dbName ) );

		if ( $existingGroup ) {
			$groupData = (array)self::groupPermissions( $group, $dbName );
			$perms = array_merge( $addp, array_diff( $groupData['permissions'], $removep ) );
			$addGroups = array_merge( $addag, array_diff( $groupData['addgroups'], $removeag ) );
			$removeGroups = array_merge( $addrg, array_diff( $groupData['removegroups'], $removerg ) );
		} else {
			// Not an existing group
			$perms = $addp;
			$addGroups = $addag;
			$removeGroups = $addrg;
		}

		$row = [
			'perm_dbname' => $dbName,
			'perm_group' => $group,
			'perm_permissions' => json_encode( $perms ),
			'perm_addgroups' => json_encode( $addGroups ),
			'perm_removegroups' => json_encode( $removeGroups )
		];

		if ( $existingGroup ) {
			$dbw->update(
				'mw_permissions',
				$row,
				[
					'perm_dbname' => $dbName,
					'perm_group' => $group
				]
			);
		} else {
			$dbw->insert(
				'mw_permissions',
				$row
			);
		}
	}
}
