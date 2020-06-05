<?php
class SpecialDeletedWikis extends SpecialPage {
	public function __construct() {
		parent::__construct( 'DeletedWikis' );
	}

	public function execute( $par ) {
		$this->setHeaders();

		$pager = new ManageWikiDeletedWikiPager();
		$pager->getFullOutput();

	}
}
