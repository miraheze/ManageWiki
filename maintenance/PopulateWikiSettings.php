<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\Maintenance;

class PopulateWikiSettings extends Maintenance {

	/**
	 * Initializes the PopulateWikiSettings maintenance script with required options.
	 *
	 * Defines command-line options for specifying a MediaWiki setting to modify, an optional source file of wiki-value pairs, and a flag to remove the setting. Requires the ManageWiki extension to be loaded.
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'wgsetting', 'The $wg setting minus $.', true, true );
		$this->addOption( 'sourcelist', 'File in format of "wiki|value" for the $wg setting above.', false, true );
		$this->addOption( 'remove', 'Removes setting listed with --wgsetting.' );

		$this->requireExtension( 'ManageWiki' );
	}

	/****
	 * Applies or removes a specified MediaWiki setting across multiple wikis based on command-line options.
	 *
	 * If the `--remove` option is set, removes the specified setting from the local wiki. Otherwise, reads a source list file containing wiki database names and setting values, and applies the setting to each listed wiki.
	 * Terminates with a fatal error if neither `--remove` nor a valid `--sourcelist` is provided.
	 */
	public function execute(): void {
		$moduleFactory = $this->getServiceContainer()->get( 'ManageWikiModuleFactory' );
		if ( $this->hasOption( 'remove' ) ) {
			$mwSettings = $moduleFactory->settingsLocal();
			$mwSettings->remove( [ $this->getOption( 'wgsetting' ) ] );
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
			$mwSettings->modify( [ $this->getOption( 'wgsetting' ) => $setting ] );
			$mwSettings->commit();
		}
	}
}

// @codeCoverageIgnoreStart
return PopulateWikiSettings::class;
// @codeCoverageIgnoreEnd
