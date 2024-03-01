<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MainConfigNames;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\AtEase\AtEase;

class PopulateNamespaces extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	public function execute() {
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$this->fatalError( 'Disable ManageWiki Namespaces on this wiki.' );
		}

		$dbw = $this->getDB( DB_PRIMARY, [], $this->getConfig()->get( 'CreateWikiDatabase' ) );

		$namespaces = $this->getConfig()->get( MainConfigNames::CanonicalNamespaceNames ) + [ 0 => '<Main>' ];

		AtEase::suppressWarnings();

		foreach ( $namespaces as $id => $name ) {
			if ( $id < 0 ) {
				// We don't like 'imaginary' namespaces
				continue;
			}

			$matchedNSKeys = array_keys( $this->getConfig()->get( MainConfigNames::NamespaceAliases ), $id );
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
					'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname )
				],
				__METHOD__
			);

			if ( !$res || !is_object( $res ) ) {
				$this->insertNamespace( $dbw, $id, $name, $nsAliases );
			} else {
				foreach ( $res as $row ) {
					if ( $row->ns_namespace_id !== (int)$id ) {
						$this->insertNamespace( $dbw, $id, $name, $nsAliases );
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
				'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
				'ns_namespace_id' => (int)$id,
				'ns_namespace_name' => (string)$name,
				'ns_searchable' => (int)$this->getConfig()->get( MainConfigNames::NamespacesToBeSearchedDefault )[$id],
				'ns_subpages' => (int)$this->getConfig()->get( MainConfigNames::NamespacesWithSubpages )[$id],
				'ns_content' => (int)$this->getConfig()->get( MainConfigNames::ContentNamespaces )[$id],
				'ns_content_model' => (string)( $this->getConfig()->get( MainConfigNames::NamespaceContentModels )[$id] ?? 'wikitext' ),
				'ns_protection' => ( is_array( $this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id] ) ) ?
					(string)$this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id][0] :
					(string)$this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id],
				'ns_aliases' => (string)json_encode( $nsAliases ),
				'ns_core' => (int)( $id < 1000 ),
				'ns_additional' => (string)json_encode( [] ),
			],
			__METHOD__
		);
	}
}

$maintClass = PopulateNamespaces::class;
require_once RUN_MAINTENANCE_IF_MAIN;
