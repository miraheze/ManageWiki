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
			if ( is_array( $extension ) ) {
				$count = 0;
				foreach ( $extension as $or ) {
					if ( in_array( $or, $extensionList ) ) {
						$count++;
					}
				}

				if ( !$count ) {
					return false;
				}
			} elseif ( !in_array( $extension, $extensionList ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param int $lim Cut off number
	 * @return bool Whether limit is exceeded or not
	 */
	private static function articles( int $lim ) {
		return (bool)( SiteStats::articles() <= $lim );
	}

	/**
	 * @param int $lim Cut off number
	 * @return bool Whether limit is exceeded or not
	 */
	private static function pages( int $lim ) {
		return (bool)( SiteStats::pages() <= $lim );
	}
}
