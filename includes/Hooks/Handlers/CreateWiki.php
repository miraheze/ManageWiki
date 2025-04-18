<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use Exception;
use LocalisationCache;
use MediaWiki\Config\Config;
use Miraheze\CreateWiki\Hooks\CreateWikiCreationHook;
use Miraheze\CreateWiki\Hooks\CreateWikiDataFactoryBuilderHook;
use Miraheze\CreateWiki\Hooks\CreateWikiStatePrivateHook;
use Miraheze\CreateWiki\Hooks\CreateWikiStatePublicHook;
use Miraheze\CreateWiki\Hooks\CreateWikiTablesHook;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\ManageWiki;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\IReadableDatabase;

class CreateWiki implements
	CreateWikiCreationHook,
	CreateWikiDataFactoryBuilderHook,
	CreateWikiStatePrivateHook,
	CreateWikiStatePublicHook,
	CreateWikiTablesHook
{

	public function __construct(
		private readonly Config $config,
		private readonly LoggerInterface $logger,
		private readonly LocalisationCache $localisationCache
	) {
	}

    /** @inheritDoc */
	public function onCreateWikiCreation( string $dbname, bool $private ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list( group: null ) ), [ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] );

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
				$this->onCreateWikiStatePrivate( $dbname );
			}
		}

		if ( $this->config->get( ConfigNames::Extensions ) && $this->config->get( ConfigNames::ExtensionsDefault ) ) {
			$mwExtensions = new ManageWikiExtensions( $dbname );
			$mwExtensions->add( $this->config->get( ConfigNames::ExtensionsDefault ) );
			$mwExtensions->commit();
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list( id: null ) );

			$mwNamespaces = new ManageWikiNamespaces( $dbname );
			$mwNamespaces->disableNamespaceMigrationJob();

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit();
			}
		}
	}

    /** @inheritDoc */
	public function onCreateWikiDataFactoryBuilder(
		string $wiki,
		IReadableDatabase $dbr,
		array &$cacheArray
	): void {
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
			$manageWikiExtensions = $this->config->get( ConfigNames::Extensions );
			foreach ( json_decode( $setObject->s_extensions ?? '[]', true ) as $ext ) {
				if ( isset( $manageWikiExtensions[$ext] ) ) {
					$cacheArray['extensions'][] = $manageWikiExtensions[$ext]['var'] ??
						$manageWikiExtensions[$ext]['name'];
					continue;
				}

				$this->logger->error( 'Extension/Skin {ext} not set in {config}', [
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
				$lcName = $this->localisationCache->getItem( $languageCode, 'namespaceNames' );

				if ( $languageCode !== 'en' ) {
					$lcEN = $this->localisationCache->getItem( 'en', 'namespaceNames' );
				}
			} catch ( Exception $e ) {
				$this->logger->warning( 'Caught exception trying to load Localisation Cache: {exception}', [
					'exception' => $e,
				] );
			}

			$additional = $this->config->get( ConfigNames::NamespacesAdditional );
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
					if ( !$this->isAdditionalSettingForNamespace( $conf, $ns->ns_namespace_id ) ) {
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
						$this->setNamespaceSettingJson( $cacheArray, (int)$ns->ns_namespace_id, $var, $val, $conf );
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
					$this->isAdditionalSettingForNamespace( $conf, NS_SPECIAL )
				) {
					$this->setNamespaceSettingJson( $cacheArray, NS_SPECIAL, $var, $conf['overridedefault'][NS_SPECIAL], $conf );
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

				foreach ( $this->config->get( ConfigNames::PermissionsAdditionalRights )[$perm->perm_group] ?? [] as $right => $bool ) {
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
						$this->config->get( ConfigNames::PermissionsAdditionalAddGroups )[$perm->perm_group] ?? []
					),
					'removegroups' => array_merge(
						json_decode( $perm->perm_removegroups ?? '', true ) ?? [],
						$this->config->get( ConfigNames::PermissionsAdditionalRemoveGroups )[$perm->perm_group] ?? []
					),
					'addself' => json_decode( $perm->perm_addgroupstoself ?? '', true ),
					'removeself' => json_decode( $perm->perm_removegroupsfromself ?? '', true ),
					'autopromote' => json_decode( $perm->perm_autopromote ?? '', true ),
				];
			}

			$diffKeys = array_keys(
				array_diff_key( $this->config->get( ConfigNames::PermissionsAdditionalRights ), $cacheArray['permissions'] ?? [] )
			);

			foreach ( $diffKeys as $missingKey ) {
				$missingPermissions = [];

				foreach ( $this->config->get( ConfigNames::PermissionsAdditionalRights )[$missingKey] as $right => $bool ) {
					if ( $bool ) {
						$missingPermissions[] = $right;
					}
				}

				$cacheArray['permissions'][$missingKey] = [
					'permissions' => $missingPermissions,
					'addgroups' => $this->config->get( ConfigNames::PermissionsAdditionalAddGroups )[$missingKey] ?? [],
					'removegroups' => $this->config->get( ConfigNames::PermissionsAdditionalRemoveGroups )[$missingKey] ?? [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => [],
				];
			}
		}
	}

    /** @inheritDoc */
	public function onCreateWikiStatePrivate( string $dbname ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) && $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ) {
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$mwPermissions = new ManageWikiPermissions( $dbname );

			$defaultPrivate = $mwPermissionsDefault->list(
				$this->config->get( ConfigNames::PermissionsDefaultPrivateGroup )
			);

			$privateArray = [];

			foreach ( $defaultPrivate as $name => $value ) {
				if ( $name === 'autopromote' ) {
					$privateArray[$name] = $value;
					continue;
				}

				$privateArray[$name]['add'] = $value;
			}

			$mwPermissions->modify( $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ), $privateArray );
			$mwPermissions->modify( 'sysop', [ 'addgroups' => [ 'add' => [ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] ], 'removegroups' => [ 'add' => [ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] ] ] );
			$mwPermissions->commit();
		}
	}

    /** @inheritDoc */
	public function onCreateWikiStatePublic( string $dbname ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) && $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ) {
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$mwPermissions->remove( $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) );

			foreach ( array_keys( $mwPermissions->list( group: null ) ) as $group ) {
				$mwPermissions->modify( $group, [ 'addgroups' => [ 'remove' => [ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] ], 'removegroups' => [ 'remove' => [ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] ] ] );
			}

			$mwPermissions->commit();
		}
	}

    /** @inheritDoc */
	public function onCreateWikiTables( array &$tables ): void {
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

	/**
	 * Adds the namespace setting for the supplied variable
	 *
	 * @param array &$cacheArray array for cache
	 * @param int $nsID namespace ID number as an integer
	 * @param string $var variable name
	 * @param mixed $val variable value
	 * @param array $varConf variable config from ConfigNames::NamespacesAdditional[$var]
	 */
	private function setNamespaceSettingJson(
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
	private function isAdditionalSettingForNamespace( array $conf, int $nsID ): bool {
		// T12237: Do not apply additional settings if the setting is not for the
		// namespace that we are on, otherwise it is very likely for the namespace to
		// not have setting set, and cause settings set before to be ignored

		$only = null;
		if ( isset( $conf['only'] ) ) {
			$only = (array)$conf['only'];
		}

		return $only === null || in_array( $nsID, $only );
	}
}
