<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use MediaWiki\MediaWikiServices;
use Wikimedia\AtEase\AtEase;

class ManageWikiPopulateNamespaces extends Maintenance {
	private $config;

	public function __construct() {
		parent::__construct();
	}

	public function execute() {
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );

		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$this->fatalError( 'Disable ManageWiki Namespaces on this wiki.' );
		}

		$dbw = wfGetDB( DB_MASTER, [], $this->config->get( 'CreateWikiDatabase' ) );

		$namespaces = $this->config->get( 'CanonicalNamespaceNames' ) + [ 0 => '<Main>' ];

		AtEase::suppressWarnings();

		foreach ( $namespaces as $id => $name ) {
			if ( $id < 0 ) {
				// We don't like 'imaginary' namespaces
				continue;
			}

			$matchedNSKeys = array_keys( $this->config->get( 'NamespaceAliases' ), $id );
			$nsAliases = [];

			foreach ( $matchedNSKeys as $o => $n ) {
				$nsAliases[] = $n;
			}

			$res = $dbw->select(
				'mw_namespaces',
				[
					'ns_namespace_name',
					'ns_namespace_id'
				],
				[
					'ns_dbname' => $this->config->get( 'DBname' )
				],
				__METHOD__
			);


			if ( !$res || !is_object( $res ) ) {
				$this->insertNamespace( $dbw, $id, $name, $nsAliases);
			} else {
				foreach ( $res as $row ) {
					if ( $row->ns_namespace_id !== (int)$id ) {
						$this->insertNamespace( $dbw, $id, $name, $nsAliases);
					}
				}
			}
		}

		AtEase::restoreWarnings();
	}
	
	public function insertNamespace( $dbw, $id, $name, $nsAliases ) {
		$dbw->insert(
			'mw_namespaces',
			[
				'ns_dbname' => $this->config->get( 'DBname' ),
				'ns_namespace_id' => (int)$id,
				'ns_namespace_name' => (string)$name,
				'ns_searchable' => (int)$this->config->get( 'NamespacesToBeSearchedDefault' )[$id],
				'ns_subpages' => (int)$this->config->get( 'NamespacesWithSubpages' )[$id],
				'ns_content' => (int)$this->config->get( 'ContentNamespaces' )[$id],
				'ns_content_model' => isset( $this->config->get( 'NamespaceContentModels' )[$id] ) ? (string)$this->config->get( 'NamespaceContentModels' )[$id] : 'wikitext',
				'ns_protection' => ( is_array( $this->config->get( 'NamespaceProtection' )[$id] ) ) ? (string)$this->config->get( 'NamespaceProtection' )[$id][0] : (string)$this->config->get( 'NamespaceProtection' )[$id],
				'ns_aliases' => (string)json_encode( $nsAliases ),
				'ns_core' => (int)( $id < 1000 ),
				'ns_additional' => (string)json_encode( [] ),
			],
			__METHOD__
		);
	}
}

$maintClass = 'ManageWikiPopulateNamespaces';
require_once RUN_MAINTENANCE_IF_MAIN;
