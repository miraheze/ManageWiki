<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;

class PopulateGroupPermissionsWithDefaults extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$connectionProvider = $this->getServiceContainer()->getConnectionProvider();
		$dbw = $connectionProvider->getPrimaryDatabase( 'virtual-createwiki' );

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
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list() ), [ $this->getConfig()->get( 'ManageWikiPermissionsDefaultPrivateGroup' ) ] );

			foreach ( $defaultGroups as $newgroup ) {
				$groupData = $mwPermissionsDefault->list( $newgroup );
				$groupArray = [];

				foreach ( $groupData as $name => $value ) {
					if ( $name === 'autopromote' ) {
						$groupArray[$name] = $value;
						continue;
					}

					$groupArray[$name]['add'] = $value;
				}

				$mwPermissions->modify( $newgroup, $groupArray );
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
