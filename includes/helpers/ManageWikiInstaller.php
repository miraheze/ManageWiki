<?php

class ManageWikiInstaller {
	public static function process( string $dbname, array $actions ) {
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
			} elseif( $action == 'mwscript' ) {
				$stepresponse['mwscript'] = self::mwscript( $dbname, $data );
			} else {
				return false;
			}
		}

		return !(bool)array_search( false, $stepresponse );

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
					if ( !is_dir( $baseloc . $location ) && !mkdir( $baseloc . $location ) ) {
						return false;
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
		$mwPermissions = new ManageWikiPermissions( $dbname );

		foreach ( $data as $group => $mod ) {
			$groupData = [
				'permissions' => [
					'add' => $mod['permissions'] ?? []
				],
				'addgroups' => [
					'add' => $mod['addgroups'] ?? []
				],
				'removegroups' => [
					'add' => $mod['removegroups'] ?? []
				]
			];

			$mwPermissions->modify( $group, $groupData );
		}

		$mwPermissions->commit();

		return true;
	}

	private static function namespaces( string $dbname, array $data ) {
		$mwNamespaces = new ManageWikiNamespaces( $dbname );
		foreach ( $data as $name => $i ) {
			$id = $i['id'];
			unset( $i['id'] );
			$i['name'] = $name;

			$mwNamespaces->modify( $id, $i );
		}

		$mwNamespaces->commit();

		return true;
	}

	private static function mwscript( string $dbname, array $data ) {
		if ( Shell::isDisabled() ) {
			throw new MWException( 'Shell is disabled.' );
		}

		foreach ( $data as $script => $options ) {
			$params = [
				'script' => $script,
				'options' => $options
			];

			$mwJob = new MWScriptJob( $dbname, $params );

			JobQueueGroup::singleton()->push( $mwJob );
		}
	}
}
