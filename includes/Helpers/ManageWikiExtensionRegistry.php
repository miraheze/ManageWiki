<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Registration\ExtensionRegistry;

class ManageWikiExtensionRegistry extends ExtensionRegistry {

	/**
	 * @var ExtensionRegistry
	 */
	private static $instance;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function queue( $path ) {
		if ( str_ends_with( $path, 'WikibaseClient/extension.json' ) ) {
			$path = str_replace( 'WikibaseClient/extension.json', 'Wikibase/extension-client.json', $path );
		}
		if ( str_ends_with( $path, 'WikibaseRepo/extension.json' ) ) {
			$path = str_replace( 'WikibaseRepo/extension.json', 'Wikibase/extension-repo.json', $path );
		}

		parent::queue( $path . 'test' );
	}
}
