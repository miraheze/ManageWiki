<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulateSettings extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'wgsetting', 'The $wg setting minus $.' );
		$this->addOption( 'sourcelist', 'File in format of "wiki|value" for the $wg setting above.' );
	}

	public function execute() {
		$settingsource = file( $this->getOption( 'sourcelist' ) );

		foreach ( $settingsource as $input ) {
			$wikiDB = explode( '|', $input, 2 );
			list( $DBname, $settingvalue ) = array_pad( $wikiDB, 2, '' );

			$this->output( "Setting $settingvalue for $DBname\n" );

			$setting = str_replace( "\n", '', $settingvalue );

			if ( $setting === "true" ) {
				$setting = true;
			} elseif ( $setting === "false" ) {
				$setting = false;
			}

			$mwSettings = new ManageWikiSettings( $DBname );
			$mwSettings->modify( [ $this->getOption( 'wgsetting' ) => $setting ] );
			$mwSettings->commit();
		}
	}
}

$maintClass = 'ManageWikiPopulateSettings';
require_once RUN_MAINTENANCE_IF_MAIN;
