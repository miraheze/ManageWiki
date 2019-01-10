<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiModifyGroupPermission extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'group', 'The group name you want to change.', true );
		$this->addOption( 'addperms', 'Comma separated list of permissions to add.', false, true );
		$this->addOption( 'removeperms', 'Comma separated list of permissions to remove.', false, true );
	}

	function execute() {
		$addp = (array)explode( ',', $this->getOption( 'addperms', '' ) );
		$removep = (array)explode( ',', $this->getOption( 'removeperms', '' ) );

		ManageWiki::modifyPermissions( $this->getArg( 0 ), $addp, $removep );
	}
}

$maintClass = 'ManageWikiModifyGroupPermission';
require_once RUN_MAINTENANCE_IF_MAIN;
