<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Tested by updating or installing MediaWiki.
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../../../sql';

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_namespaces',
			"$dir/mw_namespaces.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_permissions',
			"$dir/mw_permissions.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_settings',
			"$dir/mw_settings.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'modifyTable',
			'mw_namespaces',
			"$dir/patches/patch-namespace-name-alter.sql",
			true,
		] );
	}
}
