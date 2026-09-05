<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Wikimedia\Message\MessageValue;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ParamValidator\ParamValidator;
use function hash_equals;
use function preg_match;

/**
 * Regenerates the wiki data cache file on the server that receives the request.
 * POST /managewiki/v0/cache/reset/{dbname}
 */
class ResetCacheHandler extends SimpleHandler {

	private const string VALID_DBNAME = '/^[a-z][a-z0-9_]{0,63}$/';

	private const int MAX_ATTEMPTS = 10;
	private const int THROTTLE_SECONDS = 60;

	public function __construct(
		private readonly BagOStuff $cache,
		private readonly Config $config,
		private readonly DataStoreFactory $dataStoreFactory,
	) {
	}

	public function run( string $dbname ): Response {
		if ( !$this->config->get( ConfigNames::CacheUpdateRestEnabled ) ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				404, new MessageValue( 'managewiki-rest-disabled' )
			);
		}

		if ( $this->isThrottled() ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				429, new MessageValue( 'managewiki-rest-throttled' )
			);
		}

		$validatedBody = $this->getValidatedBody();

		$key = '';
		if ( $validatedBody ) {
			$key = $validatedBody['key'];
		}

		$configuredKey = (string)$this->config->get( ConfigNames::CacheUpdateKey );

		if ( $configuredKey === '' || !hash_equals( $configuredKey, $key ) ) {
			$this->recordFailure();
			return $this->getResponseFactory()->createLocalizedHttpError(
				403, new MessageValue( 'managewiki-rest-invalidkey' )
			);
		}

		if ( !preg_match( self::VALID_DBNAME, $dbname ) ) {
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

	private function throttleKey(): string {
		$params = $this->getRequest()->getServerParams();
		$clientIp = $params['REMOTE_ADDR'] ?? '';
		return $this->cache->makeGlobalKey( 'ManageWiki', 'cache-reset-attempts', $clientIp );
	}

	private function isThrottled(): bool {
		return (int)$this->cache->get( $this->throttleKey() ) >= self::MAX_ATTEMPTS;
	}

	private function recordFailure(): void {
		$key = $this->throttleKey();
		$attempts = $this->cache->incrWithInit( $key, self::THROTTLE_SECONDS, 1, 1 );
		if ( $attempts === false ) {
			$this->cache->set( $key, 1, self::THROTTLE_SECONDS );
		}
	}

	public function needsWriteAccess(): true {
		return true;
	}

	public function requireSafeAgainstCsrf(): true {
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
