<?php

namespace Miraheze\ManageWiki\Hooks;

use Skin;

interface ManageWikiAfterSidebarLinksHook {

	/**
	 * @param Skin $skin
	 * @param array &$sidebar
	 * @return void
	 */
	public function onManageWikiAfterSidebarLinks( Skin $skin, array &$sidebar ): void;
}
