<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Registration\ExtensionProcessor;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\StaticArrayWriter;
use function array_column;
use function array_fill_keys;
use function array_merge;
use function file_put_contents;
use function glob;
use const LOCK_EX;

class RebuildExtensionListCache extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Rebuild or generate extension-list cache file.' );
		$this->addOption( 'cachedir', 'Path to the cachedir to use; defaults to the configured value.', false );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute(): void {
		$queue = array_fill_keys( array_merge(
			glob( $this->getConfig()->get( MainConfigNames::ExtensionDirectory ) . '/*/extension*.json' ),
			glob( $this->getConfig()->get( MainConfigNames::StyleDirectory ) . '/*/skin.json' )
		), true );

		$processor = new ExtensionProcessor();
		foreach ( $queue as $path => $_ ) {
			$processor->extractInfoFromFile( $path );
		}

		$data = $processor->getExtractedInfo();
		$list = array_column( $data['credits'], 'path', 'name' );

		$defaultCacheDir = $this->getConfig()->get( ConfigNames::CacheDirectory ) ?:
			$this->getConfig()->get( MainConfigNames::CacheDirectory );

		$cacheDir = $this->getOption( 'cachedir', $defaultCacheDir );

		$contents = StaticArrayWriter::write( $list, 'Auto-generated extension list cache.' );
		file_put_contents( "$cacheDir/extension-list.php", $contents, LOCK_EX );
	}
}

// @codeCoverageIgnoreStart
return RebuildExtensionListCache::class;
// @codeCoverageIgnoreEnd
