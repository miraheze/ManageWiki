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
		global $wgCreateWikiDatabase, $wgDBname, $wmgPrivateWiki;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
		$dbw->delete(
			'mw_permissions',
			[
				'perm_dbname' => $wgDBname
			],
			__METHOD__
		);

		ManageWikiHooks::onCreateWikiCreation( $wgDBname, $wmgPrivateWiki );
	}
}

$maintClass = 'ManageWikiPopulatePermissionsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
