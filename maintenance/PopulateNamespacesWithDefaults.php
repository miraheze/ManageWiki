<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;

class PopulateNamespacesWithDefaults extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites namespaces to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	/**
	 * Populates the mw_namespaces table with default namespace values if none exist for the current wiki.
	 *
	 * If the 'overwrite' option is set, existing namespace entries for the current database are deleted before repopulation.
	 * After inserting default namespaces, resets related wiki data to reflect the changes.
	 */
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
			$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
			$mwNamespaces = $moduleFactory->namespacesLocal();
			$mwNamespacesDefault = $moduleFactory->namespacesDefault();
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
