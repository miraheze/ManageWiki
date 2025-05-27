<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\MovePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use RecentChange;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageValue;

class MessageUpdater {

	public function __construct(
		private readonly DeletePageFactory $deletePageFactory,
		private readonly ITextFormatter $textFormatter,
		private readonly MovePageFactory $movePageFactory,
		private readonly TitleFactory $titleFactory,
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	public function doDelete( string $name, User $user ): void {
		$title = $this->titleFactory->newFromText( $name, NS_MEDIAWIKI );
		if ( $title === null || !$title->exists() ) {
			return;
		}

		$reason = $this->textFormatter->format(
			MessageValue::new( 'managewiki-message-deleted' )
		);

		$page = $this->wikiPageFactory->newFromTitle( $title );
		$deletePage = $this->deletePageFactory->newDeletePage( $page, $user );
		$deletePage->deleteUnsafe( $reason );
	}

	public function doMove(
		string $oldName,
		string $newName,
		User $user
	): void {
		$fromTitle = $this->titleFactory->newFromText( $oldName, NS_MEDIAWIKI );
		if ( $fromTitle === null || !$fromTitle->exists() ) {
			return;
		}

		$toTitle = $this->titleFactory->newFromText( $newName, NS_MEDIAWIKI );
		if ( $toTitle === null || !$toTitle->canExist() ) {
			// If we can't move it, we still don't need it.
			$this->doDelete( $oldName, $user );
			return;
		}

		$reason = $this->textFormatter->format(
			MessageValue::new( 'managewiki-message-moved' )
		);

		$movePage = $this->movePageFactory->newMovePage( $fromTitle, $toTitle );
		$movePage->move( $user, $reason, createRedirect: false );
	}

	public function doUpdate(
		string $name,
		string $content,
		User $user
	): void {
		$title = $this->titleFactory->newFromText( $name, NS_MEDIAWIKI );
		if ( $title === null ) {
			return;
		}

		$page = $this->wikiPageFactory->newFromTitle( $title );

		$updater = $page->newPageUpdater( $user );
		$updater->setContent(
			SlotRecord::MAIN,
			$page->getContentHandler()->makeContent( $content, $title )
		);

		$summary = $this->textFormatter->format(
			MessageValue::new( 'managewiki-message-updated' )
		);

		$comment = CommentStoreComment::newUnsavedComment( $summary );

		$updater->setFlags( EDIT_MINOR | EDIT_SUPPRESS_RC );
		$updater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
		$updater->saveRevision( $comment );
	}
}
