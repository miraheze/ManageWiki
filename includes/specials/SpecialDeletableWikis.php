<?php
class SpecialDeletableWikis extends SpecialPage {
	function __construct() {
		parent::__construct( 'DeletableWikis' );
	}

	function execute( $par ) {
		$out = $this->getOutput();
		$this->setHeaders();

		$pager = new ManageWikiDeletableWikiPager();
		$table = $pager->getBody();

		$this->getOutput()->addHTML( $pager->getNavigationBar() . $table . $pager->getNavigationBar() );

	}
}
