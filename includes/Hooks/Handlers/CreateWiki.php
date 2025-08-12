<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use Miraheze\CreateWiki\Hooks\CreateWikiCreationHook;
use Miraheze\CreateWiki\Hooks\CreateWikiDeletionHook;
use Miraheze\CreateWiki\Hooks\CreateWikiRenameHook;
use Miraheze\CreateWiki\Hooks\CreateWikiStatePrivateHook;
use Miraheze\CreateWiki\Hooks\CreateWikiStatePublicHook;
use Miraheze\CreateWiki\Hooks\CreateWikiTablesHook;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\DefaultPermissions;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Wikimedia\Rdbms\DBConnRef;

class CreateWiki implements
	CreateWikiCreationHook,
	CreateWikiDeletionHook,
	CreateWikiRenameHook,
	CreateWikiStatePrivateHook,
	CreateWikiStatePublicHook,
	CreateWikiTablesHook
{

	public function __construct(
		private readonly Config $config,
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly DefaultPermissions $defaultPermissions,
		private readonly ModuleFactory $moduleFactory
	) {
	}

	/** @inheritDoc */
	public function onCreateWikiCreation( string $dbname, bool $private ): void {
		if ( $this->moduleFactory->isEnabled( 'permissions' ) ) {
			$this->defaultPermissions->populatePermissions( $dbname, $private );
		}

		if (
			$this->config->get( ConfigNames::Extensions ) &&
			$this->config->get( ConfigNames::ExtensionsDefault )
		) {
			$mwExtensions = $this->moduleFactory->extensions( $dbname );
			$mwExtensions->add( $this->config->get( ConfigNames::ExtensionsDefault ) );
			$mwExtensions->commit();
		}

		if ( $this->moduleFactory->isEnabled( 'namespaces' ) ) {
			$mwNamespacesDefault = $this->moduleFactory->namespacesDefault();
			$defaultNamespaces = $mwNamespacesDefault->listIds();

			$mwNamespaces = $this->moduleFactory->namespaces( $dbname );
			$mwNamespaces->disableNamespaceMigrationJob();

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify(
					$namespace,
					$mwNamespacesDefault->list( $namespace ),
					maintainPrefix: false
				);
				$mwNamespaces->commit();
			}
		}
	}

	/**
	 * @inheritDoc
	 * @param DBConnRef $cwdb @phan-unused-param
	 */
	public function onCreateWikiDeletion( DBConnRef $cwdb, string $dbname ): void {
		$dataStore = $this->dataStoreFactory->newInstance( $dbname );
		$dataStore->deleteWikiData( $dbname );
	}

	/**
	 * @inheritDoc
	 * @param DBConnRef $cwdb @phan-unused-param
	 */
	public function onCreateWikiRename(
		DBConnRef $cwdb,
		string $oldDbName,
		string $newDbName
	): void {
		/**
		 * Since the wiki at $newDbName likely won't be cached yet, this will also
		 * run resetWikiData() on it since it has no mtime, so that it will
		 * generate the new cache file for it as well.
		 */
		$dataStore = $this->dataStoreFactory->newInstance( $newDbName );
		$dataStore->deleteWikiData( $oldDbName );
	}

	/** @inheritDoc */
	public function onCreateWikiStatePrivate( string $dbname ): void {
		if ( !$this->moduleFactory->isEnabled( 'permissions' ) ) {
			return;
		}

		$this->defaultPermissions->populatePrivatePermissons( $dbname );
	}

	/** @inheritDoc */
	public function onCreateWikiStatePublic( string $dbname ): void {
		$defaultPrivateGroup = $this->config->get( ConfigNames::PermissionsDefaultPrivateGroup );
		if ( !$this->moduleFactory->isEnabled( 'permissions' ) || !$defaultPrivateGroup ) {
			return;
		}

		$mwPermissions = $this->moduleFactory->permissions( $dbname );
		// We don't need to continue if it doesn't exist
		if ( !$mwPermissions->exists( $defaultPrivateGroup ) ) {
			return;
		}

		$mwPermissions->remove( $defaultPrivateGroup );
		$mwPermissions->commit();
	}

	/** @inheritDoc */
	public function onCreateWikiTables( array &$tables ): void {
		if ( $this->moduleFactory->isEnabled( 'extensions' ) || $this->moduleFactory->isEnabled( 'settings' ) ) {
			$tables['mw_settings'] = 's_dbname';
		}

		if ( $this->moduleFactory->isEnabled( 'permissions' ) ) {
			$tables['mw_permissions'] = 'perm_dbname';
		}

		if ( $this->moduleFactory->isEnabled( 'namespaces' ) ) {
			$tables['mw_namespaces'] = 'ns_dbname';
		}
	}
}
