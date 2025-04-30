<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Config\Config;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiModuleFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;
use Miraheze\ManageWiki\Hooks\Handlers\CreateWiki;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
use Psr\Log\LoggerInterface;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'ManageWikiModuleFactory' => static function ( MediaWikiServices $services ): ManageWikiModuleFactory {
		return new ManageWikiModuleFactory(
			$services->get( 'ManageWikiExtensions' ),
			$services->get( 'ManageWikiNamespaces' ),
			$services->get( 'ManageWikiPermissions' ),
			$services->get( 'ManageWikiSettings' ),
			$services->get( 'RemoteWikiFactory' ),
			new ServiceOptions(
				ManageWikiModuleFactory::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'CreateWikiHookHandler' => static function ( MediaWikiServices $services ): CreateWiki {
		return new CreateWiki(
			$services->get( 'ManageWikiConfig' ),
			$services->get( 'ManageWikiLogger' ),
			$services->get( 'ManageWikiModuleFactory' ),
			$services->getLocalisationCache()
		);
	},
	'ManageWikiConfig' => static function ( MediaWikiServices $services ): Config {
		return $services->getConfigFactory()->makeConfig( 'ManageWiki' );
	},
	'ManageWikiExtensions' => static function ( MediaWikiServices $services ): ManageWikiExtensions {
		return new ManageWikiExtensions(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->get( 'ManageWikiLogger' ),
			new ServiceOptions(
				ManageWikiExtensions::CONSTRUCTOR_OPTIONS,
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
	'ManageWikiNamespaces' => static function ( MediaWikiServices $services ): ManageWikiNamespaces {
		return new ManageWikiNamespaces(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->getJobQueueGroupFactory(),
			$services->getNamespaceInfo(),
			new ServiceOptions(
				ManageWikiNamespaces::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
	'ManageWikiPermissions' => static function ( MediaWikiServices $services ): ManageWikiPermissions {
		return new ManageWikiPermissions(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			$services->getActorStoreFactory(),
			$services->getUserGroupManagerFactory(),
			$services->getMessageFormatterFactory()->getTextFormatter(
				$services->getContentLanguageCode()->toString()
			),
		);
	},
	'ManageWikiSettings' => static function ( MediaWikiServices $services ): ManageWikiSettings {
		return new ManageWikiSettings(
			$services->get( 'CreateWikiDatabaseUtils' ),
			$services->get( 'CreateWikiDataFactory' ),
			new ServiceOptions(
				ManageWikiSettings::CONSTRUCTOR_OPTIONS,
				$services->get( 'ManageWikiConfig' )
			)
		);
	},
];

// @codeCoverageIgnoreEnd
