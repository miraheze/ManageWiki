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

		if ( $this->hasOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_namespaces',
				[
					'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
				],
				__METHOD__
			);
		}

		$checkRow = $dbw->selectRow(
			'mw_namespaces',
			[
				'*',
			],
			[
				'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
			],
			__METHOD__
		);

		if ( !$checkRow ) {
			$mwNamespaces = new ManageWikiNamespaces( $this->getConfig()->get( MainConfigNames::DBname ) );
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
			$defaultNamespaces = array_keys( $mwNamespacesDefault->list( id: null ) );

			foreach ( $defaultNamespaces as $namespace ) {
				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
				$mwNamespaces->commit();
			}

			$dataFactory = $this->getServiceContainer()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $this->getConfig()->get( MainConfigNames::DBname ) );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateNamespacesWithDefaults::class;
// @codeCoverageIgnoreEnd
