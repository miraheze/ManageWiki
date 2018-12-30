<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiPopulateNamespaces extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	function execute() {
		global $wgCreateWikiDatabase, $wgDBname, $wgCanonicalNamespaceNames, $wgNamespaceAliases, $wgNamespacesToBeSearchedDefault, $wgNamespacesWithSubpages, $wgContentNamespaces, $wgNamespaceProtection;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$namespaces = $wgCanonicalNamespaceNames + [ 0 => 'Main' ];

		Wikimedia\suppressWarnings();

		foreach ( $namespaces as $id => $name ) {
			if ( $id < 0 ) {
				// We don't like 'imaginary' namespaces
				continue;
			}

			$matchedNSKeys = array_keys( $wgNamespaceAliases, $id );
			$nsAliases = [];

			foreach ( $matchedNSKeys as $o => $n ) {
				$nsAliases[] = $n;
			}

			$dbw->insert(
				'mw_namespaces',
				[
					'ns_dbname' => $wgDBname,
					'ns_namespace_id' => (int)$id,
					'ns_namespace_name' => (string)$name,
					'ns_searchable' => (int)$wgNamespacesToBeSearchedDefault[$id],
					'ns_subpages' => (int)$wgNamespacesWithSubpages[$id],
					'ns_content' => (int)$wgContentNamespaces[$id],
					'ns_protection' => ( is_array( $wgNamespaceProtection[$id] ) ) ? (string)$wgNamespaceProtection[$id][0] : (string)$wgNamespaceProtection[$id],
					'ns_aliases' => (string)json_encode( $nsAliases ),
					'ns_core' => ( $id < 1000 ) ? 1 : 0 // we assume less than < is "core", could do with smarter logic!
				],
				__METHOD__
			);
		}

		Wikimedia\restoreWarnings();
	}
}

$maintClass = 'ManageWikiPopulateNamespaces';
require_once RUN_MAINTENANCE_IF_MAIN;
