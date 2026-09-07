<?php

namespace Miraheze\ManageWiki\Traits;

use function array_filter;
use function array_merge;
use function array_values;
use function is_numeric;
use const ARRAY_FILTER_USE_KEY;

trait PermissionsHelperTrait {

	/**
	 * @param array $perms The permissions array, which is either a numeric array consisting of permission names
	 * or an associative array with keys 'enable' and/or 'disable' mapping to a numeric array of permissions required
	 * on enable/disable.
	 * @param bool $enable Whether the check is performed against enabling the extension.
	 *
	 * @return list<string> Numeric array of permission names required.
	 */
	private function processPermissionRequirements( array $perms, bool $enable ): array {
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

	private function resolvePermissions( array $requires, bool $enable ): array {
		$perms = $this->processPermissionRequirements( $requires['permissions'] ?? [], $enable );
		// If permission requirements are one-way, then we may end up with no permission requirements.
		// In this case we need to unset the attribute so that permission checks are skipped entirely.
		if ( $perms === [] ) {
			unset( $requires['permissions'] );
			return $requires;
		}

		$requires['permissions'] = $perms;
		return $requires;
	}
}
