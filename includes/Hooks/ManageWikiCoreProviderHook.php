<?php

namespace Miraheze\ManageWiki\Hooks;

use Miraheze\ManageWiki\ICoreModule;

interface ManageWikiCoreProviderHook {

	/**
	 * @param ICoreModule &$provider
	 * @param string $dbname
	 * @return void
	 */
	public function onManageWikiCoreProvider( ICoreModule &$provider, string $dbname ): void;
}
