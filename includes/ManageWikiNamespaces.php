<?php

class ManageWikiNamespaces {
	public static function configurableNamespaces( bool $id = false, bool $readable = false, bool $main = false ) {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$arrayOut = [];

		$res = $dbr->select(
			'mw_namespaces',
			[
				'ns_namespace_name',
				'ns_namespace_id'
			],
			[
				'ns_dbname' => $wgDBname
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			if ( $main && !( $row->ns_namespace_id % 2 == 0 ) ) {
				continue;
			}

			if ( $id ) {
				$arrayOut[$row->ns_namespace_id] = ( $readable ) ? str_replace( '_', ' ', $row->ns_namespace_name ) : $row->ns_namespace_name;
			} else {
				$arrayOut[] = ( $readable ) ? str_replace( '_', ' ', $row->ns_namespace_name ) : $row->ns_namespace_name;
			}
		}

		return $arrayOut;
	}

	public static function nextNamespaceID() {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$lastID = $dbr->selectRow(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => $wgDBname,
				'ns_namespace_id >= 3000'
			],
			__METHOD__,
			[
				'ORDER BY' => 'ns_namespace_id DESC'
			]
		);

		if ( $lastID ) {
			return $lastID->ns_namespace_id + 1;
		}

		return 3000;
	}
}
