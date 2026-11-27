<?php

namespace Miraheze\ManageWiki\Tests\Specials;

use Closure;
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
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\WikiUndeleter;
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
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'UndeleteWiki' );
	}

	public function testTargetWikiRightPermitsUndeletionOnce(): void {
		$this->mockTargetWikiAccount( [ 'wiki-manager' ] );
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

	/** @dataProvider provideUnauthorizedTargetWikiAccounts */
	public function testRequiresRightAndAccountAttachmentOnTargetWiki( array $groups, bool $attached ): void {
		$this->mockTargetWikiAccount( $groups, $attached );

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
		$caller = __METHOD__;
		$this->mockTargetWikiAccount( function () use ( $caller ): array {
			$this->getDb()->newUpdateQueryBuilder()
				->update( 'cw_wikis' )
				->set( [ 'wiki_locked' => 1 ] )
				->where( [ 'wiki_dbname' => self::TARGET_WIKI ] )
				->caller( $caller )
				->execute();
			return [ 'wiki-manager' ];
		} );

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest( [ 'wpdbname' => self::TARGET_WIKI ], true ),
			'qqx', $this->getTestUser()->getAuthority() );

		$this->assertStringContainsString( '(managewiki-mwlocked)', $html );
		$this->assertSame( 1, (int)$this->getWikiState()->wiki_deleted );
		$this->assertSame( 0, $this->countUndeletionLogs() );
	}

	private function mockTargetWikiAccount( array|Closure $groups, bool $attached = true ): void {
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'isAttached' )->willReturnCallback(
			static fn ( UserIdentity $user, string|false $wikiId = UserIdentity::LOCAL ): bool =>
				$wikiId === UserIdentity::LOCAL || ( $wikiId === self::TARGET_WIKI && $attached )
		);
		$identityLookup = $this->createMock( UserIdentityLookup::class );
		$identityLookup->method( 'getUserIdentityByName' )->willReturnCallback(
			static fn ( string $name ): UserIdentity => new UserIdentityValue( 23, $name, self::TARGET_WIKI )
		);
		$actorStoreFactory = $this->createMock( ActorStoreFactory::class );
		$actorStoreFactory->method( 'getUserIdentityLookup' )->with( self::TARGET_WIKI )->willReturn( $identityLookup );
		$groupManager = $this->createMock( UserGroupManager::class );
		$groupManager->method( 'getUserEffectiveGroups' )
			->with( $this->callback( static fn ( UserIdentity $user ): bool =>
				$user->getWikiId() === self::TARGET_WIKI && $user->getId( self::TARGET_WIKI ) === 23
			), IDBAccessObject::READ_LATEST )
			->willReturnCallback( $groups instanceof Closure ? $groups : static fn (): array => $groups );
		$groupManagerFactory = $this->createMock( UserGroupManagerFactory::class );
		$groupManagerFactory->method( 'getUserGroupManager' )->with( self::TARGET_WIKI )->willReturn( $groupManager );

		$services = $this->getServiceContainer();
		$this->setService( 'ManageWikiWikiUndeleter', new WikiUndeleter(
			$services->get( 'ManageWikiDatabaseUtils' ),
			$actorStoreFactory,
			$centralIdLookup,
			$groupManagerFactory,
			$services->get( 'ManageWikiModuleFactory' ),
			static function (): void {
			}
		) );
	}

	private function getWikiState(): object {
		return $this->getDb()->newSelectQueryBuilder()
			->select( [ 'wiki_deleted', 'wiki_deleted_timestamp', 'wiki_private' ] )
			->from( 'cw_wikis' )
			->where( [ 'wiki_dbname' => self::TARGET_WIKI ] )
			->caller( __METHOD__ )
			->fetchRow();
	}

	private function countUndeletionLogs(): int {
		return $this->getDb()->newSelectQueryBuilder()
			->from( 'logging' )
			->where( [ 'log_type' => 'managewiki', 'log_action' => 'undelete' ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}
}
