<?php

namespace Miraheze\ManageWiki\Exceptions;

use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Exception\MWExceptionRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;

abstract class ErrorBase extends ErrorPageError {

	protected function __construct( string $msg, array $params ) {
		$this->msg = $msg;
		$this->params = $params;
		$errorBody = new RawMessage( Html::errorBox( $this->getMessageObject()->parse() ) );
		if ( self::isCommandLine() ) {
			$fallback = $this->getMessageObject()->inContentLanguage()->text();
			$errorBody = new RawMessage( MWExceptionRenderer::msg( $msg, $fallback, $params ) );
		}

		parent::__construct( 'errorpagetitle', $errorBody, [] );
	}
}
