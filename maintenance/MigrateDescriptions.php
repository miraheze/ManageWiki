<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;

class MigrateDescriptions extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$remoteWikiFactory = $this->getServiceContainer()->get( 'RemoteWikiFactory' );

		foreach ( $this->getConfig()->get( MainConfigNames::LocalDatabases ) as $wiki ) {
			$remoteWiki = $remoteWikiFactory->newInstance( $wiki );
			$mwSettings = new ManageWikiSettings( $wiki );
			$setList = $mwSettings->list();

			if ( $setList['wgWikiDiscoverDescription'] ?? false ) {
				$this->output( "Migrating description for $wiki\n" );

				$remoteWiki->setExtraFieldData( 'description', $setList['wgWikiDiscoverDescription'] );
				$remoteWiki->commit();

				$mwSettings->remove( [ 'wgWikiDiscoverDescription' ] );
				$mwSettings->commit();
			}
		}
	}
}

// @codeCoverageIgnoreStart
return MigrateDescriptions::class;
// @codeCoverageIgnoreEnd
