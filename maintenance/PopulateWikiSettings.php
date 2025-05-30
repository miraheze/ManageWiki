<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use function array_pad;
use function explode;
use function file;
use function is_numeric;
use function str_replace;
use function strpos;

class PopulateWikiSettings extends Maintenance {

	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'setting', 'The setting variable minus the $.', true, true );
		$this->addOption( 'sourcelist', 'File in format of "wikidb|value" for the setting above.', false, true );
		$this->addOption( 'remove', 'Removes setting listed with --setting.' );

		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();
		if ( $this->hasOption( 'remove' ) ) {
			$mwSettings = $this->moduleFactory->settingsLocal();
			$mwSettings->remove( [ $this->getOption( 'setting' ) ], default: null );
			$mwSettings->commit();
			return;
		}

		if ( !$this->hasOption( 'sourcelist' ) ) {
			$this->fatalError( 'You must provide --sourcelist when not using --remove' );
		}

		$settingSource = file( $this->getOption( 'sourcelist' ) );

		foreach ( $settingSource as $input ) {
			$wikidb = explode( '|', $input, 2 );
			[ $dbname, $settingValue ] = array_pad( $wikidb, 2, '' );

			$this->output( "Setting $settingValue for $dbname\n" );

			$value = str_replace( "\n", '', $settingValue );

			if ( $value === 'true' ) {
				$value = true;
			}

			if ( $value === 'false' ) {
				$value = false;
			}

			if ( is_numeric( $value ) ) {
				// Handle setting float and integer values
				$setting = strpos( $value, '.' ) !== false ? (float)$value : (int)$value;
			}

			$mwSettings = $this->moduleFactory->settings( $dbname );
			$mwSettings->modify( [ $this->getOption( 'setting' ) => $value ], default: null );
			$mwSettings->commit();
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateWikiSettings::class;
// @codeCoverageIgnoreEnd
