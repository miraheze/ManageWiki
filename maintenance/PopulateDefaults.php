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
		$dbw = $this->getPrimaryDB();

		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_namespaces.sql' );
		$dbw->sourceFile( __DIR__ . '/../sql/defaults/mw_permissions.sql' );

		$this->output( "Populated defaults for global database '{$dbw->getDomainID()}'\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
return PopulateDefaults::class;
// @codeCoverageIgnoreEnd
