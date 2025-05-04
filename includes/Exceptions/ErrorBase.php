<?php

namespace Miraheze\ManageWiki\Exceptions;

use ErrorPageError;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MWExceptionRenderer;

abstract class ErrorBase extends ErrorPageError {

	protected function __construct( string $msg, array $params ) {
		$errorBody = new RawMessage( Html::errorBox( wfMessage( $msg, $params )->parse() ) );
		if ( self::isCommandLine() ) {
			$fallback = wfMessage( $msg, $params )->inContentLanguage()->text();
			$errorBody = new RawMessage( MWExceptionRenderer::msg( $msg, $fallback, $params ) );
		}

		parent::__construct( 'errorpagetitle', $errorBody, [] );
	}
}
