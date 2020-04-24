<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiToggleExtension extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'ext', 'The ManageWiki name of the extension.', true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
	}

	public function execute() {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$ext = $this->getArg( 0 );

		$enable = !(bool)$this->getOption( 'disable' );

		$exts = (string)$dbw->selectRow(
			'mw_settings',
			[ 's_extensions' ],
			[ 's_dbname' => $wgDBname ],
			__METHOD__
		)->s_extensions;

		if ( is_null( $exts ) ) {
			$extensions = [];
		} else {
			$extensions = (array)json_decode( $exts, true );
		}

		if ( in_array( (string)$ext, $extensions ) && !$enable ) {
			$newextensions = array_diff( $extensions, (array)$ext );
		} elseif ( !in_array( $ext, $extensions ) && $enable ) {
			$newextensions = $extensions;
			$newextensions[] = (string)$ext;
			sort( $newextensions );
		} else {
			$this->output( "No change to extension ($ext) state on $wgDBname." );

			return false;
		}

		$dbw->update( 'mw_settings',
			[ 's_extensions' => json_encode( $newextensions ) ],
			[ 's_dbname' => $wgDBname ],
			__METHOD__
		);

		Hooks::run( 'ManageWikiModifiedSettings', [ $wgDBname ] );

		$cWJ = new CreateWikiJson( $wgDBname );
		$cWJ->resetWiki();
	}
}

$maintClass = 'ManageWikiToggleExtension';
require_once RUN_MAINTENANCE_IF_MAIN;
