<?php

namespace Miraheze\ManageWiki;

use DateTimeZone;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

class ManageWiki {

	public static function checkSetup( string $module, bool $verbose = false, $out = null ) {
		// Checks ManageWiki module is enabled before doing anything
		// $verbose means output an error. Otherwise return true/false.
		if ( !MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' )->get( 'ManageWiki' )[$module] ) {
			if ( $verbose && $out ) {
				$out->addWikiMsg( 'managewiki-disabled', $module );
			}

			return false;
		}

		return true;
	}

	public static function listModules() {
		return array_keys( MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' )->get( 'ManageWiki' ), true );
	}

	public static function checkPermission( RemoteWikiFactory $remoteWiki, User $user, string $perm ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $remoteWiki->isLocked() && !$permissionManager->userHasRight( $user, 'managewiki-restricted' ) ) {
			return false;
		}

		if ( !$permissionManager->userHasRight( $user, 'managewiki-' . $perm ) ) {
			return false;
		}

		return true;
	}

	public static function getTimezoneList() {
		$identifiers = DateTimeZone::listIdentifiers( DateTimeZone::ALL );
		$timeZoneList = [];

		foreach ( $identifiers as $identifier ) {
			$parts = explode( '/', $identifier, 2 );
			if ( count( $parts ) !== 2 && $parts[0] !== 'UTC' ) {
				continue;
			}

			$timeZoneList[$identifier] = $identifier;
		}

		return $timeZoneList;
	}

	public static function handleMatrix( $conversion, $to ) {
		if ( $to === 'php' ) {
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

	public static function namespaceID( string $namespace ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( 'virtual-createwiki' );

		$nsID = ( $namespace == '' ) ? false : $dbr->selectRow(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => $config->get( 'DBname' ),
				'ns_namespace_id' => $namespace
			],
			__METHOD__
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
