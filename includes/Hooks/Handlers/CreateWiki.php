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
use Miraheze\ManageWiki\Helpers\ManageWikiModuleFactory;
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
		private readonly ManageWikiModuleFactory $moduleFactory,
		private readonly LocalisationCache $localisationCache
	) {
	}

	/** @inheritDoc */
	public function onCreateWikiCreation( string $dbname, bool $private ): void {
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$mwPermissionsDefault = $this->moduleFactory->permissionsDefault();
			$mwPermissions = $this->moduleFactory->permissions( $dbname );
			$defaultGroups = array_diff(
				array_keys( $mwPermissionsDefault->list( group: null ) ),
				[ $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup ) ]
			);

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

		if (
			$this->config->get( ConfigNames::Extensions ) &&
			$this->config->get( ConfigNames::ExtensionsDefault )
		) {
			$mwExtensions = $this->moduleFactory->extensions( $dbname );
			$mwExtensions->add( $this->config->get( ConfigNames::ExtensionsDefault ) );
			$mwExtensions->commit();
		}

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$mwNamespacesDefault = $this->moduleFactory->namespacesDefault();
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list( id: null ) );

			$mwNamespaces = $this->moduleFactory->namespaces( $dbname );
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
		$setObject = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $wiki ] )
			->caller( __METHOD__ )
			->fetchRow();

		// Don't need to manipulate this much
		if ( $setObject !== false && ManageWiki::checkSetup( 'settings' ) ) {
			$cacheArray['settings'] = json_decode( $setObject->s_settings ?? '[]', true );
		}

		// Let's create an array of variables so we can easily loop these to enable
		if ( $setObject !== false && ManageWiki::checkSetup( 'extensions' ) ) {
			$manageWikiExtensions = $this->config->get( ConfigNames::Extensions );
			foreach ( json_decode( $setObject->s_extensions ?? '[]', true ) as $ext ) {
				if ( isset( $manageWikiExtensions[$ext] ) ) {
					$cacheArray['extensions'][] = $manageWikiExtensions[$ext]['name'];
					continue;
				}

				$this->logger->error( 'Extension/Skin {ext} not set in {config}', [
					'config' => ConfigNames::Extensions,
					'ext' => $ext,
				] );
			}
		}

		// Collate NS entries and decode their entries for the array
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$nsObjects = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'mw_namespaces' )
				->where( [ 'ns_dbname' => $wiki ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			$metaNamespace = null;
			$metaNamespaceTalk = null;

			foreach ( $nsObjects as $ns ) {
				if ( $metaNamespace !== null && $metaNamespaceTalk !== null ) {
					// Both found, no need to continue
					break;
				}

				$id = (int)$ns->ns_namespace_id;

				if ( $id === NS_PROJECT ) {
					$metaNamespace = $ns->ns_namespace_name;
					continue;
				}

				if ( $id === NS_PROJECT_TALK ) {
					$metaNamespaceTalk = $ns->ns_namespace_name;
				}
			}

			$lcName = [];
			$lcEN = [];

			try {
				$languageCode = $cacheArray['core']['wgLanguageCode'] ?? 'en';
				$lcName = $this->localisationCache->getItem( $languageCode, 'namespaceNames' );
				$lcName[NS_PROJECT_TALK] = str_replace( '$1',
					$lcName[NS_PROJECT] ?? $metaNamespace,
					$lcName[NS_PROJECT_TALK] ?? $metaNamespaceTalk
				);

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
				$nsName = $lcName[(int)$ns->ns_namespace_id] ?? $ns->ns_namespace_name;
				$lcAlias = $lcEN[(int)$ns->ns_namespace_id] ?? null;

				$cacheArray['namespaces'][$nsName] = [
					'id' => (int)$ns->ns_namespace_id,
					'core' => (bool)$ns->ns_core,
					'searchable' => (bool)$ns->ns_searchable,
					'subpages' => (bool)$ns->ns_subpages,
					'content' => (bool)$ns->ns_content,
					'contentmodel' => $ns->ns_content_model,
					'protection' => $ns->ns_protection ?: false,
					'aliases' => array_merge(
						json_decode( str_replace( [ ' ', ':' ], '_', $ns->ns_aliases ?? '' ), true ),
						(array)$lcAlias
					),
					'additional' => json_decode( $ns->ns_additional ?? '', true ),
				];

				$nsAdditional = (array)json_decode( $ns->ns_additional ?? '', true );

				foreach ( $additional as $var => $conf ) {
					$nsID = (int)$ns->ns_namespace_id;

					if ( !$this->isAdditionalSettingForNamespace( $conf, $nsID ) ) {
						continue;
					}

					if ( isset( $nsAdditional[$var] ) ) {
						$val = $nsAdditional[$var];
					} elseif ( is_array( $conf['overridedefault'] ) ) {
						$val = $conf['overridedefault'][$nsID]
							?? $conf['overridedefault']['default']
							?? null;

						if ( $val === null ) {
							// Skip if no fallback exists
							continue;
						}
					} else {
						$val = $conf['overridedefault'];
					}

					if ( $val ) {
						$this->setNamespaceSettingJson( $cacheArray, $nsID, $var, $val, $conf );
						continue;
					}

					if ( empty( $conf['constant'] ) && empty( $cacheArray['settings'][$var] ) ) {
						$cacheArray['settings'][$var] = [];
					}
				}
			}

			// Search for and apply overridedefaults to NS_SPECIAL
			// Notably, we do not apply 'default' overridedefault to NS_SPECIAL
			// It must exist as it's own key in overridedefault
			foreach ( $additional as $var => $conf ) {
				if (
					( $conf['overridedefault'][NS_SPECIAL] ?? false ) &&
					$this->isAdditionalSettingForNamespace( $conf, NS_SPECIAL )
				) {
					$val = $conf['overridedefault'][NS_SPECIAL];
					$this->setNamespaceSettingJson( $cacheArray, NS_SPECIAL, $var, $val, $conf );
				}
			}
		}

		// Same as NS above but for permissions
		if ( ManageWiki::checkSetup( 'permissions' ) ) {
			$permObjects = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'mw_permissions' )
				->where( [ 'perm_dbname' => $wiki ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			$additionalRights = $this->config->get( ConfigNames::PermissionsAdditionalRights );
			$additionalAddGroups = $this->config->get( ConfigNames::PermissionsAdditionalAddGroups );
			$additionalRemoveGroups = $this->config->get( ConfigNames::PermissionsAdditionalRemoveGroups );

			foreach ( $permObjects as $perm ) {
				$addPerms = [];
				$removePerms = [];

				foreach ( $additionalRights[$perm->perm_group] ?? [] as $right => $bool ) {
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
						$additionalAddGroups[$perm->perm_group] ?? []
					),
					'removegroups' => array_merge(
						json_decode( $perm->perm_removegroups ?? '', true ) ?? [],
						$additionalRemoveGroups[$perm->perm_group] ?? []
					),
					'addself' => json_decode( $perm->perm_addgroupstoself ?? '', true ),
					'removeself' => json_decode( $perm->perm_removegroupsfromself ?? '', true ),
					'autopromote' => json_decode( $perm->perm_autopromote ?? '', true ),
				];
			}

			$diffKeys = array_keys(
				array_diff_key( $additionalRights, $cacheArray['permissions'] ?? [] )
			);

			foreach ( $diffKeys as $missingKey ) {
				$missingPermissions = [];

				foreach ( $additionalRights[$missingKey] as $right => $bool ) {
					if ( $bool ) {
						$missingPermissions[] = $right;
					}
				}

				$cacheArray['permissions'][$missingKey] = [
					'permissions' => $missingPermissions,
					'addgroups' => $additionalAddGroups[$missingKey] ?? [],
					'removegroups' => $additionalRemoveGroups[$missingKey] ?? [],
					'addself' => [],
					'removeself' => [],
					'autopromote' => [],
				];
			}
		}
	}

	/** @inheritDoc */
	public function onCreateWikiStatePrivate( string $dbname ): void {
		$defaultPrivateGroup = $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup );
		if ( !ManageWiki::checkSetup( 'permissions' ) || !$defaultPrivateGroup ) {
			return;
		}

		$mwPermissionsDefault = $this->moduleFactory->permissionsDefault();
		$mwPermissions = $this->moduleFactory->permissions( $dbname );

		$defaultPrivate = $mwPermissionsDefault->list( $defaultPrivateGroup );

		$privateArray = [];
		foreach ( $defaultPrivate as $name => $value ) {
			if ( $name === 'autopromote' ) {
				$privateArray[$name] = $value;
				continue;
			}

			$privateArray[$name]['add'] = $value;
		}

		$mwPermissions->modify( $defaultPrivateGroup, $privateArray );

		$mwPermissions->modify( 'sysop', [
			'addgroups' => [
				'add' => [ $defaultPrivateGroup ],
			],
			'removegroups' => [
				'add' => [ $defaultPrivateGroup ],
			],
		] );

		$mwPermissions->commit();
	}

	/** @inheritDoc */
	public function onCreateWikiStatePublic( string $dbname ): void {
		$defaultPrivateGroup = $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup );
		if ( !ManageWiki::checkSetup( 'permissions' ) || !$defaultPrivateGroup ) {
			return;
		}

		$mwPermissions = $this->moduleFactory->permissions( $dbname );
		$mwPermissions->remove( $defaultPrivateGroup );

		foreach ( array_keys( $mwPermissions->list( group: null ) ) as $group ) {
			$mwPermissions->modify( $group, [
				'addgroups' => [
					'remove' => [ $defaultPrivateGroup ],
				],
				'removegroups' => [
					'remove' => [ $defaultPrivateGroup ],
				],
			] );
		}

		$mwPermissions->commit();
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
		if ( $varConf['type'] === 'check' ) {
			$cacheArray['settings'][$var][] = $nsID;
			return;
		}

		if ( $varConf['type'] === 'vestyle' ) {
			$cacheArray['settings'][$var][$nsID] = true;
			return;
		}

		if ( $varConf['constant'] ?? false ) {
			$cacheArray['settings'][$var] = str_replace( [ ' ', ':' ], '_', $val );
			return;
		}

		$cacheArray['settings'][$var][$nsID] = $val;
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

		$only = $conf['only'] ?? null;
		return $only === null || in_array( $nsID, (array)$only, true );
	}
}
