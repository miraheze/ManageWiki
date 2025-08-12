<?php

namespace Miraheze\ManageWiki\Hooks;

use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

interface ManageWikiDataStoreBuilderHook {

	/**
	 * @param ModuleFactory $moduleFactory
	 *   ModuleFactory connection to be able to read module data.
	 * @param string $dbname
	 *   The target wiki's database name (e.g., "examplewiki").
	 * @param array &$cacheArray
	 *   The cache array that can be manipulated to add new entries to the
	 *   ManageWiki cache for the individual wiki.
	 *
	 * @return void This hook must not abort, it must return no value.
	 * @codeCoverageIgnore Cannot be annotated as covered.
	 */
	public function onManageWikiDataStoreBuilder(
		ModuleFactory $moduleFactory,
		string $dbname,
		array &$cacheArray
	): void;
}
