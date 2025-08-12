<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\IPUtils;
use function strlen;
use const PROTO_INTERNAL;

class CacheUpdate {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::Servers,
	];

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UrlUtils $urlUtils,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function addUpdate(): void {
		DeferredUpdates::addCallableUpdate(
			fn () => $this->doUpdate()
		);
	}

	public function doUpdate(): void {
		$servers = $this->options->get( ConfigNames::Servers );
		if ( $servers === [] ) {
			// If no servers are configured, early exit.
			return;
		}

		$mainPageUrl = $this->titleFactory->newMainPage()->getFullURL();
		$url = $this->urlUtils->expand( $mainPageUrl, PROTO_INTERNAL );
		if ( $url === null ) {
			return;
		}

		$urlInfo = $this->urlUtils->parse( $url ) ?? false;
		$urlHost = strlen( $urlInfo['port'] ?? '' )
			? IPUtils::combineHostAndPort( $urlInfo['host'], (int)$urlInfo['port'] )
			: $urlInfo['host'];

		$baseReq = [
			'method' => 'PURGE',
			'url' => $url,
			'headers' => [
				'Host' => $urlHost,
				'Connection' => 'Keep-Alive',
				'Proxy-Connection' => 'Keep-Alive',
				'User-Agent' => 'ManageWiki extension',
			],
		];

		$reqs = [];
		foreach ( $servers as $server ) {
			$reqs[] = ( $baseReq + [ 'proxy' => $server ] );
		}

		$http = $this->httpRequestFactory->createMultiClient( [
			'maxConnsPerHost' => 8,
			'usePipelining' => true,
		] );

		$http->runMulti( $reqs );
	}
}
