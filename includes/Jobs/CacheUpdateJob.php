<?php

namespace Miraheze\ManageWiki\Jobs;

use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\Job;
use Miraheze\ManageWiki\ConfigNames;
use Psr\Log\LoggerInterface;
use function count;
use function http_build_query;
use function implode;
use function rawurlencode;

class CacheUpdateJob extends Job {

	public const string JOB_NAME = 'CacheUpdateJob';

	private readonly string $dbname;

	public function __construct(
		array $params,
		private readonly Config $config,
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->dbname = $params['dbname'];
	}

	public function run(): bool {
		$servers = $this->config->get( ConfigNames::Servers );
		if ( $servers === [] ) {
			return true;
		}

		if ( !$this->config->get( ConfigNames::CacheUpdateRestEnabled ) ) {
			$this->logger->error(
				'CacheUpdateJob can not run, ManageWikiCacheUpdateRestEnabled is disabled.'
			);

			return false;
		}

		$key = (string)$this->config->get( ConfigNames::CacheUpdateKey );
		$domain = (string)$this->config->get( ConfigNames::CacheUpdateDomain );
		$debugHeader = (string)$this->config->get( ConfigNames::CacheUpdateDebugHeader );

		if ( $key === '' || $domain === '' || $debugHeader === '' ) {
			$this->logger->error(
				'CacheUpdateJob can not run, one of ManageWikiCacheUpdateKey, ' .
				'ManageWikiCacheUpdateDomain, or ManageWikiCacheUpdateDebugHeader ' .
				'is not configured.'
			);

			return false;
		}

		$url = 'https://' . $domain . '/w/rest.php/managewiki/v0/cache/reset/' .
			rawurlencode( $this->dbname );

		$body = http_build_query( [ 'key' => $key ] );

		$requests = [];
		foreach ( $servers as $server ) {
			$requests[$server] = [
				'method' => 'POST',
				'url' => $url,
				'body' => $body,
				'headers' => [
					'Content-Type' => 'application/x-www-form-urlencoded',
					$debugHeader => $server,
				],
			];
		}

		$http = $this->httpRequestFactory->createMultiClient( [
			'maxConnsPerHost' => 8,
			'usePipelining' => true,
		] );

		$responses = $http->runMulti( $requests );

		$failed = [];
		foreach ( $responses as $server => $requestResult ) {
			$code = $requestResult['response']['code'] ?? 0;
			if ( $code !== 204 ) {
				$failed[] = $server;
			}
		}

		if ( $failed !== [] ) {
			$this->logger->error(
				'CacheUpdateJob failed on {count} server(s) for {dbname}: {servers}',
				[
					'count' => count( $failed ),
					'dbname' => $this->dbname,
					'servers' => implode( ', ', $failed ),
				]
			);

			return false;
		}

		return true;
	}
}
