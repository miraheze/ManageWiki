<?php

namespace Miraheze\ManageWiki\Jobs;

use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\IPUtils;
use function strlen;
use const PROTO_INTERNAL;

class CacheUpdateJob extends Job {

	public const JOB_NAME = 'CacheUpdateJob';

	public function __construct(
		private readonly Config $config,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UrlUtils $urlUtils
	) {
		parent::__construct( self::JOB_NAME );
	}

	/** @inheritDoc */
	public function run(): true {
		$servers = $this->config->get( ConfigNames::Servers );
		if ( $servers === [] ) {
			// If no servers are configured, early exit.
			return true;
		}

		$mainPageUrl = $this->titleFactory->newMainPage()->getFullURL();
		$url = $this->urlUtils->expand( $mainPageUrl, PROTO_INTERNAL );
		if ( $url === null ) {
			return true;
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
		return true;
	}
}
