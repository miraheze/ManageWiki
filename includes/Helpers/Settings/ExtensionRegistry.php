<?php

namespace Miraheze\ManageWiki\Helpers\Settings;

use MediaWiki\Registration\ExtensionRegistry as CoreExtensionRegistry;
use function str_ends_with;
use function str_replace;

class ExtensionRegistry extends CoreExtensionRegistry {

	private static ?self $instance;

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function queue( string $path ): void {
		if ( str_ends_with( $path, 'WikibaseClient/extension.json' ) ) {
			$path = str_replace( 'WikibaseClient/extension.json', 'Wikibase/extension-client.json', $path );
		}

		if ( str_ends_with( $path, 'WikibaseRepo/extension.json' ) ) {
			$path = str_replace( 'WikibaseRepo/extension.json', 'Wikibase/extension-repo.json', $path );
		}

		parent::queue( $path );
	}
}
