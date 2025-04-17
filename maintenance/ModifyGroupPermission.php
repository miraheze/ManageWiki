<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;

class ModifyGroupPermission extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addArg( 'group', 'The group name you want to change.', false );
		$this->addOption( 'all', 'Gets all perm group names.' );
		$this->addOption( 'addperms', 'Comma separated list of permissions to add.', false, true );
		$this->addOption( 'removeperms', 'Comma separated list of permissions to remove.', false, true );
		$this->addOption( 'newaddgroups', 'Comma separated list of groups to add to the list of addable groups.', false, true );
		$this->addOption( 'removeaddgroups', 'Comma separated list of groups to remove from the list of addable groups.', false, true );
		$this->addOption( 'newremovegroups', 'Comma separated list of groups to add to the list of removable groups.', false, true );
		$this->addOption( 'removeremovegroups', 'Comma separated list of groups to remove from the list of removable groups.', false, true );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$mwPermissions = new ManageWikiPermissions( $this->getConfig()->get( MainConfigNames::DBname ) );

		$permData = [
			'permissions' => [
				'add' => $this->getValue( 'addperms' ),
				'remove' => $this->getValue( 'removeperms' ),
			],
			'addgroups' => [
				'add' => $this->getValue( 'newaddgroups' ),
				'remove' => $this->getValue( 'removeaddgroups' ),
			],
			'removegroups' => [
				'add' => $this->getValue( 'newremovegroups' ),
				'remove' => $this->getValue( 'removeremovegroups' ),
			],
		];

		if ( $this->hasOption( 'all' ) ) {
			$groups = array_keys( $mwPermissions->list( group: null ) );

			foreach ( $groups as $group ) {
				$this->changeGroup( $group, $permData, $mwPermissions );
			}

			return;
		}

		if ( $this->getArg( 0 ) ) {
			$this->changeGroup( $this->getArg( 0 ), $permData, $mwPermissions );
			return;
		}

		$this->fatalError( 'You must supply either the group as a arg or use --all' );
	}

	private function changeGroup(
		string $name,
		array $permData,
		ManageWikiPermissions $mwPermissions
	): void {
		$groupData = $mwPermissions->list( group: $name );

		if ( !in_array( $name, $this->getConfig()->get( ConfigNames::PermissionsPermanentGroups ) ) && ( count( $permData['permissions']['remove'] ) > 0 ) && ( count( $groupData['permissions'] ) === count( $permData['permissions']['remove'] ) ) ) {
			$mwPermissions->remove( $name );
		} else {
			$mwPermissions->modify( $name, $permData );
		}

		$mwPermissions->commit();
	}

	private function getValue( string $option ): array {
		return $this->getOption( $option, '' ) === '' ?
			[] : explode( ',', $this->getOption( $option, '' ) );
	}
}

// @codeCoverageIgnoreStart
return ModifyGroupPermission::class;
// @codeCoverageIgnoreEnd
