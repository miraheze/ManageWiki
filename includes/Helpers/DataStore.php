<?php

namespace Miraheze\ManageWiki\Helpers;

use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\StaticArrayWriter;
use function file_exists;
use function file_put_contents;
use function is_array;
use function rename;
use function tempnam;
use function time;
use function unlink;

class DataStore {

	private const CACHE_KEY = 'ManageWiki';

	private int $timestamp;

	public function __construct(
		private readonly BagOStuff $cache,
		private readonly CacheUpdate $cacheUpdate,
		private readonly HookRunner $hookRunner,
		private readonly ModuleFactory $moduleFactory,
		private readonly string $cacheDir,
		private readonly string $dbname
	) {
		$this->timestamp = (int)$this->cache->get(
			$this->cache->makeGlobalKey( self::CACHE_KEY, $dbname )
		);

		if ( !$this->timestamp ) {
			$this->resetWikiData( isNewChanges: true );
		}
	}

	/**
	 * Syncs the cache by checking if the cached wiki data is outdated.
	 * If the wiki file has been modified, it will reset and
	 * regenerate the cached data.
	 */
	public function syncCache(): void {
		// mtime will be 0 if the file does not exist as well, which means
		// it will be generated.
		$mtime = $this->getCachedWikiData()['mtime'] ?? 0;

		// Regenerate wiki data cache if the file does not exist or has no valid mtime
		if ( $mtime === 0 || $mtime < $this->timestamp ) {
			$this->resetWikiData( isNewChanges: false );
		}
	}

	public function hasState( string $state ): bool {
		$data = $this->getCachedWikiData();
		// We just check this first because it won't be set at all
		// if the core module is disabled or if the state
		// in particular is disabled.
		if ( isset( $data['states'][$state] ) ) {
			if ( $state === 'inactive' && $data['states'][$state] === 'exempt' ) {
				return false;
			}

			return $data['states'][$state];
		}

		if ( !$this->moduleFactory->isEnabled( 'core' ) ) {
			return false;
		}

		try {
			$mwCore = $this->moduleFactory->core( $this->dbname );
			$stateChecks = [
				'private' => [ 'private-wikis', 'isPrivate' ],
				'closed' => [ 'closed-wikis', 'isClosed' ],
				'inactive' => [ 'inactive-wikis', 'isInactive' ],
				'experimental' => [ 'experimental-wikis', 'isExperimental' ],
				'deleted' => [ 'action-delete', 'isDeleted' ],
				'locked' => [ 'action-lock', 'isLocked' ],
			];

			if ( !isset( $stateChecks[$state] ) ) {
				return false;
			}

			[ $feature, $method ] = $stateChecks[$state];
			if ( !$mwCore->isEnabled( $feature ) ) {
				return false;
			}

			return $mwCore->$method();
		} catch ( MissingWikiError ) {
			// We don't want to error here. If the wiki doesn't
			// exist then it can't have the state.
			return false;
		}
	}

	/**
	 * Retrieves new information for the wiki and updates the cache.
	 */
	public function resetWikiData( bool $isNewChanges ): void {
		$mtime = time();
		if ( $isNewChanges ) {
			$this->timestamp = $mtime;
			$this->cache->set(
				$this->cache->makeGlobalKey( self::CACHE_KEY, $this->dbname ),
				$mtime
			);

			$this->cacheUpdate->addUpdate();
		}

		$cacheArray = [
			'mtime' => $mtime,
			'database' => $this->dbname,
		];

		if ( $this->moduleFactory->isEnabled( 'core' ) ) {
			try {
				$mwCore = $this->moduleFactory->core( $this->dbname );
				$cacheArray['category'] = $mwCore->getCategory();
				$cacheArray['created'] = $mwCore->getCreationDate();
				$cacheArray['dbcluster'] = $mwCore->getDBCluster();
				$cacheArray['url'] = $mwCore->getServerName() ?: false;
				$cacheArray['core'] = [
					'wgSitename' => $mwCore->getSiteName(),
					'wgLanguageCode' => $mwCore->getLanguage(),
				];

				$states = [];
				if ( $mwCore->isEnabled( 'private-wikis' ) ) {
					$states['private'] = $mwCore->isPrivate();
				}

				if ( $mwCore->isEnabled( 'closed-wikis' ) ) {
					$states['closed'] = $mwCore->isClosed();
				}

				if ( $mwCore->isEnabled( 'inactive-wikis' ) ) {
					$states['inactive'] = $mwCore->isInactiveExempt() ? 'exempt' :
						$mwCore->isInactive();
				}

				if ( $mwCore->isEnabled( 'experimental-wikis' ) ) {
					$states['experimental'] = $mwCore->isExperimental();
				}

				if ( $mwCore->isEnabled( 'action-delete' ) ) {
					$states['deleted'] = $mwCore->isDeleted();
				}

				if ( $mwCore->isEnabled( 'action-lock' ) ) {
					$states['locked'] = $mwCore->isLocked();
				}

				$cacheArray['states'] = $states;
			} catch ( MissingWikiError ) {
				// Do nothing, we don't need to handle that here.
			}
		}

		if ( $this->moduleFactory->isEnabled( 'settings' ) ) {
			$cacheArray['settings'] = $this->moduleFactory->settings( $this->dbname )->listAll();
		}

		if ( $this->moduleFactory->isEnabled( 'extensions' ) ) {
			$cacheArray['extensions'] = $this->moduleFactory->extensions( $this->dbname )->listNames();
		}

		if ( $this->moduleFactory->isEnabled( 'permissions' ) ) {
			$cacheArray['permissions'] = $this->moduleFactory->permissions( $this->dbname )->getCachedData();
		}

		if ( $this->moduleFactory->isEnabled( 'namespaces' ) ) {
			$this->moduleFactory->namespaces( $this->dbname )->setCachedData( $cacheArray );
		}

		$this->hookRunner->onManageWikiDataStoreBuilder( $this->moduleFactory, $this->dbname, $cacheArray );
		$this->writeToFile( $this->dbname, $cacheArray );
	}

	/**
	 * Deletes the wiki data cache for a wiki.
	 * Probably used when a wiki is deleted or renamed.
	 */
	public function deleteWikiData( string $dbname ): void {
		$this->cache->delete( $this->cache->makeGlobalKey( self::CACHE_KEY, $dbname ) );
		if ( file_exists( "{$this->cacheDir}/$dbname.php" ) ) {
			unlink( "{$this->cacheDir}/$dbname.php" );
		}
	}

	/**
	 * Writes data to a PHP file in the cache directory.
	 */
	private function writeToFile( string $fileName, array $data ): void {
		$tmpFile = tempnam( $this->cacheDir, $fileName );
		if ( $tmpFile !== false ) {
			$contents = StaticArrayWriter::write( $data, 'Automatically generated by ManageWiki' );
			if ( file_put_contents( $tmpFile, $contents ) ) {
				if ( !rename( $tmpFile, "{$this->cacheDir}/$fileName.php" ) ) {
					unlink( $tmpFile );
				}
			} else {
				unlink( $tmpFile );
			}
		}
	}

	private function getCachedWikiData(): array {
		// Avoid using file_exists for performance reasons. Including the file directly leverages
		// the opcode cache and prevents any file system access.
		// We only handle failures if the include does not work.

		$filePath = "{$this->cacheDir}/{$this->dbname}.php";
		$cacheData = AtEase::quietCall(
			static fn ( string $path ): array|false => include $path,
			$filePath
		);

		if ( is_array( $cacheData ) ) {
			return $cacheData;
		}

		return [ 'mtime' => 0 ];
	}
}
