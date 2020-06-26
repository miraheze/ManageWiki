<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class FixRCMaxAge extends Maintenance {

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$remoteWiki = RemoteWiki::newFromName( $wgDBname );
		$settingsarray = $remoteWiki->getSettings();

		if ( isset( $settingsarray['wgRCMaxAge'] ) ) {
			if ( (int)$settingsarray['wgRCMaxAge'] < 1 ) {
				$settingsarray['wgRCMaxAge'] = 1;
			} else if ( (int)$settingsarray['wgRCMaxAge'] > 15552000 ) 
				$settingsarray['wgRCMaxAge'] = 15552000;
			} else {
				$settingsarray['wgRCMaxAge'] = (int)$settingsarray['wgRCMaxAge'];
			}
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

		$cWJ = new CreateWikiJson( $wgDBname );
		$cWJ->resetWiki();
	}
}

$maintClass = 'FixRCMaxAge';
require_once RUN_MAINTENANCE_IF_MAIN;
