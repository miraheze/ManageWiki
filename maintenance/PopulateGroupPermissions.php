<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\ManageWiki;

class PopulateGroupPermissions extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$this->fatalError( 'Disable ManageWiki Permissions on this wiki.' );
		}

		$excluded = $this->getConfig()->get( ConfigNames::PermissionsDisallowedGroups );

		$grouparray = [];

		foreach ( $this->getConfig()->get( MainConfigNames::GroupPermissions ) as $group => $perm ) {
			$permsarray = [];

			if ( !in_array( $group, $excluded, true ) ) {
				foreach ( $perm as $name => $value ) {
					if ( $value ) {
						$permsarray[] = $name;
					}
				}

				$grouparray[$group]['perms'] = json_encode( $permsarray );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::AddGroups ) as $group => $add ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$grouparray[$group]['add'] = json_encode( $add );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::RemoveGroups ) as $group => $remove ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$grouparray[$group]['remove'] = json_encode( $remove );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsAddToSelf ) as $group => $adds ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$grouparray[$group]['addself'] = json_encode( $adds );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsRemoveFromSelf ) as $group => $removes ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$grouparray[$group]['removeself'] = json_encode( $removes );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::Autopromote ) as $group => $promo ) {
			if ( !in_array( $group, $excluded, true ) ) {
				$grouparray[$group]['autopromote'] = json_encode( $promo );
			}
		}

		$databaseUtils = $this->getServiceContainer()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		foreach ( $grouparray as $groupname => $groupatr ) {
			$check = $dbw->newSelectQueryBuilder()
				->select( 'perm_group' )
				->from( 'mw_permissions' )
				->where( [
					'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
					'perm_group' => $groupname,
				] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$check ) {
				$dbw->newInsertQueryBuilder()
					->insertInto( 'mw_permissions' )
					->row( [
						'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
						'perm_group' => $groupname,
						'perm_permissions' => empty( $groupatr['perms'] ) ? json_encode( [] ) : $groupatr['perms'],
						'perm_addgroups' => empty( $groupatr['add'] ) ? json_encode( [] ) : $groupatr['add'],
						'perm_removegroups' => empty( $groupatr['remove'] ) ? json_encode( [] ) : $groupatr['remove'],
						'perm_addgroupstoself' => empty( $groupatr['addself'] ) ? json_encode( [] ) : $groupatr['addself'],
						'perm_removegroupsfromself' => empty( $groupatr['removeself'] ) ? json_encode( [] ) : $groupatr['removeself'],
						'perm_autopromote' => empty( $groupatr['autopromote'] ) ? json_encode( [] ) : $groupatr['autopromote'],
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
