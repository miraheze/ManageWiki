<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

class ModuleFactory {

	private const DEFAULT_DATABASE = 'default';

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::DBname,
	];

	/**
	 * Initializes the ModuleFactory with required ManageWiki service dependencies and configuration options.
	 *
	 * Ensures that all necessary configuration options are present for proper factory operation.
	 */
	public function __construct(
		private readonly ManageWikiExtensions $extensions,
		private readonly ManageWikiNamespaces $namespaces,
		private readonly ManageWikiPermissions $permissions,
		private readonly ManageWikiSettings $settings,
		private readonly RemoteWikiFactory $core,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Creates a new RemoteWikiFactory instance for the specified database.
	 *
	 * @param string $dbname The name of the target database.
	 * @return RemoteWikiFactory Instance scoped to the given database.
	 */
	public function core( string $dbname ): RemoteWikiFactory {
		return $this->core->newInstance( $dbname );
	}

	/**
	 * Creates a new ManageWikiExtensions instance for the specified database.
	 *
	 * @param string $dbname The name of the target database.
	 * @return ManageWikiExtensions Instance scoped to the given database.
	 */
	public function extensions( string $dbname ): ManageWikiExtensions {
		return $this->extensions->newInstance( $dbname );
	}

	/**
	 * Creates a new ManageWikiNamespaces instance for the specified database.
	 *
	 * @param string $dbname The name of the database to scope the instance to.
	 * @return ManageWikiNamespaces Instance configured for the given database.
	 */
	public function namespaces( string $dbname ): ManageWikiNamespaces {
		return $this->namespaces->newInstance( $dbname );
	}

	/**
	 * Creates a new ManageWikiPermissions instance for the specified database.
	 *
	 * @param string $dbname The name of the database for which to create the permissions instance.
	 * @return ManageWikiPermissions Instance scoped to the given database.
	 */
	public function permissions( string $dbname ): ManageWikiPermissions {
		return $this->permissions->newInstance( $dbname );
	}

	/**
	 * Creates a new ManageWikiSettings instance for the specified database.
	 *
	 * @param string $dbname The name of the database to scope the settings instance to.
	 * @return ManageWikiSettings Instance configured for the given database.
	 */
	public function settings( string $dbname ): ManageWikiSettings {
		return $this->settings->newInstance( $dbname );
	}

	/**
	 * Returns a ManageWikiNamespaces instance for the default database.
	 *
	 * @return ManageWikiNamespaces Instance scoped to the default database.
	 */
	public function namespacesDefault(): ManageWikiNamespaces {
		return $this->namespaces( self::DEFAULT_DATABASE );
	}

	/**
	 * Returns a ManageWikiPermissions instance for the default database.
	 *
	 * @return ManageWikiPermissions Instance scoped to the default database.
	 */
	public function permissionsDefault(): ManageWikiPermissions {
		return $this->permissions( self::DEFAULT_DATABASE );
	}

	/**
	 * Returns a RemoteWikiFactory instance for the local database as specified in the configuration.
	 *
	 * @return RemoteWikiFactory Instance scoped to the local database.
	 */
	public function coreLocal(): RemoteWikiFactory {
		return $this->core( $this->options->get( MainConfigNames::DBname ) );
	}

	/**
	 * Returns a ManageWikiExtensions instance for the local database.
	 *
	 * The local database name is retrieved from the configuration options.
	 *
	 * @return ManageWikiExtensions Instance scoped to the local database.
	 */
	public function extensionsLocal(): ManageWikiExtensions {
		return $this->extensions( $this->options->get( MainConfigNames::DBname ) );
	}

	/**
	 * Returns a ManageWikiNamespaces instance for the local database.
	 *
	 * The local database name is retrieved from the configuration options.
	 *
	 * @return ManageWikiNamespaces Instance scoped to the local database.
	 */
	public function namespacesLocal(): ManageWikiNamespaces {
		return $this->namespaces( $this->options->get( MainConfigNames::DBname ) );
	}

	/**
	 * Returns a ManageWikiPermissions instance for the local database.
	 *
	 * The local database name is retrieved from the configuration options.
	 *
	 * @return ManageWikiPermissions Instance scoped to the local database.
	 */
	public function permissionsLocal(): ManageWikiPermissions {
		return $this->permissions( $this->options->get( MainConfigNames::DBname ) );
	}

	/**
	 * Returns a ManageWikiSettings instance for the local database.
	 *
	 * The local database name is retrieved from the configuration options.
	 *
	 * @return ManageWikiSettings Instance scoped to the local database.
	 */
	public function settingsLocal(): ManageWikiSettings {
		return $this->settings( $this->options->get( MainConfigNames::DBname ) );
	}
}
