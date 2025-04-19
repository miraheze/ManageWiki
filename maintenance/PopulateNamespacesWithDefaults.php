<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;

class PopulateNamespacesWithDefaults extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites namespaces to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$databaseUtils = $this->getServiceContainer()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		$dbname = $this->getConfig()->get( MainConfigNames::DBname );

		if ( $this->hasOption( 'overwrite' ) ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'mw_namespaces' )
				->where( [ 'ns_dbname' => $dbname ] )
				->caller( __METHOD__ )
				->execute();
		}

		$checkRow = $dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mw_namespaces' )
			->where( [ 'ns_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$checkRow ) {
			$mwNamespaces = new ManageWikiNamespaces( $dbname );
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list( id: null ) );

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit();
			}

			$dataFactory = $this->getServiceContainer()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateNamespacesWithDefaults::class;
// @codeCoverageIgnoreEnd
