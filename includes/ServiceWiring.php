<?php

namespace Miraheze\ManageWiki;

use Closure;
use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;
use Miraheze\ManageWiki\Helpers\ModuleFactory;
use Miraheze\ManageWiki\Hooks\Handlers\CreateWiki;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
use Psr\Log\LoggerInterface;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'ManageWikiLogger' => static fn (): LoggerInterface =>
		LoggerFactory::getInstance( 'ManageWiki' ),

	'ManageWikiConfig' => static fn ( MediaWikiServices $services ) =>
		$services->getConfigFactory()->makeConfig( 'ManageWiki' ),

	'ManageWikiExtensionsFactory' => static fn ( MediaWikiServices $services ): Closure =>
		static fn ( string $dbname ): ManageWikiExtensions => new ManageWikiExtensions(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiLogger' ),
			new ServiceOptions(
				ManageWikiExtensions::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			),
			$dbname
		),

	'ManageWikiNamespacesFactory' => static fn ( MediaWikiServices $services ): Closure =>
		static fn ( string $dbname ): ManageWikiNamespaces => new ManageWikiNamespaces(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->getJobQueueGroupFactory(),
			$services->getNamespaceInfo(),
			new ServiceOptions(
				ManageWikiNamespaces::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			),
			$dbname
		),

	'ManageWikiPermissionsFactory' => static fn ( MediaWikiServices $services ): Closure =>
		static fn ( string $dbname ): ManageWikiPermissions => new ManageWikiPermissions(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->getActorStoreFactory(),
			$services->getUserGroupManagerFactory(),
			$services->getMessageFormatterFactory()->getTextFormatter(
				$services->getContentLanguageCode()->toString()
			),
			$dbname
		),

	'ManageWikiSettingsFactory' => static fn ( MediaWikiServices $services ): Closure =>
		static fn ( string $dbname ): ManageWikiSettings => new ManageWikiSettings(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			new ServiceOptions(
				ManageWikiSettings::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			),
			$dbname
		),

	'ManageWikiModuleFactory' => static fn ( MediaWikiServices $services ): ModuleFactory =>
		new ModuleFactory(
			$services->get( 'ManageWikiExtensionsFactory' ),
			$services->get( 'ManageWikiNamespacesFactory' ),
			$services->get( 'ManageWikiPermissionsFactory' ),
			$services->get( 'ManageWikiSettingsFactory' ),
			$services->get( 'RemoteWikiFactory' ),
			new ServiceOptions(
				ModuleFactory::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		),

	'ManageWikiHookRunner' => static fn ( MediaWikiServices $services ): ManageWikiHookRunner =>
		new ManageWikiHookRunner( $services->getHookContainer() ),

	'CreateWikiHookHandler' => static fn ( MediaWikiServices $services ): CreateWiki =>
		new CreateWiki(
			$services->get( 'ManageWikiConfig' ),
			$services->get( 'ManageWikiLogger' ),
			$services->get( 'ManageWikiModuleFactory' ),
			$services->getLocalisationCache()
		),
];
// @codeCoverageIgnoreEnd
