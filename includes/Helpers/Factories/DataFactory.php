<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\HookRunner;
use ObjectCacheFactory;
use Wikimedia\AtEase\AtEase;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\Rdbms\IReadableDatabase;
use function file_exists;
use function file_put_contents;
use function is_array;
use function rename;
use function tempnam;
use function time;
use function unlink;
use function var_export;
use function wfTempDir;

class DataFactory {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::CacheDirectory,
		ConfigNames::CacheType,
	];

	private const CACHE_KEY = 'ManageWiki';

	private readonly BagOStuff $cache;
	private IReadableDatabase $dbr;

	/** @var string The wiki database name. */
	private string $dbname;

	/** @var string The directory path for cache files. */
	private readonly string $cacheDir;

	/** @var int The cached timestamp for the wiki information. */
	private int $wikiTimestamp;

	public function __construct(
		ObjectCacheFactory $objectCacheFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly HookRunner $hookRunner,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->cache = ( $this->options->get( ConfigNames::CacheType ) !== null ) ?
			$objectCacheFactory->getInstance( $this->options->get( ConfigNames::CacheType ) ) :
			$objectCacheFactory->getLocalClusterInstance();

		$this->cacheDir = $this->options->get( ConfigNames::CacheDirectory );
	}

	public function newInstance( string $dbname ): self {
		$this->dbname = $dbname;
		$this->wikiTimestamp = (int)$this->cache->get(
			$this->cache->makeGlobalKey( self::CACHE_KEY, $dbname )
		);

		if ( !$this->wikiTimestamp ) {
			$this->resetWikiData( isNewChanges: true );
		}

		return $this;
	}

	/**
	 * Syncs the cache by checking if the cached wiki data or database list is outdated.
	 * If either the wiki or database cache file has been modified, it will reset
	 * and regenerate the cached data.
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
	 * Resets the wiki data information.
	 *
	 * This method retrieves new information for the wiki and updates the cache.
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

		$this->dbr ??= $this->databaseUtils->getGlobalReplicaDB();

		$row = $this->dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mw_settings' )
			->where( [ 's_dbname' => $this->dbname ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			throw new MissingWikiError( $this->dbname );
		}

		$cacheArray = [
			'mtime' => $mtime,
			'database' => $row->s_dbname,
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

	/**
	 * @return array Cached wiki data.
	 */
	private function getCachedWikiData(): array {
		// Avoid using file_exists for performance reasons. Including the file directly leverages
		// the opcode cache and prevents any file system access.
		// We only handle failures if the include does not work.

		$filePath = "{$this->cacheDir}/{$this->dbname}.php";
		$cacheData = AtEase::quietCall( static function ( $path ) {
			return include $path;
		}, $filePath );

		if ( is_array( $cacheData ) ) {
			return $cacheData;
		}

		return [ 'mtime' => 0 ];
	}
}
