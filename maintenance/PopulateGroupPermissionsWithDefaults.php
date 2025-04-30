<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;

class PopulateGroupPermissionsWithDefaults extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	/**
	 * Populates the current wiki's group permissions with default values if none exist, optionally overwriting existing permissions.
	 *
	 * If the 'overwrite' option is set, existing permissions for the current database are deleted before applying defaults. After populating permissions, the wiki data is reset to reflect the changes.
	 */
	public function execute(): void {
		$databaseUtils = $this->getServiceContainer()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		$dbname = $this->getConfig()->get( MainConfigNames::DBname );

		if ( $this->hasOption( 'overwrite' ) ) {
			$dbw->newDeleteQueryBuilder()
				->deleteFrom( 'mw_permissions' )
				->where( [ 'perm_dbname' => $dbname ] )
				->caller( __METHOD__ )
				->execute();
		}

		$checkRow = $dbw->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$checkRow ) {
			$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
			$mwPermissions = $moduleFactory->permissionsLocal();
			$mwPermissionsDefault = $moduleFactory->permissionsDefault();
			$defaultGroups = array_diff(
				array_keys( $mwPermissionsDefault->list( group: null ) ),
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

			$dataFactory = $this->getServiceContainer()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateGroupPermissionsWithDefaults::class;
// @codeCoverageIgnoreEnd
