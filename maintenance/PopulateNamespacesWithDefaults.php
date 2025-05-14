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

	public function execute(): void {
		$databaseUtils = $this->getServiceContainer()->get( 'ManageWikiDatabaseUtils' );
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
			$defaultNamespaces = $mwNamespacesDefault->listIds();

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify(
					$namespace,
					$mwNamespacesDefault->list( $namespace ),
					maintainPrefix: false
				);
			}

			$mwNamespaces->commit();
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateNamespacesWithDefaults::class;
// @codeCoverageIgnoreEnd
