<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;
use Psr\Log\LoggerInterface;

// PHPUnit does not understand coverage for this file.
// It is covered though, see ServiceWiringTest.
// @codeCoverageIgnoreStart

return [
	'ManageWikiHookRunner' => static function ( MediaWikiServices $services ): ManageWikiHookRunner {
		return new ManageWikiHookRunner( $services->getHookContainer() );
	},
	'ManageWikiLogger' => static function (): LoggerInterface {
		return LoggerFactory::getInstance( 'ManageWiki' );
	},
];

// @codeCoverageIgnoreEnd
