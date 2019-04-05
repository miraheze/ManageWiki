<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulatePermissionsWithDefaults extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	function execute() {
		global $wgCreateWikiDatabase, $wgManageWikiPermissionsDefaultPrivateGroup, $wgDBname;

		if ( !ManageWiki::checkSetup( 'permissions' ) ) {
			$this->fatalError( 'Enable ManageWiki Permissions on this wiki.' );
		}

		$defaultGroups = array_diff( (array)ManageWikiPermissions::defaultGroups(), (array)$wgManageWikiPermissionsDefaultPrivateGroup );

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		foreach ( $defaultGroups as $newgroup ) {
			$grouparray = ManageWikiPermissions::defaultGroupPermissions( $newgroup );

			$dbw->insert(
				'mw_permissions',
				[
					'perm_dbname' => $wgDBname,
					'perm_group' => $newgroup,
					'perm_permissions' => json_encode( $grouparray['permissions'] ),
					'perm_addgroups' => json_encode( $grouparray['addgroups'] ),
					'perm_removegroups' => json_encode( $grouparray['removegroups'] )
				],
				__METHOD__
			);
		}

		ManageWikiCDB::changes( 'permissions' );
	}
}

$maintClass = 'ManageWikiPopulatePermissionsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
