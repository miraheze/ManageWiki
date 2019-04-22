<?php

class ManageWikiCDB {
	public static function latest( string $module ) {
		global $wgManageWikiCDBDirectory, $wgDBname;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			// all the cache stuff
			$cache = ObjectCache::getLocalClusterInstance();
			$key = $cache->makeKey( 'ManageWiki', $module );
			$cacheVersion = $cache->get( $key );

			// all the CBD stuff
			$cdbrVersion = ManageWikiCDB::get( $module, 'version' );

			if ( !(bool)$cacheVersion || (int)$cdbrVersion != (int)$cacheVersion ) {
				return false;
			}
		}

		return true;
	}

	public static function get( string $module, $section ) {
		global $wgManageWikiCDBDirectory, $wgDBname;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			$cdbfile = "$wgManageWikiCDBDirectory/$wgDBname-$module.cdb";

			if ( file_exists( $cdbfile ) ) {
				$cdbr = \Cdb\Reader::open( $cdbfile );

				if ( is_array( $section ) ) {
					$returnArray = [];

					foreach ( $section as $key ) {
						$returnArray[$key] = $cdbr->get( $key );
					}

					return $returnArray;
				}

				return $cdbr->get( $section );
			}
		}

		return null;
	}

	public static function upsert( string $module ) {
		// upsert is a mashed term of "update" and "insert"
		// function should update a CDB or ensure one exists.
		global $wgManageWikiCDBDirectory, $wgCreateWikiDatabase, $wgDBname;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			$cdbFile = "$wgManageWikiCDBDirectory/$wgDBname-$module.cdb";

			$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

			if ( $module == 'permissions' ) {
				$dbTable = 'mw_permissions';
				$dbSelector = 'perm_dbname';
			} elseif ( $module == 'namespaces' ) {
				$dbTable = 'mw_namespaces';
				$dbSelector = 'ns_dbname';
			} else {
				return 'Need to add a fatal';
			}

			$moduleRes = $dbr->select(
				$dbTable,
				'*',
				[
					$dbSelector => $wgDBname
				],
				__METHOD__
			);

			// This will be an array that is pushed to CDB.
			// It will contain everything and is very important!
			$cacheArray = [];

			if ( $module == 'permissions' ) {
				foreach ( $moduleRes as $row ) {
					$group = $row->perm_group;
					$cacheArray['wgGroupPermissions'][$group] = json_decode( $row->perm_permissions, true );
					$cacheArray['wgAddGroups'][$group] = json_decode( $row->perm_addgroups, true );
					$cacheArray['wgRemoveGroups'][$group] = json_decode( $row->perm_removegroups, true );
					$cacheArray['wgGroupsAddToSelf'][$group] = json_decode( $row->perm_addgroupstoself, true );
					$cacheArray['wgGroupsRemoveFromSelf'][$group] = json_decode( $row->perm_removegroupsfromself, true );
					$cacheArray['availabeGroups'][] = $group;
				}
			} elseif ( $module == 'namespaces' ) {
				foreach ( $moduleRes as $row ) {
					$nsID = (int)$row->ns_namespace_id;

					if ( $row->ns_content ) {
						$cacheArray['wgContentNamespaces'][] = $nsID;
					}

					if ( !$row->ns_core ) {
						$cacheArray['wgExtraNamespaces'][$nsID] = $row->ns_namespace_name;
					}

					if ( $row->ns_protection ) {
						$cacheArray['wgNamespaceProtection'][$nsID] = $row->ns_protection;
					}

					if ( $row->ns_searchable ) {
						$cacheArray['wgNamespacesToBeSearchedDefault'][$nsID] = true;
					}

					if ( $row->ns_subpages ) {
						$cacheArray['wgNamespacesWithSubpages'][$nsID] = true;
					}

					foreach ( (array)json_decode( $row->ns_aliases, true ) as $alias ) {
						if ( !empty( $alias ) ) {
							$cacheArray['wgNamespaceAliases'][$alias] = $nsID;
						}
					}

					if ( $row->ns_core ) {
						$cacheArray['wgManageWikiNamespacesCore'][] = $nsID;
					}
				}
			} else {
				// nothing
			}

			// json-ify stuff
			foreach( $cacheArray as $key => $val ) {
				$valObj = ( is_array( $val ) ) ? (object) $val : $val;
				$cacheArray[$key] = json_encode( $valObj );
			}

			// Let's grab the cache version
			$cache = ObjectCache::getLocalClusterInstance();
			$key = $cache->makeKey( 'ManageWiki', $module );
			$cacheVersion = $cache->get( $key );

			// CDB version
			if ( file_exists( $cdbFile ) ) {
				$cdbr = \Cdb\Reader::open( $cdbFile );
			}

			$cdbVersion = ( isset( $cdbr ) ) ? $cdbr->get( 'version' ) : null;

			// If we're running an out of date version, let's do nothing.
			// If we're ruinning "the latest", let's increase it.
			// If we don't have a key... let's make one!
			if ( !$cacheVersion ) {
				$cacheVersion = (int)$cache->set( $key, 1, rand( 84600, 88200 ) );
			} elseif ( $cacheVersion == $cdbVersion) {
				$cacheVersion = (int)$cache->incr( $key );
			}

			// Now we've added our end key to the array, let's push it
			$cacheArray['version'] = $cacheVersion;

			$cdbw = \Cdb\Writer::open( $cdbFile );

			foreach ( $cacheArray as $key => $value ) {
				$cdbw->set( $key, $value );
			}

			$cdbw->close();

			return true;
		}

		return false;
	}

	public static function delete( string $module ) {
		global $wgManageWikiCDBDirectory, $wgDBname;

		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			$cache = ObjectCache::getLocalClusterInstance();
			$key = $cache->makeKey( 'ManageWiki', $module );
			$cache->delete( $key );

			return unlink( "$wgManageWikiCDBDirectory/$wgDBname-$module.cdb" );
		}
	}

	public static function changes( string $module ) {
		if ( ManageWiki::checkSetup( 'cdb' ) ) {
			$cache = ObjectCache::getLocalClusterInstance();
			$key = $cache->makeKey( 'ManageWiki', $module );
			$cache->incr( $key );
		}
	}

}
