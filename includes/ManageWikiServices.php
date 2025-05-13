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
class ManageWikiServices {

	public function __construct(
		private readonly MediaWikiServices $coreServices
	) {
	}

	/**
	 * Static version of the constructor, for nicer syntax.
	 * @param MediaWikiServices $coreServices
	 * @return static
	 */
	public static function wrap( MediaWikiServices $coreServices ): static {
		return new static( $coreServices );
	}

	/**
	 * @return Config ManageWiki configuration
	 */
	public function getManageWikiConfig(): Config {
		return $this->coreServices->get( 'ManageWikiConfig' );
	}

	/**
	 * @return CoreFactory Factory for core ManageWiki module
	 */
	public function getManageWikiCoreFactory(): CoreFactory {
		return $this->coreServices->get( 'ManageWikiCoreFactory' );
	}

	/**
	 * @return DatabaseUtils Database utility methods
	 */
	public function getManageWikiDatabaseUtils(): DatabaseUtils {
		return $this->coreServices->get( 'ManageWikiDatabaseUtils' );
	}

	/**
	 * @return DefaultPermissions Default permissions manager
	 */
	public function getManageWikiDefaultPermissions(): DefaultPermissions {
		return $this->coreServices->get( 'ManageWikiDefaultPermissions' );
	}

	/**
	 * @return ExtensionsFactory Factory for the ManageWiki extensions module
	 */
	public function getManageWikiExtensionsFactory(): ExtensionsFactory {
		return $this->coreServices->get( 'ManageWikiExtensionsFactory' );
	}

	/**
	 * @return ManageWikiHookRunner Hook runner for ManageWiki hooks
	 */
	public function getManageWikiHookRunner(): ManageWikiHookRunner {
		return $this->coreServices->get( 'ManageWikiHookRunner' );
	}

	/**
	 * @return LoggerInterface Logger for ManageWiki
	 */
	public function getManageWikiLogger(): LoggerInterface {
		return $this->coreServices->get( 'ManageWikiLogger' );
	}

	/**
	 * @return NamespacesFactory Factory for namespace configuration module
	 */
	public function getManageWikiNamespacesFactory(): NamespacesFactory {
		return $this->coreServices->get( 'ManageWikiNamespacesFactory' );
	}

	/**
	 * @return PermissionsFactory Factory for permissions module
	 */
	public function getManageWikiPermissionsFactory(): PermissionsFactory {
		return $this->coreServices->get( 'ManageWikiPermissionsFactory' );
	}

	/**
	 * @return SettingsFactory Factory for settings module
	 */
	public function getManageWikiSettingsFactory(): SettingsFactory {
		return $this->coreServices->get( 'ManageWikiSettingsFactory' );
	}

	/**
	 * @return ModuleFactory Factory for managing all modules (core, extensions, etc.)
	 */
	public function getManageWikiModuleFactory(): ModuleFactory {
		return $this->coreServices->get( 'ManageWikiModuleFactory' );
	}
}
