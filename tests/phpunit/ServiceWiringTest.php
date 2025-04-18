<?php

namespace Miraheze\ManageWiki\Tests;

use Generator;
use MediaWikiIntegrationTestCase;

/**
 * @coversNothing
 * @group ManageWiki
 * @group Database
 * @group medium
 */
class ServiceWiringTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideService
	 */
	public function testService( string $name ): void {
		$this->getServiceContainer()->get( $name );
		$this->addToAssertionCount( 1 );
	}

	public function provideService(): Generator {
		$wiring = require __DIR__ . '/../../includes/ServiceWiring.php';
		foreach ( $wiring as $name => $_ ) {
			yield $name => [ $name ];
		}
	}
}
