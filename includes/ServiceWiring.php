<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\CoreModule;
use Miraheze\ManageWiki\Helpers\DefaultPermissions;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\Factories\CoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\ExtensionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Factories\NamespacesFactory;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
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
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->get( 'ManageWikiLogger' ),
			new ServiceOptions(
				ExtensionsModule::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiHookRunner' => static function ( MediaWikiServices $services ): ManageWikiHookRunner {
		return new ManageWikiHookRunner( $services->getHookContainer() );
	},
	'ManageWikiLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'ManageWiki' );
	},
	'ManageWikiNamespacesFactory' => static function ( MediaWikiServices $services ): NamespacesFactory {
		return new NamespacesFactory(
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiDatabaseUtils' ),
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
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiDatabaseUtils' ),
			$services->getActorStoreFactory(),
			$services->getUserGroupManagerFactory(),
			$services->getMessageFormatterFactory()->getTextFormatter(
				$services->getContentLanguageCode()->toString()
			)
		);
	},
	'ManageWikiSettingsFactory' => static function ( MediaWikiServices $services ): SettingsFactory {
		return new SettingsFactory(
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiDatabaseUtils' ),
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
];

// @codeCoverageIgnoreEnd
