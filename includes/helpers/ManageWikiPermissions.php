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
				'wgRemoveGroups' => json_decode( $row->perm_removegroups, true ),
				'wgGroupsAddToSelf' => json_decode( $row->perm_addgroupstoself, true ),
				'wgGroupsRemoveFromSelf' => json_decode( $row->perm_removegroupsfromself, true )
			];

			$data = [
				'permissions' => json_decode( $row->perm_permissions, true ),
				'ag' => $groupAssigns['wgAddGroups'],
				'rg' => $groupAssigns['wgRemoveGroups'],
				'ags' => $groupAssigns['wgGroupsAddToSelf'],
				'rgs' => $groupAssigns['wgGroupsRemoveFromSelf'],
				'autopromote' => json_decode( $row->perm_autopromote, true ),
				'matrix' => ManageWiki::handleMatrix( json_encode( $groupAssigns ), 'php' ),
			];
		} else {
			$data = [
				'permissions' => [],
				'ag' => [],
				'rg' => [],
				'ags' => [],
				'rgs' => [],
				'autopromote' => [],
				'matrix' => []
			];
		}

		return $data;
	}

	public static function groupAssignBuilder( string $group, string $wiki = null ) {
		global $wgDBname, $wgManageWikiPermissionsBlacklistRights, $wgManageWikiPermissionsBlacklistGroups;

		$dbName = $wiki ?? $wgDBname;

		$groupData = static::groupPermissions( $group, $dbName );

		return [
			'allPermissions' => array_diff( User::getAllRights(), ( isset( $wgManageWikiPermissionsBlacklistRights[$group] ) ) ? array_merge( $wgManageWikiPermissionsBlacklistRights[$group], $wgManageWikiPermissionsBlacklistRights['any'] ) : $wgManageWikiPermissionsBlacklistRights['any'] ),
			'assignedPermissions' => $groupData['permissions'],
			'allGroups' => array_diff( static::availableGroups( $dbName ), $wgManageWikiPermissionsBlacklistGroups, User::getImplicitGroups() ),
			'groupMatrix' => $groupData['matrix'],
			'autopromote' => $groupData['autopromote']
		];
	}

	public static function modifyPermissions( string $group, array $addp = [], array $removep = [], array $addag = [], array $removeag = [], array $addrg = [], array $removerg = [], array $addags = [], array $removeags = [], array $addrgs = [], array $removergs = [], string $wiki = null ) {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbName = $wiki ?? $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$existingGroup = in_array( $group, static::availableGroups( $dbName ) );

		if ( $existingGroup ) {
			$groupData = (array)static::groupPermissions( $group, $dbName );
			$perms = array_merge( $addp, array_diff( $groupData['permissions'], $removep ) );
			$addGroups = array_merge( $addag, array_diff( $groupData['ag'], $removeag ) );
			$removeGroups = array_merge( $addrg, array_diff( $groupData['rg'], $removerg ) );
			$addGroupsToSelf = array_merge( $addags, array_diff( $groupData['ags'], $removeags ) );
			$removeGroupsFromSelf = array_merge( $removergs, array_diff( $groupData['rgs'], $removergs ) );
		} else {
			// Not an existing group
			$perms = $addp;
			$addGroups = $addag;
			$removeGroups = $addrg;
			$addGroupsToSelf = $addags;
			$removeGroupsFromSelf = $addrgs;
		}

		$row = [
			'perm_dbname' => $dbName,
			'perm_group' => $group,
			'perm_permissions' => json_encode( array_unique( $perms ) ),
			'perm_addgroups' => json_encode( array_unique( $addGroups ) ),
			'perm_removegroups' => json_encode( array_unique( $removeGroups ) ),
			'perm_addgroupstoself' => json_encode( array_unique( $addGroupsToSelf ) ),
			'perm_removegroupsfromself' => json_encode( array_unique( $removeGroupsFromSelf ) )
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
