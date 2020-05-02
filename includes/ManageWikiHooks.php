<?php

use MediaWiki\MediaWikiServices;

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

			$lcName = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( $jsonArray['core']['wgLanguageCode'], 'namespaceNames' );

			if ( $jsonArray['core']['wgLanguageCode'] != 'en' ) {
				$lcEN = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( 'en', 'namespaceNames' );
			}

			foreach ( $nsObjects as $ns ) {
				$nsName = $lcName[$ns->ns_namespace_id] ?? $ns->ns_namespace_name;
				$lcAlias = $lcEN[$ns->ns_namespace_id] ?? null;

				$jsonArray['namespaces'][$nsName] = [
					'id' => $ns->ns_namespace_id,
					'core' => (bool)$ns->ns_core,
					'searchable' => (bool)$ns->ns_searchable,
					'subpages' => (bool)$ns->ns_subpages,
					'content' => (bool)$ns->ns_content,
					'contentmodel' => $ns->ns_content_model,
					'protection' => ( (bool)$ns->ns_protection ) ? $ns->ns_protection : false,
					'aliases' => array_merge( json_decode( $ns->ns_aliases, true ), (array)$lcAlias ),
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
					'permissions' => array_merge( json_decode( $perm->perm_permissions, true ), $addPerms ),
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
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list() ), (array)$wgManageWikiPermissionsDefaultPrivateGroup );

			foreach ( $defaultGroups as $newgroup ) {
				$groupData = $mwPermissionsDefault->list( $newgroup );
				$groupArray = [];

				foreach ( $groupData as $name => $value ) {
					if ( $name == 'autopromote' ) {
						$groupArray[$name] = $value;
					} else {
						$groupArray[$name]['add'] = $value;
					}
				}

				$mwPermissions->modify( $newgroup, $groupArray );
			}

			$mwPermissions->commit();

			if ( $private ) {
				ManageWikiHooks::onCreateWikiStatePrivate( $dbname );
			}

		}

		if ( $wgManageWikiExtensions && $wgManageWikiExtensionsDefault ) {
			$mwExt = new ManageWikiExtensions( $dbname );
			$mwExt->add( $wgManageWikiExtensions );
			$mwExt->commit();
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list() );
			$mwNamespaces = new ManageWikiNamespaces( $dbname );

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit();
			}
		}
	}

	public static function onCreateWikiTables( &$tables ) {
		if ( ManageWiki::checkSetup( 'extensions' ) || ManageWiki::checkSetup( 'settings' ) ) {
			$tables['mw_settings'] = 's_dbname';
		}

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
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$defaultPrivate = $mwPermissionsDefault->list();
			$privateArray = [];

			foreach ( $defaultPrivate as $name => $value ) {
				$privateArray[$name]['add'] = $value;
			}

			$mwPermissions->modify( $wgManageWikiPermissionsDefaultPrivateGroup, $privateArray );
			$mwPermissions->modify( 'sysop', [ 'addgroups' => [ 'add' => $wgManageWikiPermissionsDefaultPrivateGroup ], 'removegroups' => [ 'add' => $wgManageWikiPermissionsDefaultPrivateGroup ] ] );
			$mwPermissions->commit();
		}
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		global $wgManageWikiPermissionsDefaultPrivateGroup, $wgCreateWikiDatabase;

		if ( ManageWiki::checkSetup( 'permissions' ) && $wgManageWikiPermissionsDefaultPrivateGroup ) {
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$mwPermissions->remove( $wgManageWikiPermissionsDefaultPrivateGroup );

			foreach ( array_keys( $mwPermissions->list() ) as $group ) {
				$mwPermissions->modify( $group, [ 'addgroups' => [ 'remove' => $wgManageWikiPermissionsDefaultPrivateGroup ], 'removegroups' => [ 'remove' => $wgManageWikiPermissionsDefaultPrivateGroup ] ] );
			}

			$mwPermissions->commit();
		}
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiForceSidebarLinks, $wgManageWiki;

		$append = '';

		$user = $skin->getUser();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( !$permissionManager->userHasRight( $user, 'managewiki' ) ) {
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
