<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
use Miraheze\ManageWiki\ICoreModule;

class CoreFactory {

	public function __construct(
		private readonly ManageWikiHookRunner $hookRunner
	) {
	}

	public function newInstance( string $dbname ): ?ICoreModule {
		$provider = null;
		$this->hookRunner->onManageWikiCoreProvider( $provider );
		return $provider;
	}
}
