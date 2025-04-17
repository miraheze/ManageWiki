<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\ManageWiki\DeletedWikisPager;

class SpecialDeletedWikis extends SpecialPage {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
		parent::__construct( 'DeletedWikis' );
	}

	/**
	 * @param ?string $par
	 */
	public function execute( $par ): void {
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
}
