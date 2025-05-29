<?php

namespace Miraheze\ManageWiki\Hooks;

use Skin;

/**
 * Hook interface for modifying the ManageWiki sidebar links after they are built.
 *
 * This hook is called after the ManageWiki sidebar links have been populated,
 * but before they are rendered. It allows extensions to modify, add, or remove
 * entries from the sidebar links.
 */
interface ManageWikiAfterSidebarLinksHook {

	/**
	 * This hook is triggered after the ManageWiki sidebar links have been constructed.
	 *
	 * @param Skin $skin The current skin object rendering the page.
	 * @param array<int, array<string, string>> &$sidebarLinks
	 *   An array of sidebar links in the format:
	 *   [
	 *     [
	 *       'text' => 'Link text',
	 *       'id' => 'DOM ID of the link',
	 *       'href' => 'URL of the link (must be properly escaped)'
	 *     ],
	 *     ...
	 *   ]
	 *   This array is passed by reference and can be modified.
	 *
	 * @return void
	 */
	public function onManageWikiAfterSidebarLinks( Skin $skin, array &$sidebarLinks ): void;
}
