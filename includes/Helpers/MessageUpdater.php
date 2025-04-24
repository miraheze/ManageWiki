<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;

class MessageUpdater {

	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly WikiPageFactory $wikiPageFactory
	) {
	}

	public function doUpdate( string $name, string $content, User $user ): void {
		$title = $this->titleFactory->newFromText( $name, NS_MEDIAWIKI );
		$page = $this->wikiPageFactory->newFromTitle( $title );

		$updater = $page->newPageUpdater( $user );
		$updater->setContent(
			SlotRecord::MAIN,
			$page->getContentHandler()->makeContent( $content, $title )
		);

		$comment = CommentStoreComment::newUnsavedComment( $summary );
		$updater->saveRevision( $comment );
	}
}
