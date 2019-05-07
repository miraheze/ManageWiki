<?php
class SpecialDeletedWikis extends SpecialPage {
	function __construct() {
		parent::__construct( 'DeletedWikis' );
	}

	function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();

		$pager = new ManageWikiDeletedWikiPager();
		$table = $pager->getBody();

		$this->getOutput()->addHTML( $pager->getNavigationBar() . $table . $pager->getNavigationBar() );

	}
}
