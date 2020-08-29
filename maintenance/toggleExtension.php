<?php

use MediaWiki\MediaWikiServices;

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
		$mwExt = new ManageWikiExtensions( MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'DBname' ) );

		$ext = $this->getArg( 0 );

		$enable = !(bool)$this->getOption( 'disable' );

		if ( $enable ) {
			$mwExt->add( $ext );
		} else {
			$mwExt->remove( $ext );
		}

		$mwExt->commit();
	}
}

$maintClass = 'ManageWikiToggleExtension';
require_once RUN_MAINTENANCE_IF_MAIN;
