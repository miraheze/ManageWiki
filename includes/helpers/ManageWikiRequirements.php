<?php

use MediaWiki\MediaWikiServices;

/**
 * Helper class for de-centralising requirement checking
 */
class ManageWikiRequirements {
	/**
	 * Master class for evaluating whether requirements are met, and at what level
	 *
	 * @param array $actions Requirements that need to be met
	 * @param array $extensionList Enabled extensions on the wiki
	 * @param bool $ignorePerms Whether a permissions check should be carried out
	 * @return bool Whether the extension can be enabled
	 */
	public static function process( array $actions, array $extensionList = [], bool $ignorePerms = false ) {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepResponse = [];

		foreach ( $actions as $action => $data ) {
			if ( $action == 'permissions' ) {
				$stepResponse['permissions'] = ( $ignorePerms ) ? true : self::permissions( $data );
			} elseif ( $action == 'extensions' ) {
				$stepResponse['extensions'] = self::extensions( $data, $extensionList );
			} elseif ( $action == 'articles' ) {
				$stepResponse['articles'] = self::articles( $data );
			} elseif ( $action == 'pages' ) {
				$stepResponse['pages'] = self::pages( $data );
			} else {
				return false;
			}
		}

		return !(bool)array_search( false, $stepResponse );
	}

	/**
	 * @param array $data Array of permissions needed
	 * @return bool Whether permissions requirements are met
	 */
	private static function permissions( array $data ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		foreach ( $data as $perm ) {
			if ( !$permissionManager->userHasRight( RequestContext::getMain()->getUser(), $perm ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array $data Array of extensions needed
	 * @param array $extensionList Extensions already enabled on the wiki
	 * @return bool Whether extension requirements are met
	 */
	private static function extensions( array $data, array $extensionList ) {
		foreach ( $data as $extension ) {
			if ( isset( $extensionList[$extension] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $lim String-based comparison for limit
	 * @return bool Whether limit is exceeded or not
	 */
	private static function articles( string $lim ) {
		return (bool)eval( "return " . SiteStats::articles() . " $lim;" );
	}

	/**
	 * @param string $lim String-based comparison for limit
	 * @return bool Whether limit is exceeded or not
	 */
	private static function pages( string $lim ) {
		return (bool)eval( "return " . SiteStats::pages() . " $lim;" );
	}
}
