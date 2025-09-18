<?php

namespace Miraheze\ManageWiki\Jobs;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\Job;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Utils\UrlUtils;
use Wikimedia\IPUtils;
use function strlen;
use const PROTO_INTERNAL;

class CacheUpdateJob extends Job {

	public const JOB_NAME = 'CacheUpdateJob';

	private readonly array $servers;

	public function __construct(
		array $params,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly TitleFactory $titleFactory,
		private readonly UrlUtils $urlUtils
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->servers = $params['servers'];
	}

	/** @inheritDoc */
	public function run(): true {
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
		foreach ( $this->servers as $server ) {
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
