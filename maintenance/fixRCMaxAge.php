<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiRemoveSettings extends Maintenance {

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$remoteWiki = RemoteWiki::newFromName( $wgDBname );
		$settingsarray = $remoteWiki->getSettings();

		if ( isset( $settingsarray['wgRCMaxAge'] ) ) {
			$settingsarray['wgRCMaxAge'] = (int)$settingsarray['wgRCMaxAge'];
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
