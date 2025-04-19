<?php

namespace Miraheze\ManageWiki;

use DateTimeZone;
use MediaWiki\MediaWikiServices;

class ManageWiki {

	public static function checkSetup( string $module ): bool {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		return $config->get( ConfigNames::ManageWiki )[$module] ?? false;
	}

	public static function getTimezoneList(): array {
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

	public static function handleMatrix(
		array|string $conversion,
		string $to
	): array|string|null {
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
		} elseif ( $to === 'phparray' ) {
			// $to is phparray therefore $conversion must be php as json will be already phparray'd
			$phparrayout = [];

			foreach ( (array)$conversion as $phparray ) {
				$element = explode( '-', $phparray, 2 );
				$phparrayout[$element[0]][] = $element[1];
			}

			return $phparrayout;
		} elseif ( $to === 'json' ) {
			// $to is json, therefore $conversion must be php
			return json_encode( $conversion ) ?: null;
		}

		return null;
	}

	public static function namespaceID( string $dbname, string $namespace ): int {
		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$dbr = $databaseUtils->getGlobalReplicaDB();

		$nsID = $namespace === '' ? false : $dbr->selectRow(
			'mw_namespaces',
			'ns_namespace_id',
			[
				'ns_dbname' => $dbname,
				'ns_namespace_id' => $namespace,
			],
			__METHOD__
		)->ns_namespace_id;

		if ( is_bool( $nsID ) ) {
			$lastID = $dbr->selectRow(
				'mw_namespaces',
				'ns_namespace_id',
				[
					'ns_dbname' => $dbname,
					'ns_namespace_id >= 3000',
				],
				__METHOD__,
				[
					'ORDER BY' => 'ns_namespace_id DESC',
				]
			);

			$nsID = $lastID ? $lastID->ns_namespace_id + 1 : 3000;
		}

		return $nsID;
	}
}
