<?php

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class populateWikiSettingsWithDefaults extends Maintenance {
	public function __construct() {
		parent::__construct();
	}

	public function execute() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'createwiki' );
		$dbw = wfGetDB( DB_MASTER, [], $config->get( 'CreateWikiDatabase' ) );

		$dbw->insert(
			'cw_wikis',
			[
				's_dbname' => $config->get( 'DBname' ),
				's_extensions' => json_encode( [] ),
				's_settings' => json_encode( [] )
			]
		);

		$this->recacheWikiJson( $config->get( 'DBname' ) );
	}

	private function recacheWikiJson( string $wiki ) {
		$cWJ = new CreateWikiJson( $wiki );
		$cWJ->resetWiki();
	}
}

$maintClass = 'populateWikiSettingsWithDefaults';
require_once RUN_MAINTENANCE_IF_MAIN;
