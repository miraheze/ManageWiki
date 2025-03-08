<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;

class RemoveSettings extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addArg( 'setting', 'The ManageWiki name of the setting.', true );
		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$setting = $this->getArg( 0 );

		$mwSetting = new ManageWikiSettings( $this->getConfig()->get( MainConfigNames::DBname ) );
		$mwSetting->remove( $setting );
		$mwSetting->commit();
	}
}

// @codeCoverageIgnoreStart
return RemoveSettings::class;
// @codeCoverageIgnoreEnd
