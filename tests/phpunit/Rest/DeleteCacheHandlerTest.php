<?php

namespace Miraheze\ManageWiki\Tests\Rest;

use MediaWiki\Config\HashConfig;
use MediaWiki\MainConfigNames;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWikiIntegrationTestCase;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Rest\CacheRestUtils;
use Miraheze\ManageWiki\Rest\DeleteCacheHandler;
use function file_exists;
use function file_put_contents;
use function json_encode;
use function mt_rand;
use function sys_get_temp_dir;
use function unlink;

/**
 * @group ManageWiki
 * @coversDefaultClass \Miraheze\ManageWiki\Rest\DeleteCacheHandler
 */
class DeleteCacheHandlerTest extends MediaWikiIntegrationTestCase {

	use HandlerTestTrait;

	private ?string $cacheDir = null;

	protected function tearDown(): void {
		if ( $this->cacheDir !== null && file_exists( $this->cacheDir ) ) {
			unlink( $this->cacheDir );
		}

		parent::tearDown();
	}

	private function newRequest( array $body ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'bodyContents' => json_encode( $body ),
			'headers' => [ 'Content-Type' => 'application/json' ],
		] );
	}

	private function newConfig(): HashConfig {
		return new HashConfig( [
			ConfigNames::CacheDirectory => sys_get_temp_dir(),
			MainConfigNames::CacheDirectory => sys_get_temp_dir(),
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

		$handler = new DeleteCacheHandler( $restUtils, $this->newConfig() );

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

		$handler = new DeleteCacheHandler( $restUtils, $this->newConfig() );

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

		$handler = new DeleteCacheHandler( $restUtils, $this->newConfig() );

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

		$handler = new DeleteCacheHandler( $restUtils, $this->newConfig() );

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
	public function testSuccessDeletesExistingFile(): void {
		$dbname = 'managewikitest' . mt_rand( 1000, 9999 );
		$cacheDir = sys_get_temp_dir();
		$path = "$cacheDir/$dbname.php";

		file_put_contents( $path, '<?php return [];' );
		$this->assertFileExists( $path );
		$this->cacheDir = $path;

		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( true );
		$restUtils->method( 'isValidDbname' )->willReturn( true );

		$config = new HashConfig( [
			ConfigNames::CacheDirectory => $cacheDir,
			MainConfigNames::CacheDirectory => $cacheDir,
		] );

		$handler = new DeleteCacheHandler( $restUtils, $config );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'correct' ] ),
			[ 'dbname' => $dbname ]
		);

		$this->assertSame( 204, $response->getStatusCode() );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * @covers ::run
	 */
	public function testSuccessWhenFileDoesNotExist(): void {
		$restUtils = $this->createMock( CacheRestUtils::class );
		$restUtils->method( 'isRestEnabled' )->willReturn( true );
		$restUtils->method( 'isThrottled' )->willReturn( false );
		$restUtils->method( 'isValidKey' )->willReturn( true );
		$restUtils->method( 'isValidDbname' )->willReturn( true );

		$handler = new DeleteCacheHandler( $restUtils, $this->newConfig() );

		$response = $this->executeHandler(
			$handler,
			$this->newRequest( [ 'key' => 'correct' ] ),
			[ 'dbname' => 'awikithatwasneverwritten' ]
		);

		$this->assertSame( 204, $response->getStatusCode() );
	}
}
