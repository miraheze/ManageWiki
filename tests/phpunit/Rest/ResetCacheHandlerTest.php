<?php

namespace Miraheze\ManageWiki\Tests\Rest;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\DataStore;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Miraheze\ManageWiki\Rest\CacheRestUtils;
use Miraheze\ManageWiki\Rest\ResetCacheHandler;
use function json_encode;

/**
 * @group ManageWiki
 * @coversDefaultClass \Miraheze\ManageWiki\Rest\ResetCacheHandler
 */
class ResetCacheHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private function newRequest( array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	/**
	 * @covers ::run
	 * @covers ::getParamSettings
	 * @covers ::getBodyParamSettings
	 */
	public function testDisabledReturns404(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( false );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'x' ] ),
			[ 'dbname' => 'examplewiki' ]
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	/**
	 * @covers ::run
	 */
	public function testThrottledReturns429(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( true );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'x' ] ),
			[ 'dbname' => 'examplewiki' ]
		);

		$this->assertSame( 429, $response->getStatusCode() );
	}

	/**
	 * @covers ::run
	 */
	public function testInvalidKeyReturns403(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( false );
		$restUtils->expects( $this->once() )->method( 'recordFailure' );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'wrong' ] ),
			[ 'dbname' => 'examplewiki' ]
		);

		$this->assertSame( 403, $response->getStatusCode() );
	}

	/**
	 * @covers ::run
	 */
	public function testInvalidDbnameReturns400(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( true );
		$restUtils->method( 'isValidDbname' )->willReturn( false );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$dataStoreFactory->expects( $this->never() )->method( 'newInstance' );

		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'correct' ] ),
			[ 'dbname' => '../not-a-real-dbname' ]
		);

		$this->assertSame( 400, $response->getStatusCode() );
	}

	/**
	 * @covers ::run
	 */
	public function testUnknownDbnameReturns404(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( true );
		$restUtils->method( 'isValidDbname' )->willReturn( true );

		$dataStore = $this->createMock( DataStore::class );
		$dataStore->method( 'resetWikiData' )
			->willThrowException( new MissingWikiError( 'examplewiki' ) );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$dataStoreFactory->method( 'newInstance' )->willReturn( $dataStore );

		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'correct' ] ),
			[ 'dbname' => 'examplewiki' ]
		);

		$this->assertSame( 404, $response->getStatusCode() );
	}

	/**
	 * @covers ::run
	 */
	public function testSuccessReturns204(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( true );
		$restUtils->method( 'isValidDbname' )->willReturn( true );

		$dataStore = $this->createMock( DataStore::class );
		$dataStore->expects( $this->once() )
			->method( 'resetWikiData' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			->with( false );

		$dataStoreFactory = $this->createMock( DataStoreFactory::class );
		$dataStoreFactory->method( 'newInstance' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			->with( 'examplewiki' )
			->willReturn( $dataStore );

		$handler = new ResetCacheHandler( $restUtils, $dataStoreFactory );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'correct' ] ),
			[ 'dbname' => 'examplewiki' ]
		);

		$this->assertSame( 204, $response->getStatusCode() );
	}
}
