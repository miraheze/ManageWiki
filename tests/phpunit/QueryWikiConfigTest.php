<?php

namespace Miraheze\ManageWiki\Tests;

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * @group ManageWiki
 * @group Database
 * @group medium
 * @coversDefaultClass \Miraheze\ManageWiki\Api\QueryWikiConfig
 */
class QueryWikiConfigTest extends ApiTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::execute
	 */
	public function testQueryWikiConfig(): void {
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'wikiconfig',
			'wcfwikis' => 'wikidb',
		], null, null, self::getTestUser()->getUser() );
		$this->addToAssertionCount( 1 );
	}
}
