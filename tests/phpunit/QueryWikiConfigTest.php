<?php

/**
 * @group ManageWiki
 * @group Database
 * @group medium
 * @coversDefaultClass ApiQueryWikiConfig
 */
class QueryWikiConfigTest extends ApiTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::execute
	 */
	public function testQueryWikiConfig() {
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'wikiconfig',
			'wcfwikis' => 'wikidb',
		], null, null, self::getTestUser()->getUser() );
		$this->addToAssertionCount( 1 );
	}
}
