<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use JobSpecification;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use RuntimeException;

class ManageWikiInstaller {

	public static function process(
		string $dbname,
		array $actions,
		bool $install = true
	): bool {
		foreach ( $actions as $action => $data ) {
			$result = match ( $action ) {
				'sql' => self::sql( $dbname, $data ),
				'files' => self::files( $dbname, $data ),
				'permissions'=> self::permissions( $dbname, $data, $install ),
				'namespaces' => self::namespaces( $dbname, $data, $install ),
				'mwscript' => self::mwscript( $dbname, $data ),
				'settings' => self::settings( $dbname, $data ),
				default => false,
			};

			if ( $result === false ) {
				return false;
			}
		}

		return true;
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

	private static function files( string $dbname, array $data ): bool {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$baseloc = $config->get( MainConfigNames::UploadDirectory ) . $dbname;

		foreach ( $data as $location => $source ) {
			if ( str_ends_with( $location, '/' ) ) {
				if ( $source === true ) {
					if ( !is_dir( $baseloc . $location ) && !mkdir( $baseloc . $location ) ) {
						return false;
					}
				} else {
					$files = array_diff( scandir( $source ), [ '.', '..' ] );

					foreach ( $files as $file ) {
						if ( !copy( $source . $file, $baseloc . $location . $file ) ) {
							return false;
						}
					}
				}
			} else {
				if ( !copy( $source, $baseloc . $location ) ) {
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
