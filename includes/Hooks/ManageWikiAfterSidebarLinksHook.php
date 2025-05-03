<?php

namespace Miraheze\ManageWiki\Hooks;

use Skin;

interface ManageWikiAfterSidebarLinksHook {

	/**
	 * @param Skin $skin
	 * @param array &$sidebarLinks
	 * @return void
	 */
	public function onManageWikiAfterSidebarLinks( Skin $skin, array &$sidebarLinks ): void;
}
