<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\SiteStats\SiteStats;

/**
 * Helper class for de-centralizing requirement checking
 */
class ManageWikiRequirements {

	/**
	 * Main class for evaluating whether requirements are met, and at what level
	 *
	 * @param array $actions Requirements that need to be met
	 * @param array $extList Enabled extensions on the wiki
	 * @return array List of requirements that failed
	 */
	public static function process( array $actions, array $extList ): array {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepResponse = [];

		foreach ( $actions as $action => $data ) {
			switch ( $action ) {
				case 'permissions':
					$stepResponse['permissions'] = self::permissions( $data );
					break;
				case 'extensions':
					$stepResponse['extensions'] = self::extensions( $data, $extList );
					break;
				case 'activeusers':
					$stepResponse['activeusers'] = self::activeUsers( $data );
					break;
				case 'articles':
					$stepResponse['articles'] = self::articles( $data );
					break;
				case 'pages':
					$stepResponse['pages'] = self::pages( $data );
					break;
				case 'images':
					$stepResponse['images'] = self::images( $data );
					break;
				case 'settings':
					$stepResponse['settings'] = self::settings( $data );
					break;
				case 'visibility':
					$stepResponse['visibility'] = self::visibility( $data );
					break;
				default:
					return [];
			}
		}

		return $stepResponse;
	}

	/**
	 * @param array $data Array of permissions needed
	 * @return array Missing permissions (empty array if all good)
	 */
	private static function permissions( array $data ): array {
		if ( PHP_SAPI === 'cli' ) {
			return [];
		}

		$missing = [];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		foreach ( $data as $perm ) {
			if ( !$permissionManager->userHasRight( RequestContext::getMain()->getUser(), $perm ) ) {
				$missing[] = $perm;
			}
		}

		return $missing;
	}

	/**
	 * @param array $data Array of extensions needed
	 * @param array $extList Extensions already enabled on the wiki
	 * @return array Missing extensions (empty array if all good)
	 */
	private static function extensions( array $data, array $extList ): array {
		$missing = [];

		foreach ( $data as $extension ) {
			if ( is_array( $extension ) ) {
				// OR logic: at least one must exist
				if ( !array_intersect( $extension, $extList ) ) {
					$missing[] = implode( ' | ', $extension );
				}
			} elseif ( !in_array( $extension, $extList, true ) ) {
				$missing[] = $extension;
			}
		}

		return $missing;
	}

	/**
	 * @param int $limit Cut off number
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function activeUsers( int $limit ): array {
		return SiteStats::activeUsers() <= $limit ? [] : [ "must have <= $limit active users" ];
	}

	/**
	 * @param int $limit Cut off number
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function articles( int $limit ): array {
		return SiteStats::articles() <= $limit ? [] : [ "must have <= $limit articles" ];
	}

	/**
	 * @param int $limit Cut off number
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function pages( int $limit ): array {
		return SiteStats::pages() <= $limit ? [] : [ "must have <= $limit pages" ];
	}

	/**
	 * @param int $limit Cut off number
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function images( int $limit ): array {
		return SiteStats::images() <= $limit ? [] : [ "must have <= $limit images" ];
	}

	/**
	 * @param array $data
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function settings( array $data ): array {
		$config = MediaWikiServices::getInstance()->getMainConfig();
		$database = $data['dbname'] ?? $config->get( MainConfigNames::DBname );
		$setting = $data['setting'];
		$value = $data['value'];

		$manageWikiSettings = new ManageWikiSettings( $database );
		$wikiValue = $manageWikiSettings->list( $setting );

		if ( $wikiValue !== null ) {
			if ( $wikiValue === $value || in_array( $value, (array)$wikiValue, true ) ) {
				return [];
			}
		}

		return [ 'setting mismatch' ];
	}

	/**
	 * @param array $data
	 * @return array Empty array if OK, array with reason if failed
	 */
	private static function visibility( array $data ): array {
		$missing = [];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$isPrivate = !$permissionManager->isEveryoneAllowed( 'read' );

		foreach ( $data as $key => $val ) {
			if ( $key === 'state' ) {
				if ( ( $val === 'private' && !$isPrivate ) || ( $val === 'public' && $isPrivate ) ) {
					$missing[] = "wrong state (expected $val)";
				}
			} elseif ( $key === 'permissions' ) {
				foreach ( (array) $val as $perm ) {
					if ( !$permissionManager->userHasRight( RequestContext::getMain()->getUser(), $perm ) ) {
						$missing[] = "missing permission $perm";
					}
				}
			}
		}

		return $missing;
	}
}
