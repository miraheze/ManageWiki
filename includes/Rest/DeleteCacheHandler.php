<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use function file_exists;
use function unlink;

/**
 * Deletes the wiki data cache file on the server that receives the request.
 * POST /managewiki/v0/cache/delete/{dbname}
 */
class DeleteCacheHandler extends SimpleHandler {

	public function __construct(
		private readonly CacheRestUtils $restUtils,
		private readonly Config $config,
	) {
	}

	public function run( string $dbname ): Response {
		if ( !$this->restUtils->isRestEnabled() ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				404, new MessageValue( 'managewiki-rest-disabled' )
			);
		}

		$clientIp = $this->getRequest()->getServerParams()['REMOTE_ADDR'] ?? '';
		if ( $this->restUtils->isThrottled( 'delete', $clientIp ) ) {
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
			$this->restUtils->recordFailure( 'delete', $clientIp );
			return $this->getResponseFactory()->createLocalizedHttpError(
				403, new MessageValue( 'managewiki-rest-invalidkey' )
			);
		}

		if ( !$this->restUtils->isValidDbname( $dbname ) ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				400, new MessageValue( 'managewiki-rest-invaliddbname' )
			);
		}

		$cacheDir = $this->config->get( ConfigNames::CacheDirectory ) ?:
			$this->config->get( MainConfigNames::CacheDirectory );

		$path = "$cacheDir/$dbname.php";
		if ( file_exists( $path ) ) {
			unlink( $path );
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
