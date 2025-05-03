<?php

namespace Miraheze\ManageWiki\Hooks;

use Miraheze\ManageWiki\ICoreModule;

interface ManageWikiCoreProviderHook {

	/**
	 * @param ?ICoreModule &$provider
	 * @return void
	 */
	public function onManageWikiCoreProvider( ?ICoreModule &$provider ): void;
}
