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
		$this->addOption( 'newaddgroups', 'Comma separated list of groups to add to the list of addable groups.', false, true );
		$this->addOption( 'removeaddgroups', 'Comma separated list of groups to remove from the list of addable groups.', false, true );
		$this->addOption( 'newremovegroups', 'Comma separated list of groups to add to the list of removable groups.', false, true );
		$this->addOption( 'removeremovegroups', 'Comma separated list of groups to remove from the list of removable groups.', false, true );
	}

	function execute() {
		$addp = (array)explode( ',', $this->getOption( 'addperms', '' ) );
		$removep = (array)explode( ',', $this->getOption( 'removeperms', '' ) );
		$addag = (array)explode( ',', $this->getOption( 'newaddgroups', '' ) );
		$removeag = (array)explode( ',', $this->getOption( 'removeaddgroups', '' ) );
		$addrg = (array)explode( ',', $this->getOption( 'newremovegroups', '' ) );
		$removerg = (array)explode( ',', $this->getOption( 'removeremovegroups', '' ) );

		ManageWikiPermissions::modifyPermissions( $this->getArg( 0 ), $addp, $removep, $addag, $removeag, $addrg, $removerg, [], [], [], [] );

		ManageWikiCDB::changes( 'permissions' );
	}
}

$maintClass = 'ManageWikiModifyGroupPermission';
require_once RUN_MAINTENANCE_IF_MAIN;
