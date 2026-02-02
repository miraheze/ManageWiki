<?php

namespace Miraheze\ManageWiki\Helpers\Settings;

use MediaWiki\Settings\Config\GlobalConfigBuilder;
use MediaWiki\Settings\Config\PhpIniSink;
use MediaWiki\Settings\SettingsBuilder;
use const MW_INSTALL_PATH;

class ManageWikiSettingsBuilder extends SettingsBuilder {

	public static function getInstance(): self {
		static $instance = null;

		if ( !$instance ) {
			$instance = new self(
				MW_INSTALL_PATH,
				ExtensionRegistry::getInstance(),
				new GlobalConfigBuilder( '' ),
				new PhpIniSink()
			);
		}

		return $instance;
	}
}
