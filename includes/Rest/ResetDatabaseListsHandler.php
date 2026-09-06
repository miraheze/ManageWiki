<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Fires the ManageWikiResetDatabaseLists hook on the server that receives
 * the request. ManageWiki doesn't know what a database list even is, or
 * where it lives, whatever extension manages wiki creation implements the
 * hook and does the actual regeneration.
 * POST /managewiki/v0/cache/reset-database-lists
 */
class ResetDatabaseListsHandler extends SimpleHandler {

	public function __construct(
		private readonly CacheRestUtils $restUtils,
		private readonly HookRunner $hookRunner,
	) {
	}

	public function run(): Response {
		if ( !$this->restUtils->isRestEnabled() ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				404, new MessageValue( 'managewiki-rest-disabled' )
			);
		}

		$clientIp = $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? '';
		if ( $this->restUtils->isThrottled( 'reset-database-lists', $clientIp ) ) {
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
			$this->restUtils->recordFailure( 'reset-database-lists', $clientIp );
			return $this->getResponseFactory()->createLocalizedHttpError(
				403, new MessageValue( 'managewiki-rest-invalidkey' )
			);
		}

		$this->hookRunner->onManageWikiResetDatabaseLists();
		return $this->getResponseFactory()->createNoContent();
	}

	public function needsWriteAccess(): true {
		return true;
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
