<?php

namespace Miraheze\ManageWiki\Tests\Unit\Jobs;

use MediaWikiUnitTestCase;
use Miraheze\ManageWiki\Helpers\CacheUpdate;
use Miraheze\ManageWiki\Jobs\CacheUpdateJob;

/**
 * @group ManageWiki
 * @coversDefaultClass \Miraheze\ManageWiki\Jobs\CacheUpdateJob
 */
class CacheUpdateJobTest extends MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunReset(): void {
		$cacheUpdate = $this->createMock( CacheUpdate::class );
		$cacheUpdate->expects( $this->once() )
			->method( 'executeNow' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			->with( 'reset', 'examplewiki' )
			->willReturn( true );

		$job = new CacheUpdateJob(
			[ 'action' => 'reset', 'dbname' => 'examplewiki' ],
			$cacheUpdate
		);

		$this->assertTrue( $job->run() );
	}

	/**
	 * @covers ::__construct
	 * @covers ::run
	 */
	public function testRunDeleteFailure(): void {
		$cacheUpdate = $this->createMock( CacheUpdate::class );
		$cacheUpdate->expects( $this->once() )
			->method( 'executeNow' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentProbablyReal
			->with( 'delete', 'examplewiki' )
			->willReturn( false );

		$job = new CacheUpdateJob(
			[ 'action' => 'delete', 'dbname' => 'examplewiki' ],
			$cacheUpdate
		);

		$this->assertFalse( $job->run() );
	}
}
