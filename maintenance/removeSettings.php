<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;

class RemoveSettings extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'ext', 'The ManageWiki name of the setting.', true );
	}

	public function execute() {
		$setting = $this->getArg( 0 );

		$mwSetting = new ManageWikiSettings( MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'DBname' ) );
		$mwSetting->remove( $setting );
		$mwSetting->commit();
	}
}

$maintClass = RemoveSettings::class;
require_once RUN_MAINTENANCE_IF_MAIN;
