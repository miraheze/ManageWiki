<?php

use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulateNamespacesWithDefaults extends Maintenance {
	private $config;

	public function __construct() {
		parent::__construct();
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
		$this->addOption( 'overwrite', 'This overwrites namespaces to reset them back to the default.', false, false );
	}

	public function execute() {
		$dbw = wfGetDB( DB_MASTER, [], $this->config->get( 'CreateWikiDatabase' ) );

		if ( $this->getOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_namespaces',
				[
					'ns_dbname' => $this->config->get( 'DBname' )
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
				'ns_dbname' => $this->config->get( 'DBname' )
			]
		);

		if ( !$checkRow ) {
 			$mwNamespaces = new ManageWikiNamespaces( $this->config->get( 'DBname' ) );
			$mwNamespacesDefault = new ManageWikiNamespaces( 'default' );
 			$defaultNamespaces = array_keys( $mwNamespacesDefault->list() );

 			foreach ( $defaultNamespaces as $namespace ) {
 				$mwNamespaces->modify( $namespace, $mwNamespacesDefault->list( $namespace ) );
 				$mwNamespaces->commit();
 			}

			$cWJ = new CreateWikiJson( $this->config->get( 'DBname' ) );
			$cWJ->resetWiki();
		}
	}
}

$maintClass = 'ManageWikiPopulateNamespacesWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
