<?php

namespace Miraheze\ManageWiki\Traits;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\WikiMap\WikiMap;

trait ConfigHelperTrait {

	private function getRemoteConfigIfNeeded(
		ServiceOptions $options,
		string $configName,
		string $dbname
	): mixed {
		if ( WikiMap::isCurrentWikiId( $dbname ) ) {
			return $options->get( $configName );
		}

		global $wgConf;
		return $wgConf->get( $this->getConfigName( $configName ), $dbname );
	}

	private function getConfigName( string $name ): string {
		return "wg$name";
	}

	private function getConfigVar( string $name ): string {
		return "\$wg$name";
	}
}
