<?php

namespace Miraheze\ManageWiki;

use DateTimeZone;
use MediaWiki\Config\Config;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\SelectQueryBuilder;

class Utils {

	public function __construct(
		private readonly Config $config,
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
	}

	public function checkSetup( string $module ): bool {
		return $this->config->get( ConfigNames::ModulesEnabled )[$module] ?? false;
	}

	public function getTimezoneList(): array {
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

	public function handleMatrix(
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

	public function namespaceID( string $dbname, string $namespace ): int {
		$dbr = $this->databaseUtils->getGlobalReplicaDB();
		$nsID = $namespace === '' ? false : $dbr->newSelectQueryBuilder()
			->select( 'ns_namespace_id' )
			->from( 'mw_namespaces' )
			->where( [
				'ns_dbname' => $dbname,
				'ns_namespace_id' => $namespace,
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $nsID === false ) {
			$lastID = $dbr->newSelectQueryBuilder()
				->select( 'ns_namespace_id' )
				->from( 'mw_namespaces' )
				->where( [
					'ns_dbname' => $dbname,
					$dbr->expr( 'ns_namespace_id', '>=', 3000 ),
				] )
				->orderBy( 'ns_namespace_id', SelectQueryBuilder::SORT_DESC )
				->caller( __METHOD__ )
				->fetchField();

			$nsID = $lastID !== false ? $lastID + 1 : 3000;
		}

		return $nsID;
	}
}
