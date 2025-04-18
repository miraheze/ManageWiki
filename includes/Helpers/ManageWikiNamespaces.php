<?php

namespace Miraheze\ManageWiki\Helpers;

use JobSpecification;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Jobs\NamespaceMigrationJob;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler for interacting with Namespace configuration
 */
class ManageWikiNamespaces implements IConfigModule {

	private Config $config;
	private IDatabase $dbw;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $deleteNamespaces = [];
	private array $liveNamespaces = [];

	private bool $runNamespaceMigrationJob = true;

	private string $dbname;
	private ?string $log = null;

	public function __construct( string $dbname ) {
		$this->dbname = $dbname;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		$this->dbw = $databaseUtils->getGlobalPrimaryDB();

		$namespaces = $this->dbw->select(
			'mw_namespaces',
			'*',
			[
				'ns_dbname' => $dbname,
			],
			__METHOD__
		);

		foreach ( $namespaces as $ns ) {
			$this->liveNamespaces[$ns->ns_namespace_id] = [
				'name' => $ns->ns_namespace_name,
				'searchable' => (int)$ns->ns_searchable,
				'subpages' => (int)$ns->ns_subpages,
				'content' => (int)$ns->ns_content,
				'contentmodel' => $ns->ns_content_model,
				'protection' => $ns->ns_protection,
				'aliases' => json_decode( $ns->ns_aliases, true ),
				'core' => (int)$ns->ns_core,
				'additional' => json_decode( $ns->ns_additional, true ),
			];
		}
	}

	/**
	 * Checks whether or not the specified namespace exists
	 * @param int $id Namespace ID to check
	 * @return bool Whether or not the namespace exists
	 */
	public function exists( int $id ): bool {
		return isset( $this->liveNamespaces[$id] );
	}

	/**
	 * Lists either all namespaces or a specific one
	 * @param ?int $id Namespace ID wanted (null for all)
	 * @return array Namespace configuration
	 */
	public function list( ?int $id ): array {
		if ( $id === null ) {
			return $this->liveNamespaces;
		}

		return $this->liveNamespaces[$id] ?? [
			'name' => null,
			'searchable' => 0,
			'subpages' => 0,
			'content' => 0,
			'contentmodel' => 'wikitext',
			'protection' => '',
			'aliases' => [],
			'core' => 0,
			'additional' => [],
		];
	}

	/**
	 * Modify a namespace handler
	 * @param int $id Namespace ID
	 * @param array $data Overriding information about the namespace
	 * @param bool $maintainPrefix
	 */
	public function modify(
		int $id,
		array $data,
		bool $maintainPrefix = false
	): void {
		$excluded = array_map( 'strtolower', $this->config->get( ConfigNames::NamespacesDisallowedNames ) );
		if ( in_array( strtolower( $data['name'] ), $excluded ) ) {
			$this->errors[] = [
				'managewiki-error-disallowednamespace' => [
					$data['name'],
				],
			];
		}

		// We will handle all processing in final stages
		$nsData = [
			'name' => $this->liveNamespaces[$id]['name'] ?? null,
			'searchable' => $this->liveNamespaces[$id]['searchable'] ?? 0,
			'subpages' => $this->liveNamespaces[$id]['subpages'] ?? 0,
			'content' => $this->liveNamespaces[$id]['content'] ?? 0,
			'contentmodel' => $this->liveNamespaces[$id]['contentmodel'] ?? 'wikitext',
			'protection' => $this->liveNamespaces[$id]['protection'] ?? '',
			'aliases' => $this->liveNamespaces[$id]['aliases'] ?? [],
			'core' => $this->liveNamespaces[$id]['core'] ?? 0,
			'additional' => $this->liveNamespaces[$id]['additional'] ?? [],
			'maintainprefix' => $maintainPrefix,
		];

		// Overwrite the defaults above with our new modified values
		foreach ( $data as $name => $value ) {
			if ( $nsData[$name] !== $value ) {
				$this->changes[$id][$name] = [
					'old' => $nsData[$name],
					'new' => $value,
				];

				$nsData[$name] = $value;
			}
		}

		$this->liveNamespaces[$id] = $nsData;
	}

	/**
	 * Remove a namespace
	 * @param int $id Namespace ID
	 * @param int $newNamespace Namespace ID to migrate to
	 * @param bool $maintainPrefix
	 */
	public function remove(
		int $id,
		int $newNamespace,
		bool $maintainPrefix = false
	): void {
		// Utilize changes differently in this case
		$this->changes[$id] = [
			'old' => [
				'name' => $this->liveNamespaces[$id]['name'],
			],
			'new' => [
				'name' => $newNamespace,
				'maintainprefix' => $maintainPrefix,
			],
		];

		// We will handle all processing in final stages
		unset( $this->liveNamespaces[$id] );

		// Push to a deletion queue
		$this->deleteNamespaces[] = $id;
	}

	public function isTalk( int $id ): bool {
		return $id % 2 === 1;
	}

	public function disableNamespaceMigrationJob(): void {
		$this->runNamespaceMigrationJob = false;
	}

	public function isDeleting( int|string $namespace ): bool {
		return in_array( (int)$namespace, $this->deleteNamespaces );
	}

	public function getErrors(): array {
		return $this->errors;
	}

	public function hasChanges(): bool {
		return (bool)$this->changes;
	}

	public function setLogAction( string $action ): void {
		$this->log = $action;
	}

	public function getLogAction(): string {
		return $this->log ?? 'namespaces';
	}

	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	public function getLogParams(): array {
		return $this->logParams;
	}

	public function commit(): void {
		foreach ( array_keys( $this->changes ) as $id ) {
			if ( $this->isDeleting( $id ) ) {
				$this->log = 'namespaces-delete';

				if ( !$this->isTalk( $id ) ) {
					$this->logParams = [
						'5::namespace' => $this->changes[$id]['old']['name'],
					];
				}

				$this->dbw->delete(
					'mw_namespaces',
					[
						'ns_dbname' => $this->dbname,
						'ns_namespace_id' => $id,
					],
					__METHOD__
				);

				$jobParams = [
					'action' => 'delete',
					'dbname' => $this->dbname,
					'nsID' => $id,
					'nsName' => $this->changes[$id]['old']['name'],
					'nsNew' => $this->changes[$id]['new']['name'],
					'maintainPrefix' => $this->changes[$id]['new']['maintainprefix'],
				];
			} else {
				$builtTable = [
					'ns_namespace_name' => $this->liveNamespaces[$id]['name'],
					'ns_searchable' => $this->liveNamespaces[$id]['searchable'],
					'ns_subpages' => $this->liveNamespaces[$id]['subpages'],
					'ns_content' => $this->liveNamespaces[$id]['content'],
					'ns_content_model' => $this->liveNamespaces[$id]['contentmodel'],
					'ns_protection' => $this->liveNamespaces[$id]['protection'],
					'ns_aliases' => json_encode( $this->liveNamespaces[$id]['aliases'] ),
					'ns_core' => $this->liveNamespaces[$id]['core'],
					'ns_additional' => json_encode( $this->liveNamespaces[$id]['additional'] ),
				];

				$jobParams = [
					'action' => 'rename',
					'dbname' => $this->dbname,
					'nsID' => $id,
					'nsName' => $this->liveNamespaces[$id]['name'],
					'nsNew' => null,
					'maintainPrefix' => $this->liveNamespaces[$id]['maintainprefix'] ?? false,
				];

				$this->dbw->upsert(
					'mw_namespaces',
					[
						'ns_dbname' => $this->dbname,
						'ns_namespace_id' => $id,
					] + $builtTable,
					[
						[
							'ns_dbname',
							'ns_namespace_id',
						],
					],
					$builtTable,
					__METHOD__
				);

				if ( !$this->logParams || !$this->isTalk( $id ) ) {
					$this->logParams = [
						'5::namespace' => $this->liveNamespaces[$id]['name'],
					];
				}
			}

			if ( $this->dbname !== 'default' && $this->runNamespaceMigrationJob ) {
				$jobQueueGroupFactory = MediaWikiServices::getInstance()->getJobQueueGroupFactory();
				$jobQueueGroup = $jobQueueGroupFactory->makeJobQueueGroup();

				$jobQueueGroup->push(
					new JobSpecification(
						NamespaceMigrationJob::JOB_NAME,
						$jobParams
					)
				);
			}
		}

		if ( $this->dbname !== 'default' ) {
			$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $this->dbname );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}
