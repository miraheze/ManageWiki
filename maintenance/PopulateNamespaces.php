<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ManageWikiServices;
use Wikimedia\Rdbms\IDatabase;

class PopulateNamespaces extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'force', 'Force populating namespaces even if ManageWiki namespaces is enabled.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$moduleFactory = ManageWikiServices::wrap( $this->getServiceContainer() )->getModuleFactory();
		if ( !$this->hasOption( 'force' ) && $moduleFactory->isEnabled( 'namespaces' ) ) {
			$this->fatalError( 'Disable ManageWiki Namespaces on this wiki.' );
		}

		$databaseUtils = $this->getServiceContainer()->get( 'ManageWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		$siteName = $this->getConfig()->get( MainConfigNames::Sitename );
		$metaNS = ucfirst( str_replace( ' ', '_', $siteName ) );

		$namespaces = $this->getConfig()->get( MainConfigNames::CanonicalNamespaceNames );
		$namespaces[NS_MAIN] = '<Main>';

		foreach ( $namespaces as $id => $name ) {
			$id = (int)$id;
			if ( $id < 0 ) {
				// We don't like 'imaginary' namespaces
				continue;
			}

			if ( $id === NS_PROJECT ) {
				$name = $metaNS;
			}

			if ( $id === NS_PROJECT_TALK ) {
				$name = '$1_talk';
			}

			$matchedNSKeys = array_keys( $this->getConfig()->get( MainConfigNames::NamespaceAliases ), $id, true );
			$nsAliases = [];

			foreach ( $matchedNSKeys as $o => $n ) {
				$nsAliases[] = $n;
			}

			$check = $dbw->newSelectQueryBuilder()
				->table( 'mw_namespaces' )
				->fields( [
					'ns_namespace_name',
					'ns_namespace_id',
				] )
				->where( [
					'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
					'ns_namespace_id' => $id,
				] )
				->caller( __METHOD__ )
				->fetchRow();

			if ( !$check ) {
				$this->insertNamespace( $dbw, $id, (string)$name, $nsAliases );
			}
		}
	}

	private function insertNamespace(
		IDatabase $dbw,
		int $id,
		string $name,
		array $nsAliases
	): void {
		$namespacesToBeSearchedDefault = $this->getConfig()->get( MainConfigNames::NamespacesToBeSearchedDefault );
		$namespaceContentModels = $this->getConfig()->get( MainConfigNames::NamespaceContentModels );
		$namespaceProtection = $this->getConfig()->get( MainConfigNames::NamespaceProtection );
		$dbw->newInsertQueryBuilder()
			->insertInto( 'mw_namespaces' )
			->row( [
				'ns_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
				'ns_namespace_id' => $id,
				'ns_namespace_name' => $name,
				'ns_searchable' => (int)( $namespacesToBeSearchedDefault[$id] ?? 0 ),
				'ns_subpages' => (int)( $this->getConfig()->get( MainConfigNames::NamespacesWithSubpages )[$id] ?? 0 ),
				'ns_content' => (int)( $this->getConfig()->get( MainConfigNames::ContentNamespaces )[$id] ?? 0 ),
				'ns_content_model' => (string)( $namespaceContentModels[$id] ?? CONTENT_MODEL_WIKITEXT ),
				'ns_protection' => is_array( $namespaceProtection[$id] ?? false ) ?
					(string)( $namespaceProtection[$id][0] ?? '' ) :
					(string)( $namespaceProtection[$id] ?? '' ),
				'ns_aliases' => json_encode( $nsAliases ) ?: '[]',
				'ns_core' => $id < 1000,
				'ns_additional' => '[]',
			] )
			->caller( __METHOD__ )
			->execute();
	}
}

// @codeCoverageIgnoreStart
return PopulateNamespaces::class;
// @codeCoverageIgnoreEnd
