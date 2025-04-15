<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\ManageWiki\Jobs\NamespaceMigrationJob;
use Wikimedia\Rdbms\IDatabase;

/**
 * Handler for interacting with Namespace configuration
 */
class ManageWikiNamespaces {

	private Config $config;
	private IDatabase $dbw;

	private array $deleteNamespaces = [];
	private array $liveNamespaces = [];

	private string $wiki;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];

	private string $log = 'namespaces';

	public function __construct( string $wiki ) {
		$this->wiki = $wiki;
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );

		$this->dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		$namespaces = $this->dbw->select(
			'mw_namespaces',
			'*',
			[
				'ns_dbname' => $wiki,
			],
			__METHOD__
		);

		// Bring database values to class scope
		foreach ( $namespaces as $ns ) {
			$this->liveNamespaces[$ns->ns_namespace_id] = [
				'name' => $ns->ns_namespace_name,
				'searchable' => $ns->ns_searchable,
				'subpages' => $ns->ns_subpages,
				'content' => $ns->ns_content,
				'contentmodel' => $ns->ns_content_model,
				'protection' => $ns->ns_protection,
				'aliases' => json_decode( $ns->ns_aliases, true ),
				'core' => $ns->ns_core,
				'additional' => json_decode( $ns->ns_additional, true ),
			];
		}
	}

	/**
	 * Lists either all namespaces or a specific one
	 * @param int|null $id Namespace ID wanted (null for all)
	 * @return array Namespace configuration
	 */
	public function list( ?int $id = null ): array {
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
		$excluded = array_map( 'strtolower', $this->config->get( 'ManageWikiNamespacesDisallowedNames' ) );
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
		// Utilise changes differently in this case
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

	public function getErrors(): array {
		return $this->errors;
	}

	public function hasChanges(): bool {
		return (bool)$this->changes;
	}

	public function setLogAction( string $action ): void {
		$this->log = $action;
	}

	public function addLogParam( string $param, mixed $value ): void {
		$this->logParams[$param] = $value;
	}

	public function getLogAction(): ?string {
		return $this->log;
	}

	public function getLogParams(): array {
		return $this->logParams;
	}

	public function commit( bool $runNamespaceMigrationJob = true ): void {
		foreach ( array_keys( $this->changes ) as $id ) {
			if ( in_array( $id, $this->deleteNamespaces ) ) {
				$this->log = 'namespaces-delete';

				if ( !$this->isTalk( $id ) ) {
					$this->logParams = [
						'5::namespace' => $this->changes[$id]['old']['name'],
					];
				}

				$this->dbw->delete(
					'mw_namespaces',
					[
						'ns_dbname' => $this->wiki,
						'ns_namespace_id' => $id,
					],
					__METHOD__
				);

				$jobParams = [
					'action' => 'delete',
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
					'nsID' => $id,
					'nsName' => $this->liveNamespaces[$id]['name'],
					'maintainPrefix' => $this->liveNamespaces[$id]['maintainprefix'] ?? false,
				];

				$this->dbw->upsert(
					'mw_namespaces',
					[
						'ns_dbname' => $this->wiki,
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

			if ( $this->wiki !== 'default' && $runNamespaceMigrationJob ) {
				$job = new NamespaceMigrationJob( SpecialPage::getTitleFor( 'ManageWiki' ), $jobParams );
				MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup()->push( $job );
			}
		}

		if ( $this->wiki !== 'default' ) {
			$dataFactory = MediaWikiServices::getInstance()->get( 'CreateWikiDataFactory' );
			$data = $dataFactory->newInstance( $this->wiki );
			$data->resetWikiData( isNewChanges: true );
		}
	}
}
