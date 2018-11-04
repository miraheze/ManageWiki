<?php
class ManageWikiHooks {
	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onSetupAfterCache() {
		global $wgManageWikiPermissionsManagement, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgCreateWikiDatabase, $wgDBname, $wgManageWikiPermissionsAdditionalRights, $wgManageWikiPermissionsAdditionalAddGroups, $wgManageWikiPermissionsAdditionalRemoveGroups, $wgManageWikiCDBDirectory;
		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( $wgManageWikiPermissionsManagement ) {
			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];
			$cacheArray = [];
			$useDB = true;
			$useCDB = false;


			if ( $wgManageWikiCDBDirectory ) {
				$cdbfile = "$wgManageWikiCDBDirectory/permissions-$wgDBname.cdb";
				$useCDB = true;

				if ( file_exists( $cdbfile ) ) {
					// We're using CDB already so let's get it
					$cdbr = \Cdb\Reader::open( $cdbfile );
					$cache = ObjectCache::getLocalClusterInstance();
					$cacheValue = $cache->get( $cache->makeKey( 'ManageWiki', 'mwpermissions' ) );

					// check whether $cdbr (stored value) is equal to $cache (last change)
					if ( (bool)$cacheValue && ( ( (int)$cdbr->get( 'getVersion' ) == (int)$cacheValue ) ) ) {
						$permissionsArray = (array)json_decode( $cdbr->get( 'permissions' ), true );
						$availableGroups = (array)json_decode( $cdbr->get( 'availablegroups' ), true );
						$useDB = false;

						foreach ( $availableGroups as $group ) {
							$groupArray = $permissionsArray[$group];

							foreach ( (array)$groupArray['permissions'] as $perm ) {
								$wgGroupPermissions[$group][$perm] = true;
							}

							$wgAddGroups[$group] = $groupArray['addgroups'];
							$wgRemoveGroups[$group] = $groupArray['removegroups'];
						}
					}

					$cdbr->close();
				}
			}

			if ( $useDB ) {
				$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );
				$res = $dbr->select(
					'mw_permissions',
					[ 'perm_group', 'perm_permissions', 'perm_addgroups', 'perm_removegroups' ],
					[ 'perm_dbname' => $wgDBname ],
					__METHOD__
				);

				foreach ( $res as $row ) {
					$permsJson = json_decode( $row->perm_permissions, true );
						foreach ( (array)$permsJson as $perm ) {
						$wgGroupPermissions[$row->perm_group][$perm] = true;
					}

					$wgAddGroups[$row->perm_group] = json_decode( $row->perm_addgroups, true );

					$wgRemoveGroups[$row->perm_group] = json_decode( $row->perm_removegroups, true );

					$cacheArray[$row->perm_group] = [
						'permissions' => $permsJson,
						'addgroups' => json_decode( $row->perm_addgroups, true ),
						'removegroups' => json_decode( $row->perm_removegroups, true )
					];
				}

				if ( $useCDB ) {
					// Let's make a CDB!
					$cache = ObjectCache::getLocalClusterInstance();
					$cacheVersion = $cache->get( $cache->makeKey( 'ManageWiki', 'mwpermissions' ) );

					if ( !$cacheVersion ) {
						$cacheVersion = $cache->set( $cache->makeKey( 'ManageWiki', 'mwpermissions' ), (int)1 );
					}

					$cdbw = \Cdb\Writer::open( $cdbfile );
					$cdbw->set( 'getVersion', (string)$cacheVersion );
					$cdbw->set( 'availablegroups', json_encode( ManageWiki::availableGroups() ) );
					$cdbw->set( 'permissions', json_encode( $cacheArray ) );
					$cdbw->close();
				}
			}

			if ( $wgManageWikiPermissionsAdditionalRights ) {
				$wgGroupPermissions = array_merge_recursive( $wgGroupPermissions, $wgManageWikiPermissionsAdditionalRights );
			}

			if ( $wgManageWikiPermissionsAdditionalAddGroups ) {
				$wgAddGroups = array_merge_recursive( $wgAddGroups, $wgManageWikiPermissionsAdditionalAddGroups );
			}

			if ( $wgManageWikiPermissionsAdditionalRemoveGroups ) {
				$wgRemoveGroups = array_merge_recursive( $wgRemoveGroups, $wgManageWikiPermissionsAdditionalRemoveGroups );
			}

		}
	}

	public static function onCreateWikiCreation( $dbname, $private ) {
		global $wgManageWikiPermissionsManagement, $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgManageWikiExtensionsDefault;

		if ( $wgManageWikiPermissionsManagement ) {
			$defaultGroups = array_diff( (array)ManageWiki::defaultGroups(), (array)$wgManageWikiPermissionsDefaultPrivateGroup );

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

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
			}

			if ( $private ) {
				ManageWikiHooks::onCreateWikiStatePrivate( $dbname );
			}

			$updateCache = ManageWiki::updateCDBCacheVersion();
		}

		if ( $wgManageWikiExtensions && $wgManageWikiExtensionsDefault ) {
			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			$cur = explode( ",",
				(string)$dbw->selectRow(
					'cw_wikis',
					[ 'wiki_extensions' ],
					[ 'wiki_dbname' => $dbname ],
					__METHOD__
				)->wiki_extensions
			);

			$newlist = implode( ",", array_merge( $cur, $wgManageWikiExtensionsDefault ) );

			$dbw->update(
				'cw_wikis',
				[ 'wiki_extensions' => $newlist ],
				[ 'wiki_dbname' => $dbname ],
				__METHOD__
			);
		}
	}

	public static function onCreateWikiDeletion( $dbw, $wiki ) {
		global $wgManageWikiPermissionsManagement, $wgManageWikiCDBDirectory;

		if ( $wgManageWikiPermissionsManagement ) {
			DeleteWiki::doDeletes( $dbw, 'mw_permissions', 'perm_dbname', $wiki );

			if ( $wgManageWikiCDBDirectory ) {
				$wiki = wfEscapeShellArg( $wiki );
				unlink( $wgManageWikiCDBDirectory . '/permissions-' . $wiki . '.cdb' );
			}
		}
	}

	public static function onCreateWikiRename( $dbw, $old, $new ) {
		global $wgManageWikiPermissionsManagement, $wgManageWikiCDBDirectory;

		if ( $wgManageWikiPermissionsManagement ) {
			RenameWiki::doRename( $dbw, 'mw_permissions', 'perm_dbname', $old, $new );

			if ( $wgManageWikiCDBDirectory ) {
				unlink( $wgManageWikiCDBDirectory . '/permissions-' . $old . '.cdb' );
			}
		}
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiPermissionsManagement;

		if ( $wgManageWikiPermissionsManagement && $wgManageWikiPermissionsDefaultPrivateGroup ) {

			$defaultarray = ManageWiki::defaultGroupPermissions( $wgManageWikiPermissionsDefaultPrivateGroup );

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			$check = $dbw->selectRow( 'mw_permissions',
				[ 'perm_dbname' ],
				[
					'perm_dbname' => $dbname,
					'perm_group' => $wgManageWikiPermissionsDefaultPrivateGroup
				],
				__METHOD__
			);

			if ( !$check ) {
				$dbw->insert( 'mw_permissions',
					[
						'perm_dbname' => $dbname,
						'perm_group' => $wgManageWikiPermissionsDefaultPrivateGroup,
						'perm_permissions' => json_encode( $defaultarray['permissions'] ),
						'perm_addgroups' => json_encode( $defaultarray['addgroups'] ),
						'perm_removegroups' => json_encode( $defaultarray['removegroups'] )
					],
					__METHOD__
				);
			}

			$publicGroups = [ '*', 'user' ];

			foreach ( $publicGroups as $group ) {
				$meta = ManageWiki::groupPermissions( $group );
				$perms = $meta['permissions'];

				$newperms = array_diff( $perms, [ 'read' ] );

				$dbw->update(
					'mw_permissions',
					[
						'perm_permissions' => json_encode( $newperms )
					],
					[
						'perm_dbname' => $dbname,
						'perm_group' => $group
					],
					__METHOD__
				);
			}
		}

		$updateCache = ManageWiki::updateCDBCacheVersion();
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

		$updateCache = ManageWiki::updateCDBCacheVersion();
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
