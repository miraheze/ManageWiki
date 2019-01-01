<?php

class ManageWikiInstaller {
	public static function process( string $dbname, string $type, array $actions ) {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepresponse = [];

		foreach ( $actions as $action => $data ) {
			if ( $action == 'sql' ) {
				$stepresponse['sql'] = self::sql( $dbname, $data );
			} elseif ( $action == 'files' ) {
				$stepresponse['files'] = self::files( $dbname, $data );
			} elseif ( $action == 'permissions' ) {
				$stepresponse['permissions'] = self::permissions( $dbname, $data );
			} elseif ( $action == 'namespaces' ) {
				$stepresponse['namespaces'] = self::namespaces( $dbname, $data );
			} else {
				return false;
			}
		}

		$proceed = ( (bool)array_search( false, $stepresponse ) ) ? false : true;

		return $proceed;

	}

	private static function sql( string $dbname, array $data ) {
		$dbw = wfGetDB( DB_MASTER, [], $dbname );

		foreach ( $data as $table => $sql ) {
			if ( !$dbw->tableExists( $table ) ) {
				try {
					$dbw->sourceFile( $sql );
				} catch ( Exception $e ) {
					return false;
				}
			}
		}

		return true;
	}

	private static function files( string $dbname, array $data ) {
		global $wgUploadDirectory;

		$baseloc = $wgUploadDirectory . $dbname;

		foreach ( $data as $location => $source ) {
			if ( substr( $location, -1 ) == '/' ) {
				if ( $source === true ) {
					if ( !is_dir( $baseloc . $location ) ) {
						if ( !mkdir( $baseloc . $location ) ) {
							return false;
						}
					}
				} else {
					$files = array_diff( scandir( $source, [ '.', '..' ] ) );

					foreach ( $files as $file ) {
						if ( !copy( $source . $file, $baseloc . $location . $file ) ) {
							return false;
						}
					}
				}
			} else {
				if ( !copy( $source, $baseloc . $location ) ) {
					return false;
				}
			}
		}

		return true;
	}

	private static function permissions( string $dbname, array $data ) {
		foreach ( $data as $group => $mod ) {
			if ( !isset( $mod['permissions'] ) ) {
				$mod['permissions'] = [];
			}
			if ( !isset( $mod['addgroups'] ) ) {
				$mod['addgroups'] = [];
			}
			if ( !isset( $mod['removegroups'] ) ) {
				$mod['removegroups'] = [];
			}
			ManageWiki::modifyPermissions( $group, $addp = $mod['permissions'], $addag = $mod['addgroups'], $addrg = $mod['removegroups' ], $wiki = $dbname );
		}

		return true;
	}

	// @TODO: Will handle management of namesapces. Needs MWN to be done.
	private static function namespaces( string $dbname, array $data ) {
		return false;
	}
}
