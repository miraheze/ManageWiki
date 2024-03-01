<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MainConfigNames;
use Miraheze\CreateWiki\CreateWikiJson;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;

class PopulateGroupPermissionsWithDefaults extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addOption( 'overwrite', 'This overwrites perms to reset them back to the default.', false, false );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$dbw = $this->getDB( DB_PRIMARY, [], $this->getConfig()->get( 'CreateWikiDatabase' ) );

		if ( $this->getOption( 'overwrite' ) ) {
			$dbw->delete(
				'mw_permissions',
				[
					'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname )
				],
				__METHOD__
			);
		}

		$checkRow = $dbw->selectRow(
			'mw_permissions',
			[
				'*'
			],
			[
				'perm_dbname' => $this->getConfig()->get( MainConfigNames::DBname )
			]
		);

		if ( !$checkRow ) {
			$mwPermissions = new ManageWikiPermissions( $this->getConfig()->get( MainConfigNames::DBname ) );
			$mwPermissionsDefault = new ManageWikiPermissions( 'default' );
			$defaultGroups = array_diff( array_keys( $mwPermissionsDefault->list() ), (array)$this->getConfig()->get( 'ManageWikiPermissionsDefaultPrivateGroup' ) );

			foreach ( $defaultGroups as $newgroup ) {
				$groupData = $mwPermissionsDefault->list( $newgroup );
				$groupArray = [];

				foreach ( $groupData as $name => $value ) {
					if ( $name == 'autopromote' ) {
						$groupArray[$name] = $value;
					} else {
						$groupArray[$name]['add'] = $value;
					}
				}

				$mwPermissions->modify( $newgroup, $groupArray );
			}

			$mwPermissions->commit();

			$cWJ = new CreateWikiJson(
				$this->getConfig()->get( MainConfigNames::DBname ),
				$this->getServiceContainer()->get( 'CreateWikiHookRunner' )
			);

			$cWJ->resetWiki();
		}
	}
}

$maintClass = PopulateGroupPermissionsWithDefaults::class;
require_once RUN_MAINTENANCE_IF_MAIN;
