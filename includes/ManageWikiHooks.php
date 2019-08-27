<?php
class ManageWikiHooks {
	public static function fnManageWikiSchemaUpdates( DatabaseUpdater $updater ) {
		global $wgCreateWikiDatabase, $wgDBname;

		if ( $wgCreateWikiDatabase === $wgDBname ) {
			$updater->addExtensionTable( 'mw_namespaces',
					__DIR__ . '/../sql/mw_namespaces.sql' );
			$updater->addExtensionTable( 'mw_permissions',
					__DIR__ . '/../sql/mw_permissions.sql' );
			$updater->modifyTable( 'mw_permissions',
					__DIR__ . '/../sql/patches/patch-groups-self.sql', true );
			$updater->modifyTable( 'mw_permissions',
					__DIR__ . '/../sql/patches/patch-autopromote.sql', true );
			$updater->modifyTable( 'mw_namespaces',
					__DIR__ . '/../sql/patches/patch-namespaces-additional.sql', true );
			$updater->modifyTable( 'mw_namespaces',
					__DIR__ . '/../sql/patches/patch-namespace-core-alter.sql', true );
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
			$wgManageWikiNamespacesCore, $wgContentNamespaces, $wgExtraNamespaces, $wgNamespaceProtection, $wgNamespacesToBeSearchedDefault, $wgNamespaceAliases, $wgNamespacesWithSubpages, $wgManageWikiPermissionsAdditionalAddGroupsSelf,
			$wgManageWikiPermissionsAdditionalRemoveGroupsSelf, $wgGroupsAddToSelf, $wgGroupsRemoveFromSelf, $wgAutopromote, $wgNamespaceContentModels, $wgManageWikiNamespacesAdditional;

		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];
			$wgGroupsAddToSelf = [];
			$wgGroupsRemoveFromSelf = [];
			$wgAutopromote = [];

			if ( !ManageWikiCDB::latest( 'permissions' ) ) {
				ManageWikiCDB::upsert( 'permissions' );
			}

			$permsArray = ManageWikiCDB::get( 'permissions', [ 'wgGroupPermissions', 'wgAddGroups', 'wgRemoveGroups', 'wgGroupsAddToSelf', 'wgGroupsRemoveFromSelf', 'wgAutopromote' ] );

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
				} elseif ( $key == 'wgAutopromote' ) {
					if ( !is_null( $array ) ) {
						$$key = $array;
					}
				} else {
					foreach ( $array as $i => $groups ) {
						if ( is_array( $groups ) && count( $groups ) >= 1 ) {
							foreach ( $groups as $id => $group ) {
								$$key[$i][] = $group;
							}
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

			if ( $wgManageWikiPermissionsAdditionalAddGroupsSelf ) {
				$wgGroupsAddToSelf = array_merge_recursive( $wgGroupsAddToSelf, $wgManageWikiPermissionsAdditionalAddGroupsSelf );
			}

			if ( $wgManageWikiPermissionsAdditionalRemoveGroupsSelf ) {
				$wgGroupsRemoveFromSelf = array_merge_recursive( $wgGroupsRemoveFromSelf, $wgManageWikiPermissionsAdditionalRemoveGroupsSelf );
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

			$nsArray = ManageWikiCDB::get( 'namespaces', [ 'wgContentNamespaces', 'wgNamespaceContentModels', 'wgExtraNamespaces', 'wgNamespaceProtection', 'wgNamespacesToBeSearchedDefault', 'wgNamespacesWithSubpages', 'wgNamespaceAliases', 'wgManageWikiNamespacesCore', 'mwAdditional' ] );

			foreach ( $nsArray as $key => $json ) {
				$nsArray[$key] = json_decode( $json, true );
			}

			foreach ( $nsArray as $key => $array ) {
				if ( !is_array( $array ) ) {
					continue;
				}

				if ( $key == 'mwAdditional' ) {
					foreach ( $array as $key => $id ) {
						global $$key;

						if ( !empty( $id ) && isset( $wgManageWikiNamespacesAdditional[$key] ) ) {
							foreach ( $id as $nsID ) {
								$$key[$nsID] = ( $wgManageWikiNamespacesAdditional[$key]['vestyle'] ) ? true : $nsID;
							}
						}
					}
				} else {
					foreach ( $array as $id => $val ) {
						$$key[$id] = $val;
					}
				}
			}
		}
	}

	public static function onCreateWikiCreation( $dbname, $private ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgManageWikiExtensionsDefault, $wgCanonicalNamespaceNames, $wgNamespaceAliases,
			$wgNamespacesToBeSearchedDefault, $wgNamespacesWithSubpages, $wgContentNamespaces, $wgNamespaceProtection;

		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$defaultGroups = array_diff( (array)ManageWikiPermissions::availableGroups( 'default' ), (array)$wgManageWikiPermissionsDefaultPrivateGroup );

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			foreach ( $defaultGroups as $newgroup ) {
				$groupArray = ManageWikiPermissions::groupPermissions( $newgroup, 'default' );

				$dbw->insert(
					'mw_permissions',
					[
						'perm_dbname' => $dbname,
						'perm_group' => $newgroup,
						'perm_permissions' => json_encode( $groupArray['permissions'] ),
						'perm_addgroups' => json_encode( $groupArray['ag'] ),
						'perm_removegroups' => json_encode( $groupArray['rg'] ),
						'perm_addgroupstoself' => json_encode( $groupArray['ags'] ),
						'perm_removegroupsfromself' => json_encode( $groupArray['rgs'] ),
						'perm_autopromote' => ( is_null( $groupArray['autopromote'] ) ) ? null : json_encode( $groupArray['autopromote'] )
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
			$defaultCanonicalNamespaces = (array)ManageWikiNamespaces::defaultCanonicalNamespaces();

			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			foreach ( $defaultCanonicalNamespaces as $newnamespace ) {
				$namespacesArray = ManageWikiNamespaces::defaultNamespaces( $newnamespace );

				$dbw->insert(
					'mw_namespaces',
					[
						'ns_dbname' => $dbname,
						'ns_namespace_id' => $newnamespace,
						'ns_namespace_name' => (string)$namespacesArray['ns_namespace_name'],
						'ns_searchable' => (int)$namespacesArray['ns_searchable'],
						'ns_subpages' => (int)$namespacesArray['ns_subpages'],
						'ns_content' => (int)$namespacesArray['ns_content'],
						'ns_content_model' => $namespacesArray['ns_content_model'],
						'ns_protection' => $namespacesArray['ns_protection'],
						'ns_aliases' => (string)$namespacesArray['ns_aliases'],
						'ns_core' => (int)$namespacesArray['ns_core'],
						'ns_additional' => $namespacesArray['ns_additional']
					],
					__METHOD__
				);
			}

			ManageWikiCDB::changes( 'namespaces' );
		}
	}

	public static function onCreateWikiTables( &$tables ) {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$tables['mw_permissions'] = 'perm_dbname';
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$tables['mw_namespaces'] = 'ns_dbname';
		}
	}

	public static function onCreateWikiDeletion( $dbw, $wiki ) {
		global $wgManageWikiCDBDirectory;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			if ( ManageWiki::checkSetup( 'permissions' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $wiki . '-permissions.cdb' );
			}

			if ( ManageWiki::checkSetup( 'namespaces' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $wiki . '-namespaces.cdb' );
			}
		}
	}

	public static function onCreateWikiRename( $dbw, $old, $new ) {
		global $wgManageWikiCDBDirectory;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			if ( ManageWiki::checkSetup( 'permissions' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $old . '-permissions.cdb' );
			}

			if ( ManageWiki::checkSetup( 'namespaces' ) ) {
				unlink( $wgManageWikiCDBDirectory . '/' . $old . '-namespaces.cdb' );
			}
		}
	}

	public static function onCreateWikiStatePrivate( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase;

		if ( ManageWiki::checkSetup( 'permissions' ) && $wgManageWikiPermissionsDefaultPrivateGroup ) {

			$defaultarray = ManageWikiPermissions::groupPermissions( $wgManageWikiPermissionsDefaultPrivateGroup, 'default' );

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
						'perm_addgroups' => json_encode( $defaultarray['ag'] ),
						'perm_removegroups' => json_encode( $defaultarray['rg'] ),
						'perm_addgroupstoself' => json_encode( $defaultarray['ags'] ),
						'perm_removegroupsfromself' => json_encode( $defaultarray['rgs'] ),
						'perm_autopromote' => ( is_null( $defaultarray['autopromote'] ) ) ? null : json_encode( $defaultarray['autopromote'] )
					],
					__METHOD__
				);
			}

			$sysopMeta = ManageWikiPermissions::groupPermissions( 'sysop' );
			$sysopAdd = array_merge( $sysopMeta['ag'], [ $wgManageWikiPermissionsDefaultPrivateGroup ] );
			$sysopRemove = array_merge( $sysopMeta['rg'], [ $wgManageWikiPermissionsDefaultPrivateGroup ] );

			$dbw->update(
				'mw_permissions',
				[
					'perm_addgroups' => json_encode( $sysopAdd ),
					'perm_removegroups' => json_encode( $sysopRemove ),
				],
				[
					'perm_dbname' => $dbname,
					'perm_group' => 'sysop'
				],
				__METHOD__
			);
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

			// Fully delete group by removing all other groups' ability to manage it
			$groups = ManageWikiPermissions::availableGroups();
			foreach ( $groups as $group ) {
				$rights = ManageWikiPermissions::groupPermissions( $group );
				$addGroups = $rights['ag'];
				$removeGroups = $rights['rg'];

				if ( in_array( $wgManageWikiPermissionsDefaultPrivateGroup, $addGroups ) || in_array( $wgManageWikiPermissionsDefaultPrivateGroup, $removeGroups ) ) {
					$addGroups = array_diff( $addGroups, [ $wgManageWikiPermissionsDefaultPrivateGroup ] );
					$removeGroups = array_diff( $removeGroups, [ $wgManageWikiPermissionsDefaultPrivateGroup ] );

					$dbw->update(
						'mw_permissions',
						[
							'perm_addgroups' => json_encode( $addGroups ),
							'perm_removegroups' => json_encode( $removeGroups ),
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

		ManageWikiCDB::changes( 'permissions' );
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiSidebarLinks, $wgManageWiki;

		$append = ( $skin->getUser()->isAllowed( 'managewiki' ) ) ? '' : '-view';

		foreach ( (array)ManageWiki::listModules() as $module ) {
			$bar['Administration'][] = [
				'text' => wfMessage( "managewiki-link-{$module}{$append}" )->plain(),
				'id' => "managewiki{$module}link",
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL() )
			];
		}
	}
}
