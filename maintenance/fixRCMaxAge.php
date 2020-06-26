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
			$settingsarray['wgRCMaxAge'] = (int)$settingsarray['wgRCMaxAge'];
			if ( $settingsarray['wgRCMaxAge'] < 1 ) {
                              $settingsarray['wgRCMaxAge'] = 1;
                        }
                        if ( $settingsarray['wgRCMaxAge'] > 15552000 ) {
                              $settingsarray['wgRCMaxAge'] = 15552000;
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
	}
}

$maintClass = 'FixRCMaxAge';
require_once RUN_MAINTENANCE_IF_MAIN;
