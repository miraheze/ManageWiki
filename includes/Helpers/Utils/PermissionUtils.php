<?php

namespace Miraheze\ManageWiki\Helpers\Utils;

use function array_filter;
use function array_merge;
use function array_values;
use function is_numeric;
use const ARRAY_FILTER_USE_KEY;

class PermissionUtils {

	/**
	 * @param array|null $perms The permissions array, which is either a numeric array consisting of permission names
	 * or an associative array with keys 'enable' and/or 'disable' mapping to a numeric array of permissions required
	 * on enable/disable.
	 * @param bool $enable Whether the check is performed against enabling the extension.
	 *
	 * @return list<string> Numeric array of permission names required.
	 */
	public static function processPermissionRequirements( ?array $perms, bool $enable ): array {
		$perms ??= [];
		if ( $enable ) {
			$perms = array_merge( $perms, $perms['enable'] ?? [] );
		} else {
			$perms = array_merge( $perms, $perms['disable'] ?? [] );
		}
		// Drop non-numeric keys (e.g. 'enable')
		$filtered = array_filter( $perms, static fn ( $k ) => is_numeric( $k ), ARRAY_FILTER_USE_KEY );
		// Make phan happy about the return type
		return array_values( $filtered );
	}
}
