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
		ConfigNames::CacheUpdateDebugAccessKey,
		ConfigNames::CacheUpdateDebugAccessKeyHeader,
		ConfigNames::CacheUpdateDebugHeader,
		ConfigNames::CacheUpdateDomain,
		ConfigNames::CacheUpdateKey,
		ConfigNames::CacheUpdateRestEnabled,
		ConfigNames::CacheUpdateServers,
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
		if ( !$this->isExecutionAllowed( $action ) ) {
			return;
		}

		$this->jobQueueGroupFactory->makeJobQueueGroup()->push(
			new JobSpecification( CacheUpdateJob::JOB_NAME, [
				'action' => $action,
				'dbname' => $dbname,
			] )
		);
	}

	public function executeNow( string $action, string $dbname ): bool {
		if ( !$this->isExecutionAllowed( $action ) ) {
			return true;
		}

		$servers = $this->options->get( ConfigNames::CacheUpdateServers );
		$key = (string)$this->options->get( ConfigNames::CacheUpdateKey );
		$domain = (string)$this->options->get( ConfigNames::CacheUpdateDomain );
		$debugHeader = (string)$this->options->get( ConfigNames::CacheUpdateDebugHeader );

		$restPath = $this->options->get( MainConfigNames::RestPath );
		$url = "https://$domain$restPath/managewiki/v0/cache/$action";
		if ( $action !== 'reset-database-lists' ) {
			$url .= "/$dbname";
		}

		$body = json_encode( [ 'key' => $key ] );
		$headers = [ 'Content-Type' => 'application/json' ];

		$debugAccessKeyHeader = (string)$this->options->get( ConfigNames::CacheUpdateDebugAccessKeyHeader );
		$debugAccessKey = (string)$this->options->get( ConfigNames::CacheUpdateDebugAccessKey );
		if ( $debugAccessKeyHeader !== '' && $debugAccessKey !== '' ) {
			$headers[$debugAccessKeyHeader] = $debugAccessKey;
		}

		$requests = [];
		foreach ( $servers as $server ) {
			$requests[$server] = [
				'method' => 'POST',
				'url' => $url,
				'body' => $body,
				'headers' => $headers + [
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
				'{class} failed on {count} server(s) for {dbname}: {servers}',
				[
					'class' => self::class,
					'count' => count( $failed ),
					'dbname' => $dbname,
					'servers' => implode( ', ', $failed ),
				]
			);

			return false;
		}

		$this->logger->info(
			'{class} successful on all servers for {dbname}.',
			[
				'class' => self::class,
				'dbname' => $dbname,
			]
		);

		return true;
	}

	private function isExecutionAllowed( string $action ): bool {
		$servers = $this->options->get( ConfigNames::CacheUpdateServers );
		if ( $servers === [] ) {
			// No servers configured.
			return false;
		}

		if ( !$this->options->get( ConfigNames::CacheUpdateRestEnabled ) ) {
			$this->logger->error(
				'{class} can not run, {config} is disabled.',
				[
					'class' => self::class,
					'config' => ConfigNames::CacheUpdateRestEnabled,
				]
			);

			return false;
		}

		$key = (string)$this->options->get( ConfigNames::CacheUpdateKey );
		$domain = (string)$this->options->get( ConfigNames::CacheUpdateDomain );
		$debugHeader = (string)$this->options->get( ConfigNames::CacheUpdateDebugHeader );

		if ( $key === '' || $domain === '' || $debugHeader === '' ) {
			$this->logger->error(
				'{class} can not run, one of {keys} is not configured.',
				[
					'class' => self::class,
					'keys' => implode( ', ', [
						ConfigNames::CacheUpdateDebugHeader,
						ConfigNames::CacheUpdateDomain,
						ConfigNames::CacheUpdateKey,
					] ),
				]
			);

			return false;
		}

		if ( !in_array( $action, [ 'delete', 'reset', 'reset-database-lists' ], true ) ) {
			$this->logger->error(
				'{class} can not run, {action} is an invalid action.',
				[
					'action' => $action,
					'class' => self::class,
				]
			);

			return false;
		}

		return true;
	}
}
