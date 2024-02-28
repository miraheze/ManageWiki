<?php

namespace Miraheze\ManageWiki\Specials;

use IncludableSpecialPage;
use Miraheze\ManageWiki\Helpers\ManageWikiInactiveExemptWikiPager;

class SpecialInactivityExemptWikis extends IncludableSpecialPage {
		public function __construct() {
				parent::__construct( 'InactivityExemptWikis' );
		}

		public function execute( $par ) {
				$this->setHeaders();
				$this->outputHeader();

				$pager = new ManageWikiInactiveExemptWikiPager( $this );

				$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
		}
}
