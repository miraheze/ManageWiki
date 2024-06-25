<?php

namespace Miraheze\ManageWiki;

use DateTimeZone;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\RemoteWiki;
use User;

class ManageWiki {

    /**
     * @param string $module the module that we're asking for (core, extensions etc)
     * @param bool $verbose should output an error?
     * @param $out ref to Output.php
     * @return bool
     */
	public static function checkSetup( string $module, bool $verbose = false, $out = null ): bool {
		// Checks ManageWiki module is enabled before doing anything
		// $verbose means output an error. Otherwise return true/false.
		if ( !MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'ManageWiki' )[$module] ) {
			if ( $verbose && $out ) {
				$out->addWikiMsg( 'managewiki-disabled', $module );
			}

			return false;
		}

		return true;
	}

    /**
     * Get a list of all the modules that are enabled
     * @return int[]|string[]
     */
	public static function listModules() {
		return array_keys( MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'ManageWiki' ), true );
	}

    /**
     * Check if the user in question has access to the specific part of ManageWiki they're asking for
     * @param RemoteWiki $rm
     * @param User $user the user in question
     * @param string $perm
     * @return bool
     */
	public static function checkPermission( RemoteWiki $rm, User $user, string $perm ): bool {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $rm->isLocked() && !$permissionManager->userHasRight( $user, 'managewiki-restricted' ) ) {
			return false;
		}

		if ( !$permissionManager->userHasRight( $user, 'managewiki-' . $perm ) ) {
			return false;
		}

		return true;
	}

    /**
     * Get the list of timezones
     * @return array
     */
	public static function getTimezoneList(): array {
		$identifiers = DateTimeZone::listIdentifiers( DateTimeZone::ALL );

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

    /**
     * Handle converting between a phparray and json for use in OOUI CheckMatrix
     * @param $conversion
     * @param $to
     * @return array|false|string|null
     */
	public static function handleMatrix( $conversion, $to ) {
		if ( $to == 'php' ) {
			// $to is php, therefore $conversion must be json
			$phpin = json_decode( $conversion, true );

			$phpout = [];

			foreach ( $phpin as $key => $value ) {
				// We may have an array, may not - let's make it one
				foreach ( (array)$value as $val ) {
					$phpout[] = "$key-$val";
				}
			}

			return $phpout;
		} elseif ( $to == 'phparray' ) {
			// $to is phparray therefore $conversion must be php as json will be already phparray'd
			$phparrayout = [];

			foreach ( (array)$conversion as $phparray ) {
				$element = explode( '-', $phparray, 2 );
				$phparrayout[$element[0]][] = $element[1];
			}

			return $phparrayout;
		} elseif ( $to == 'json' ) {
			// $to is json, therefore $conversion must be php
			return json_encode( $conversion );
		}

		return null;
	}

    /**
     * @param string $namespace
     * @return false|int
     */
	public static function namespaceID( string $namespace ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $config->get( 'CreateWikiDatabase' ) )
			->getMaintenanceConnectionRef( DB_REPLICA, [], $config->get( 'CreateWikiDatabase' ) );

		$nsID = ( $namespace == '' ) ? false : $dbr->selectRow(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => $config->get( 'DBname' ),
				'ns_namespace_id' => $namespace
			]
		)->ns_namespace_id;

		if ( is_bool( $nsID ) ) {
			$lastID = $dbr->selectRow(
				'mw_namespaces',
				'ns_namespace_id',
				[
					'ns_dbname' => $config->get( 'DBname' ),
					'ns_namespace_id >= 3000'
				],
				__METHOD__,
				[
					'ORDER BY' => 'ns_namespace_id DESC'
				]
			);

			$nsID = ( $lastID ) ? $lastID->ns_namespace_id + 1 : 3000;
		}

		return $nsID;
	}
}
