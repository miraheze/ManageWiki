<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MainConfigNames;
use Miraheze\ManageWiki\ManageWiki;

class PopulateGroupPermissions extends Maintenance {
	public function execute() {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$this->fatalError( 'Disable ManageWiki Permissions on this wiki.' );
		}

		$excluded = $this->getConfig()->get( 'ManageWikiPermissionsDisallowedGroups' );

		$grouparray = [];

		foreach ( $this->getConfig()->get( MainConfigNames::GroupPermissions ) as $group => $perm ) {
			$permsarray = [];

			if ( !in_array( $group, $excluded ) ) {
				foreach ( $perm as $name => $value ) {
					if ( $value ) {
						$permsarray[] = $name;
					}
				}

				$grouparray[$group]['perms'] = json_encode( $permsarray );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::AddGroups ) as $group => $add ) {
			if ( !in_array( $group, $excluded ) ) {
				$grouparray[$group]['add'] = json_encode( $add );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::RemoveGroups ) as $group => $remove ) {
			if ( !in_array( $group, $excluded ) ) {
				$grouparray[$group]['remove'] = json_encode( $remove );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsAddToSelf ) as $group => $adds ) {
			if ( !in_array( $group, $excluded ) ) {
				$grouparray[$group]['addself'] = json_encode( $adds );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::GroupsRemoveFromSelf ) as $group => $removes ) {
			if ( !in_array( $group, $excluded ) ) {
				$grouparray[$group]['removeself'] = json_encode( $removes );
			}
		}

		foreach ( $this->getConfig()->get( MainConfigNames::Autopromote ) as $group => $promo ) {
			if ( !in_array( $group, $excluded ) ) {
				$grouparray[$group]['autopromote'] = json_encode( $promo );
			}
		}

		$dbw = $this->getDB( DB_PRIMARY, [], $this->getConfig()->get( 'CreateWikiDatabase' ) );

		foreach ( $grouparray as $groupname => $groupatr ) {
			$check = $dbw->selectRow(
				'mw_permissions',
				[ 'perm_group' ],
				[
					'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
					'perm_group' => $groupname
				],
				__METHOD__
			);

			if ( !$check ) {
				$dbw->insert( 'mw_permissions',
					[
						'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
						'perm_group' => $groupname,
						'perm_permissions' => empty( $groupatr['perms'] ) ? json_encode( [] ) : $groupatr['perms'],
						'perm_addgroups' => empty( $groupatr['add'] ) ? json_encode( [] ) : $groupatr['add'],
						'perm_removegroups' => empty( $groupatr['remove'] ) ? json_encode( [] ) : $groupatr['remove'],
						'perm_addgroupstoself' => empty( $groupatr['adds'] ) ? json_encode( [] ) : $groupatr['adds'],
						'perm_removegroupsfromself' => empty( $groupatr['removes'] ) ? json_encode( [] ) : $groupatr['removes'],
						'perm_autopromote' => empty( $groupatr['autopromote'] ) ? json_encode( [] ) : $groupatr['autopromote']
					],
					__METHOD__
				);
			}
		}
	}
}

$maintClass = PopulateGroupPermissions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
