<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulateNamespacesWithDefaults extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites namespaces to reset them back to the default.', false, false );
	}

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		if ( $this->getOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_namespaces',
				[
					'ns_dbname' => $wgDBname
				],
				__METHOD__
			);
		}

		$checkRow = $dbw->selectRow(
			'mw_namespaces',
			[
				'*'
			],
			[
				'ns_dbname' => $wgDBname
			]
		);

		if ( !$checkRow ) {
 			$mwNamespaces = new ManageWikiNamespaces( $wgDBname );
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
 			$defaultNamespaces = array_keys( $mwNamespacesDefault->list() );

 			foreach ( $defaultNamespaces as $namespace ) {
 				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
 				$mwNamespaces->commit();
 			}

			$cWJ = new CreateWikiJson( $wgDBname );
			$cWJ->resetWiki();
		}
	}
}

$maintClass = 'ManageWikiPopulateNamespacesWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
