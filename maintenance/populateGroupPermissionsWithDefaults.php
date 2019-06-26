<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulatePermissionsWithDefaults extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.', false, false );
	}

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname, $wmgPrivateWiki, $wgManageWikiPermissionsDefaultPrivateGroup;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		if ( $this->getOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_permissions',
				[
					'perm_dbname' => $wgDBname
				],
				__METHOD__
			);
		}

		$checkRow = $dbw->selectRow(
			'mw_permissions',
			[
				'*'
			],
			[
				'perm_dbname' => $wgDBname
			]
		);

		if ( !$checkRow ) {
			$defaultGroups = array_diff( (array)ManageWikiPermissions::availableGroups( 'default' ), (array)$wgManageWikiPermissionsDefaultPrivateGroup );
			foreach ( $defaultGroups as $newgroup ) {
				$groupArray = ManageWikiPermissions::groupPermissions( $newgroup, 'default' );
				$dbw->insert(
					'mw_permissions',
					[
						'perm_dbname' => $wgDBname,
						'perm_group' => $newgroup,
						'perm_permissions' => json_encode( $groupArray['permissions'] ),
						'perm_addgroups' => json_encode( $groupArray['ag'] ),
						'perm_removegroups' => json_encode( $groupArray['rg'] ),
						'perm_addgroupstoself' => json_encode( $groupArray['ags'] ),
						'perm_removegroupsfromself' => json_encode( $groupArray['rgs'] ),
						'perm_autopromote' => ( is_null( $groupArray['autopromote'] ) ) ? null : json_encode( $groupArray['autopromote'] )
					],
					__METHOD__
				);
			}
			if ( $wmgPrivateWiki ) {
				ManageWikiHooks::onCreateWikiStatePrivate( $wgDBname );
			}

			ManageWikiCDB::changes( 'permissions' );
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissionsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
