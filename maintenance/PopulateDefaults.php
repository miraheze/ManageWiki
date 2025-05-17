<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

class PopulateDefaults extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ManageWiki' );
	}

	protected function getUpdateKey(): string {
		return __CLASS__;
	}

	protected function doDBUpdates(): bool {
		$databaseUtils = $this->getServiceContainer()->get( 'ManageWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_namespaces.sql' );
		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_permissions.sql' );

		$this->output( "Populated defaults for global database '{$dbw->getDomainID()}'\n" );

		$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
		$defaultPermissions = $this->getServiceContainer()->get( 'ManageWikiDefaultPermissions' );

		$centralWiki = $databaseUtils->getCentralWikiID();
		$defaultPermissions->populatePermissions(
			$centralWiki,
			$moduleFactory->core( $centralWiki )->isPrivate()
		);

		$this->output( "Populated default permissions for central wiki '$centralWiki'\n" );

		return true;
	}
}

// @codeCoverageIgnoreStart
return PopulateDefaults::class;
// @codeCoverageIgnoreEnd
