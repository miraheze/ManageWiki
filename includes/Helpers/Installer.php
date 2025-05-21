<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use JobSpecification;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Shell\Shell;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use Psr\Log\LoggerInterface;
use ShellDisabledError;
use Wikimedia\Rdbms\ILBFactory;

class Installer {

	public function __construct(
		private readonly ILBFactory $dbLoadBalancerFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger,
		private readonly ModuleFactory $moduleFactory,
		private readonly string $dbname
	) {
	}

	public function execute( array $actions, bool $install ): bool {
		// Produces an array of steps and results (so we can fail what we can't do but apply what works)
		$stepResponse = [];

		foreach ( $actions as $action => $data ) {
			switch ( $action ) {
				case 'sql':
					$stepResponse['sql'] = $this->sql( $data );
					break;
				case 'permissions':
					$stepResponse['permissions'] = $this->permissions( $data, $install );
					break;
				case 'namespaces':
					$stepResponse['namespaces'] = $this->namespaces( $data, $install );
					break;
				case 'mwscript':
					$stepResponse['mwscript'] = $this->mwscript( $data );
					break;
				case 'settings':
					$stepResponse['settings'] = $this->settings( $data );
					break;
				default:
					return false;
			}
		}

		return !in_array( false, $stepResponse, true );
	}

	private function sql( array $data ): bool {
		$lb = $this->dbLoadBalancerFactory->getMainLB( $this->dbname );
		$dbw = $lb->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->dbname );
		foreach ( $data as $table => $sql ) {
			if ( !$dbw->tableExists( $table, __METHOD__ ) ) {
				try {
					$dbw->sourceFile( $sql, fname: __METHOD__ );
				} catch ( Exception $e ) {
					$this->logger->error(
						'Caught exception trying to load {path} for {table} on {dbname}: {exception}',
						[
							'dbname' => $this->dbname,
							'exception' => $e,
							'path' => $sql,
							'table' => $table,
						]
					);

					return false;
				}
			}
		}

		return true;
	}

	private function permissions( array $data, bool $install ): bool {
		$mwPermissions = $this->moduleFactory->permissions( $this->dbname );
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

	private function namespaces( array $data, bool $install ): bool {
		$mwNamespaces = $this->moduleFactory->namespaces( $this->dbname );
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

	private function mwscript( array $data ): bool {
		if ( Shell::isDisabled() ) {
			throw new ShellDisabledError();
		}

		$jobQueueGroup = $this->jobQueueGroupFactory->makeJobQueueGroup();
		$jobQueueGroup->push(
			new JobSpecification(
				MWScriptJob::JOB_NAME,
				[
					'data' => $data,
					'dbname' => $this->dbname,
				]
			)
		);

		return true;
	}

	private function settings( array $data ): bool {
		$mwSettings = $this->moduleFactory->settings( $this->dbname );
		$mwSettings->modify( $data, default: null );
		$mwSettings->commit();
		return true;
	}
}
