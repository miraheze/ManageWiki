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
		global $wgCreateWikiDatabase, $wgManageWikiPermissionsBlacklistGroups, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups;

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
			$addarray = [];

			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['add'] = json_encode( $add );
			}
		}

		foreach ( $wgRemoveGroups as $group => $remove ) {
			$removearray = [];

			if ( !in_array( $group, $blacklist ) ) {
				$grouparray[$group]['remove'] = json_encode( $remove );
			}
		}

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		foreach ( $grouppermsarray as $groupname => $groupatr ) {
			$dbw->insert( 'mw_permissions',
				array(
					'perm_wiki' => $wgDBname,
					'perm_group' => $groupname,
					'perm_permissions' => $groupatr['perms'],
					'perm_addgroups' => $groupatr['add'],
					'perm_removegroups' => $groupatr['remove'],
				),
				__METHOD__
			);
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissions';
require_once RUN_MAINTENANCE_IF_MAIN;
