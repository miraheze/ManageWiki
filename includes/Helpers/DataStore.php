<?php

namespace Miraheze\ManageWiki\Helpers;

use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectCache\BagOStuff;
use function apcu_delete;
use function apcu_entry;
use function file_put_contents;
use function function_exists;
use function is_array;
use function opcache_compile_file;
use function opcache_invalidate;
use function rename;
use function tempnam;
use function time;
use function unlink;
use function var_export;

class DataStore {

	private const CACHE_KEY = 'ManageWiki';
	private const APCU_KEY_PREFIX = 'ManageWiki:cache:';

	private static array $reqCache = [];

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

	public function isPrivate(): bool {
		$data = $this->getCachedWikiData();
		if ( isset( $data['states']['private'] ) ) {
			return $data['states']['private'];
		}

		if ( !$this->moduleFactory->isEnabled( 'core' ) ) {
			return false;
		}

		try {
			$mwCore = $this->moduleFactory->core( $this->dbname );
			if ( !$mwCore->isEnabled( 'private-wikis' ) ) {
				return false;
			}

			return $mwCore->isPrivate();
		} catch ( MissingWikiError ) {
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
		$filePath = "{$this->cacheDir}/$dbname.php";
		if ( function_exists( 'apcu_delete' ) ) {
			apcu_delete( self::APCU_KEY_PREFIX . $filePath );
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $filePath, true );
		}

		AtEase::quietCall(
			static fn ( string $path ): bool => unlink( $path ),
			$filePath
		);
	}

	/**
	 * Writes data to a PHP file in the cache directory.
	 */
	private function writeToFile( string $fileName, array $data ): void {
		$tmpFile = tempnam( $this->cacheDir, $fileName . '.' );
		$targetPath = "{$this->cacheDir}/$fileName.php";

		$payload = "<?php\n\nreturn " . var_export( $data, true ) . ";\n";
		$written = AtEase::quietCall(
			static fn ( string $path, string $content ): int|false =>
				file_put_contents( $path, $content, LOCK_EX ),
			$tmpFile, $payload
		);

		if ( $written === false ) {
			AtEase::quietCall(
				static fn ( string $path ): bool => unlink( $path ),
				$tmpFile
			);
			return;
		}

		$renamed = AtEase::quietCall(
			static fn ( string $source, string $target ): bool => rename( $source, $target ),
			$tmpFile, $targetPath
		);

		if ( !$renamed ) {
			AtEase::quietCall(
				static fn ( string $path ): bool => unlink( $path ),
				$tmpFile
			);
			return;
		}

		if ( function_exists( 'apcu_delete' ) ) {
			apcu_delete( self::APCU_KEY_PREFIX . $targetPath );
		}

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( $targetPath, true );
		}

		if ( function_exists( 'opcache_compile_file' ) ) {
			opcache_compile_file( $targetPath );
		}

		unset( self::$reqCache[$targetPath] );
	}

	private function getCachedWikiData(): array {
		// Avoid using file_exists for performance reasons. Including the file directly leverages
		// the opcode cache and prevents any file system access.
		// We only handle failures if the include does not work.

		$filePath = "{$this->cacheDir}/{$this->dbname}.php";

		if ( isset( self::$reqCache[$filePath] ) ) {
			return self::$reqCache[$filePath];
		}

		if ( function_exists( 'apcu_entry' ) ) {
			$data = apcu_entry(
				self::APCU_KEY_PREFIX . $filePath,
				static function () use ( $filePath ): array {
					$cacheData = AtEase::quietCall(
						static fn ( string $path ): array|false => include $path,
						$filePath
					);

					return is_array( $cacheData ) ? $cacheData : [ 'mtime' => 0 ];
				}
			);

			self::$reqCache[$filePath] = $data;
			return $data;
		}

		$cacheData = AtEase::quietCall(
			static fn ( string $path ): array|false => include $path,
			$filePath
		);

		$result = is_array( $cacheData ) ? $cacheData : [ 'mtime' => 0 ];
		self::$reqCache[$filePath] = $result;
		return $result;
	}
}
