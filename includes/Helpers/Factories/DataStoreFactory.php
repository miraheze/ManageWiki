<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use Closure;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\CacheUpdate;
use Miraheze\ManageWiki\Helpers\DataStore;
use Miraheze\ManageWiki\Hooks\HookRunner;
use ObjectCacheFactory;
use Wikimedia\ObjectCache\BagOStuff;

class DataStoreFactory {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::CacheDirectory,
		ConfigNames::CacheType,
		MainConfigNames::CacheDirectory,
	];

	private readonly BagOStuff $cache;

	public function __construct(
		ObjectCacheFactory $objectCacheFactory,
		private readonly CacheUpdate $cacheUpdate,
		private readonly HookRunner $hookRunner,
		private readonly Closure $moduleFactoryClosure,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->cache = ( $this->options->get( ConfigNames::CacheType ) !== null ) ?
			$objectCacheFactory->getInstance( $this->options->get( ConfigNames::CacheType ) ) :
			$objectCacheFactory->getLocalClusterInstance();
	}

	public function newInstance( string $dbname ): DataStore {
		$cacheDir = $this->options->get( ConfigNames::CacheDirectory ) ?:
			$this->options->get( MainConfigNames::CacheDirectory );
		return new DataStore(
			$this->cache,
			$this->cacheUpdate,
			$this->hookRunner,
			( $this->moduleFactoryClosure )(),
			$cacheDir,
			$dbname
		);
	}
}
