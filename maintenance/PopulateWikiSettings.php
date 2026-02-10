<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use function array_pad;
use function explode;
use function file;
use function is_numeric;
use function str_contains;
use function str_replace;
use function var_export;

class PopulateWikiSettings extends Maintenance {

	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addOption( 'setting', 'The setting variable minus the $.', true, true );
		$this->addOption( 'sourcelist', 'File in format of "wikidb|value" for the setting above.', false, true );
		$this->addOption( 'remove', 'Removes setting listed with --setting.' );
		$this->addOption( 'all-wikis', 'Remove this setting from all wikis. Only valid with --remove.' );
		$this->addOption( 'execute', 'Confirm execution. Required if using --all-wikis.' );

		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();

		$setting = $this->getOption( 'setting' );

		if ( $this->hasOption( 'remove' ) ) {
			if ( $this->hasOption( 'all-wikis' ) ) {
				if ( !$this->hasOption( 'execute' ) ) {
					$this->fatalError( 'You must use --execute when using --remove with --all-wikis.', 2 );
				}

				$dbnames = $this->getConfig()->get( MainConfigNames::LocalDatabases );
				foreach ( $dbnames as $dbname ) {
					$mwSettings = $this->moduleFactory->settings( $dbname );
					$mwSettings->remove( [ $setting ], default: null );
					$mwSettings->commit();
					$this->output( "Removed $setting from $dbname\n" );
				}

				$this->output( "Removed $setting from all wikis.\n" );
				return;
			}

			// Local only
			$mwSettings = $this->moduleFactory->settingsLocal();
			$mwSettings->remove( [ $setting ], default: null );
			$mwSettings->commit();
			$this->output( "Removed $setting from local wiki.\n" );
			return;
		}

		if ( !$this->hasOption( 'sourcelist' ) ) {
			$this->fatalError( 'You must provide --sourcelist when not using --remove' );
		}

		$settingSource = file( $this->getOption( 'sourcelist' ) );
		foreach ( $settingSource as $input ) {
			$wikidb = explode( '|', $input, 2 );
			[ $dbname, $settingValue ] = array_pad( $wikidb, 2, '' );

			$value = str_replace( "\n", '', $settingValue );

			if ( $value === 'true' ) {
				$value = true;
			}

			if ( $value === 'false' ) {
				$value = false;
			}

			if ( is_numeric( $value ) ) {
				// Handle setting float and integer values
				$value = str_contains( $value, '.' ) ? (float)$value : (int)$value;
			}

			$mwSettings = $this->moduleFactory->settings( $dbname );
			$mwSettings->modify( [ $setting => $value ], default: null );
			$mwSettings->commit();

			$this->output( "Set $setting to " . var_export( $value, true ) . " on $dbname\n" );
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateWikiSettings::class;
// @codeCoverageIgnoreEnd
