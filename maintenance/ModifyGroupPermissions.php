<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\PermissionsModule;
use function explode;
use function in_array;

class ModifyGroupPermissions extends Maintenance {

	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'group', 'The group name you want to change.', false, true );
		$this->addOption( 'delete', 'Delete the group entirely instead of modifying its permissions.' );
		$this->addOption( 'addperms', 'Comma separated list of permissions to add.', false, true );
		$this->addOption( 'removeperms', 'Comma separated list of permissions to remove.', false, true );

		$this->addOption( 'newaddgroups',
			'Comma separated list of groups to add to the list of addable groups.',
			false, true
		);

		$this->addOption( 'removeaddgroups',
			'Comma separated list of groups to remove from the list of addable groups.',
			false, true
		);

		$this->addOption( 'newremovegroups',
			'Comma separated list of groups to add to the list of removable groups.',
			false, true
		);

		$this->addOption( 'removeremovegroups',
			'Comma separated list of groups to remove from the list of removable groups.',
			false, true
		);

		$this->addOption( 'all-groups', 'Apply the change to every existing group instead of a single group.' );
		$this->addOption( 'all-wikis', 'Apply the change to all wikis instead of just the local wiki.' );
		$this->addOption( 'execute', 'Confirm execution. Required if using --all-wikis.' );

		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();
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

		if ( $this->hasOption( 'all-wikis' ) ) {
			if ( !$this->hasOption( 'execute' ) ) {
				$this->fatalError( 'You must use --execute when using --all-wikis.', 2 );
			}

			$dbnames = $this->getConfig()->get( MainConfigNames::LocalDatabases );
			foreach ( $dbnames as $dbname ) {
				$mwPermissions = $this->moduleFactory->permissions( $dbname );
				$this->applyToGroups( $mwPermissions, $permData, $dbname );
			}

			$this->output( "Applied changes to all wikis.\n" );
			return;
		}

		// Local only
		$mwPermissions = $this->moduleFactory->permissionsLocal();
		$this->applyToGroups( $mwPermissions, $permData, 'local wiki' );
	}

	private function applyToGroups(
		PermissionsModule $mwPermissions,
		array $permData,
		string $target
	): void {
		if ( $this->hasOption( 'all-groups' ) ) {
			$groups = $mwPermissions->listGroups();
			foreach ( $groups as $group ) {
				$this->changeGroup( $mwPermissions, $permData, $group, $target );
			}

			return;
		}

		if ( $this->hasOption( 'group' ) ) {
			$group = $this->getOption( 'group' );
			$this->changeGroup( $mwPermissions, $permData, $group, $target );
			return;
		}

		$this->fatalError( 'You must supply either supply --group or use --all-groups' );
	}

	private function changeGroup(
		PermissionsModule $mwPermissions,
		array $permData,
		string $group,
		string $target
	): void {
		if ( $this->hasOption( 'delete' ) ) {
			$permanentGroups = $this->getConfig()->get( ConfigNames::PermissionsPermanentGroups );
			if ( in_array( $group, $permanentGroups, true ) ) {
				$this->output( "Skipped deleting $group on $target: group is marked permanent\n" );
				return;
			}

			$mwPermissions->remove( $group );
			if ( $mwPermissions->getErrors() ) {
				$this->output( "Failed to delete $group on $target\n" );
				return;
			}

			$mwPermissions->commit();
			$this->output( "Deleted $group from $target\n" );
			return;
		}

		$mwPermissions->modify( $group, $permData );
		$mwPermissions->commit();
		$this->output( "Modified $group on $target\n" );
	}

	/**
	 * @return array{}|non-empty-list<string>
	 */
	private function getValue( string $option ): array {
		$value = $this->getOption( $option, '' );
		return $value === '' ? [] : explode( ',', $value );
	}
}

// @codeCoverageIgnoreStart
return ModifyGroupPermissions::class;
// @codeCoverageIgnoreEnd
