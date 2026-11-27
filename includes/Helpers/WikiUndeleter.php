<?php

namespace Miraheze\ManageWiki\Helpers;

use Closure;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Wikimedia\Rdbms\IDBAccessObject;
use function in_array;
use function mb_strtolower;
use function trim;

class WikiUndeleter {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly CentralIdLookup $centralIdLookup,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ModuleFactory $moduleFactory,
		/** @var Closure(string):void */
		private readonly Closure $refreshWikiData,
	) {
	}

	public function undelete( string $dbname, User $performer ): Status {
		$dbname = mb_strtolower( trim( $dbname ) );
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$wikiQuery = $dbw->newSelectQueryBuilder()
			->from( 'cw_wikis' )
			->where( [ 'wiki_dbname' => $dbname ] )
			->caller( __METHOD__ );
		$wiki = $wikiQuery->select( [ 'wiki_dbname', 'wiki_private' ] )->fetchRow();
		if ( $wiki === false ) {
			return Status::newFatal( 'managewiki-error-missingwiki', $dbname );
		}

		$dbname = $wiki->wiki_dbname;
		if ( !$this->hasLocalManageWikiRight( $performer, $dbname, (bool)$wiki->wiki_private ) ) {
			return Status::newFatal( 'managewiki-undeletewiki-notallowed', $dbname );
		}

		// Check the state in the write so a concurrent lock or undeletion wins if it commits first.
		$dbw->newUpdateQueryBuilder()
			->update( 'cw_wikis' )
			->set( [ 'wiki_deleted' => 0, 'wiki_deleted_timestamp' => null ] )
			->where( [ 'wiki_dbname' => $dbname, 'wiki_deleted' => 1, 'wiki_locked' => 0 ] )
			->caller( __METHOD__ )
			->execute();

		if ( $dbw->affectedRows() === 0 ) {
			$wiki = $wikiQuery->select( [ 'wiki_deleted', 'wiki_locked' ] )->forUpdate()->fetchRow();
			return match ( true ) {
				$wiki === false => Status::newFatal( 'managewiki-error-missingwiki', $dbname ),
				(bool)$wiki->wiki_locked => Status::newFatal( 'managewiki-mwlocked' ),
				!(bool)$wiki->wiki_deleted => Status::newFatal( 'managewiki-undeletewiki-notdeleted', $dbname ),
				default => Status::newFatal( 'managewiki-undeletewiki-statechanged', $dbname ),
			};
		}

		$logEntry = new ManualLogEntry( 'managewiki', 'undelete' );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'ManageWiki', "core/$dbname" ) );
		$logEntry->setParameters( [ '4::wiki' => $dbname ] );
		$logEntry->publish( $logEntry->insert() );

		// The database lists and ManageWiki cache must reflect the committed state.
		DeferredUpdates::addCallableUpdate(
			function () use ( $dbname ): void {
				( $this->refreshWikiData )( $dbname );
			},
			DeferredUpdates::PRESEND,
			$dbw
		);

		return Status::newGood();
	}

	private function hasLocalManageWikiRight( User $performer, string $dbname, bool $isPrivate ): bool {
		if ( $performer->isLocked() || !$this->centralIdLookup->isAttached( $performer ) ||
			!$this->centralIdLookup->isAttached( $performer, $dbname )
		) {
			return false;
		}

		$localUser = $this->actorStoreFactory->getUserIdentityLookup( $dbname )
			->getUserIdentityByName( $performer->getName(), IDBAccessObject::READ_LATEST );
		if ( $localUser === null || !$localUser->isRegistered() ) {
			return false;
		}

		$groups = $this->userGroupManagerFactory->getUserGroupManager( $dbname )
			->getUserEffectiveGroups( $localUser, IDBAccessObject::READ_LATEST );
		$permissions = $this->moduleFactory->permissions( $dbname )->getCachedData( $isPrivate );
		foreach ( $groups as $group ) {
			if ( in_array( 'managewiki-core', $permissions[$group]['permissions'] ?? [], true ) ) {
				return true;
			}
		}

		return false;
	}
}
