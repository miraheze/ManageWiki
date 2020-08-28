<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulatePermissions extends Maintenance {
	public function execute() {
		global $wgCreateWikiDatabase, $wgManageWikiPermissionsBlacklistGroups, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgRevokePermissions, $wgDBname, $wgGroupsAddToSelf, $wgGroupsRemoveFromSelf, $wgAutopromote;

		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$this->fatalError( 'Disable ManageWiki Permissions on this wiki.' );
		}


		$blacklist = $wgManageWikiPermissionsBlacklistGroups;

		$grouparray = [];

		foreach ( $wgGroupPermissions as $group => $perm ) {
			$permsarray = [];

			if ( !in_array( $group, $blacklist) ) {
				foreach ( $perm as $name => $value ) {
					if ( $value ) {
						$permsarray[] = $name;
					}
				}

				$grouparray[$group]['perms'] = json_encode( $permsarray );
			}
		}

		foreach ( $wgAddGroups as $group => $add ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['add'] = json_encode( $add );
			}
		}

		foreach ( $wgRemoveGroups as $group => $remove ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['remove'] = json_encode( $remove );
			}
		}

		foreach ( $wgGroupsAddToSelf as $group => $adds ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['addself'] = json_encode( $adds );
			}
		}

		foreach ( $wgGroupsRemoveFromSelf as $group => $removes ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['removeself'] = json_encode( $removes );
			}
		}

		foreach ( $wgRevokePermissions as $group => $revokes ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['revoke'] = json_encode( $revokes );
			}
		}

		foreach ( $wgAutopromote as $group => $promo ) {
			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['autopromote'] = json_encode( $promo );
			}
		}

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		foreach ( $grouparray as $groupname => $groupatr ) {
			$check = $dbw->selectRow(
				'mw_permissions',
				[ 'perm_group' ],
				[
					'perm_dbname' => $wgDBname,
					'perm_group' => $groupname
				],
				__METHOD__
			);

			if ( !$check ) {
				$dbw->insert( 'mw_permissions',
					[
						'perm_dbname' => $wgDBname,
						'perm_group' => $groupname,
						'perm_permissions' => $groupatr['perms'],
						'perm_addgroups' => empty( $groupatr['add'] ) ? json_encode( [] ) : $groupatr['add'],
						'perm_removegroups' => empty( $groupatr['remove'] ) ? json_encode( [] ) : $groupatr['remove'],
						'perm_addgroupstoself' => empty( $groupatr['adds'] ) ? json_encode( [] ) : $groupatr['adds'],
						'perm_removegroupsfromself' => empty( $groupatr['removes'] ) ? json_encode( [] ) : $groupatr['removes'],
						'perm_revoke' => empty( $groupatr['revokes'] ) ? json_encode( [] ) : $groupatr['revokes'],
						'perm_autopromote' => empty( $groupatr['autopromote'] ) ? json_encode( [] ) : $groupatr['autopromote']
					],
					__METHOD__
				);
			}
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissions';
require_once RUN_MAINTENANCE_IF_MAIN;
