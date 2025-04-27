<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use JobSpecification;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use RuntimeException;

class ManageWikiInstaller {

	public static function process(
		string $dbname,
		array $actions,
		bool $install
	): bool {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepResponse = [];

		foreach ( $actions as $action => $data ) {
			switch ( $action ) {
				case 'sql':
					$stepResponse['sql'] = self::sql( $dbname, $data );
					break;
				case 'permissions':
					$stepResponse['permissions'] = self::permissions( $dbname, $data, $install );
					break;
				case 'namespaces':
					$stepResponse['namespaces'] = self::namespaces( $dbname, $data, $install );
					break;
				case 'mwscript':
					$stepResponse['mwscript'] = self::mwscript( $dbname, $data );
					break;
				case 'settings':
					$stepResponse['settings'] = self::settings( $dbname, $data );
					break;
				default:
					return false;
			}
		}

		return !in_array( false, $stepResponse, true );
	}

	private static function sql( string $dbname, array $data ): bool {
		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$dbw = $databaseUtils->getRemoteWikiPrimaryDB( $dbname );

		foreach ( $data as $table => $sql ) {
			if ( !$dbw->tableExists( $table ) ) {
				try {
					$dbw->sourceFile( $sql );
				} catch ( Exception $e ) {
					$logger = LoggerFactory::getInstance( 'ManageWiki' );
					$logger->error( 'Caught exception trying to load {path} for {table} on {dbname}: {exception}', [
						'dbname' => $dbname,
						'exception' => $e,
						'path' => $sql,
						'table' => $table,
					] );

					return false;
				}
			}
		}

		return true;
	}

	private static function permissions(
		string $dbname,
		array $data,
		bool $install
	): bool {
		$mwPermissions = new ManageWikiPermissions( $dbname );
		$action = $install ? 'add' : 'remove';

		foreach ( $data as $group => $mod ) {
			$groupData = [
				'permissions' => [
					$action => $mod['permissions'] ?? [],
				],
				'addgroups' => [
					$action => $mod['addgroups'] ?? [],
				],
				'removegroups' => [
					$action => $mod['removegroups'] ?? [],
				],
			];

			$mwPermissions->modify( $group, $groupData );
		}

		$mwPermissions->commit();
		return true;
	}

	private static function namespaces(
		string $dbname,
		array $data,
		bool $install
	): bool {
		$mwNamespaces = new ManageWikiNamespaces( $dbname );
		foreach ( $data as $name => $i ) {
			if ( $install ) {
				$id = $i['id'];
				unset( $i['id'] );
				$i['name'] = $name;

				$mwNamespaces->modify( $id, $i, true );
				continue;
			}

			// We migrate to either NS_MAIN (0) or NS_TALK (1),
			// depending on if this is a talk namespace or not.
			$newNamespace = $i['id'] % 2;
			$mwNamespaces->remove( $i['id'], $newNamespace, true );
		}

		$mwNamespaces->commit();
		return true;
	}

	private static function mwscript( string $dbname, array $data ): bool {
		if ( Shell::isDisabled() ) {
			throw new RuntimeException( 'Shell is disabled.' );
		}

		foreach ( $data as $script => $options ) {
			$repeatWith = [];
			if ( isset( $options['repeat-with'] ) ) {
				$repeatWith = $options['repeat-with'];
				unset( $options['repeat-with'] );
			}

			$jobQueueGroupFactory = MediaWikiServices::getInstance()->getJobQueueGroupFactory();
			$jobQueueGroup = $jobQueueGroupFactory->makeJobQueueGroup();

			$jobQueueGroup->push(
				new JobSpecification(
					MWScriptJob::JOB_NAME,
					[
						'dbname' => $dbname,
						'script' => $script,
						'options' => $options,
					]
				)
			);

			if ( $repeatWith ) {
				$jobQueueGroup->push(
					new JobSpecification(
						MWScriptJob::JOB_NAME,
						[
							'dbname' => $dbname,
							'script' => $script,
							'options' => $repeatWith,
						]
					)
				);
			}
		}

		return true;
	}

	private static function settings( string $dbname, array $data ): bool {
		$mwSettings = new ManageWikiSettings( $dbname );
		$mwSettings->modify( $data );
		$mwSettings->commit();
		return true;
	}
}
