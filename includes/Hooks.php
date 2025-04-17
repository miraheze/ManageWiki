<?php

namespace Miraheze\ManageWiki;

use Exception;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Skin;
use Wikimedia\Rdbms\IReadableDatabase;

class Hooks {

	private static function getConfig( string $var ): mixed {
		return MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' )->get( $var );
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$dir = __DIR__ . '/../sql';

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'mw_namespaces',
			"$dir/mw_namespaces.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'mw_permissions',
			"$dir/mw_permissions.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'mw_settings',
			"$dir/mw_settings.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'modifyTable',
			'mw_namespaces',
			"$dir/patches/patch-namespace-core-alter.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_permissions',
			'perm_addgroupstoself',
			"$dir/patches/patch-groups-self.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_permissions',
			'perm_autopromote',
			"$dir/patches/patch-autopromote.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_namespaces',
			'ns_additional',
			"$dir/patches/patch-namespaces-additional.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addIndex',
			'mw_namespaces',
			'ns_dbname',
			"$dir/patches/patch-namespaces-add-indexes.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addIndex',
			'mw_permissions',
			'perm_dbname',
			"$dir/patches/patch-permissions-add-indexes.sql",
			true,
		] );
	}

	public static function onContentHandlerForModelID(
		string $modelName,
		ContentHandler &$handler
	): void {
		$handler = new TextContentHandler( $modelName );
	}

	public static function onCreateWikiDataFactoryBuilder(
		string $wiki,
		IReadableDatabase $dbr,
		array &$cacheArray
	): void {
		$logger = LoggerFactory::getInstance( 'ManageWiki' );

		$setObject = $dbr->selectRow(
			'mw_settings',
			'*',
			[
				's_dbname' => $wiki,
			],
			__METHOD__
		);

		// Don't need to manipulate this much
		if ( ManageWiki::checkSetup( 'settings' ) ) {
			$cacheArray['settings'] = json_decode( $setObject->s_settings ?? '[]', true );
		}

		// Let's create an array of variables so we can easily loop these to enable
		if ( ManageWiki::checkSetup( 'extensions' ) ) {
			$manageWikiExtensions = self::getConfig( ConfigNames::Extensions );
			foreach ( json_decode( $setObject->s_extensions ?? '[]', true ) as $ext ) {
				if ( isset( $manageWikiExtensions[$ext] ) ) {
					$cacheArray['extensions'][] = $manageWikiExtensions[$ext]['var'] ??
						$manageWikiExtensions[$ext]['name'];
					continue;
				}

				$logger->error( 'Extension/Skin {ext} not set in {config}', [
					'ext' => $ext,
					'config' => ConfigNames::Extensions,
				] );
			}
		}

		// Collate NS entries and decode their entries for the array
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$nsObjects = $dbr->select(
				'mw_namespaces',
				'*',
				[
					'ns_dbname' => $wiki,
				],
				__METHOD__
			);

			$lcName = [];
			$lcEN = [];

			try {
				$languageCode = $cacheArray['core']['wgLanguageCode'] ?? 'en';
				$lcName = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( $languageCode, 'namespaceNames' );

				if ( $languageCode !== 'en' ) {
					$lcEN = MediaWikiServices::getInstance()->getLocalisationCache()->getItem( 'en', 'namespaceNames' );
				}
			} catch ( Exception $e ) {
				$logger->warning( 'Caught exception trying to load Localisation Cache: {exception}', [
					'exception' => $e,
				] );
			}

			$additional = self::getConfig( ConfigNames::NamespacesAdditional );
			foreach ( $nsObjects as $ns ) {
				$nsName = $lcName[$ns->ns_namespace_id] ?? $ns->ns_namespace_name;
				$lcAlias = $lcEN[$ns->ns_namespace_id] ?? null;

				$cacheArray['namespaces'][$nsName] = [
					'id' => $ns->ns_namespace_id,
					'core' => (bool)$ns->ns_core,
					'searchable' => (bool)$ns->ns_searchable,
					'subpages' => (bool)$ns->ns_subpages,
					'content' => (bool)$ns->ns_content,
					'contentmodel' => $ns->ns_content_model,
					'protection' => ( (bool)$ns->ns_protection ) ? $ns->ns_protection : false,
					'aliases' => array_merge( json_decode( str_replace( [ ' ', ':' ], '_', $ns->ns_aliases ?? '' ), true ), (array)$lcAlias ),
					'additional' => json_decode( $ns->ns_additional ?? '', true ),
				];

				$nsAdditional = (array)json_decode( $ns->ns_additional ?? '', true );

				foreach ( $additional as $var => $conf ) {
					if ( !self::isAdditionalSettingForNamespace( $conf, $ns->ns_namespace_id ) ) {
						continue;
					}

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
						self::setNamespaceSettingJson( $cacheArray, (int)$ns->ns_namespace_id, $var, $val, $conf );
					} elseif (
						!isset( $conf['constant'] ) &&
						( !isset( $cacheArray['settings'][$var] ) || !$cacheArray['settings'][$var] )
					) {
						$cacheArray['settings'][$var] = [];
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
					$conf['overridedefault'][NS_SPECIAL] &&
					self::isAdditionalSettingForNamespace( $conf, NS_SPECIAL )
				) {
					self::setNamespaceSettingJson( $cacheArray, NS_SPECIAL, $var, $conf['overridedefault'][NS_SPECIAL], $conf );
				}
			}
		}

		// Same as NS above but for permissions
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$permObjects = $dbr->select(
				'mw_permissions',
				'*',
				[
					'perm_dbname' => $wiki,
				],
				__METHOD__
			);

			foreach ( $permObjects as $perm ) {
				$addPerms = [];
				$removePerms = [];

				foreach ( ( self::getConfig( ConfigNames::PermissionsAdditionalRights )[$perm->perm_group] ?? [] ) as $right => $bool ) {
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

				$cacheArray['permissions'][$perm->perm_group] = [
					'permissions' => $filteredPermissions,
					'addgroups' => array_merge(
						json_decode( $perm->perm_addgroups ?? '', true ) ?? [],
						self::getConfig( ConfigNames::PermissionsAdditionalAddGroups )[$perm->perm_group] ?? []
					),
					'removegroups' => array_merge(
						json_decode( $perm->perm_removegroups ?? '', true ) ?? [],
						self::getConfig( ConfigNames::PermissionsAdditionalRemoveGroups )[$perm->perm_group] ?? []
					),
					'addself' => json_decode( $perm->perm_addgroupstoself ?? '', true ),
					'removeself' => json_decode( $perm->perm_removegroupsfromself ?? '', true ),
					'autopromote' => json_decode( $perm->perm_autopromote ?? '', true ),
				];
			}

			$diffKeys = array_keys(
				array_diff_key( self::getConfig( ConfigNames::PermissionsAdditionalRights ), $cacheArray['permissions'] ?? [] )
			);

			foreach ( $diffKeys as $missingKey ) {
				$missingPermissions = [];

				foreach ( self::getConfig( ConfigNames::PermissionsAdditionalRights )[$missingKey] as $right => $bool ) {
					if ( $bool ) {
						$missingPermissions[] = $right;
					}
				}

				$cacheArray['permissions'][$missingKey] = [
					'permissions' => $missingPermissions,
					'addgroups' => self::getConfig( ConfigNames::PermissionsAdditionalAddGroups )[$missingKey] ?? [],
					'removegroups' => self::getConfig( ConfigNames::PermissionsAdditionalRemoveGroups )[$missingKey] ?? [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => [],
				];
			}
		}
	}

	/**
	 * Adds the namespace setting for the supplied variable
	 *
	 * @param array &$cacheArray array for cache
	 * @param int $nsID namespace ID number as an integer
	 * @param string $var variable name
	 * @param mixed $val variable value
	 * @param array $varConf variable config from ConfigNames::NamespacesAdditional[$var]
	 */
	private static function setNamespaceSettingJson(
		array &$cacheArray,
		int $nsID,
		string $var,
		mixed $val,
		array $varConf
	): void {
		switch ( $varConf['type'] ) {
			case 'check':
				$cacheArray['settings'][$var][] = $nsID;
				break;
			case 'vestyle':
				$cacheArray['settings'][$var][$nsID] = true;
				break;
			default:
				if ( $varConf['constant'] ?? false ) {
					$cacheArray['settings'][$var] = str_replace( [ ' ', ':' ], '_', $val );
				} else {
					$cacheArray['settings'][$var][$nsID] = $val;
				}
		}
	}

	/**
	 * Checks if the namespace is for the additional setting given
	 *
	 * @param array $conf additional setting to check
	 * @param int $nsID namespace ID to check if the setting is allowed for
	 * @return bool Whether or not the setting is enabled for the namespace
	 */
	private static function isAdditionalSettingForNamespace( array $conf, int $nsID ): bool {
		// T12237: Do not apply additional settings if the setting is not for the
		// namespace that we are on, otherwise it is very likely for the namespace to
		// not have setting set, and cause settings set before to be ignored

		$only = null;
		if ( isset( $conf['only'] ) ) {
			$only = (array)$conf['only'];
		}

		return $only === null || in_array( $nsID, $only );
	}

	public static function onCreateWikiCreation( string $dbname, bool $private ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list( group: null ) ), [ self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ] );

			foreach ( $defaultGroups as $newGroup ) {
				$groupData = $mwPermissionsDefault->list( $newGroup );
				$groupArray = [];

				foreach ( $groupData as $name => $value ) {
					if ( $name === 'autopromote' ) {
						$groupArray[$name] = $value;
						continue;
					}

					$groupArray[$name]['add'] = $value;
				}

				$mwPermissions->modify( $newGroup, $groupArray );
			}

			$mwPermissions->commit();

			if ( $private ) {
				self::onCreateWikiStatePrivate( $dbname );
			}

		}

		if ( self::getConfig( ConfigNames::Extensions ) && self::getConfig( ConfigNames::ExtensionsDefault ) ) {
			$mwExtensions = new ManageWikiExtensions( $dbname );
			$mwExtensions->add( self::getConfig( ConfigNames::ExtensionsDefault ) );
			$mwExtensions->commit();
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list() );

			$mwNamespaces = new ManageWikiNamespaces( $dbname );
			$mwNamespaces->disableNamespaceMigrationJob();

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit();
			}
		}
	}

	public static function onCreateWikiTables( array &$tables ): void {
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

	public static function onCreateWikiStatePrivate( string $dbname ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) && self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$defaultPrivate = $mwPermissionsDefault->list(
				self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup )
			);

			$privateArray = [];

			foreach ( $defaultPrivate as $name => $value ) {
				if ( $name === 'autopromote' ) {
					$privateArray[$name] = $value;
					continue;
				}

				$privateArray[$name]['add'] = $value;
			}

			$mwPermissions->modify( self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ), $privateArray );
			$mwPermissions->modify( 'sysop', [ 'addgroups' => [ 'add' => [ self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ] ], 'removegroups' => [ 'add' => [ self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ] ] ] );
			$mwPermissions->commit();
		}
	}

	public static function onCreateWikiStatePublic( string $dbname ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) && self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ) {
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$mwPermissions->remove( self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) );

			foreach ( array_keys( $mwPermissions->list( group: null ) ) as $group ) {
				$mwPermissions->modify( $group, [ 'addgroups' => [ 'remove' => [ self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ] ], 'removegroups' => [ 'remove' => [ self::getConfig( ConfigNames::PermissionsDefaultPrivateGroup ) ] ] ] );
			}

			$mwPermissions->commit();
		}
	}

	public static function onSidebarBeforeOutput( Skin $skin, array &$sidebar ): void {
		$user = $skin->getUser();
		$services = MediaWikiServices::getInstance();
		$permissionManager = $services->getPermissionManager();
		$userOptionsLookup = $services->getUserOptionsLookup();

		$hideSidebar = !self::getConfig( ConfigNames::ForceSidebarLinks ) &&
			!$userOptionsLookup->getBoolOption( $user, 'managewikisidebar' );

		$modules = array_keys( self::getConfig( ConfigNames::ManageWiki ), true );
		foreach ( $modules as $module ) {
			$append = '';
			if ( !$permissionManager->userHasRight( $user, "managewiki-$module" ) ) {
				if ( $hideSidebar ) {
					continue;
				}

				$append = '-view';
			}

			$sidebar['managewiki-sidebar-header'][] = [
				'text' => $skin->msg( "managewiki-link-{$module}{$append}" )->text(),
				'id' => "managewiki{$module}link",
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL() ),
			];
		}
	}

	public static function onGetPreferences( User $user, array &$preferences ): void {
		$preferences['managewikisidebar'] = [
			'type' => 'toggle',
			'label-message' => 'managewiki-toggle-forcesidebar',
			'section' => 'rendering',
		];
	}
}
