<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use function count;
use function in_array;

class ToggleExtension extends Maintenance {

	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'name', 'The ManageWiki name of the extension.', true, true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
		$this->addOption( 'all-wikis', 'Enable/disable the extension on all wikis.' );
		$this->addOption( 'execute', 'Confirm execution. Required if using --all-wikis.' );
		$this->addOption( 'no-list', 'Don\'t list on which wikis this script has ran. This may speed up execution.' );

		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();

		$noList = $this->hasOption( 'no-list' );
		$allWikis = $this->hasOption( 'all-wikis' );
		$dbnames = $allWikis ?
			$this->getConfig()->get( MainConfigNames::LocalDatabases ) :
			[ $this->getConfig()->get( MainConfigNames::DBname ) ];

		$name = $this->getOption( 'name' );
		$disable = $this->hasOption( 'disable' );

		if ( $allWikis && !$this->hasOption( 'execute' ) ) {
			$this->fatalError( 'You must run with --execute when running with --all-wikis.', 2 );
		}

		foreach ( $dbnames as $dbname ) {
			$mwExtensions = $this->moduleFactory->extensions( $dbname );
			$extList = $mwExtensions->list();
			if ( $disable && in_array( $name, $extList, true ) ) {
				$mwExtensions->remove( [ $name ] );
				$mwExtensions->commit();
				if ( !$noList ) {
					$this->output( "Disabled $name on $dbname.\n" );
				}

				continue;
			}

			if ( !in_array( $name, $extList, true ) && !$disable ) {
				$mwExtensions->add( [ $name ] );
				$mwExtensions->commit();
				if ( !$noList ) {
					$this->output( "Enabled $name on $dbname.\n" );
				}
			}

			if ( !in_array( $name, $extList, true ) && $disable && !$allWikis ) {
				$this->fatalError( "Failed to disable $name on $dbname: Wrong case?" );
			}
		}

		if ( $noList && count( $dbnames ) > 1 ) {
			if ( $disable ) {
				$this->output( "Disabled $name on all wikis that it was enabled on.\n" );
				return;
			}

			$this->output( "Enabled $name on all wikis.\n" );
		}
	}
}

// @codeCoverageIgnoreStart
return ToggleExtension::class;
// @codeCoverageIgnoreEnd
