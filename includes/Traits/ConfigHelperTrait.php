<?php

namespace Miraheze\ManageWiki\Traits;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\WikiMap\WikiMap;
use Miraheze\ManageWiki\ConfigNames;

trait ConfigHelperTrait {

	private function getRemoteConfigIfNeeded(
		ServiceOptions $options,
		string $dbname,
		string $configName
	): mixed {
		if ( WikiMap::isCurrentWikiId( $dbname ) ) {
			return $options->get( $configName );
		}

		$conf = $options->get( ConfigNames::Conf );
		'@phan-var SiteConfiguration $conf';
		return $conf->get( $this->getConfigName( $configName ), $dbname );
	}

	private function getConfigName( string $name ): string {
		return "wg$name";
	}

	private function getConfigVar( string $name ): string {
		return "\$wg$name";
	}
}
