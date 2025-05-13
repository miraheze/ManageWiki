<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\Maintenance;

class PopulateWikiSettings extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'wgsetting', 'The $wg setting minus $.', true, true );
		$this->addOption( 'sourcelist', 'File in format of "wiki|value" for the $wg setting above.', false, true );
		$this->addOption( 'remove', 'Removes setting listed with --wgsetting.' );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
		if ( $this->hasOption( 'remove' ) ) {
			$mwSettings = $moduleFactory->settingsLocal();
			$mwSettings->remove( [ $this->getOption( 'wgsetting' ) ], default: null );
			$mwSettings->commit();
			return;
		}

		if ( !$this->hasOption( 'sourcelist' ) ) {
			$this->fatalError( 'You must provide --sourcelist when not using --remove' );
		}

		$settingsource = file( $this->getOption( 'sourcelist' ) );

		foreach ( $settingsource as $input ) {
			$wikidb = explode( '|', $input, 2 );
			[ $dbname, $settingvalue ] = array_pad( $wikidb, 2, '' );

			$this->output( "Setting $settingvalue for $dbname\n" );

			$setting = str_replace( "\n", '', $settingvalue );

			if ( $setting === 'true' ) {
				$setting = true;
			} elseif ( $setting === 'false' ) {
				$setting = false;
			}

			$mwSettings = $moduleFactory->settings( $dbname );
			$mwSettings->modify( [ $this->getOption( 'wgsetting' ) => $setting ], default: null );
			$mwSettings->commit();
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateWikiSettings::class;
// @codeCoverageIgnoreEnd
