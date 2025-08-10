<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\FormFactory\FormFactory;
use Miraheze\ManageWiki\FormFactory\FormFactoryBuilder;
use Miraheze\ManageWiki\Helpers\CoreModule;
use Miraheze\ManageWiki\Helpers\DefaultPermissions;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\Factories\CoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\DataFactory;
use Miraheze\ManageWiki\Helpers\Factories\ExtensionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\InstallerFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Factories\NamespacesFactory;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\RequirementsFactory;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\TypesBuilder;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Psr\Log\LoggerInterface;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'ManageWikiConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'ManageWiki' );
	},
	'ManageWikiCoreFactory' => static function ( MediaWikiServices $services ): CoreFactory {
		return new CoreFactory(
			$services->get( 'ManageWikiHookRunner' ),
			$services->get( 'ManageWikiSettingsFactory' ),
			new ServiceOptions(
				CoreModule::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiDatabaseUtils' => static function ( MediaWikiServices $services ): DatabaseUtils {
		return new DatabaseUtils( $services->getConnectionProvider() );
	},
	'ManageWikiDataFactory' => static function ( MediaWikiServices $services ): DataFactory {
		return new DataFactory(
			$services->getObjectCacheFactory(),
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiHookRunner' ),
			new ServiceOptions(
				DataFactory::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiDefaultPermissions' => static function ( MediaWikiServices $services ): DefaultPermissions {
		return new DefaultPermissions(
			$services->get( 'ManageWikiModuleFactory' ),
			new ServiceOptions(
				DefaultPermissions::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiExtensionsFactory' => static function ( MediaWikiServices $services ): ExtensionsFactory {
		return new ExtensionsFactory(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiDataFactory' ),
			$services->get( 'ManageWikiInstallerFactory' ),
			$services->get( 'ManageWikiLogger' ),
			$services->get( 'ManageWikiRequirementsFactory' ),
			new ServiceOptions(
				ExtensionsModule::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiFormFactory' => static function ( MediaWikiServices $services ): FormFactory {
		return new FormFactory( $services->get( 'ManageWikiFormFactoryBuilder' ) );
	},
	'ManageWikiFormFactoryBuilder' => static function ( MediaWikiServices $services ): FormFactoryBuilder {
		return new FormFactoryBuilder(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiHookRunner' ),
			$services->get( 'ManageWikiLogger' ),
			$services->get( 'ManageWikiRequirementsFactory' ),
			$services->get( 'ManageWikiTypesBuilder' ),
			$services->getLinkRenderer(),
			$services->getObjectCacheFactory(),
			$services->getPermissionManager(),
			$services->getUserGroupManager(),
			new ServiceOptions(
				FormFactoryBuilder::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiHookRunner' => static function ( MediaWikiServices $services ): HookRunner {
		return new HookRunner( $services->getHookContainer() );
	},
	'ManageWikiInstallerFactory' => static function ( MediaWikiServices $services ): InstallerFactory {
		return new InstallerFactory(
			$services->getDBLoadBalancerFactory(),
			$services->getJobQueueGroupFactory(),
			$services->get( 'ManageWikiLogger' ),
			// Use a closure to avoid circular dependency
			static fn (): ModuleFactory => $services->get( 'ManageWikiModuleFactory' )
		);
	},
	'ManageWikiLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'ManageWiki' );
	},
	'ManageWikiNamespacesFactory' => static function ( MediaWikiServices $services ): NamespacesFactory {
		return new NamespacesFactory(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiDataFactory' ),
			$services->getJobQueueGroupFactory(),
			$services->getNamespaceInfo(),
			new ServiceOptions(
				NamespacesModule::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiPermissionsFactory' => static function ( MediaWikiServices $services ): PermissionsFactory {
		return new PermissionsFactory(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiDataFactory' ),
			$services->getActorStoreFactory(),
			$services->getUserGroupManagerFactory(),
			$services->getMessageFormatterFactory()->getTextFormatter(
				$services->getContentLanguageCode()->toString()
			)
		);
	},
	'ManageWikiRequirementsFactory' => static function ( MediaWikiServices $services ): RequirementsFactory {
		return new RequirementsFactory(
			$services->get( 'ManageWikiCoreFactory' ),
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiSettingsFactory' )
		);
	},
	'ManageWikiSettingsFactory' => static function ( MediaWikiServices $services ): SettingsFactory {
		return new SettingsFactory(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiDataFactory' ),
			$services->get( 'ManageWikiInstallerFactory' ),
			new ServiceOptions(
				SettingsModule::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiModuleFactory' => static function ( MediaWikiServices $services ): ModuleFactory {
		return new ModuleFactory(
			$services->get( 'ManageWikiCoreFactory' ),
			$services->get( 'ManageWikiExtensionsFactory' ),
			$services->get( 'ManageWikiNamespacesFactory' ),
			$services->get( 'ManageWikiPermissionsFactory' ),
			$services->get( 'ManageWikiSettingsFactory' ),
			new ServiceOptions(
				ModuleFactory::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiTypesBuilder' => static function ( MediaWikiServices $services ): TypesBuilder {
		return new TypesBuilder(
			$services->get( 'ManageWikiPermissionsFactory' ),
			$services->getContentHandlerFactory(),
			$services->getInterwikiLookup(),
			$services->getPermissionManager(),
			$services->getSkinFactory(),
			$services->getUserOptionsLookup(),
			new ServiceOptions(
				TypesBuilder::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
];

// @codeCoverageIgnoreEnd
