<?php

namespace Miraheze\ManageWiki\Exceptions;

class MissingWikiError extends ErrorBase {

	public function __construct( string $dbname ) {
		parent::__construct( 'managewiki-error-missingwiki', [ $dbname ] );
	}
}
