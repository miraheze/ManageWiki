<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;

class ToggleExtension extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'name', 'The ManageWiki name of the extension.', true, true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
		$this->addOption( 'all-wikis', 'Run on all wikis present in $wgLocalDatabases.' );
		$this->addOption( 'confirm', 'Confirm execution. Required if using --all-wikis' );
		$this->addOption( 'no-list', 'Don\'t list on which wikis this script has ran. This may speed up execution.' );
		$this->addOption( 'force-remove', 'Force removal of extension when not in config.' );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$forceRemove = $this->hasOption( 'force-remove' );
		$noList = $this->hasOption( 'no-list' );
		$allWikis = $this->hasOption( 'all-wikis' );
		$wikis = $allWikis ?
			$this->getConfig()->get( MainConfigNames::LocalDatabases ) :
			[ $this->getConfig()->get( MainConfigNames::DBname ) ];

		$name = $this->getOption( 'name' );
		$disable = $this->hasOption( 'disable' );

		if ( $allWikis && !$this->hasOption( 'confirm' ) ) {
			$this->fatalError( 'You must run with --confirm when running with --all-wikis.', 2 );
		}

		$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
		foreach ( $wikis as $wiki ) {
			$mwExtensions = $moduleFactory->extensions( $wiki );
			$extList = $mwExtensions->list();
			if ( $disable && ( in_array( $name, $extList, true ) || $forceRemove ) ) {
				$mwExtensions->remove( [ $name ], $forceRemove );
				$mwExtensions->commit();
				if ( !$noList ) {
					$this->output( "Disabled $name on $wiki\n" );
				}

				continue;
			}

			if ( !in_array( $name, $extList, true ) && !$disable ) {
				$mwExtensions->add( [ $name ] );
				$mwExtensions->commit();
				if ( !$noList ) {
					$this->output( "Enabled $name on $wiki\n" );
				}
			}
		}

		if ( $noList && count( $wikis ) > 1 ) {
			if ( $disable ) {
				$this->output( "Disabled $name on all wikis that it was enabled on.\n" );
				return;
			}

			$this->output( "Enabled $name on all wikis in \$wgLocalDatabases.\n" );
		}
	}
}

// @codeCoverageIgnoreStart
return ToggleExtension::class;
// @codeCoverageIgnoreEnd
