<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\DefaultPermissions;
use Miraheze\ManageWiki\Helpers\Factories\CoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\ExtensionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Factories\NamespacesFactory;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
use Psr\Log\LoggerInterface;

/**
 * A simple wrapper for MediaWikiServices, to support type safety when accessing
 * services defined by this extension.
 */
class Services extends MediaWikiServices {

	/**
	 * @return Config ManageWiki configuration
	 */
	public function getConfig(): Config {
		return $this->getService( 'ManageWikiConfig' );
	}

	/**
	 * @return CoreFactory Factory for core ManageWiki module
	 */
	public function getCoreFactory(): CoreFactory {
		return $this->getService( 'ManageWikiCoreFactory' );
	}

	/**
	 * @return DatabaseUtils Database utility methods
	 */
	public function getDatabaseUtils(): DatabaseUtils {
		return $this->getService( 'ManageWikiDatabaseUtils' );
	}

	/**
	 * @return DefaultPermissions Default permissions manager
	 */
	public function getDefaultPermissions(): DefaultPermissions {
		return $this->getService( 'ManageWikiDefaultPermissions' );
	}

	/**
	 * @return ExtensionsFactory Factory for the ManageWiki extensions module
	 */
	public function getExtensionsFactory(): ExtensionsFactory {
		return $this->getService( 'ManageWikiExtensionsFactory' );
	}

	/**
	 * @return ManageWikiHookRunner Hook runner for ManageWiki hooks
	 */
	public function getHookRunner(): ManageWikiHookRunner {
		return $this->getService( 'ManageWikiHookRunner' );
	}

	/**
	 * @return LoggerInterface Logger for ManageWiki
	 */
	public function getLogger(): LoggerInterface {
		return $this->getService( 'ManageWikiLogger' );
	}

	/**
	 * @return NamespacesFactory Factory for namespace configuration module
	 */
	public function getNamespacesFactory(): NamespacesFactory {
		return $this->getService( 'ManageWikiNamespacesFactory' );
	}

	/**
	 * @return PermissionsFactory Factory for permissions module
	 */
	public function getPermissionsFactory(): PermissionsFactory {
		return $this->getService( 'ManageWikiPermissionsFactory' );
	}

	/**
	 * @return SettingsFactory Factory for settings module
	 */
	public function getSettingsFactory(): SettingsFactory {
		return $this->getService( 'ManageWikiSettingsFactory' );
	}

	/**
	 * @return ModuleFactory Factory for managing all modules (core, extensions, etc.)
	 */
	public function getModuleFactory(): ModuleFactory {
		return $this->getService( 'ManageWikiModuleFactory' );
	}
}
