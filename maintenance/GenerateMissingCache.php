<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use function file_exists;

class GenerateMissingCache extends Maintenance {

	private DataStoreFactory $dataStoreFactory;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Generates ManageWiki cache files for all wikis that are currently missing one.' );
		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->dataStoreFactory = $services->get( 'ManageWikiDataStoreFactory' );
	}

	public function execute(): void {
		$this->initServices();
		$cacheDir = $this->getConfig()->get( ConfigNames::CacheDirectory ) ?:
			$this->getConfig()->get( MainConfigNames::CacheDirectory );

		foreach ( $this->getConfig()->get( MainConfigNames::LocalDatabases ) as $dbname ) {
			if ( file_exists( "$cacheDir/$dbname.php" ) ) {
				continue;
			}

			$dataStore = $this->dataStoreFactory->newInstance( $dbname );
			$dataStore->resetWikiData( isNewChanges: true );

			$this->output( "Cache generated for $dbname\n" );
		}
	}
}

// @codeCoverageIgnoreStart
return GenerateMissingCache::class;
// @codeCoverageIgnoreEnd
