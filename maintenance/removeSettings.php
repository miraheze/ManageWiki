<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiRemoveSettings extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'wgsetting', 'The $wg setting minus $.' );
	}

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$remoteWiki = RemoteWiki::newFromName( $wgDBname );
		$settingsarray = $remoteWiki->getSettings();

		if ( isset( $settingsarray[ $this->getOption( 'wgsetting' ) ] ) ) {
			unset( $settingsarray[ $this->getOption( 'wgsetting' ) ] );
		}

		$settings = json_encode( $settingsarray );

		$dbw->update( 'mw_settings',
			[
				's_settings' => $settings
			],
			[
				's_dbname' => $wgDBname
			],
			__METHOD__
		);
	}
}

$maintClass = 'ManageWikiRemoveSettings';
require_once RUN_MAINTENANCE_IF_MAIN;
