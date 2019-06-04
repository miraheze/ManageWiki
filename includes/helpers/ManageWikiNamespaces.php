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

	public static function namespaceID( $namespace ) {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$exists = $dbr->selectRow(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => $wgDBname,
				'ns_namespace_id' => $namespace
			]
		);

		return ( $exists ) ? (int)$exists->ns_namespace_id : static::nextNamespaceID();
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

	public static function defaultCanonicalNamespaces() {
		global $wgCreateWikiDatabase;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$res = $dbr->select(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => 'default'
			]
		);

		$namespaces = [];

		foreach( $res as $row ) {
			$namespaces[] = $row->ns_namespace_id;
		}

		return $namespaces;
	}

	public static function defaultNamespaces( $namespace ) {
		global $wgCreateWikiDatabase;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$row = $dbr->selectRow(
			'mw_namespaces',
			[
				'ns_namespace_name',
				'ns_searchable',
				'ns_subpages',
				'ns_content',
				'ns_content_model',
				'ns_protection',
				'ns_aliases',
				'ns_core',
				'ns_additional'
			],
			[
				'ns_dbname' => 'default',
				'ns_namespace_id' => $namespace
			]
		);

		$ns = [];

		$ns['ns_namespace_name'] = $row->ns_namespace_name;
		$ns['ns_searchable'] = $row->ns_searchable;
		$ns['ns_subpages'] = $row->ns_subpages;
		$ns['ns_subpages'] = $row->ns_subpages;
		$ns['ns_content'] = $row->ns_content;
		$ns['ns_content_model'] = $row->ns_content_model;
		$ns['ns_protection'] = $row->ns_protection;
		$ns['ns_aliases'] = $row->ns_aliases;
		$ns['ns_core'] = $row->ns_core;
		$ns['ns_additional'] = $row->ns_additional;

		return (array)$ns;
	}

	public static function modifyNamespace( int $id, string $name, int $search, int $subpages, string $protection, int $content, string $model, int $core, array $aliases, array $additional, string $wiki = null ) {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbName = $wiki ?? $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$row = [
			'ns_dbname' => $dbName,
			'ns_namespace_id' => $id,
			'ns_namespace_name' => $name,
			'ns_searchable' => $search,
			'ns_subpages' => $subpages,
			'ns_protection' => $protection,
			'ns_content' => $content,
			'ns_content_model' => $model,
			'ns_core' => $core,
			'ns_aliases' => json_encode( $aliases ),
			'ns_additional' => json_encode( $additional )
		];

		$check = $dbw->selectRow(
			'mw_namespaces',
			'*',
			[
				'ns_dbname' => $dbName,
				'ns_namespace_id' => $id
			]
		);

		if ( $check ) {
			$dbw->update(
				'mw_namespaces',
				$row,
				[
					'ns_dbname' => $dbName,
					'ns_namespace_id' => $id
				]
			);
		} else {
			$dbw->insert(
				'mw_namespaces',
				$row
			);
		}
	}
}
