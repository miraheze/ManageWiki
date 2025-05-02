<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;

class PopulateGroupPermissions extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'force', 'Force populating permissions even if ManageWiki permissions is enabled.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
		if ( !$this->hasOption( 'force' ) && $moduleFactory->isEnabled( 'permissions' ) ) {
			$this->fatalError( 'Disable ManageWiki Permissions on this wiki.' );
		}

		$excluded = $this->getConfig()->get( ConfigNames::PermissionsDisallowedGroups );

		$groupArray = [];
		foreach ( $this->getConfig()->get( MainConfigNames::GroupPermissions ) as $group => $perm ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$permsArray = array_filter( $perm, static fn ( mixed $value ): bool => (bool)$value );
				$groupArray[$group]['perms'] = $permsArray;
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::AddGroups ) as $group => $add ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$groupArray[$group]['add'] = $add;
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::RemoveGroups ) as $group => $remove ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$groupArray[$group]['remove'] = $remove;
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsAddToSelf ) as $group => $adds ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$groupArray[$group]['addself'] = $adds;
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsRemoveFromSelf ) as $group => $removes ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$groupArray[$group]['removeself'] = $removes;
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::Autopromote ) as $group => $promo ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$groupArray[$group]['autopromote'] = $promo;
			}
		}

		$databaseUtils = $this->getServiceContainer()->get( 'ManageWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		$dbname = $this->getConfig()->get( MainConfigNames::DBname );
		foreach ( $groupArray as $groupName => $groupAttrs ) {
			$check = $dbw->newSelectQueryBuilder()
				->select( 'perm_group' )
				->from( 'mw_permissions' )
				->where( [
					'perm_dbname' => $dbname,
					'perm_group' => $groupName,
				] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$check ) {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'mw_permissions' )
					->row( [
						'perm_dbname' => $dbname,
						'perm_group' => $groupName,
						'perm_permissions' => json_encode( $groupAttrs['perms'] ?? [] ),
						'perm_addgroups' => json_encode( $groupAttrs['add'] ?? [] ),
						'perm_removegroups' => json_encode( $groupAttrs['remove'] ?? [] ),
						'perm_addgroupstoself' => json_encode( $groupAttrs['addself'] ?? [] ),
						'perm_removegroupsfromself' => json_encode( $groupAttrs['removeself'] ?? [] ),
						'perm_autopromote' => json_encode( $groupAttrs['autopromote'] ?? [] ),
					] )
					->caller( __METHOD__ )
					->execute();
			}
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateGroupPermissions::class;
// @codeCoverageIgnoreEnd
