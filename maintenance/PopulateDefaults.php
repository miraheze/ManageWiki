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
		return true;
	}
}

// @codeCoverageIgnoreStart
return PopulateDefaults::class;
// @codeCoverageIgnoreEnd
