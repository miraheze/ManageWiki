<?php

class ManageWikiHooks {
	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onSetupAfterCache() {
		global $IP, $wgManageWikiPermissionsManagement, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgCreateWikiDatabase, $wgDBname, $wgManageWikiPermissionsAdditionalRights, $wgManageWikiPermissionsAdditionalAddGroups, $wgManageWikiPermissionsAdditionalRemoveGroups;

		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( $wgManageWikiPermissionsManagement ) {
			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];
			
			// Emergency fix for excessive DB queries
			if ( file_exists( "$IP/cache/managewiki-permissions-{$wgDBname}.cdb" ) ) {
				$res = unserialize( file_get_contents( "$IP/cache/managewiki-permissions-{$wgDBname}.cdb" ) );
			} else {
				$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

				$res = $dbr->select(
					'mw_permissions',
					[ 'perm_group', 'perm_permissions', 'perm_addgroups', 'perm_removegroups' ],
					[ 'perm_dbname' => $wgDBname ],
					__METHOD__
				);
				
				file_put_contents( "$IP/cache/managewiki-permissions-{$wgDBname}.cdb", serialize( $res ) );
			}

			foreach ( $res as $row ) {
				$permsjson = json_decode( $row->perm_permissions );

				foreach ( (array)$permsjson as $perm ) {
					$wgGroupPermissions[$row->perm_group][$perm] = true;
				}

				if ( $wgManageWikiPermissionsAdditionalRights ) {
					$wgGroupPermissions = array_merge_recursive( $wgGroupPermissions, $wgManageWikiPermissionsAdditionalRights );
				}

				$wgAddGroups[$row->perm_group] = json_decode( $row->perm_addgroups );

				if ( $wgManageWikiPermissionsAdditionalAddGroups ) {
					$wgAddGroups = array_merge_recursive( $wgAddGroups, $wgManageWikiPermissionsAdditionalAddGroups );
				}

				$wgRemoveGroups[$row->perm_group] = json_decode( $row->perm_removegroups );

				if ( $wgManageWikiPermissionsAdditionalRemoveGroups ) {
					$wgRemoveGroups = array_merge_recursive( $wgRemoveGroups, $wgManageWikiPermissionsAdditionalRemoveGroups );
				}
			}
		}
	}

	public static function onCreateWikiCreation( $dbname, $private ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase;

		$defaultGroups = ManageWiki::defaultGroups();

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		if ( !$private ) {
			$defaultGroups = array_diff( $defaultGroups, [ "member" ] );
		}

		foreach ( $defaultGroups as $newgroup ) {
			$grouparray = ManageWiki::defaultGroupPermissions( $newgroup );

			$dbw->insert(
				'mw_permissions',
				[
					'perm_dbname' => $dbname,
					'perm_group' => $newgroup,
					'perm_permissions' => json_encode( $grouparray['permissions'] ),
					'perm_addgroups' => json_encode( $grouparray['add'] ),
					'perm_removegroups' => json_encode( $grouparray['remove'] )
				],
				__METHOD__
			);
		}
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiPermissionsManagement;

		if ( $wgManageWikiPermissionsManagement && $wgManageWikiPermissionsDefaultPrivateGroup ) {

			$defaultarray = ManageWiki::defaultGroupPermissions( $wgManageWikiPermissionsDefaultPrivateGroup );

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			$dbw->insert(
				'mw_permissions',
				[
					'perm_dbname' => $dbname,
					'perm_group' => $wgManageWikiPermissionsDefaultPrivateGroup,
					'perm_permissions' => json_encode( $defaultarray['permissions'] ),
					'perm_addgroups' => json_encode( $defaultarray['add'] ),
					'perm_removegroups' => json_encode( $defaultarray['remove'] ),
				],
				__METHOD__
			);

			$publicGroups = [ '*', 'user' ];

			foreach ( $publicGroups as $group ) {
				$meta = ManageWiki::groupPermissions( $group );
				$perms = $meta['permissions'];

				$newperms = array_diff( $perms, [ 'read' ] );

				$dbw->update(
					'mw_permissions',
					[
						'perm_permissions' => $json_encode( $newperms )
					],
					[
						'perm_dbname' => $dbname,
						'perm_group' => $group
					],
					__METHOD__
				);
			}
		}
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiPermissionsManagement;

		if ( $wgManageWikiPermissionsManagement && $wgManageWikiPermissionsDefaultPrivateGroup ) {

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			$dbw->delete(
				'mw_permissions',
				[
					'perm_dbname' => $dbname,
					'perm_group' => $wgManageWikiPermissionsDefaultPrivateGroup
				],
				__METHOD__
			);

			$meta = ManageWiki::groupPermissions( '*' );
			$perms = $meta['permissions'];
			$perms[] = "read";

			$dbw->update(
				'mw_permissions',
				[
					'perm_permissions' => json_encode( $perms )
				],
				[
					'perm_dbname' => $dbname,
					'perm_group' => '*'
				],
				__METHOD__
			);
		}
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiSidebarLinks, $wgEnableManageWiki, $wgManageWikiExtensions,
			$wgManageWikiPermissionsManagement, $wgManageWikiSettings;
		if (
			$skin->getUser()->isAllowed( 'managewiki' ) &&
			$wgManageWikiSidebarLinks
		) {
			if ( $wgEnableManageWiki ) {
				$bar['Administration'][] = [
					'text' => wfMessage( 'managewiki-link' )->plain(),
					'id' => 'managewikilink',
					'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki' )->getFullURL() )
				];

				if ( $wgManageWikiExtensions ) {
					$bar['Administration'][] = [
						'text' => wfMessage( 'managewiki-extensions-link' )->plain(),
						'id' => 'managewikiextensionslink',
						'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWikiExtensions' )->getFullURL() )
					];
				}

				if ( $wgManageWikiPermissionsManagement ) {
					$bar['Administration'][] = [
						'text' => wfMessage( 'managewiki-permissions-link' )->plain(),
						'id' => 'managewikipermissionslink',
						'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWikiPermissions' )->getFullURL() )
					];
				}

				if ( $wgManageWikiSettings ) {
					$bar['Administration'][] = [
						'text' => wfMessage( 'managewiki-settings-link' )->plain(),
						'id' => 'managewikisettingslink',
						'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWikiSettings' )->getFullURL() )
					];
				}
			}
		}
	}
}
