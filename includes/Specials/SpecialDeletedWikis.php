<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\ManageWiki\Helpers\ManageWikiDeletedWikiPager;

class SpecialDeletedWikis extends SpecialPage {

	public function __construct() {
		parent::__construct( 'DeletedWikis' );
	}

	public function execute( $par ): void {
		$this->setHeaders();
		$this->outputHeader();

		$pager = new ManageWikiDeletedWikiPager( $this );

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}
}
