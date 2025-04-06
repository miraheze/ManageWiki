<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\AtEase\AtEase;
use Wikimedia\Rdbms\IDatabase;

class PopulateNamespaces extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		if ( ManageWiki::checkSetup( 'namespaces' ) ) {
			$this->fatalError( 'Disable ManageWiki Namespaces on this wiki.' );
		}

		$connectionProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $connectionProvider->getPrimaryDatabase( 'virtual-createwiki' );

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
					'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
					'ns_namespace_id' => (int)$id
				],
				__METHOD__
			);

			if ( !$res || $res->count() === 0 ) {
				$this->insertNamespace( $dbw, (int)$id, (string)$name, $nsAliases );
			}
		}

		AtEase::restoreWarnings();
	}

	private function insertNamespace(
		IDatabase $dbw,
		int $id,
		string $name,
		array $nsAliases
	): void {
		$dbw->insert(
			'mw_namespaces',
			[
				'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
				'ns_namespace_id' => $id,
				'ns_namespace_name' => $name,
				'ns_searchable' => (int)$this->getConfig()->get( MainConfigNames::NamespacesToBeSearchedDefault )[$id],
				'ns_subpages' => (int)$this->getConfig()->get( MainConfigNames::NamespacesWithSubpages )[$id],
				'ns_content' => (int)$this->getConfig()->get( MainConfigNames::ContentNamespaces )[$id],
				'ns_content_model' => (string)( $this->getConfig()->get( MainConfigNames::NamespaceContentModels )[$id] ?? 'wikitext' ),
				'ns_protection' => ( is_array( $this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id] ) ) ?
					(string)$this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id][0] :
					(string)$this->getConfig()->get( MainConfigNames::NamespaceProtection )[$id],
				'ns_aliases' => json_encode( $nsAliases ) ?: '[]',
				'ns_core' => $id < 1000,
				'ns_additional' => json_encode( [] ) ?: '[]',
			],
			__METHOD__
		);
	}
}

// @codeCoverageIgnoreStart
return PopulateNamespaces::class;
// @codeCoverageIgnoreEnd
