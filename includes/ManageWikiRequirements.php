<?php

class ManageWikiRequirements {
	public static function process( string $dbname, array $actions, IContextSource $context ) {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepresponse = [];

		foreach ( $actions as $action => $data ) {
			if ( $action == 'permissions' ) {
				$stepresponse['permissions'] = self::permissions( $data, $context );
			} elseif ( $action == 'extensions' ) {
				$stepresponse['extensions'] = self::extensions( $dbname, $data );
			} else {
				return false;
			}
		}

		$proceed = ( (bool)array_search( false, $stepresponse ) ) ? false : true;

		return $proceed;

	}

	private static function permissions( array $data, IContextSource $context ) {
		foreach ( $data as $perm ) {
			if ( !$context->getUser()->isAllowed( $perm ) ) {
				return false;
			}
		}

		return true;
	}

	private static function extensions( string $dbname, array $data ) {
		$remoteWiki = RemoteWiki::newFromName( $dbname );

		foreach ( $data as $extension ) {
			if ( !$remoteWiki->hasExtension( $extension ) ) {
				return false;
			}
		}

		return true;
	}
}
