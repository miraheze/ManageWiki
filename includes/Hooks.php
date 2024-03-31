<?php

namespace Miraheze\ManageWiki;

use DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use SpecialPage;
use TextContentHandler;
use User;
use Wikimedia\Rdbms\DBConnRef;

class Hooks {
	private static function getConfig( string $var ) {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( $var );
	}

	public static function fnManageWikiSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'mw_namespaces',
				__DIR__ . '/../sql/mw_namespaces.sql' );
		$updater->addExtensionTable( 'mw_permissions',
				__DIR__ . '/../sql/mw_permissions.sql' );
		$updater->addExtensionTable( 'mw_settings',
				__DIR__ . '/../sql/mw_settings.sql' );

		$updater->modifyExtensionTable( 'mw_namespaces',
				__DIR__ . '/../sql/patches/patch-namespace-core-alter.sql' );

		$updater->addExtensionField( 'mw_permissions', 'perm_addgroupstoself',
				__DIR__ . '/../sql/patches/patch-groups-self.sql' );
		$updater->addExtensionField( 'mw_permissions', 'perm_autopromote',
				__DIR__ . '/../sql/patches/patch-autopromote.sql' );
		$updater->addExtensionField( 'mw_namespaces', 'ns_additional',
				__DIR__ . '/../sql/patches/patch-namespaces-additional.sql' );

		$updater->addExtensionIndex( 'mw_namespaces', 'ns_dbname',
				__DIR__ . '/../sql/patches/patch-namespaces-add-indexes.sql' );
		$updater->addExtensionIndex( 'mw_permissions', 'perm_dbname',
				__DIR__ . '/../sql/patches/patch-permissions-add-indexes.sql' );
	}

	public static function onRegistration() {
		global $wgLogTypes;

		if ( !in_array( 'farmer', $wgLogTypes ) ) {
			$wgLogTypes[] = 'farmer';
		}
	}

	public static function onContentHandlerForModelID( $modelId, &$handler ) {
		$handler = new TextContentHandler( $modelId );
	}

	public static function onCreateWikiJsonBuilder( string $wiki, DBConnRef $dbr, array &$jsonArray ) {
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
			$manageWikiExtensions = self::getConfig( 'ManageWikiExtensions' );
			foreach ( json_decode( $setObject->s_extensions, true ) as $ext ) {
				$jsonArray['extensions'][] = $manageWikiExtensions[$ext]['var'] ??
					$manageWikiExtensions[$ext]['name'];
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

			$lcName = [];
			$lcEN = [];

			try {
				$lcName = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( $jsonArray['core']['wgLanguageCode'], 'namespaceNames' );

				if ( $jsonArray['core']['wgLanguageCode'] != 'en' ) {
					$lcEN = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( 'en', 'namespaceNames' );
				}
			} catch ( \Exception $e ) {
				$logger = LoggerFactory::getInstance( 'ManageWiki' );
				$logger->warning( 'Caught exception trying to load Localisation Cache: {exception}', [
					'exception' => $e,
				] );
			}

			$additional = self::getConfig( 'ManageWikiNamespacesAdditional' );
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
					'aliases' => array_merge( json_decode( $ns->ns_aliases ?? '', true ), (array)$lcAlias ),
					'additional' => json_decode( $ns->ns_additional ?? '', true )
				];

				$nsAdditional = (array)json_decode( $ns->ns_additional ?? '', true );

				foreach ( $additional as $var => $conf ) {
					// Select value if configured, otherwise fall back to overridedefault
					if ( isset( $nsAdditional[$var] ) ) {
						$val = $nsAdditional[$var];
					} elseif ( is_array( $conf['overridedefault'] ) ) {
						if ( array_key_exists( (int)$ns->ns_namespace_id, $conf['overridedefault'] ) ) {
							$val = $conf['overridedefault'][(int)$ns->ns_namespace_id];
						} elseif ( array_key_exists( 'default', $conf['overridedefault'] ) ) {
							$val = $conf['overridedefault']['default'];
						} else {
							// TODO: throw error? this should probably not be allowed
							$val = null;
						}
					} else {
						$val = $conf['overridedefault'];
					}

					if ( $val ) {
						self::setNamespaceSettingJson( $jsonArray, (int)$ns->ns_namespace_id, $var, $val, $conf );
					} elseif (
						!isset( $conf['constant'] ) &&
						( !isset( $jsonArray['settings'][$var] ) || !$jsonArray['settings'][$var] )
					) {
						$jsonArray['settings'][$var] = [];
					}
				}
			}
			// Search for and apply overridedefaults to NS_SPECIAL
			// Notably, we do not apply 'default' overridedefault to NS_SPECIAL
			// It must exist as it's own key in overridedefault
			foreach ( $additional as $var => $conf ) {
				if (
					is_array( $conf['overridedefault'] ) &&
					array_key_exists( NS_SPECIAL, $conf['overridedefault'] ) &&
					$conf['overridedefault'][NS_SPECIAL]
				) {
					self::setNamespaceSettingJson( $jsonArray, NS_SPECIAL, $var, $conf['overridedefault'][NS_SPECIAL], $conf );
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
				$addPerms = [];
				$removePerms = [];

				foreach ( ( self::getConfig( 'ManageWikiPermissionsAdditionalRights' )[$perm->perm_group] ?? [] ) as $right => $bool ) {
					if ( $bool ) {
						$addPerms[] = $right;
						continue;
					}

					if ( $bool === false ) {
						$removePerms[] = $right;
					}
				}

				$permissions = array_merge( json_decode( $perm->perm_permissions ?? '', true ) ?? [], $addPerms );
				$filteredPermissions = array_diff( $permissions, $removePerms );

				$jsonArray['permissions'][$perm->perm_group] = [
					'permissions' => $filteredPermissions,
					'addgroups' => array_merge(
						json_decode( $perm->perm_addgroups ?? '', true ) ?? [],
						self::getConfig( 'ManageWikiPermissionsAdditionalAddGroups' )[$perm->perm_group] ?? []
					),
					'removegroups' => array_merge(
						json_decode( $perm->perm_removegroups ?? '', true ) ?? [],
						self::getConfig( 'ManageWikiPermissionsAdditionalRemoveGroups' )[$perm->perm_group] ?? []
					),
					'addself' => json_decode( $perm->perm_addgroupstoself ?? '', true ),
					'removeself' => json_decode( $perm->perm_removegroupsfromself ?? '', true ),
					'autopromote' => json_decode( $perm->perm_autopromote ?? '', true )
				];
			}

			$diffKeys = array_keys(
				array_diff_key( self::getConfig( 'ManageWikiPermissionsAdditionalRights' ), $jsonArray['permissions'] ?? [] )
			);

			foreach ( $diffKeys as $missingKey ) {
				$missingPermissions = [];

				foreach ( self::getConfig( 'ManageWikiPermissionsAdditionalRights' )[$missingKey] as $right => $bool ) {
					if ( $bool ) {
						$missingPermissions[] = $right;
					}
				}

				$jsonArray['permissions'][$missingKey] = [
					'permissions' => $missingPermissions,
					'addgroups' => self::getConfig( 'ManageWikiPermissionsAdditionalAddGroups' )[$missingKey] ?? [],
					'removegroups' => self::getConfig( 'ManageWikiPermissionsAdditionalRemoveGroups' )[$missingKey] ?? [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => []
				];
			}
		}
	}

	/**
	 * Adds the namespace setting for the supplied variable
	 *
	 * @param array &$jsonArray array representation of the JSON output
	 * @param int $nsID namespace ID number as an integer
	 * @param string $var variable name
	 * @param mixed $val variable value
	 * @param array $varConf variable config from wgManageWikiNamespacesAdditional[$var]
	 */
	private static function setNamespaceSettingJson(
		array &$jsonArray, int $nsID, string $var, $val, array $varConf
	) {
		switch ( $varConf['type'] ) {
			case 'check':
				$jsonArray['settings'][$var][] = $nsID;
				break;
			case 'vestyle':
				$jsonArray['settings'][$var][$nsID] = true;
				break;
			default:
				if ( ( $varConf['constant'] ) ?? false ) {
					$jsonArray['settings'][$var] = str_replace( ' ', '_', $val );
				} else {
					$jsonArray['settings'][$var][$nsID] = $val;
				}
		}
	}

	public static function onCreateWikiCreation( $dbname, $private ) {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list() ), (array)self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) );

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
				self::onCreateWikiStatePrivate( $dbname );
			}

		}

		if ( self::getConfig( 'ManageWikiExtensions' ) && self::getConfig( 'ManageWikiExtensionsDefault' ) ) {
			$mwExt = new ManageWikiExtensions( $dbname );
			$mwExt->add( self::getConfig( 'ManageWikiExtensionsDefault' ) );
			$mwExt->commit();
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list() );
			$mwNamespaces = new ManageWikiNamespaces( $dbname );

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit( false );
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
		if ( ManageWiki::checkSetup( 'permissions' ) && self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$defaultPrivate = $mwPermissionsDefault->list( self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) );
			$privateArray = [];

			foreach ( $defaultPrivate as $name => $value ) {
				if ( $name == 'autopromote' ) {
					$privateArray[$name] = $value;
				} else {
					$privateArray[$name]['add'] = $value;
				}
			}

			$mwPermissions->modify( self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ), $privateArray );
			$mwPermissions->modify( 'sysop', [ 'addgroups' => [ 'add' => [ self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ] ], 'removegroups' => [ 'add' => [ self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ] ] ] );
			$mwPermissions->commit();
		}
	}

	public static function onCreateWikiStatePublic( $dbname ) {
		if ( ManageWiki::checkSetup( 'permissions' ) && self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ) {
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$mwPermissions->remove( self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) );

			foreach ( array_keys( $mwPermissions->list() ) as $group ) {
				$mwPermissions->modify( $group, [ 'addgroups' => [ 'remove' => [ self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ] ], 'removegroups' => [ 'remove' => [ self::getConfig( 'ManageWikiPermissionsDefaultPrivateGroup' ) ] ] ] );
			}

			$mwPermissions->commit();
		}
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		$user = $skin->getUser();
		$services = MediaWikiServices::getInstance();
		$permissionManager = $services->getPermissionManager();
		$userOptionsLookup = $services->getUserOptionsLookup();

		$hideSidebar = !self::getConfig( 'ManageWikiForceSidebarLinks' ) &&
			!$userOptionsLookup->getOption( $user, 'managewikisidebar', 0 );

		foreach ( (array)ManageWiki::listModules() as $module ) {
			$append = '';
			if ( !$permissionManager->userHasRight( $user, 'managewiki-' . $module ) ) {
				if ( $hideSidebar ) {
					continue;
				}

				$append = '-view';
			}

			$bar['managewiki-sidebar-header'][] = [
				'text' => $skin->msg( "managewiki-link-{$module}{$append}" )->text(),
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
