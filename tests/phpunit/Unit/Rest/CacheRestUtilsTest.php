<?php

namespace Miraheze\ManageWiki\Tests\Unit\Rest;

use MediaWiki\Config\ServiceOptions;
use MediaWikiUnitTestCase;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Rest\CacheRestUtils;
use Wikimedia\ObjectCache\BagOStuff;

/**
 * @group ManageWiki
 * @coversDefaultClass \Miraheze\ManageWiki\Rest\CacheRestUtils
 */
class CacheRestUtilsTest extends MediaWikiUnitTestCase {

	private function newUtils( string $key, bool $enabled, BagOStuff $cache ): CacheRestUtils {
		$options = $this->createMock( ServiceOptions::class );
		$options->method( 'assertRequiredOptions' )->willReturn( null );
		$options->method( 'get' )->willReturnMap( [
			[ ConfigNames::CacheUpdateKey, $key ],
			[ ConfigNames::CacheUpdateRestEnabled, $enabled ],
		] );

		return new CacheRestUtils( $cache, $options );
	}

	/**
	 * @covers ::__construct
	 * @covers ::isRestEnabled
	 */
	public function testIsRestEnabledTrue(): void {
		$cache = $this->createMock( BagOStuff::class );
		$this->assertTrue( $this->newUtils( 'secret', true, $cache )->isRestEnabled() );
	}

	/**
	 * @covers ::isRestEnabled
	 */
	public function testIsRestEnabledFalse(): void {
		$cache = $this->createMock( BagOStuff::class );
		$this->assertFalse( $this->newUtils( 'secret', false, $cache )->isRestEnabled() );
	}

	/**
	 * @covers ::isValidKey
	 */
	public function testIsValidKeyCorrect(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( 'correct-key', true, $cache );
		$this->assertTrue( $utils->isValidKey( 'correct-key' ) );
	}

	/**
	 * @covers ::isValidKey
	 */
	public function testIsValidKeyIncorrect(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( 'correct-key', true, $cache );
		$this->assertFalse( $utils->isValidKey( 'wrong-key' ) );
	}

	/**
	 * @covers ::isValidKey
	 */
	public function testIsValidKeyEmptyConfiguredKey(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( '', true, $cache );
		$this->assertFalse( $utils->isValidKey( '' ) );
	}

	/**
	 * @covers ::isValidDbname
	 */
	public function testIsValidDbnameAccepted(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( 'secret', true, $cache );
		$this->assertTrue( $utils->isValidDbname( 'examplewiki' ) );
	}

	/**
	 * @covers ::isValidDbname
	 */
	public function testIsValidDbnameRejectsUppercase(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( 'secret', true, $cache );
		$this->assertFalse( $utils->isValidDbname( 'ExampleWiki' ) );
	}

	/**
	 * @covers ::isValidDbname
	 */
	public function testIsValidDbnameRejectsPathTraversal(): void {
		$cache = $this->createMock( BagOStuff::class );
		$utils = $this->newUtils( 'secret', true, $cache );
		$this->assertFalse( $utils->isValidDbname( '../../etc/passwd' ) );
	}

	/**
	 * @covers ::isThrottled
	 */
	public function testIsThrottledFalseBelowLimit(): void {
		$cache = $this->createMock( BagOStuff::class );
		$cache->method( 'makeGlobalKey' )->willReturn( 'cache-key' );
		$cache->method( 'get' )->willReturn( 5 );

		$utils = $this->newUtils( 'secret', true, $cache );
		$this->assertFalse( $utils->isThrottled( 'reset', '127.0.0.1' ) );
	}

	/**
	 * @covers ::isThrottled
	 */
	public function testIsThrottledTrueAtLimit(): void {
		$cache = $this->createMock( BagOStuff::class );
		$cache->method( 'makeGlobalKey' )->willReturn( 'cache-key' );
		$cache->method( 'get' )->willReturn( 10 );

		$utils = $this->newUtils( 'secret', true, $cache );
		$this->assertTrue( $utils->isThrottled( 'reset', '127.0.0.1' ) );
	}

	/**
	 * @covers ::recordFailure
	 */
	public function testRecordFailureIncrementsExistingCounter(): void {
		$cache = $this->createMock( BagOStuff::class );
		$cache->method( 'makeGlobalKey' )->willReturn( 'cache-key' );
		$cache->expects( $this->once() )
			->method( 'incrWithInit' )
			->willReturn( 1 );
		$cache->expects( $this->never() )->method( 'set' );

		$utils = $this->newUtils( 'secret', true, $cache );
		$utils->recordFailure( 'reset', '127.0.0.1' );
	}

	/**
	 * @covers ::recordFailure
	 */
	public function testRecordFailureFallsBackToSetWhenIncrFails(): void {
		$cache = $this->createMock( BagOStuff::class );
		$cache->method( 'makeGlobalKey' )->willReturn( 'cache-key' );
		$cache->method( 'incrWithInit' )->willReturn( false );
		$cache->expects( $this->once() )
			->method( 'set' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			->with( 'cache-key', 1, $this->anything() );

		$utils = $this->newUtils( 'secret', true, $cache );
		$utils->recordFailure( 'reset', '127.0.0.1' );
	}
}
