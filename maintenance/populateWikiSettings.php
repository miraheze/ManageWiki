<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulateSettings extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'wgsetting', 'The $wg setting minus $.', true );
		$this->addOption( 'sourcelist', 'File in format of "wiki|value" for the $wg setting above.', true );
	}

	function execute() {
		$dbw = wfGetDB( DB_MASTER );

		$settingsource = file( $this->getOption( 'sourcelist' ) );

		foreach ( $settingsource as $input ) {
			$wikiDB = explode( '|', $input, 2 );
			list( $DBname, $settingvalue ) = array_pad( $wikiDB, 2, '' );

			$remoteWiki = RemoteWiki::newFromName( $DBname );

			$settingsarray = $remoteWiki->getSettings();

			$settingsarray[$this->getOption('wgsetting')] = str_replace( "\n", '', $settingvalue );

			$settings = json_encode( $settingsarray );

			$dbw->update( 'cw_wikis',
				array(
					'wiki_settings' => $settings
				),
				array(
					'wiki_dbname' => $DBname
				),
				__METHOD__
			);

			unset( $remoteWiki );
		}
	}
}

$maintClass = 'ManageWikiPopulateSettings';
require_once RUN_MAINTENANCE_IF_MAIN;
