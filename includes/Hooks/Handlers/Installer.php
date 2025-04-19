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
			'virtual-createwiki',
			'addTable',
			'mw_namespaces',
			"$dir/mw_namespaces.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'mw_permissions',
			"$dir/mw_permissions.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addTable',
			'mw_settings',
			"$dir/mw_settings.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'modifyTable',
			'mw_namespaces',
			"$dir/patches/patch-namespace-core-alter.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_permissions',
			'perm_addgroupstoself',
			"$dir/patches/patch-groups-self.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_permissions',
			'perm_autopromote',
			"$dir/patches/patch-autopromote.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addField',
			'mw_namespaces',
			'ns_additional',
			"$dir/patches/patch-namespaces-additional.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addIndex',
			'mw_namespaces',
			'ns_dbname',
			"$dir/patches/patch-namespaces-add-indexes.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-createwiki',
			'addIndex',
			'mw_permissions',
			'perm_dbname',
			"$dir/patches/patch-permissions-add-indexes.sql",
			true,
		] );
	}
}
