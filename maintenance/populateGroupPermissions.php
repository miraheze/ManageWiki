<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulatePermissions extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	function execute() {
		global $wgCreateWikiDatabase, $wgManageWikiPermissionsBlacklistGroups, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgDBname;

		$blacklist = $wgManageWikiPermissionsBlacklistGroups;

		$grouparray = [];

		foreach ( $wgGroupPermissions as $group => $perm ) {
			$permsarray = [];

			if ( !in_array( $group, $blacklist) ) {
				foreach ( $perm as $name => $value ) {
					if ( $value == true ) {
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

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		foreach ( $grouparray as $groupname => $groupatr ) {
			$dbw->insert( 'mw_permissions',
				array(
					'perm_dbname' => $wgDBname,
					'perm_group' => $groupname,
					'perm_permissions' => $groupatr['perms'],
					'perm_addgroups' => empty( $groupatr['add'] ) ? json_encode( [] ) : $groupatr['add'],
					'perm_removegroups' => empty( $groupatr['remove'] ) ? json_encode( [] ) : $groupatr['remove'],
				),
				__METHOD__
			);
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissions';
require_once RUN_MAINTENANCE_IF_MAIN;
