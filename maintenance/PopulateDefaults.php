<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class PopulateDefaults extends LoggedUpdateMaintenance {

	private DatabaseUtils $databaseUtils;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ManageWiki' );
	}

	protected function getUpdateKey(): string {
		return __CLASS__;
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->databaseUtils = $services->get( 'ManageWikiDatabaseUtils' );
	}

	protected function doDBUpdates(): bool {
		$this->initServices();
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();

		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_namespaces.sql' );
		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_permissions.sql' );

		$this->output( "Populated defaults for global database '{$dbw->getDomainID()}'\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
return PopulateDefaults::class;
// @codeCoverageIgnoreEnd
