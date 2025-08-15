<?php

namespace Miraheze\ManageWiki\Specials;

use ErrorPageError;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\ManageWiki\DeletedWikisPager;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class SpecialDeletedWikis extends SpecialPage {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly ExtensionRegistry $extensionRegistry
	) {
		parent::__construct( 'DeletedWikis' );
	}

	/**
	 * @param ?string $par @phan-unused-param
	 */
	public function execute( $par ): void {
		// TODO: Move this special page to WikiDiscover instead.
		if ( !$this->extensionRegistry->isEnabled( 'CreateWiki' ) ) {
			throw new ErrorPageError( 'nosuchspecialpage', 'nospecialpagetext' );
		}

		$this->setHeaders();
		$this->outputHeader();

		$pager = new DeletedWikisPager(
			$this->databaseUtils,
			$this->getContext(),
			$this->getLinkRenderer()
		);

		$table = $pager->getFullOutput();
		$this->getOutput()->addParserOutputContent( $table );
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return $this->extensionRegistry->isEnabled( 'CreateWiki' );
	}
}
