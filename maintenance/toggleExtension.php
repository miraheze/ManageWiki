<?php

namespace Miraheze\ManageWiki\Maintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

use Maintenance;
use MediaWiki\MainConfigNames;
use MediaWiki\WikiMap\WikiMap;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;

class ToggleExtension extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addArg( 'ext', 'The ManageWiki name of the extension.', true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
		$this->addOption( 'all-wikis', 'Run on all wikis present in $wgLocalDatabases.' );
		$this->addOption( 'confirm', 'Confirm execution. Required if using --all-wikis' );
		$this->addOption( 'no-list', 'Don\'t list on which wikis this script has ran. This may speed up execution.' );
		$this->addOption( 'force-remove', 'Force removal of extension when not in config.' );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$forceRemove = $this->getOption( 'force-remove', false );
		$noList = $this->getOption( 'no-list', false );
		$allWikis = $this->getOption( 'all-wikis', false );
		$wikis = $allWikis ?
			$this->getConfig()->get( MainConfigNames::LocalDatabases ) :
			[ WikiMap::getCurrentWikiId() ];

		$ext = $this->getArg( 0 );
		$disable = $this->getOption( 'disable', false );

		if ( $allWikis && !$this->getOption( 'confirm', false ) ) {
			$this->fatalError( 'You must run with --confirm when running with --all-wikis.', 2 );
		}

		foreach ( $wikis as $wiki ) {
			$mwExt = new ManageWikiExtensions( $wiki );
			$extensionList = $mwExt->list();
			if ( $disable && ( in_array( $ext, $extensionList ) || $forceRemove ) ) {
				$mwExt->remove( $ext, $forceRemove );
				$mwExt->commit();
				if ( !$noList ) {
					$this->output( "Removed $ext from $wiki\n" );
				}
			} elseif ( !in_array( $ext, $extensionList ) && !$disable ) {
				$mwExt->add( $ext );
				$mwExt->commit();
				if ( !$noList ) {
					$this->output( "Enabled $ext on $wiki\n" );
				}
			}
		}

		if ( $noList && count( $wikis ) > 1 ) {
			if ( $disable ) {
				$this->output( "Removed $ext from all wikis in that it was enabled on.\n" );
			} else {
				$this->output( "Enabled $ext on all wikis in \$wgLocalDatabases.\n" );
			}
		}
	}
}

$maintClass = ToggleExtension::class;
require_once RUN_MAINTENANCE_IF_MAIN;
