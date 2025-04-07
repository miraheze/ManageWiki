<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;

class MigrateDescriptions extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'remove', 'Remove current value of wgWikiDiscoverDescription' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$remove = $this->hasOption( 'remove' );
		$remoteWikiFactory = $this->getServiceContainer()->get( 'RemoteWikiFactory' );

		foreach ( $this->getConfig()->get( MainConfigNames::LocalDatabases ) as $wiki ) {
			$remoteWiki = $remoteWikiFactory->newInstance( $wiki );
			$mwSettings = new ManageWikiSettings( $wiki );
			$setList = $mwSettings->list();

			if ( $setList['wgWikiDiscoverDescription'] ?? false ) {
				$this->output( "Migrating description for $wiki\n" );

				if ( !$remove ) {
					$remoteWiki->setExtraFieldData( 'description', $setList['wgWikiDiscoverDescription'] ?? null );
					$remoteWiki->commit();
					continue;
				}

				$mwSettings->remove( [ 'wgWikiDiscoverDescription' ] );
				$mwSettings->commit();
			}
		}
	}
}

// @codeCoverageIgnoreStart
return MigrateDescriptions::class;
// @codeCoverageIgnoreEnd
