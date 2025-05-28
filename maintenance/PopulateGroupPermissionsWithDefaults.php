<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

class PopulateGroupPermissionsWithDefaults extends Maintenance {

	private DatabaseUtils $databaseUtils;
	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->databaseUtils = $services->get( 'ManageWikiDatabaseUtils' );
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();

		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbname = $this->getConfig()->get( MainConfigNames::DBname );

		if ( $this->hasOption( 'overwrite' ) ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'mw_permissions' )
				->where( [ 'perm_dbname' => $dbname ] )
				->caller( __METHOD__ )
				->execute();
		}

		$checkRow = $dbw->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$checkRow ) {
			$mwPermissions = $this->moduleFactory->permissionsLocal();
			$mwPermissionsDefault = $this->moduleFactory->permissionsDefault();
			$defaultGroups = array_diff(
				$mwPermissionsDefault->listGroups(),
				[ $this->getConfig()->get( ConfigNames::PermissionsDefaultPrivateGroup ) ]
			);

			foreach ( $defaultGroups as $newGroup ) {
				$groupData = $mwPermissionsDefault->list( $newGroup );
				$groupArray = [];

				foreach ( $groupData as $name => $value ) {
					if ( $name === 'autopromote' ) {
						$groupArray[$name] = $value;
						continue;
					}

					$groupArray[$name]['add'] = $value;
				}

				$mwPermissions->modify( $newGroup, $groupArray );
			}

			$mwPermissions->commit();
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateGroupPermissionsWithDefaults::class;
// @codeCoverageIgnoreEnd
