<?php

namespace Miraheze\ManageWiki\Hooks;

/**
 * Hook interface for regenerating a wiki farm's database list cache.
 *
 * ManageWiki does not know how or where a farm's database list is stored,
 * that lives in whatever extension manages wiki creation. This hook lets
 * that extension supply the actual regeneration, fired from ManageWiki's
 * database cache reset REST endpoint.
 */
interface ManageWikiResetDatabaseListsHook {

	/**
	 * Regenerates the database list cache on the server handling this request.
	 *
	 * @return void This hook must not abort, it must return no value.
	 */
	public function onManageWikiResetDatabaseLists(): void;
}
