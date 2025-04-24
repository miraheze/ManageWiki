<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\DeletePageFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use Wikimedia\Message\ITextFormatter;
use Wikimedia\Message\MessageValue;

class MessageUpdater {

	public function __construct(
		private readonly DeletePageFactory $deletePageFactory,
		private readonly ITextFormatter $textFormatter,
		private readonly TitleFactory $titleFactory,
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	public function doDelete(
		string $name,
		string $reason,
		User $user
	): void {
		$title = $this->titleFactory->newFromText( $name, NS_MEDIAWIKI );
		if ( $title === null || !$title->exists() ) {
			return;
		}

		$reason = $this->textFormatter->format( MessageValue::new( $reason ) );

		$page = $this->wikiPageFactory->newFromTitle( $title );
		$deletePage = $this->deletePageFactory->newDeletePage( $page, $user );
		$deletePage->deleteUnsafe( $reason );
	}

	public function doUpdate(
		string $name,
		string $content,
		string $summary,
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

		$summary = $this->textFormatter->format( MessageValue::new( $summary ) );

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$updater->setFlags( EDIT_SUPPRESS_RC | EDIT_INTERNAL );
		$updater->saveRevision( $comment );
	}
}
