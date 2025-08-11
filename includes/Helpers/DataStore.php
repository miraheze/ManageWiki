<?php

namespace Miraheze\ManageWiki\Helpers;

use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectCache\BagOStuff;
use function file_exists;
use function file_put_contents;
use function is_array;
use function rename;
use function tempnam;
use function time;
use function unlink;
use function var_export;
use function wfTempDir;

class DataStore {

	private const CACHE_KEY = 'ManageWiki';

	private int $wikiTimestamp;

	public function __construct(
		private readonly BagOStuff $cache,
		private readonly HookRunner $hookRunner,
		private readonly ModuleFactory $moduleFactory,
		private readonly string $cacheDir,
		private readonly string $dbname
	) {
		$this->wikiTimestamp = (int)$this->cache->get(
			$this->cache->makeGlobalKey( self::CACHE_KEY, $dbname )
		);

		if ( !$this->wikiTimestamp ) {
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
		$wikiMtime = $this->getCachedWikiData()['mtime'] ?? 0;

		// Regenerate wiki data cache if the file does not exist or has no valid mtime
		if ( $wikiMtime === 0 || $wikiMtime < $this->wikiTimestamp ) {
			$this->resetWikiData( isNewChanges: false );
		}
	}

	/**
	 * Retrieves new information for the wiki and updates the cache.
	 */
	public function resetWikiData( bool $isNewChanges ): void {
		$mtime = time();
		if ( $isNewChanges ) {
			$this->wikiTimestamp = $mtime;
			$this->cache->set(
				$this->cache->makeGlobalKey( self::CACHE_KEY, $this->dbname ),
				$mtime
			);
		}

		if ( $this->moduleFactory->isEnabled( 'settings' ) ) {
			$cacheArray['settings'] = $this->moduleFactory->settings( $dbname )->listAll();
		}

		if ( $this->moduleFactory->isEnabled( 'extensions' ) ) {
			$cacheArray['extensions'] = $this->moduleFactory->extensions( $dbname )->listNames();
		}

		if ( $this->moduleFactory->isEnabled( 'permissions' ) ) {
			$cacheArray['permissions'] = $this->moduleFactory->permissions( $dbname )->getCachedData();
		}

		$cacheArray = [
			'mtime' => $mtime,
			'database' => $this->dbname,
		];

		$this->hookRunner->onManageWikiDataFactoryBuilder( $this->dbname, $this->dbr, $cacheArray );
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
		$tmpFile = tempnam( wfTempDir(), $fileName );
		if ( $tmpFile !== false ) {
			if ( file_put_contents( $tmpFile, "<?php\n\nreturn " . var_export( $data, true ) . ";\n" ) ) {
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
		$cacheData = AtEase::quietCall( static function ( string $path ): array|false {
			return include $path;
		}, $filePath );

		if ( is_array( $cacheData ) ) {
			return $cacheData;
		}

		return [ 'mtime' => 0 ];
	}
}
