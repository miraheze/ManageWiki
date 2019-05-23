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
		global $wgCreateWikiDatabase, $wgDBname, $wmgPrivateWiki;

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
			ManageWikiHooks::onCreateWikiCreation( $wgDBname, $wmgPrivateWiki );
		}
	}
}

$maintClass = 'ManageWikiPopulatePermissionsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
