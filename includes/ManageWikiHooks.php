<?php
class ManageWikiHooks {
	public static function fnManageWikiSchemaUpdates( DatabaseUpdater $updater ) {
		global $wgCreateWikiDatabase, $wgDBname;

		if ( $wgCreateWikiDatabase === $wgDBname ) {
			$updater->addExtensionTable( 'mw_namespaces',
					__DIR__ . '/../sql/mw_namespaces.sql' );
			$updater->addExtensionTable( 'mw_permissions',
					__DIR__ . '/../sql/mw_permissions.sql' );
			$updater->addExtensionTable( 'mw_settings',
					__DIR__ . '/../sql/mw_settings.sql' );
			$updater->modifyTable( 'mw_permissions',
					__DIR__ . '/../sql/patches/patch-groups-self.sql', true );
			$updater->modifyTable( 'mw_permissions',
					__DIR__ . '/../sql/patches/patch-autopromote.sql', true );
			$updater->modifyTable( 'mw_namespaces',
					__DIR__ . '/../sql/patches/patch-namespaces-additional.sql', true );
			$updater->modifyTable( 'mw_namespaces',
					__DIR__ . '/../sql/patches/patch-namespace-core-alter.sql', true );
			$updater->modifyTable( 'mw_namespaces',
					__DIR__ . '/../sql/patches/patch-namespaces-add-indexes.sql', true );
			$updater->modifyTable( 'mw_permissions',
					__DIR__ . '/../sql/patches/patch-permissions-add-indexes.sql', true );
		}
	}

	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onCreateWikiJsonBuilder( string $wiki, MaintainableDBConnRef $dbr, array &$jsonArray ) {
		global $wgManageWikiExtensions, $wgManageWikiPermissionsAdditionalRights, $wgManageWikiPermissionsAdditionalAddGroups, $wgManageWikiPermissionsAdditionalRemoveGroups, $wgManageWikiNamespacesAdditional;

		$setObject = $dbr->selectRow(
			'mw_settings',
			'*',
			[
				's_dbname' => $wiki
			]
		);

		// Don't need to manipulate this much
		if ( ManageWiki::checkSetup( 'settings' ) ) {
			$jsonArray['settings'] = json_decode( $setObject->s_settings, true );
		}

		// Let's create an array of variables so we can easily loop these to enable
		if ( ManageWiki::checkSetup( 'extensions' ) ) {
			foreach ( json_decode( $setObject->s_extensions, true ) as $ext ) {
				$jsonArray['extensions'][] = $wgManageWikiExtensions[$ext]['var'];
			}
		}

		// Collate NS entries and decode their entries for the array
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$nsObjects = $dbr->select(
				'mw_namespaces',
				'*',
				[
					'ns_dbname' => $wiki
				]
			);

			foreach ( $nsObjects as $ns ) {
				$jsonArray['namespaces'][$ns->ns_namespace_name] = [
					'id' => $ns->ns_namespace_id,
					'core' => (bool)$ns->ns_core,
					'searchable' => (bool)$ns->ns_searchable,
					'subpages' => (bool)$ns->ns_subpages,
					'content' => (bool)$ns->ns_content,
					'contentmodel' => $ns->ns_content_model,
					'protection' => ( (bool)$ns->ns_protection ) ? $ns->ns_protection : false,
					'aliases' => json_decode( $ns->ns_aliases, true ),
					'additional' => json_decode( $ns->ns_additional, true )
				];

				$nsAdditional = json_decode( $ns->ns_additional, true );

				foreach ( (array)$nsAdditional as $var => $val ) {
					if ( $val && isset( $wgManageWikiNamespacesAdditional[$var] ) ) {
						if ( $wgManageWikiNamespacesAdditional[$var]['vestyle'] ) {
							$jsonArray['settings'][$var][$ns->ns_namespace_id] = true;
						} else {
							$jsonArray['settings'][$var][] = $ns->ns_namespace_id;
						}
					}
				}
			}

		}

		// Same as NS above but for permissions
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$permObjects = $dbr->select(
				'mw_permissions',
				'*',
				[
					'perm_dbname' => $wiki
				]
			);

			foreach ( $permObjects as $perm ) {
				$addPerms =[];

				foreach ( ( $wgManageWikiPermissionsAdditionalRights[$perm->perm_group] ?? [] ) as $right => $bool ) {
					if ( $bool ) {
						$addPerms[] = $right;
					}
				}

				$jsonArray['permissions'][$perm->perm_group] = [
					'permissions' => array_replace( json_decode( $perm->perm_permissions, true ), $addPerms ),
					'addgroups' => array_merge( json_decode( $perm->perm_addgroups, true ), $wgManageWikiPermissionsAdditionalAddGroups[$perm->perm_group] ?? [] ),
					'removegroups' => array_merge( json_decode( $perm->perm_removegroups, true ), $wgManageWikiPermissionsAdditionalRemoveGroups[$perm->perm_group] ?? [] ),
					'addself' => json_decode( $perm->perm_addgroupstoself, true ),
					'removeself' => json_decode( $perm->perm_removegroupsfromself, true ),
					'autopromote' => json_decode( $perm->perm_autopromote, true )
				];
			}

			$diffKeys = array_keys( array_diff_key( $wgManageWikiPermissionsAdditionalRights, $jsonArray['permissions'] ) );

			foreach ( $diffKeys as $missingKey ) {
				$missingPermissions = [];

				foreach ( $wgManageWikiPermissionsAdditionalRights[$missingKey] as $right => $bool ) {
					if ( $bool ) {
						$missingPermissions[] = $right;
					}
				}

				$jsonArray['permissions'][$missingKey] = [
					'permissions' => $missingPermissions,
					'addgroups' => $wgManageWikiPermissionsAdditionalAddGroups[$missingKey] ?? [],
					'removegroups' => $wgManageWikiPermissionsAdditionalRemoveGroups[$missingKey] ?? [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => []
				];
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

		}

		if ( $wgManageWikiExtensions && $wgManageWikiExtensionsDefault ) {
			$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

			$cur = json_decode(
				$dbw->selectRow(
					'mw_settings',
					[ 's_extensions' ],
					[ 's_dbname' => $dbname ]
				)->s_extensions, true
			);

			$newlist = json_encode( array_merge( $cur, $wgManageWikiExtensionsDefault ) );

			$dbw->update(
				'mw_settings',
				[ 's_extensions' => $newlist ],
				[ 's_dbname' => $dbname ]
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

		$cWJ = new CreateWikiJson( $dbname );
		$cWJ->resetWiki();
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

		$cWJ = new CreateWikiJson( $dbname );
		$cWJ->resetWiki();
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiForceSidebarLinks, $wgManageWiki;

		$append = '';

		$user = $skin->getUser();
		if ( !$user->isAllowed( 'managewiki' ) ) {
			if ( !$wgManageWikiForceSidebarLinks && !$user->getOption( 'managewikisidebar', 0 ) ) {
				return;
			}
			$append = '-view';
		}

		foreach ( (array)ManageWiki::listModules() as $module ) {
			$bar['Administration'][] = [
				'text' => wfMessage( "managewiki-link-{$module}{$append}" )->plain(),
				'id' => "managewiki{$module}link",
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL() )
			];
		}
	}

	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['managewikisidebar'] = [
			'type' => 'toggle',
			'label-message' => 'managewiki-toggle-forcesidebar',
			'section' => 'rendering',
		];
	}
}
