<?php

namespace Miraheze\ManageWiki\Tests\Specials;

use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Miraheze\CreateWiki\Hooks\CreateWikiHookRunner;
use Miraheze\CreateWiki\Services\CreateWikiDataStore;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\WikiUndeleter;
use stdClass;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * @covers \Miraheze\ManageWiki\Specials\SpecialUndeleteWiki
 * @covers \Miraheze\ManageWiki\Helpers\WikiUndeleter
 * @group Database
 */
class SpecialUndeleteWikiTest extends SpecialPageTestBase {

	private const string TARGET_WIKI = 'undeletetestwiki';

	protected function setUp(): void {
		parent::setUp();
		if ( !$this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'CreateWiki' ) ) {
			$this->markTestSkipped( 'CreateWiki is required.' );
		}
		$this->overrideConfigValues( [
			ConfigNames::EnableUndeleteWiki => true,
			ConfigNames::PermissionsAdditionalRights => [
				'wiki-manager' => [ 'managewiki-core' => true ],
			],
		] );
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cw_wikis' )
			->row( [
				'wiki_dbname' => self::TARGET_WIKI,
				'wiki_sitename' => 'Undeletion test',
				'wiki_language' => 'en',
				'wiki_creation' => '20260101000000',
				'wiki_category' => '',
				'wiki_private' => 1,
				'wiki_deleted' => 1,
				'wiki_deleted_timestamp' => '20261101000000',
			] )
			->caller( __METHOD__ )
			->execute();
	}

	protected function newSpecialPage(): SpecialPage {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'UndeleteWiki' );
		$this->assertNotNull( $page );
		return $page;
	}

	public function testTargetWikiRightPermitsUndeletionOnce(): void {
		$this->mockTargetWikiAccount( [ 'wiki-manager' ], attached: true, lockDuringAuthorization: false );
		$user = $this->getTestUser()->getAuthority();
		$form = [ 'wpdbname' => ' UNDELETETESTWIKI ' ];

		$this->executeSpecialPage( '', new FauxRequest( $form ), 'qqx', $user );
		$this->executeSpecialPage( '', new FauxRequest( $form + [ 'wpEditToken' => 'invalid' ], true ), 'qqx', $user );
		$this->assertSame( 1, (int)$this->getWikiState()->wiki_deleted );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( $form, true ), 'qqx', $user );
		$this->assertStringContainsString( '(managewiki-undeletewiki-success)', $html );
		$wiki = $this->getWikiState();
		$this->assertSame( 0, (int)$wiki->wiki_deleted );
		$this->assertNull( $wiki->wiki_deleted_timestamp );
		$this->assertSame( 1, (int)$wiki->wiki_private );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( $form, true ), 'qqx', $user );
		$this->assertStringContainsString( '(managewiki-undeletewiki-notdeleted', $html );
		$this->assertSame( 1, $this->countUndeletionLogs() );
	}

	/**
	 * @dataProvider provideUnauthorizedTargetWikiAccounts
	 * @param list<string> $groups
	 */
	public function testRequiresRightAndAccountAttachmentOnTargetWiki( array $groups, bool $attached ): void {
		$this->mockTargetWikiAccount( $groups, $attached, lockDuringAuthorization: false );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'wpdbname' => self::TARGET_WIKI ], true ),
			'qqx', $this->getTestUser( [ 'wiki-manager' ] )->getAuthority() );

		$this->assertStringContainsString( '(managewiki-undeletewiki-notallowed', $html );
		$this->assertSame( 1, (int)$this->getWikiState()->wiki_deleted );
		$this->assertSame( 0, $this->countUndeletionLogs() );
	}

	public static function provideUnauthorizedTargetWikiAccounts(): iterable {
		yield 'bureaucrat without the right' => [ [ 'bureaucrat' ], true ];
		yield 'same name without attachment' => [ [ 'wiki-manager' ], false ];
	}

	public function testWikiLockedDuringAuthorizationCannotBeUndeleted(): void {
		$this->mockTargetWikiAccount( [ 'wiki-manager' ], attached: true, lockDuringAuthorization: true );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'wpdbname' => self::TARGET_WIKI ], true ),
			'qqx', $this->getTestUser()->getAuthority() );

		$this->assertStringContainsString( '(managewiki-mwlocked)', $html );
		$this->assertSame( 1, (int)$this->getWikiState()->wiki_deleted );
		$this->assertSame( 0, $this->countUndeletionLogs() );
	}

	/** @param list<string> $groups */
	private function mockTargetWikiAccount( array $groups, bool $attached, bool $lockDuringAuthorization ): void {
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'isAttached' )->willReturnCallback(
			static fn ( UserIdentity $user, string|false $wikiId ): bool =>
				$user->isRegistered() && ( $wikiId === UserIdentity::LOCAL ||
					( $wikiId === self::TARGET_WIKI && $attached ) )
		);
		$identityLookup = $this->createMock( UserIdentityLookup::class );
		$identityLookup->method( 'getUserIdentityByName' )->willReturnCallback(
			static fn ( string $name ): UserIdentityValue => new UserIdentityValue( 23, $name, self::TARGET_WIKI )
		);
		$actorStoreFactory = $this->createMock( ActorStoreFactory::class );
		$actorStoreFactory->method( 'getUserIdentityLookup' )->willReturnCallback(
			static function ( string $dbname ) use ( $identityLookup ): UserIdentityLookup {
				self::assertSame( self::TARGET_WIKI, $dbname );
				return $identityLookup;
			}
		);
		$groupManager = $this->createMock( UserGroupManager::class );
		$caller = __METHOD__;
		$groupManager->method( 'getUserEffectiveGroups' )->willReturnCallback(
			/** @return list<string> */
			function ( UserIdentity $user, int $flags ) use ( $groups, $lockDuringAuthorization, $caller ): array {
				self::assertSame( self::TARGET_WIKI, $user->getWikiId() );
				self::assertSame( 23, $user->getId( self::TARGET_WIKI ) );
				self::assertSame( IDBAccessObject::READ_LATEST, $flags );
				if ( $lockDuringAuthorization ) {
					$this->getDb()->newUpdateQueryBuilder()
						->update( 'cw_wikis' )
						->set( [ 'wiki_locked' => 1 ] )
						->where( [ 'wiki_dbname' => self::TARGET_WIKI ] )
						->caller( $caller )
						->execute();
				}
				return $groups;
			}
		);
		$groupManagerFactory = $this->createMock( UserGroupManagerFactory::class );
		$groupManagerFactory->method( 'getUserGroupManager' )->willReturnCallback(
			static function ( string $dbname ) use ( $groupManager ): UserGroupManager {
				self::assertSame( self::TARGET_WIKI, $dbname );
				return $groupManager;
			}
		);

		$services = $this->getServiceContainer();
		$this->setService( 'ManageWikiWikiUndeleter', new WikiUndeleter(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$actorStoreFactory,
			$centralIdLookup,
			$groupManagerFactory,
			$services->get( 'ManageWikiModuleFactory' ),
			$this->createMock( CreateWikiDataStore::class ),
			$this->createMock( CreateWikiHookRunner::class )
		) );
	}

	private function getWikiState(): stdClass {
		$wiki = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'wiki_deleted', 'wiki_deleted_timestamp', 'wiki_private' ] )
			->from( 'cw_wikis' )
			->where( [ 'wiki_dbname' => self::TARGET_WIKI ] )
			->caller( __METHOD__ )
			->fetchRow();
		$this->assertNotFalse( $wiki );
		return $wiki;
	}

	private function countUndeletionLogs(): int {
		return $this->getDb()->newSelectQueryBuilder()
			->from( 'logging' )
			->where( [ 'log_type' => 'managewiki', 'log_action' => 'undelete' ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}
}
