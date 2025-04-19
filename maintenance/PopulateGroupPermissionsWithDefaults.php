<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;

class PopulateGroupPermissionsWithDefaults extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$databaseUtils = $this->getServiceContainer()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getGlobalPrimaryDB();

		if ( $this->hasOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_permissions',
				[
					'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
				],
				__METHOD__
			);
		}

		$checkRow = $dbw->selectRow(
			'mw_permissions',
			[
				'*',
			],
			[
				'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname ),
			],
			__METHOD__
		);

		if ( !$checkRow ) {
			$mwPermissions = new ManageWikiPermissions( $this->getConfig()->get( MainConfigNames::DBname ) );
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list( group: null ) ), [ $this->getConfig()->get( ConfigNames::PermissionsDefaultPrivateGroup ) ] );

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
			$data = $dataFactory->newInstance( $this->getConfig()->get( MainConfigNames::DBname ) );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateGroupPermissionsWithDefaults::class;
// @codeCoverageIgnoreEnd
