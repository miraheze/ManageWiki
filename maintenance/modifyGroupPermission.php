<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiModifyGroupPermission extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'group', 'The group name you want to change.' );
		$this->addOption( 'all', 'Gets all perm group names.', false, true );
		$this->addOption( 'addperms', 'Comma separated list of permissions to add.', false, true );
		$this->addOption( 'removeperms', 'Comma separated list of permissions to remove.', false, true );
		$this->addOption( 'newaddgroups', 'Comma separated list of groups to add to the list of addable groups.', false, true );
		$this->addOption( 'removeaddgroups', 'Comma separated list of groups to remove from the list of addable groups.', false, true );
		$this->addOption( 'newremovegroups', 'Comma separated list of groups to add to the list of removable groups.', false, true );
		$this->addOption( 'removeremovegroups', 'Comma separated list of groups to remove from the list of removable groups.', false, true );
	}

	public function execute() {
		global $wgDBname;

		$addp = (array)explode( ',', $this->getOption( 'addperms', '' ) );
		$removep = (array)explode( ',', $this->getOption( 'removeperms', '' ) );
		$addag = (array)explode( ',', $this->getOption( 'newaddgroups', '' ) );
		$removeag = (array)explode( ',', $this->getOption( 'removeaddgroups', '' ) );
		$addrg = (array)explode( ',', $this->getOption( 'newremovegroups', '' ) );
		$removerg = (array)explode( ',', $this->getOption( 'removeremovegroups', '' ) );
		
		if ( $this->getArg( 0 ) ) {
			$this->modifyPermissions(
				$this->getArg( 0 ),
				$addp,
				$removep,
				$addag,
				$removeag,
				$addrg,
				$removerg
			);
		} elseif ( $this->getOption( 'all' ) ) {
			$res = $dbw->select(
				'mw_permissions',
				[
					'perm_group',
				],
				[
					'perm_dbname' => $wgDBname
				],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$this->modifyPermissions(
					$row->perm_group,
					$addp,
					$removep,
					$addag,
					$removeag,
					$addrg,
					$removerg
				);
			}
			ManageWikiCDB::changes( 'permissions' );
		} else {
			$this->output( 'You must supply either the group as a arg or use --all' );
		}
	}
	
	private function modifyPermissions( $groupName, $addp, $removep, $addag, $removeag, $addrg, $removerg ) {
		ManageWikiPermissions::modifyPermissions( $groupName, $addp, $removep, $addag, $removeag, $addrg, $removerg, [], [], [], [] );
	}
}

$maintClass = 'ManageWikiModifyGroupPermission';
require_once RUN_MAINTENANCE_IF_MAIN;
