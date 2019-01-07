<?php
class ManageWikiHooks {
	public static function fnManageWikiSchemaUpdates( DatabaseUpdater $updater ) {
		global $wgCreateWikiDatabase, $wgDBname;

		if ( $wgCreateWikiDatabase === $wgDBname ) {
			$updater->addExtensionTable( 'mw_namespaces',
					__DIR__ . '/../sql/mw_namespaces.sql' );
			$updater->addExtensionTable( 'mw_permissions',
					__DIR__ . '/../sql/mw_permissions.sql' );
		}
	}

	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onSetupAfterCache() {
		global $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups, $wgCreateWikiDatabase, $wgDBname, $wgManageWikiPermissionsAdditionalRights, $wgManageWikiPermissionsAdditionalAddGroups, $wgManageWikiPermissionsAdditionalRemoveGroups, $wgManageWikiCDBDirectory,
			$wgManageWikiNamespacesCore, $wgContentNamespaces, $wgExtraNamespaces, $wgNamespaceProtection, $wgNamespacesToBeSearchedDefault, $wgNamespaceAliases, $wgNamespaceWithSubpages;

		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];

			if ( !ManageWikiCDB::latest( 'permissions' ) ) {
				ManageWikiCDB::upsert( 'permissions' );
			}

			$permsArray = ManageWikiCDB::get( 'permissions', [ 'wgGroupPermissions', 'wgAddGroups', 'wgRemoveGroups' ] );

			foreach( $permsArray as $key => $json ) {
				$permsArray[$key] = json_decode( $json, true );
			}

			foreach ( $permsArray as $key => $array ) {
				if ( $key == 'wgGroupPermissions' ) {
					foreach ( $array as $group => $perms ) {
						foreach ( $perms as $i => $perm ) {
							$$key[$group][$perm] = true;
						}
					}
				} else {
					foreach ( $array as $i => $groups ) {
						foreach ( $groups as $id => $group ) {
							$$key[$i][] = $group;
						}
					}
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

		// Safe guard if - should not remove existing namespaces if we're not going to manage them
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$wgContentNamespaces = [];
			$wgExtraNamespaces = [];
			$wgNamespaceProtection = [];
			$wgNamespacesToBeSearchedDefault = [];
			$wgNamespacesWithSubpages = [];
			$wgNamespaceAliases = [];

			if ( !ManageWikiCDB::latest( 'namespaces' ) ) {
				ManageWikiCDB::upsert( 'namespaces' );
			}

			$nsArray = ManageWikiCDB::get( 'namespaces', [ 'wgContentNamespaces', 'wgExtraNamespaces', 'wgNamespaceProtection', 'wgNamespacesToBeSearchedDefault', 'wgNamespacesWithSubpages', 'wgNamespaceAliases', 'wgManageWikiNamespacesCore' ] );

			foreach ( $nsArray as $key => $json ) {
				$nsArray[$key] = json_decode( $json, true );
			}

			foreach ( $nsArray as $key => $array ) {
				if ( !is_array( $array ) ) {
					continue;
				}

				foreach ( $array as $id => $val ) {
					$$key[$id] = $val;
				}
			}
		}
	}

	public static function onCreateWikiCreation( $dbname, $private ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgManageWikiExtensionsDefault, $wgCanonicalNamespaceNames, $wgNamespaceAliases,
			$wgNamespacesToBeSearchedDefault, $wgNamespacesWithSubpages, $wgContentNamespaces, $wgNamespaceProtection;

		if ( ManageWiki::checkSetup( 'permissions' ) ) {
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

			ManageWikiCDB::changes( 'permissions' );
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

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
			$namespaces = $wgCanonicalNamespaceNames + [ 0 => '<Main>' ];

			foreach ( $namespaces as $id => $name ) {
				if ( $id < 0 ) {
					// Don't include imaginary
					continue;
				}

				$matchedNSKeys = array_keys( $wgNamespaceAliases, $id );
				$nsAliases = [];

				foreach ( $matchedNSKeys as $o => $n ) {
					$nsAliases[] = $n;
				}

				// Wiki was just created, we can assume no entries in mw_namespaces
				$dbw->insert(
					'mw_namespaces',
					[
						'ns_dbname' => $dbname,
						'ns_namespace_id' => (int)$id,
						'ns_namespace_name' => (string)$name,
						'ns_searchable' => (int)$wgNamespacesToBeSearchedDefault[$id],
						'ns_subpages' => (int)$wgNamespacesWithSubpages[$id],
						'ns_content' => (int)$wgContentNamespaces[$id],
						'ns_protection' => ( is_array( $wgNamespaceProtection[$id] ) ) ? (string)$wgNamespaceProtection[$id][0] : (string)$wgNamespaceProtection[$id],
						'ns_aliases' => (string)json_encode( $nsAliases ),
						'ns_core' => ( $id < 1000 ) ? 1 : 0 // we assume less than < is "core", could do with smarter logic!
					],
					__METHOD__
				);
			}
		}
	}

	public static function onCreateWikiDeletion( $dbw, $wiki ) {
		global $wgManageWikiCDBDirectory;

		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			DeleteWiki::doDeletes( $dbw, 'mw_permissions', 'perm_dbname', $wiki );

			if ( ManageWiki::checkSetup( 'cdb' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $wiki . '-permissions.cdb' );
			}
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			DeleteWiki::doDeletes( $dbw, 'mw_namespaces', 'ns_dbname', $wiki );

			if ( ManageWiki::checkSetup( 'cdb' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $wiki . '-namespaces.cdb' );
			}
		}
	}

	public static function onCreateWikiRename( $dbw, $old, $new ) {
		global $wgManageWikiCDBDirectory;

		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			RenameWiki::doRename( $dbw, 'mw_permissions', 'perm_dbname', $old, $new );

			if ( ManageWiki::checkSetup( 'cdb' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $old . '-permissions.cdb' );
			}
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			RenameWiki::doRename( $dbw, 'mw_namespaces', 'ns_dbname', $old, $new );

			if ( ManageWiki::checkSetup( 'cdb' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $old . '-namespaces.cdb' );
			}
		}
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase;

		if ( ManageWiki::checkSetup( 'permissions' ) && $wgManageWikiPermissionsDefaultPrivateGroup ) {

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

		ManageWikiCDB::changes( 'permissions' );
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase;

		if ( ManageWiki::checkSetup( 'permissions' ) && $wgManageWikiPermissionsDefaultPrivateGroup ) {
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

		ManageWikiCDB::changes( 'permissions' );
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiSidebarLinks, $wgManageWiki;

		if (
			$skin->getUser()->isAllowed( 'managewiki' )
			&& $wgManageWikiSidebarLinks
		) {
			$bar['Administration'][] = [
				'text' => wfMessage( 'managewiki-link' )->plain(),
				'id' => 'managewikilink',
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki' )->getFullURL() )
			];

			foreach ( (array)ManageWiki::listModules() as $module ) {
				$bar['Administration'][] = [
					'text' => wfMessage( 'managewiki-' . $module . '-link' )->plain(),
					'id' => 'managewiki' . $module . 'link',
					'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki' . ucfirst( $module ) )->getFullURL() )
				];
			}
		}
	}
}
