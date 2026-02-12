<?php

namespace Miraheze\ManageWiki\Helpers;

use Exception;
use LocalisationCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\IModule;
use Miraheze\ManageWiki\Jobs\NamespaceMigrationJob;
use Miraheze\ManageWiki\Traits\ConfigHelperTrait;
use Psr\Log\LoggerInterface;
use stdClass;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;
use Wikimedia\Rdbms\SelectQueryBuilder;
use function array_keys;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function str_replace;
use function trim;
use const CONTENT_MODEL_WIKITEXT;
use const NS_PROJECT;
use const NS_PROJECT_TALK;
use const NS_SPECIAL;

class NamespacesModule implements IModule {

	use ConfigHelperTrait;

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::NamespacesAdditional,
		ConfigNames::NamespacesDisallowedNames,
		ConfigNames::SiteConfiguration,
		MainConfigNames::MetaNamespace,
		MainConfigNames::MetaNamespaceTalk,
	];

	private readonly IReadableDatabase $dbr;

	private array $changes = [];
	private array $errors = [];
	private array $logParams = [];
	private array $deleteNamespaces = [];
	private array $liveNamespaces = [];

	private bool $runNamespaceMigrationJob = true;

	private ?string $log = null;

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LocalisationCache $localisationCache,
		private readonly LoggerInterface $logger,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ServiceOptions $options,
		private readonly string $dbname
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->dbr = $this->databaseUtils->getGlobalReplicaDB();
		$namespaces = $this->dbr->newSelectQueryBuilder()
			->select( ISQLPlatform::ALL_ROWS )
			->from( 'mw_namespaces' )
			->where( [ 'ns_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $namespaces as $ns ) {
			if ( !$ns instanceof stdClass ) {
				// Skip unexpected row
				continue;
			}

			$this->liveNamespaces[(int)$ns->ns_namespace_id] = [
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

	public function getNewId(): float|int {
		$lastID = $this->dbr->newSelectQueryBuilder()
			->select( 'ns_namespace_id' )
			->from( 'mw_namespaces' )
			->where( [
				'ns_dbname' => $this->dbname,
				$this->dbr->expr( 'ns_namespace_id', '>=', 3000 ),
			] )
			->orderBy( 'ns_namespace_id', SelectQueryBuilder::SORT_DESC )
			->caller( __METHOD__ )
			->fetchField();

		return $lastID !== false ? $lastID + 1 : 3000;
	}

	/**
	 * Checks whether or not the specified namespace exists
	 * @param int $id Namespace ID to check
	 * @return bool Whether or not the namespace exists
	 */
	public function exists( int $id ): bool {
		return isset( $this->liveNamespaces[$id] );
	}

	public function nameExists( string $name, bool $checkMetaNS ): bool {
		// Normalize
		$name = str_replace(
			[ ' ', ':' ], '_',
			mb_strtolower( trim( $name ) )
		);

		if ( $checkMetaNS && $this->isMetaNamespace( $name ) ) {
			return true;
		}

		foreach ( $this->liveNamespaces as $ns ) {
			// Normalize
			$nsName = str_replace(
				[ ' ', ':' ], '_',
				mb_strtolower( trim( $ns['name'] ) )
			);

			if ( $nsName === $name ) {
				return true;
			}

			$normalizedAliases = array_map(
				static fn ( string $alias ): string => str_replace(
					[ ' ', ':' ], '_',
					mb_strtolower( trim( $alias ) )
				),
				$ns['aliases']
			);

			if ( in_array( $name, $normalizedAliases, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function isMetaNamespace( string $name ): bool {
		$metaNamespace = mb_strtolower( trim(
			str_replace( [ ' ', ':' ], '_', $this->options->get( MainConfigNames::MetaNamespace ) )
		) );

		$metaNamespaceTalk = mb_strtolower( trim(
			str_replace( [ ' ', ':' ], '_', $this->options->get( MainConfigNames::MetaNamespaceTalk ) )
		) );

		$canonicalNameMain = mb_strtolower( trim(
			str_replace( [ ' ', ':' ], '_', $this->namespaceInfo->getCanonicalName( NS_PROJECT ) )
		) );

		$canonicalNameTalk = mb_strtolower( trim(
			str_replace( [ ' ', ':' ], '_', $this->namespaceInfo->getCanonicalName( NS_PROJECT_TALK ) )
		) );

		return in_array( $name, [ $metaNamespace, $metaNamespaceTalk,
			$canonicalNameMain, $canonicalNameTalk
		], true );
	}

	/**
	 * Retrieves data for a specific namespace
	 * @param int $id Namespace ID wanted
	 * @return array Namespace configuration
	 */
	public function list( int $id ): array {
		return $this->liveNamespaces[$id] ?? [
			'name' => null,
			'searchable' => 0,
			'subpages' => 0,
			'content' => 0,
			'contentmodel' => CONTENT_MODEL_WIKITEXT,
			'protection' => '',
			'aliases' => [],
			'core' => 0,
			'additional' => [],
		];
	}

	/**
	 * @return array<int, array>
	 */
	public function listAll(): array {
		return $this->liveNamespaces;
	}

	/**
	 * @return list<int>
	 */
	public function listIds(): array {
		return array_keys( $this->listAll() );
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
		bool $maintainPrefix
	): void {
		$excluded = array_map( 'mb_strtolower', $this->options->get( ConfigNames::NamespacesDisallowedNames ) );
		if ( in_array( mb_strtolower( $data['name'] ), $excluded, true ) ) {
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
			'contentmodel' => $this->liveNamespaces[$id]['contentmodel'] ?? CONTENT_MODEL_WIKITEXT,
			'protection' => $this->liveNamespaces[$id]['protection'] ?? '',
			'aliases' => $this->liveNamespaces[$id]['aliases'] ?? [],
			'core' => $this->liveNamespaces[$id]['core'] ?? 0,
			'additional' => $this->liveNamespaces[$id]['additional'] ?? [],
			'maintainprefix' => $maintainPrefix,
		];

		if ( $data['name'] !== $nsData['name'] ) {
			$checkMetaNS = $id !== NS_PROJECT && $id !== NS_PROJECT_TALK;
			if ( $this->nameExists( $data['name'], $checkMetaNS ) ) {
				$this->errors[] = [
					'managewiki-namespace-conflicts' => [
						$data['name'],
					],
				];
			}
		}

		if ( $data['aliases'] !== $nsData['aliases'] ) {
			foreach ( $data['aliases'] as $alias ) {
				if ( in_array( $alias, $nsData['aliases'], true ) ) {
					continue;
				}

				if ( $this->nameExists( $alias, checkMetaNS: true ) ) {
					$this->errors[] = [
						'managewiki-namespace-conflicts' => [ $alias ],
					];
				}
			}
		}

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
		bool $maintainPrefix
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

	public function isDeleting( int|string $id ): bool {
		return in_array( (int)$id, $this->deleteNamespaces, true );
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
		if ( $this->getErrors() ) {
			// Don't save anything if we have errors
			return;
		}

		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		foreach ( array_keys( $this->changes ) as $id ) {
			if ( $this->isDeleting( $id ) ) {
				$this->log = 'namespaces-delete';
				if ( !$this->isTalk( $id ) ) {
					$this->logParams = [
						'5::namespace' => $this->changes[$id]['old']['name'],
					];
				}

				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'mw_namespaces' )
					->where( [
						'ns_dbname' => $this->dbname,
						'ns_namespace_id' => $id,
					] )
					->caller( __METHOD__ )
					->execute();

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

				$dbw->newInsertQueryBuilder()
					->insertInto( 'mw_namespaces' )
					->row( [
						'ns_dbname' => $this->dbname,
						'ns_namespace_id' => $id,
					] + $builtTable )
					->onDuplicateKeyUpdate()
					->uniqueIndexFields( [
						'ns_dbname',
						'ns_namespace_id',
					] )
					->set( $builtTable )
					->caller( __METHOD__ )
					->execute();

				if ( !$this->logParams || !$this->isTalk( $id ) ) {
					$this->logParams = [
						'5::namespace' => $this->liveNamespaces[$id]['name'],
					];
				}
			}

			if ( $this->dbname !== ModuleFactory::DEFAULT_DBNAME && $this->runNamespaceMigrationJob ) {
				$jobQueueGroup = $this->jobQueueGroupFactory->makeJobQueueGroup();
				$jobQueueGroup->push(
					new JobSpecification(
						NamespaceMigrationJob::JOB_NAME,
						$jobParams
					)
				);
			}
		}

		if ( $this->dbname !== ModuleFactory::DEFAULT_DBNAME ) {
			$dataStore = $this->dataStoreFactory->newInstance( $this->dbname );
			$dataStore->resetWikiData( isNewChanges: true );
		}
	}

	public function setCachedData( array &$cacheArray ): void {
		// Prefer live values; fall back to configured MetaNamespace if missing
		if ( isset( $this->liveNamespaces[NS_PROJECT]['name'] ) ) {
			$metaNamespace = $this->liveNamespaces[NS_PROJECT]['name'];
		} else {
			$metaNamespace = $this->getRemoteConfigIfNeeded(
				$this->options, $this->dbname,
				MainConfigNames::MetaNamespace
			);
		}

		if ( isset( $this->liveNamespaces[NS_PROJECT_TALK]['name'] ) ) {
			$metaNamespaceTalk = $this->liveNamespaces[NS_PROJECT_TALK]['name'];
		} else {
			$metaNamespaceTalk = $this->getRemoteConfigIfNeeded(
				$this->options, $this->dbname,
				MainConfigNames::MetaNamespaceTalk
			);
		}

		$lcName = [];
		$lcEN = [];

		try {
			$languageCode = $cacheArray['core']['wgLanguageCode'] ?? 'en';
			$lcName = $this->localisationCache->getItem( $languageCode, 'namespaceNames' );

			// Ensure Project Talk reflects (possibly customized) Project name
			$lcName[NS_PROJECT_TALK] = str_replace( '$1',
				$lcName[NS_PROJECT] ?? $metaNamespace,
				$lcName[NS_PROJECT_TALK] ?? $metaNamespaceTalk
			);

			if ( $languageCode !== 'en' ) {
				$lcEN = $this->localisationCache->getItem( 'en', 'namespaceNames' );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Caught exception trying to load Localisation Cache: {exception}',
				[ 'exception' => $e ]
			);
		}

		$additional = $this->options->get( ConfigNames::NamespacesAdditional );
		foreach ( $this->listAll() as $id => $ns ) {
			$id = (int)$id;

			// Localized display name + English alias (if any)
			$nsName = $lcName[$id] ?? $ns['name'];
			$lcAlias = $lcEN[$id] ?? null;

			// Normalize aliases
			$aliases = array_map(
				static fn ( string $alias ): string => str_replace( [ ' ', ':' ], '_', $alias ),
				(array)( $ns['aliases'] ?? [] )
			);

			$cacheArray['namespaces'][$nsName] = [
				'id' => $id,
				'core' => (bool)$ns['core'],
				'searchable' => (bool)$ns['searchable'],
				'subpages' => (bool)$ns['subpages'],
				'content' => (bool)$ns['content'],
				'contentmodel' => $ns['contentmodel'],
				'protection' => $ns['protection'] ?: false,
				// Deduplicate aliases
				'aliases' => array_values( array_unique( array_merge( $aliases, (array)$lcAlias ) ) ),
				'additional' => (array)( $ns['additional'] ?? [] ),
			];

			$nsAdditional = (array)( $ns['additional'] ?? [] );

			// Apply NamespacesAdditional per-namespace
			foreach ( $additional as $var => $conf ) {
				if ( !$this->isAdditionalSettingForNamespace( $conf, $id ) ) {
					continue;
				}

				if ( isset( $nsAdditional[$var] ) ) {
					$val = $nsAdditional[$var];
				} elseif ( is_array( $conf['overridedefault'] ?? null ) ) {
					$val = $conf['overridedefault'][$id]
						?? $conf['overridedefault']['default']
						?? null;

					if ( $val === null ) {
						// No applicable fallback; skip
						continue;
					}
				} else {
					$val = $conf['overridedefault'] ?? null;
				}

				if ( $val ) {
					$this->setNamespaceSettingCache( $cacheArray, $id, $var, $val, $conf );
					continue;
				}

				// Ensure structure exists for falsey values (legacy behavior)
				if ( empty( $conf['constant'] ) && empty( $cacheArray['settings'][$var] ) ) {
					$cacheArray['settings'][$var] = [];
				}
			}
		}

		// Apply NS_SPECIAL overridedefaults (no general 'default' fallback here)
		foreach ( $additional as $var => $conf ) {
			$od = $conf['overridedefault'] ?? null;
			if ( isset( $od[NS_SPECIAL] ) && $this->isAdditionalSettingForNamespace( $conf, NS_SPECIAL ) ) {
				$this->setNamespaceSettingCache( $cacheArray, NS_SPECIAL, $var, $od[NS_SPECIAL], $conf );
			}
		}
	}

	/**
	 * Adds the namespace setting for the supplied variable
	 *
	 * @param array &$cacheArray array for cache
	 * @param int $nsID namespace ID number as an integer
	 * @param string $var variable name
	 * @param mixed $val variable value
	 * @param array $varConf variable config from ConfigNames::NamespacesAdditional[$var]
	 */
	private function setNamespaceSettingCache(
		array &$cacheArray,
		int $nsID,
		string $var,
		mixed $val,
		array $varConf
	): void {
		if ( $varConf['type'] === 'check' ) {
			$cacheArray['settings'][$var][] = $nsID;
			return;
		}

		if ( $varConf['type'] === 'vestyle' ) {
			$cacheArray['settings'][$var][$nsID] = true;
			return;
		}

		if ( $varConf['constant'] ?? false ) {
			$cacheArray['settings'][$var] = str_replace( [ ' ', ':' ], '_', $val );
			return;
		}

		$cacheArray['settings'][$var][$nsID] = $val;
	}

	/**
	 * Checks if the namespace is for the additional setting given
	 *
	 * @param array $conf additional setting to check
	 * @param int $nsID namespace ID to check if the setting is allowed for
	 * @return bool Whether or not the setting is enabled for the namespace
	 */
	private function isAdditionalSettingForNamespace( array $conf, int $nsID ): bool {
		// T12237: Do not apply additional settings if the setting is not for the
		// namespace that we are on, otherwise it is very likely for the namespace to
		// not have setting set, and cause settings set before to be ignored

		$only = $conf['only'] ?? null;
		return $only === null || in_array( $nsID, (array)$only, true );
	}
}
