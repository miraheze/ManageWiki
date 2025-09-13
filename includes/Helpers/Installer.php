<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use MediaWiki\Exception\ShellDisabledError;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Shell\Shell;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Jobs\MWScriptJob;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILBFactory;
use function in_array;
use function is_array;
use const DB_PRIMARY;

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
			$result = match ( $action ) {
				'sql' => $this->sql( $data ),
				'permissions' => $this->permissions( $data, $install ),
				'namespaces' => $this->namespaces( $data, $install ),
				'mwscript' => $this->mwscript( $data ),
				'settings' => $this->settings( $data ),
				default => null,
			};

			if ( $result === null ) {
				return false;
			}

			$stepResponse[$action] = $result;
		}

		return !in_array( false, $stepResponse, true );
	}

	private function sql( array $data ): bool {
		$lb = $this->dbLoadBalancerFactory->getMainLB( $this->dbname );
		$dbw = $lb->getMaintenanceConnectionRef( DB_PRIMARY, [], $this->dbname );
		foreach ( $data as $table => $sql ) {
			// Normalize table patch and indexes
			$tablePatch = $sql;
			$indexes = [];
			if ( is_array( $sql ) ) {
				$tablePatch = $sql['patch'] ?? null;
				$indexes = $sql['indexes'] ?? [];
			}

			// Apply table patch if defined
			if ( $tablePatch && !$dbw->tableExists( $table, __METHOD__ ) ) {
				try {
					$dbw->sourceFile( $tablePatch, fname: __METHOD__ );
				} catch ( Exception $e ) {
					$this->logger->error(
						'Caught exception trying to load {path} for table {table} on {dbname}: {exception}',
						[
							'dbname' => $this->dbname,
							'exception' => $e,
							'path' => $tablePatch,
							'table' => $table,
						]
					);
					return false;
				}
			}

			// Apply index patches if defined
			foreach ( $indexes as $index => $patch ) {
				if ( !$dbw->indexExists( $table, $index, __METHOD__ ) ) {
					try {
						$dbw->sourceFile( $patch, fname: __METHOD__ );
					} catch ( Exception $e ) {
						$this->logger->error(
							'Caught exception trying to load {path} for index {index} on {dbname}: {exception}',
							[
								'dbname' => $this->dbname,
								'exception' => $e,
								'path' => $patch,
								'index' => $index,
							]
						);
						return false;
					}
				}
			}
		}

		return true;
	}

	private function permissions( array $data, bool $install ): true {
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

	private function namespaces( array $data, bool $install ): true {
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

	/** @throws ShellDisabledError */
	private function mwscript( array $data ): true {
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

	private function settings( array $data ): true {
		$mwSettings = $this->moduleFactory->settings( $this->dbname );
		$mwSettings->modify( $data, default: null );
		$mwSettings->commit();
		return true;
	}
}
