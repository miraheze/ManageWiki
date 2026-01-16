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
		$type = $updater->getDB()->getType();

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_namespaces',
			"$dir/$type/mw_namespaces.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_permissions',
			"$dir/$type/mw_permissions.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addTable',
			'mw_settings',
			"$dir/$type/mw_settings.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'modifyTable',
			'mw_namespaces',
			"$dir/$type/patches/patch-namespace-core-alter.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'modifyTable',
			'mw_namespaces',
			"$dir/$type/patches/patch-namespace-name-alter.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addField',
			'mw_permissions',
			'perm_addgroupstoself',
			"$dir/$type/patches/patch-groups-self.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addField',
			'mw_permissions',
			'perm_autopromote',
			"$dir/$type/patches/patch-autopromote.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addField',
			'mw_namespaces',
			'ns_additional',
			"$dir/$type/patches/patch-namespaces-additional.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addIndex',
			'mw_namespaces',
			'ns_dbname',
			"$dir/$type/patches/patch-namespaces-add-indexes.sql",
			true,
		] );

		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-managewiki',
			'addIndex',
			'mw_permissions',
			'perm_dbname',
			"$dir/$type/patches/patch-permissions-add-indexes.sql",
			true,
		] );
	}
}
