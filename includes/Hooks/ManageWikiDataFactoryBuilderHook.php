<?php

namespace Miraheze\ManageWiki\Hooks;

use Wikimedia\Rdbms\IReadableDatabase;

interface ManageWikiDataFactoryBuilderHook {

	/**
	 * @param string $dbname
	 *   The target wiki's database name (e.g., "examplewiki").
	 * @param IReadableDatabase $dbr
	 *   Database (read) connection to use (connected to virtual-managewiki).
	 * @param array &$cacheArray
	 *   The cache array that can be manipulated to add new entries to the
	 *   ManageWiki cache for the individual wiki.
	 *
	 * @return void This hook must not abort, it must return no value.
	 * @codeCoverageIgnore Cannot be annotated as covered.
	 */
	public function onManageWikiDataFactoryBuilder(
		string $dbname,
		IReadableDatabase $dbr,
		array &$cacheArray
	): void;
}
