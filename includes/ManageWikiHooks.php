<?php
class ManageWikiHooks {
	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onSetupAfterCache() {
		global $wgManageWikiPermissionsManagement, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgCreateWikiDatabase, $wgDBname, $wgManageWikiPermissionsAdditionalRights, $wgManageWikiPermissionsAdditionalAddGroups, $wgManageWikiPermissionsAdditionalRemoveGroups;
		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( $wgManageWikiPermissionsManagement ) {
			$cache = ObjectCache::getLocalServerInstance( CACHE_MEMCACHED );

			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];

			$wikiPermissions = $cache->getWithSetCallback(
				$cache->makeKey( 'ManageWiki', 'mwpermissions' ),
				// memcached runs on each server as a standalone instance.
				// Thus, since cache invalidation only happens at one server at a time
				// (and 'getLocalServerInstance', obviously, is meant for a local caching solution)
				// one server will serve a wiki with the right permissions and other servers
				// with the wrong permissions. A final solution might be a memcached cluster
				// or central caching solution with more memory. (e.g. redis in Miraheze's setup)
				// For now, set TTL to 60 seconds so at least there is some form of caching,
				// and the short TTL matches i18n message managewiki-perm-success-text.
				// -- Southparkfan 2018/9/9
				$cache::TTL_MINUTE,
				function () use (
					$wgCreateWikiDatabase,
					$wgManageWikiPermissionsAdditionalRights,
					$wgManageWikiPermissionsAdditionalAddGroups,
					$wgManageWikiPermissionsAdditionalRemoveGroups,
					$wgDBname,
					$wgGroupPermissions,
					$wgAddGroups,
					$wgRemoveGroups
				) {
					$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );
					$res = $dbr->select(
						'mw_permissions',
						[ 'perm_group', 'perm_permissions', 'perm_addgroups', 'perm_removegroups' ],
						[ 'perm_dbname' => $wgDBname ],
						__METHOD__
					);

					foreach ( $res as $row ) {
						$permsJson = json_decode( $row->perm_permissions );

						foreach ( (array)$permsJson as $perm ) {
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

					return (string)serialize( [
						'wgGroupPermissions' => $wgGroupPermissions,
						'wgAddGroups' => $wgAddGroups,
						'wgRemoveGroups' => $wgRemoveGroups,
					] );
				}
			);

			$permissionsArray = unserialize( $wikiPermissions );

			$wgGroupPermissions = $permissionsArray['wgGroupPermissions'];
			$wgAddGroups = $permissionsArray['wgAddGroups'];
			$wgRemoveGroups = $permissionsArray['wgRemoveGroups'];
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
					'perm_addgroups' => json_encode( $grouparray['addgroups'] ),
					'perm_removegroups' => json_encode( $grouparray['removegroups'] )
				],
				__METHOD__
			);

			$cache = ObjectCache::getLocalServerInstance( CACHE_MEMCACHED );
			$cache->delete( $cache->makeKey( 'ManageWiki', 'mwpermissions' ) );
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
					'perm_addgroups' => json_encode( $defaultarray['addgroups'] ),
					'perm_removegroups' => json_encode( $defaultarray['removegroups'] ),
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

			$cache = ObjectCache::getLocalServerInstance( CACHE_MEMCACHED );
			$cache->delete( $cache->makeKey( 'ManageWiki', 'mwpermissions' ) );
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

			$cache = ObjectCache::getLocalServerInstance( CACHE_MEMCACHED );
			$cache->delete( $cache->makeKey( 'ManageWiki', 'mwpermissions' ) );
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
