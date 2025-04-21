<?php

namespace Miraheze\ManageWiki;

use DateTimeZone;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\SelectQueryBuilder;

class ManageWiki {

	public static function checkSetup( string $module ): bool {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		return $config->get( ConfigNames::ModulesEnabled )[$module] ?? false;
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

	public static function buildDisableIf( array $exts, string $conflict ): array {
		$conditions = [];

		foreach ( $exts as $entry ) {
			if ( is_array( $entry ) ) {
				// OR logic for this group
				$orConditions = [];
				foreach ( $entry as $ext ) {
					$orConditions[] = [ '!==', "ext-$ext", '1' ];
				}

				$conditions[] = count( $orConditions ) === 1 ?
					$orConditions[0] :
					array_merge( [ 'AND' ], $orConditions );
			} else {
				// Simple AND logic
				$conditions[] = [ '!==', "ext-$entry", '1' ];
			}
		}

		// Initial OR of required conditions
		$finalCondition = count( $conditions ) === 1
			? $conditions[0]
			: ( count( $conditions ) > 1 ? array_merge( [ 'OR' ], $conditions ) : null );

		if ( $conflict ) {
			$conflictCondition = [ '===', "ext-$conflict", '1' ];

			if ( $finalCondition ) {
				$finalCondition = [ 'OR', $finalCondition, $conflictCondition ];
			} else {
				$finalCondition = $conflictCondition;
			}
		}

		return $finalCondition ?? [];
	}

	public static function namespaceID( string $dbname, string $namespace ): int {
		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$dbr = $databaseUtils->getGlobalReplicaDB();

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
