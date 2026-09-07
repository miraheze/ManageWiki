<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Regenerates the wiki data cache file on the server that receives the request.
 * POST /managewiki/v0/cache/reset/{dbname}
 */
class ResetCacheHandler extends SimpleHandler {

	public function __construct(
		private readonly CacheRestUtils $restUtils,
		private readonly DataStoreFactory $dataStoreFactory,
	) {
	}

	public function run( string $dbname ): Response {
		if ( !$this->restUtils->isRestEnabled() ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				404, new MessageValue( 'managewiki-rest-disabled' )
			);
		}

		$clientIp = $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? '';
		if ( $this->restUtils->isThrottled( 'reset', $clientIp ) ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				429, new MessageValue( 'managewiki-rest-throttled' )
			);
		}

		$validatedBody = $this->getValidatedBody();

		$key = '';
		if ( $validatedBody ) {
			$key = $validatedBody['key'];
		}

		if ( !$this->restUtils->isValidKey( $key ) ) {
			$this->restUtils->recordFailure( 'reset', $clientIp );
			return $this->getResponseFactory()->createLocalizedHttpError(
				403, new MessageValue( 'managewiki-rest-invalidkey' )
			);
		}

		if ( !$this->restUtils->isValidDbname( $dbname ) ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				400, new MessageValue( 'managewiki-rest-invaliddbname' )
			);
		}

		try {
			$this->dataStoreFactory->newInstance( $dbname )->resetWikiData( isNewChanges: false );
		} catch ( MissingWikiError ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				404, new MessageValue( 'managewiki-rest-unknowndbname' )
			);
		}

		return $this->getResponseFactory()->createNoContent();
	}

	public function needsWriteAccess(): true {
		return true;
	}

	public function getParamSettings(): array {
		return [
			'dbname' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function getBodyParamSettings(): array {
		return [
			'key' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
