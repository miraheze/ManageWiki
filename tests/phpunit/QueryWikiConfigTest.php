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

	public function addDBDataOnce(): void {
		$dbw = $this->getServiceContainer()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'cw_wikis' )
			->ignore()
			->row( [
				'wiki_dbname' => 'wikidb',
				'wiki_dbcluster' => 'c1',
				'wiki_sitename' => 'TestWiki',
				'wiki_language' => 'en',
				'wiki_private' => (int)0,
				'wiki_creation' => $dbw->timestamp(),
				'wiki_category' => 'uncategorised',
				'wiki_closed' => (int)0,
				'wiki_deleted' => (int)0,
				'wiki_locked' => (int)0,
				'wiki_inactive' => (int)0,
				'wiki_inactive_exempt' => (int)0,
				'wiki_url' => 'http://127.0.0.1:9412',
			] )
			->caller( __METHOD__ )
			->execute();
	}

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
