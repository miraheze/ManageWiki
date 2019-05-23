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

	function execute() {
		global $wgCreateWikiDatabase, $wgDBname, $wmgPrivateWiki;

		$checkRow = false;

		if ( $this->getOption( 'overwrite' ) ) {
			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
			$dbw->delete(
				'mw_permissions',
				[
					'perm_dbname' => $wgDBname
				],
				__METHOD__
			);
		}

		if ( !$this->getOption( 'overwrite' ) ) {
			$checkRow = $dbr->selectRow(
				'mw_permissions',
				[
					'*'
				],
				[
					'perm_dbname' => $wgDBname
				]
			);
		}

		if ( !$checkRow ) {
			ManageWikiHooks::onCreateWikiCreation( $wgDBname, $wmgPrivateWiki );
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissionsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
