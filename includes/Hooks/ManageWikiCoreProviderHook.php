<?php

namespace Miraheze\ManageWiki\Hooks;

use Miraheze\ManageWiki\ICoreModule;

/**
 * Hook interface for overriding the default ManageWiki core module provider.
 *
 * This hook allows extensions to supply a custom implementation of the `ICoreModule`
 * for a given wiki database. If no provider is set by this hook, the default
 * `CoreModule` is used.
 */
interface ManageWikiCoreProviderHook {

	/**
	 * Allows extensions to override the default ManageWiki core module for a specific wiki.
	 *
	 * This is useful if an extension needs to inject a different `ICoreModule` implementation
	 * with additional logic or dependencies for a given wiki.
	 *
	 * @param ?ICoreModule &$provider
	 *   A nullable reference to the `ICoreModule` instance to be used. Hook implementations
	 *   should set this to a valid implementation to override the default. If left `null`,
	 *   the default `CoreModule` will be instantiated instead.
	 * @param string $dbname
	 *   The database name of the target wiki (e.g., "examplewiki").
	 *
	 * @return void
	 */
	public function onManageWikiCoreProvider( ?ICoreModule &$provider, string $dbname ): void;
}
