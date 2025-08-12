<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use function usleep;

class ResetWikiCaches extends Maintenance {

	private DataStoreFactory $dataStoreFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'all-wikis', 'Reset ManageWiki cache on all wikis.' );
		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->dataStoreFactory = $services->get( 'ManageWikiDataStoreFactory' );
	}

	public function execute(): void {
		$this->initServices();
		$allWikis = $this->hasOption( 'all-wikis' );
		$dbnames = $allWikis ?
			$this->getConfig()->get( MainConfigNames::LocalDatabases ) :
			[ $this->getConfig()->get( MainConfigNames::DBname ) ];

		foreach ( $dbnames as $dbname ) {
			$dataStore = $this->dataStoreFactory->newInstance( $dbname );
			$dataStore->resetWikiData( isNewChanges: true );
			usleep( 20000 );
		}
	}
}

// @codeCoverageIgnoreStart
return ResetWikiCaches::class;
// @codeCoverageIgnoreEnd
