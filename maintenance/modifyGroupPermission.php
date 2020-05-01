<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiModifyGroupPermission extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'group', 'The group name you want to change.', false );
		$this->addOption( 'all', 'Gets all perm group names.', false );
		$this->addOption( 'addperms', 'Comma separated list of permissions to add.', false, true );
		$this->addOption( 'removeperms', 'Comma separated list of permissions to remove.', false, true );
		$this->addOption( 'newaddgroups', 'Comma separated list of groups to add to the list of addable groups.', false, true );
		$this->addOption( 'removeaddgroups', 'Comma separated list of groups to remove from the list of addable groups.', false, true );
		$this->addOption( 'newremovegroups', 'Comma separated list of groups to add to the list of removable groups.', false, true );
		$this->addOption( 'removeremovegroups', 'Comma separated list of groups to remove from the list of removable groups.', false, true );
	}

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;

		$mwPermissions = new ManageWikiPermissions( $wgDBname );

		$permData = [
			'permissions' => [
				'add' => (array)explode( ',', $this->getOption( 'addperms', '' ) ),
				'remove' => (array)explode( ',', $this->getOption( 'removeperms', '' ) )
			],
			'addgroups' => [
				'add' => (array)explode( ',', $this->getOption( 'newaddgroups', '' ) ),
				'remove' => (array)explode( ',', $this->getOption( 'removeaddgroups', '' ) )
			],
			'removegroups' => [
				'add' => (array)explode( ',', $this->getOption( 'newremovegroups', '' ) ),
				'remove' => (array)explode( ',', $this->getOption( 'removeremovegroups', '' ) )
			]
		];

		if ( $this->getOption( 'all' ) ) {
			$groups = array_keys ( $mwPermissions->list() );

			foreach ( $groups as $group ) {
				$mwPermissions->modify( $group, $permData );
			}
		} elseif ( $this->getArg( 0 ) ) {
			$mwPermissions->modify($this->getArg(0), $permData);
		} else {
			$this->output( 'You must supply either the group as a arg or use --all' );
		}

		$mwPermissions->commit();

	}
}

$maintClass = 'ManageWikiModifyGroupPermission';
require_once RUN_MAINTENANCE_IF_MAIN;
