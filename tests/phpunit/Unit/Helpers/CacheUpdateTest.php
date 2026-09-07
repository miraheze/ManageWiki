<?php

namespace Miraheze\ManageWiki\Tests\Unit\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\MainConfigNames;
use MediaWikiUnitTestCase;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\CacheUpdate;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wikimedia\Http\MultiHttpClient;
use function array_keys;
use function json_decode;
use function reset;

/**
 * @group ManageWiki
 * @coversDefaultClass \Miraheze\ManageWiki\Helpers\CacheUpdate
 */
class CacheUpdateTest extends MediaWikiUnitTestCase {

	private const array BASE_CONFIG = [
		ConfigNames::CacheUpdateDebugAccessKey => '',
		ConfigNames::CacheUpdateDebugAccessKeyHeader => '',
		ConfigNames::CacheUpdateDebugHeader => 'X-Debug-Server',
		ConfigNames::CacheUpdateDomain => 'central.example.org',
		ConfigNames::CacheUpdateKey => 'secret-key',
		ConfigNames::CacheUpdateRestEnabled => true,
		ConfigNames::CacheUpdateServers => [ 'server1', 'server2' ],
		MainConfigNames::HTTPProxy => false,
		MainConfigNames::RestPath => '/w/rest.php',
	];

	private function newOptions( array $overrides ): ServiceOptions {
		$config = $overrides + self::BASE_CONFIG;
		$options = $this->createMock( ServiceOptions::class );
		$options->method( 'assertRequiredOptions' )->willReturn( null );
		$options->method( 'get' )->willReturnCallback(
			static fn ( string $name ): mixed => $config[$name]
		);

		return $options;
	}

	private function newCacheUpdate(
		array $configOverrides,
		?MultiHttpClient $multiClient,
		?JobQueueGroupFactory $jobQueueGroupFactory,
		?LoggerInterface $logger
	): CacheUpdate {
		$httpRequestFactory = $this->createMock( HttpRequestFactory::class );
		$httpRequestFactory->method( 'createMultiClient' )
			->willReturn( $multiClient ?? $this->createMock( MultiHttpClient::class ) );

		return new CacheUpdate(
			$httpRequestFactory,
			$jobQueueGroupFactory ?? $this->createMock( JobQueueGroupFactory::class ),
			$logger ?? new NullLogger(),
			$this->newOptions( $configOverrides )
		);
	}

	/**
	 * @covers ::__construct
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsTrueWithNoServersConfigured(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateServers => [] ],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsTrueWhenRestDisabled(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateRestEnabled => false ],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsTrueWhenKeyMissing(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateKey => '' ],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsTrueWhenDomainMissing(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateDomain => '' ],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsTrueWhenDebugHeaderMissing(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateDebugHeader => '' ],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 * @covers ::isExecutionAllowed
	 */
	public function testExecuteNowReturnsFalseForInvalidAction(): void {
		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: null,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertFalse( $cacheUpdate->executeNow( 'not-a-real-action', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 */
	public function testExecuteNowSuccessOnAllServers(): void {
		$multiClient = $this->createMock( MultiHttpClient::class );
		$multiClient->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ): array {
				$responses = [];
				foreach ( array_keys( $requests ) as $key ) {
					$responses[$key] = [ 'response' => [ 'code' => 204 ] ];
				}

				return $responses;
			}
		);

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: $multiClient,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertTrue( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 */
	public function testExecuteNowFailsWhenAServerFails(): void {
		$multiClient = $this->createMock( MultiHttpClient::class );
		$multiClient->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ): array {
				$responses = [];
				$first = true;
				foreach ( array_keys( $requests ) as $key ) {
					$responses[$key] = [
						'response' => [ 'code' => $first ? 500 : 204 ],
					];
					$first = false;
				}

				return $responses;
			}
		);

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: $multiClient,
			jobQueueGroupFactory: null,
			logger: null
		);
		$this->assertFalse( $cacheUpdate->executeNow( 'reset', 'examplewiki' ) );
	}

	/**
	 * @covers ::executeNow
	 */
	public function testExecuteNowIncludesDebugAccessKeyHeaderWhenConfigured(): void {
		$capturedRequests = [];
		$multiClient = $this->createMock( MultiHttpClient::class );
		$multiClient->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ) use ( &$capturedRequests ): array {
				$capturedRequests = $requests;
				$responses = [];
				foreach ( array_keys( $requests ) as $key ) {
					$responses[$key] = [ 'response' => [ 'code' => 204 ] ];
				}

				return $responses;
			}
		);

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [
				ConfigNames::CacheUpdateDebugAccessKeyHeader => 'X-Access-Key',
				ConfigNames::CacheUpdateDebugAccessKey => 'debug-secret',
			],
			multiClient: $multiClient,
			jobQueueGroupFactory: null,
			logger: null
		);

		$cacheUpdate->executeNow( 'reset', 'examplewiki' );

		$this->assertNotSame( [], $capturedRequests );
		foreach ( $capturedRequests as $request ) {
			$this->assertSame( 'debug-secret', $request['headers']['X-Access-Key'] );
		}
	}

	/**
	 * @covers ::executeNow
	 */
	public function testExecuteNowUrlIncludesDbname(): void {
		$capturedUrl = '';
		$multiClient = $this->createMock( MultiHttpClient::class );
		$multiClient->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ) use ( &$capturedUrl ): array {
				$capturedUrl = (string)reset( $requests )['url'];
				$responses = [];
				foreach ( array_keys( $requests ) as $key ) {
					$responses[$key] = [ 'response' => [ 'code' => 204 ] ];
				}

				return $responses;
			}
		);

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: $multiClient,
			jobQueueGroupFactory: null,
			logger: null
		);
		$cacheUpdate->executeNow( 'reset', 'examplewiki' );

		$this->assertSame(
			'https://central.example.org/w/rest.php/managewiki/v0/cache/reset/examplewiki',
			$capturedUrl
		);
	}

	/**
	 * @covers ::executeNow
	 */
	public function testExecuteNowBodyContainsKey(): void {
		$capturedBody = '';
		$multiClient = $this->createMock( MultiHttpClient::class );
		$multiClient->method( 'runMulti' )->willReturnCallback(
			static function ( array $requests ) use ( &$capturedBody ): array {
				$capturedBody = (string)reset( $requests )['body'];
				$responses = [];
				foreach ( array_keys( $requests ) as $key ) {
					$responses[$key] = [ 'response' => [ 'code' => 204 ] ];
				}

				return $responses;
			}
		);

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: $multiClient,
			jobQueueGroupFactory: null,
			logger: null
		);
		$cacheUpdate->executeNow( 'reset', 'examplewiki' );

		$decoded = json_decode( $capturedBody, true );
		$this->assertIsArray( $decoded );
		$this->assertSame( 'secret-key', $decoded['key'] );
	}

	/**
	 * @covers ::queueJob
	 */
	public function testQueueJobPushesJobWhenAllowed(): void {
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$jobQueueGroup->expects( $this->once() )->method( 'push' );

		$jobQueueGroupFactory = $this->createMock( JobQueueGroupFactory::class );
		$jobQueueGroupFactory->method( 'makeJobQueueGroup' )->willReturn( $jobQueueGroup );

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: null,
			jobQueueGroupFactory: $jobQueueGroupFactory,
			logger: null
		);
		$cacheUpdate->queueJob( 'reset', 'examplewiki' );
	}

	/**
	 * @covers ::queueJob
	 * @covers ::isExecutionAllowed
	 */
	public function testQueueJobDoesNothingWhenNotAllowed(): void {
		$jobQueueGroupFactory = $this->createMock( JobQueueGroupFactory::class );
		$jobQueueGroupFactory->expects( $this->never() )->method( 'makeJobQueueGroup' );

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [ ConfigNames::CacheUpdateServers => [] ],
			multiClient: null,
			jobQueueGroupFactory: $jobQueueGroupFactory,
			logger: null
		);
		$cacheUpdate->queueJob( 'reset', 'examplewiki' );
	}

	/**
	 * @covers ::queueJob
	 * @covers ::isExecutionAllowed
	 */
	public function testQueueJobDoesNothingForInvalidAction(): void {
		$jobQueueGroupFactory = $this->createMock( JobQueueGroupFactory::class );
		$jobQueueGroupFactory->expects( $this->never() )->method( 'makeJobQueueGroup' );

		$cacheUpdate = $this->newCacheUpdate(
			configOverrides: [],
			multiClient: null,
			jobQueueGroupFactory: $jobQueueGroupFactory,
			logger: null
		);
		$cacheUpdate->queueJob( 'not-a-real-action', 'examplewiki' );
	}
}
