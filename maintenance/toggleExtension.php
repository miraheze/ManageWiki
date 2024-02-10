<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\WikiMap\WikiMap;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;

class ToggleExtension extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'ext', 'The ManageWiki name of the extension.', true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
		$this->addOption( 'all-wikis', 'Run on all wikis present in $wgLocalDatabases.' );
		$this->addOption( 'no-list', 'Only list wikis after it has ran on them. This may speed up execution.' );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$noList = $this->getOption( 'no-list', false );
		$allWikis = $this->getOption( 'all-wikis', false );
		$wikis = $allWikis ?
			$this->getConfig()->get( 'LocalDatabases' ) :
			[ WikiMap::getCurrentWikiId() ];

		$ext = $this->getArg( 0 );
		$disable = $this->getOption( 'disable', false );

		if ( $allWikis ) {
			if ( $disable ) {
				$confirm = readline( "Are you sure you want to remove $ext from all wikis that it is enabled on? This can not be undone! (y/n) " );
			} else {
				$confirm = readline( 'Are you sure you want to enable ' . $ext . ' on all wikis in $wgLocalDatabases? This can not be undone! (y/n) ' );
			}

			if ( strtolower( $confirm ) !== 'y' ) {
				$this->fatalError( 'Aborted.', 2 );
			}
		}

		foreach ( $wikis as $wiki ) {
			$mwExt = new ManageWikiExtensions( $wiki );
			$extensionList = $mwExt->list();
			if ( $disable && in_array( $ext, $extensionList ) ) {
				$mwExt->remove( $ext );
				$mwExt->commit();
				if ( !$noList ) {
					$this->output( "Removed $ext from $wiki" );
				}
			} elseif ( !in_array( $ext, $extensionList ) ) {
				$mwExt->add( $ext );
				$mwExt->commit();
				if ( !$noList ) {
					$this->output( "Enabled $ext on $wiki" );
				}
			}
		}

		if ( $noList && count( $wikis ) > 1 ) {
			if ( $disable ) {
				$this->output( "Removed $ext from all wikis in that it was enabled on." );
			} else {
				$this->output( 'Enabled ' . $ext . ' on all wikis in $wgLocalDatabases.' );
			}
		}

	}
}

$maintClass = ToggleExtension::class;
require_once RUN_MAINTENANCE_IF_MAIN;
