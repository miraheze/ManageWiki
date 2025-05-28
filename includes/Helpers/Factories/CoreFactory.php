<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\Helpers\CoreModule;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Miraheze\ManageWiki\ICoreModule;

class CoreFactory {

	public function __construct(
		private readonly HookRunner $hookRunner,
		private readonly SettingsFactory $settingsFactory,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ICoreModule {
		$provider = null;
		$this->hookRunner->onManageWikiCoreProvider( $provider, $dbname );
		return $provider ?? new CoreModule(
			$this->settingsFactory,
			$this->options,
			$dbname
		);
	}
}
