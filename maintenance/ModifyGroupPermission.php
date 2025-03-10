<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;

class ModifyGroupPermission extends Maintenance {

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

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$mwPermissions = new ManageWikiPermissions( $this->getConfig()->get( MainConfigNames::DBname ) );

		$permData = [
			'permissions' => [
				'add' => (array)$this->getValue( 'addperms' ),
				'remove' => (array)$this->getValue( 'removeperms' ),
			],
			'addgroups' => [
				'add' => (array)$this->getValue( 'newaddgroups' ),
				'remove' => (array)$this->getValue( 'removeaddgroups' ),
			],
			'removegroups' => [
				'add' => (array)$this->getValue( 'newremovegroups' ),
				'remove' => (array)$this->getValue( 'removeremovegroups' ),
			]
		];

		if ( $this->getOption( 'all' ) ) {
			$groups = array_keys( $mwPermissions->list() );

			foreach ( $groups as $group ) {
				$this->changeGroup( $group, $permData, $mwPermissions );
			}
		} elseif ( $this->getArg( 0 ) ) {
			$this->changeGroup( $this->getArg( 0 ), $permData, $mwPermissions );
		} else {
			$this->output( 'You must supply either the group as a arg or use --all' );
		}
	}

	private function changeGroup( string $name, array $permData, object $mwPermissions ) {
		$permList = $mwPermissions->list( $name );

		if ( !in_array( $name, $this->getConfig()->get( 'ManageWikiPermissionsPermanentGroups' ) ) && ( count( $permData['permissions']['remove'] ) > 0 ) && ( count( $permList['permissions'] ) == count( $permData['permissions']['remove'] ) ) ) {
			$mwPermissions->remove( $name );
		} else {
			$mwPermissions->modify( $name, $permData );
		}

		$mwPermissions->commit();
	}

	private function getValue( string $option ) {
		return $this->getOption( $option, '' ) === '' ?
			[] : explode( ',', $this->getOption( $option, '' ) );
	}
}

// @codeCoverageIgnoreStart
return ModifyGroupPermission::class;
// @codeCoverageIgnoreEnd
