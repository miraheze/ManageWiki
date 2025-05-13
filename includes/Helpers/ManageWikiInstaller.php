<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use JobSpecification;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use Miraheze\ManageWiki\ManageWikiServices;
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
		$databaseUtils = ManageWikiServices::wrap( MediaWikiServices::getInstance() )->getDatabaseUtils();
		$dbw = $databaseUtils->getRemoteWikiPrimaryDB( $dbname );

		foreach ( $data as $table => $sql ) {
			if ( !$dbw->tableExists( $table, __METHOD__ ) ) {
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
		$moduleFactory = ManageWikiServices::wrap( MediaWikiServices::getInstance() )->getModuleFactory();
		$mwPermissions = $moduleFactory->permissions( $dbname );
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
		$moduleFactory = ManageWikiServices::wrap( MediaWikiServices::getInstance() )->getModuleFactory();
		$mwNamespaces = $moduleFactory->namespaces( $dbname );
		foreach ( $data as $name => $i ) {
			if ( $install ) {
				$id = $i['id'];
				unset( $i['id'] );
				$i['name'] = $name;

				$mwNamespaces->modify( $id, $i, maintainPrefix: true );
				continue;
			}

			// We migrate to either NS_MAIN (0) or NS_TALK (1),
			// depending on if this is a talk namespace or not.
			$newNamespace = $i['id'] % 2;
			$mwNamespaces->remove( $i['id'], $newNamespace, maintainPrefix: true );
		}

		$mwNamespaces->commit();
		return true;
	}

	private static function mwscript( string $dbname, array $data ): bool {
		if ( Shell::isDisabled() ) {
			throw new RuntimeException( 'Shell is disabled.' );
		}

		$jobQueueGroupFactory = MediaWikiServices::getInstance()->getJobQueueGroupFactory();
		$jobQueueGroup = $jobQueueGroupFactory->makeJobQueueGroup();

		$jobQueueGroup->push(
			new JobSpecification(
				MWScriptJob::JOB_NAME,
				[
					'data' => $data,
					'dbname' => $dbname,
				]
			)
		);

		return true;
	}

	private static function settings( string $dbname, array $data ): bool {
		$moduleFactory = ManageWikiServices::wrap( MediaWikiServices::getInstance() )->getModuleFactory();
		$mwSettings = $moduleFactory->settings( $dbname );
		$mwSettings->modify( $data );
		$mwSettings->commit();
		return true;
	}
}
