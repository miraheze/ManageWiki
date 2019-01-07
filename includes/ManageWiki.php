<?php

class ManageWiki {
	public static function checkSetup( string $module, bool $verbose = false, $out = false ) {
		global $wgManageWiki, $wgManageWikiCDBDirectory;

		// Checks ManageWiki module is enabled before doing anything
		// $verbose means output an error. Otherwise return true/false.

		if ( $wgManageWiki[$module] ) {
			if ( $module == 'cdb' ) {
				return ( $wgManageWikiCDBDirectory ) ? true : false;
			}

			return true;
		} else {
			if ( $verbose && $out ) {
				$out->addWikiMsg( 'managewiki-disabled', $module );
			}

			return false;
		}
	}

	public static function listModules( bool $public = true ) {
		global $wgManageWiki, $wgManageWikiBackendModules;

		$enabledModules = array_keys( $wgManageWiki, true );

		if ( $public ) {
			return array_diff( $enabledModules, $wgManageWikiBackendModules );
		}

		return $enabledModules;
	}

	public static function checkPermission( RemoteWiki $rm, User $user, bool $verbose = false, string $perm = "" ) {
		$maxPerm = ( (bool)$perm ) ? $perm : 'managewiki';

		if ( !$user->isAllowed( $maxPerm ) ) {
			if ( $verbose ) {
				throw new PermissionsError( $maxPerm );
			}

			return false;
		}

		// Early implementation of MWLocked feature - down as RemoteWiki but will be WikiManager
		//if ( $rm->isMWLocked() ) {
		//	if ( $verbose ) {
		//		return wfMessage( 'managewiki-mwlocked' )->plain();
		//	}

		//	return false;
		//}

		return true;
	}

	public static function getTimezoneList() {
		$identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

		$timeZoneList = [];

		if ( $identifiers !== false ) {
			foreach ( $identifiers as $identifier ) {
				$parts = explode( '/', $identifier, 2 );
				if ( count( $parts ) !== 2 && $parts[0] !== 'UTC' ) {
					continue;
				}

				$timeZoneList[$identifier] = $identifier;
			}
		}

		return $timeZoneList;
	}

	public static function handleMatrix( $conversion, $to ) {
		if ( $to == 'php' ) {
			// $to is php, therefore $conversion must be json
			$phpin = json_decode( $conversion, true );

			$phpout = [];

			foreach ( $phpin as $key => $value ) {
				$phpout[] = "$key-$value";
			}

			return $phpout;
		} elseif ( $to == 'phparray' ) {
			// $to is phparray therefore $conversion must be php as json will be already phparray'd
			$phparrayout = [];

			foreach ( $conversion as $phparray ) {
				$element = explode( '-', $phparray );
				$phparrayout[$element[0]] = $element[1];
			}

			return $phparrayout;
		} elseif ( $to == 'json' ) {
			// $to is json, therefore $conversion must be php
			return json_encode( $conversion );
		} else {
			return null;
		}
	}

	public static function availableGroups( $wiki = null ) {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbName = $wiki ?? $wgDBname;

		$dbr = wfGetDB( DB_SLAVE, [], $wgCreateWikiDatabase );

		$res = $dbr->select(
			'mw_permissions',
			'perm_group',
			[
				'perm_dbname' => $dbName
			],
			__METHOD__
		);

		$groups = [];

		foreach( $res as $row ) {
			$groups[] = $row->perm_group;
		}

		return $groups;
	}

	public static function groupPermissions( $group, $wiki = null ) {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbName = $wiki ?? $wgDBname;

		$dbr = wfGetDB( DB_SLAVE, [], $wgCreateWikiDatabase );

		$res = $dbr->selectRow(
			'mw_permissions',
			[
				'perm_permissions',
				'perm_addgroups',
				'perm_removegroups'
			],
			[
				'perm_dbname' => $dbName,
				'perm_group' => $group
			],
			__METHOD__
		);

		$perms = [];

		if ( !$res ) {
			$perms = [
				'permissions' => [],
				'addgroups' => [],
				'removegroups' => []
			];
		} else {
			$perms['permissions'] = json_decode( $res->perm_permissions, true );
			$perms['addgroups'] = json_decode( $res->perm_addgroups, true );
			$perms['removegroups'] = json_decode( $res->perm_removegroups, true );
		}

		return (array)$perms;
	}

	public static function defaultGroups() {
		global $wgCreateWikiDatabase;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$res = $dbr->select(
			'mw_permissions',
			'perm_group',
			[ 'perm_dbname' => 'default' ]
		);

		$groups = [];

		foreach( $res as $row ) {
			$groups[] = $row->perm_group;
		}

		return $groups;
	}

	public static function defaultGroupPermissions( $group ) {
		global $wgCreateWikiDatabase;

		$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

		$res = $dbr->selectRow(
			'mw_permissions',
			[ 'perm_permissions', 'perm_addgroups', 'perm_removegroups' ],
			[ 'perm_dbname' => 'default', 'perm_group' => $group ]
		);

		$perms = [];

		$perms['permissions'] = json_decode( $res->perm_permissions, true );
		$perms['addgroups'] = json_decode( $res->perm_addgroups, true );
		$perms['removegroups'] = json_decode( $res->perm_removegroups, true );

		return (array)$perms;
	}

	public static function modifyPermissions( $group, $addp = [], $removep = [], $addag = [], $removeag = [], $addrg = [], $removerg = [], $wiki = null ) {
		global $wgCreateWikiDatabase, $wgDBname;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$wiki = $wiki ?? $wgDBname;

		$existing = in_array( $group, ManageWiki::availableGroups() );

		if ( $existing ) {
			$xArray = (array)ManageWiki::groupPermissions( $group );
			$perms = array_merge( (array)$addp, array_diff( $xArray['permissions'], (array)$removep ) );
			$addgroups = array_merge( (array)$addag, array_diff( $xArray['addgroups'], (array)$removeag ) );
			$removegroups = array_merge( (array)$addrg, array_diff( $xArray['removegroups'], $removerg ) );
		} else {
			// if no group exists, you can't remove anything
			$perms = (array)$addp;
			$addgroups = (array)$addag;
			$removegroups = (array)$addrg;
		}

		$row = [
			'perm_dbname' => $wiki,
			'perm_group' => $group,
			'perm_permissions' => json_encode( $perms ),
			'perm_addgroups' => json_encode( $addgroups ),
			'perm_removegroups' => json_encode( $removegroups )
		];

		if ( $existing ) {
			$dbw->update(
				'mw_permissions',
				$row,
				[
					'perm_dbname' => $wiki,
					'perm_group' => $group
				],
				__METHOD__
			);
		} else {
			$dbw->insert(
				'mw_permissions',
				$row,
				__METHOD__
			);
		}
	}
}
