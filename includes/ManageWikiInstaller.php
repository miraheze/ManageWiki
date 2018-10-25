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

		// should introduce pass/fail logic into here rather than trying to manage it upstream on execution
		return $stepresponse;

	}

	private static function sql( string $dbname, array $data ) {
		$dbw = wfGetDB( DB_MASTER, [], $dbname );

		foreach ( $data as $table => $sql ) {
			if ( $dbw->tableExists( $table ) ) {
				return true;
			} else {
				return $dbw->sourceFile( $sql );
			}
		}
	}

	// @TODO: Will handle copying of files + directory creations
	private static function files( string $dbname, array $data ) {
		return false;
	}

	// @TODO: Will handle creation of a permissions row entry/modification of an existing row (needs cleaning up MWP to work)
	private static function permissions( string $dbname, array $data ) {
		return false;
	}

	// @TODO: Will handle management of namesapces. Needs MWN to be done.
	private static function namespaces( string $dbname, array $data ) {
		return false;
	}
}
