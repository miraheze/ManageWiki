<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\MainConfigNames;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Jobs\CacheUpdateJob;
use Psr\Log\LoggerInterface;
use function count;
use function implode;
use function in_array;
use function json_encode;

class CacheUpdate {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::CacheUpdateDebugHeader,
		ConfigNames::CacheUpdateDomain,
		ConfigNames::CacheUpdateKey,
		ConfigNames::CacheUpdateRestEnabled,
		ConfigNames::Servers,
		MainConfigNames::HTTPProxy,
		MainConfigNames::RestPath,
	];

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function queueJob( string $action, string $dbname ): void {
		if ( $this->options->get( ConfigNames::Servers ) === [] ) {
			// No servers configured.
			return;
		}

		$this->jobQueueGroupFactory->makeJobQueueGroup( $dbname )->push(
			new JobSpecification( CacheUpdateJob::JOB_NAME, [
				'action' => $action,
				'dbname' => $dbname,
			] )
		);
	}

	public function executeNow( string $action, string $dbname ): bool {
		$servers = $this->options->get( ConfigNames::Servers );
		if ( $servers === [] ) {
			return true;
		}

		if ( !$this->options->get( ConfigNames::CacheUpdateRestEnabled ) ) {
			$this->logger->error(
				'CacheUpdate::executeNow can not run, ManageWikiCacheUpdateRestEnabled is disabled.'
			);

			return true;
		}

		$key = (string)$this->options->get( ConfigNames::CacheUpdateKey );
		$domain = (string)$this->options->get( ConfigNames::CacheUpdateDomain );
		$debugHeader = (string)$this->options->get( ConfigNames::CacheUpdateDebugHeader );

		if ( $key === '' || $domain === '' || $debugHeader === '' ) {
			$this->logger->error(
				'CacheUpdate::executeNow can not run, one of ManageWikiCacheUpdateKey, ' .
				'ManageWikiCacheUpdateDomain, or ManageWikiCacheUpdateDebugHeader ' .
				'is not configured.'
			);

			return true;
		}

		if ( !in_array( $action, [ 'delete', 'reset' ], true ) ) {
			$this->logger->error(
				'CacheUpdate::executeNow can not run, action can only be delete or reset but it was set to {action}.',
				[ 'action' => $action ]
			);

			return true;
		}

		$restPath = $this->options->get( MainConfigNames::RestPath );
		$url = "https://$domain$restPath/managewiki/v0/cache/$action/$dbname";
		$body = json_encode( [ 'key' => $key ] );

		$requests = [];
		foreach ( $servers as $server ) {
			$requests[$server] = [
				'method' => 'POST',
				'url' => $url,
				'body' => $body,
				'headers' => [
					'Content-Type' => 'application/json',
					$debugHeader => $server,
				],
			];
		}

		$http = $this->httpRequestFactory->createMultiClient( [
			'maxConnsPerHost' => 8,
			'usePipelining' => true,
			'proxy' => $this->options->get( MainConfigNames::HTTPProxy ),
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
				'CacheUpdate::executeNow failed on {count} server(s) for {dbname}: {servers}',
				[
					'count' => count( $failed ),
					'dbname' => $dbname,
					'servers' => implode( ', ', $failed ),
				]
			);

			return false;
		}

		$this->logger->info(
			'CacheUpdate::executeNow successful on all servers for {dbname}.',
			[ 'dbname' => $dbname ]
		);

		return true;
	}
}
