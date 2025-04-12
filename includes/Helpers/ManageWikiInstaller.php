<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use RuntimeException;

class ManageWikiInstaller {

	public static function process(
		string $dbname,
		array $actions,
		bool $install = true
	): bool {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepresponse = [];

		foreach ( $actions as $action => $data ) {
			switch ( $action ) {
				case 'sql':
					$stepresponse['sql'] = self::sql( $dbname, $data );
					break;
				case 'files':
					$stepresponse['files'] = self::files( $dbname, $data );
					break;
				case 'permissions':
					$stepresponse['permissions'] = self::permissions( $dbname, $data, $install );
					break;
				case 'namespaces':
					$stepresponse['namespaces'] = self::namespaces( $dbname, $data, $install );
					break;
				case 'mwscript':
					$stepresponse['mwscript'] = self::mwscript( $dbname, $data );
					break;
				case 'settings':
					$stepresponse['settings'] = self::settings( $dbname, $data );
					break;
				default:
					return false;
			}
		}

		return !(bool)array_search( false, $stepresponse );
	}

	private static function sql( string $dbname, array $data ): bool {
		$dbw = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
			->getMainLB( $dbname )
			->getMaintenanceConnectionRef( DB_PRIMARY, [], $dbname );

		foreach ( $data as $table => $sql ) {
			if ( !$dbw->tableExists( $table ) ) {
				try {
					$dbw->sourceFile( $sql );
				} catch ( Exception $e ) {
					$logger = LoggerFactory::getInstance( 'ManageWiki' );
					$logger->error( 'Caught exception trying to load {path} for {table} on {db}: {exception}', [
						'path' => $sql,
						'table' => $table,
						'db' => $dbname,
						'exception' => $e,
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

			$mwNamespaces->remove( $i['id'], $i['id'] % 2, true );
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

			$params = [
				'dbname' => $dbname,
				'script' => $script,
				'options' => $options,
			];

			$mwJob = new MWScriptJob( Title::newMainPage(), $params );

			MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $mwJob );

			if ( $repeatWith ) {
				$params = [
					'dbname' => $dbname,
					'script' => $script,
					'options' => $repeatWith,
				];

				$mwJob = new MWScriptJob( Title::newMainPage(), $params );

				MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $mwJob );
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
