<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\DataStore;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\HookRunner;
use ObjectCacheFactory;
use Wikimedia\ObjectCache\BagOStuff;

class DataFactory {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::CacheDirectory,
		ConfigNames::CacheType,
	];

	private readonly BagOStuff $cache;

	public function __construct(
		ObjectCacheFactory $objectCacheFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly HookRunner $hookRunner,
		private readonly ModuleFactory $moduleFactory,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->cache = ( $this->options->get( ConfigNames::CacheType ) !== null ) ?
			$objectCacheFactory->getInstance( $this->options->get( ConfigNames::CacheType ) ) :
			$objectCacheFactory->getLocalClusterInstance();
	}

	public function newInstance( string $dbname ): DataStore {
		$cacheDir = $this->options->get( ConfigNames::CacheDirectory );
		return new DataStore(
			$this->cache,
			$this->databaseUtils,
			$this->hookRunner,
			$this->moduleFactory,
			$cacheDir,
			$dbname
		);
	}
}
