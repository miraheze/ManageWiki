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
			$defaultCanonicalNamespaces = (array)ManageWikiNamespaces::defaultCanonicalNamespaces();
			foreach ( $defaultCanonicalNamespaces as $newnamespace ) {
				$namespacesArray = ManageWikiNamespaces::defaultNamespaces( $newnamespace );
				$dbw->insert(
					'mw_namespaces',
					[
						'ns_dbname' => $wgDBname,
						'ns_namespace_id' => $newnamespace,
						'ns_namespace_name' => (string)$namespacesArray['ns_namespace_name'],
						'ns_searchable' => (int)$namespacesArray['ns_searchable'],
						'ns_subpages' => (int)$namespacesArray['ns_subpages'],
						'ns_content' => (int)$namespacesArray['ns_content'],
						'ns_content_model' => $namespacesArray['ns_content_model'],
						'ns_protection' => $namespacesArray['ns_protection'],
						'ns_aliases' => (string)$namespacesArray['ns_aliases'],
						'ns_core' => (int)$namespacesArray['ns_core'],
						'ns_additional' => $namespacesArray['ns_additional']
					],
					__METHOD__
				);
			}

			ManageWikiCDB::changes( 'namespaces' );
		}
	}
}

$maintClass = 'ManageWikiPopulateNamespacesWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
