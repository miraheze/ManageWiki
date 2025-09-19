<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\ConfigNames;
use SensitiveParameter;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteCacheHandler extends SimpleHandler {

	public function __construct( private readonly Config $config ) {
	}

	public function run(
		string $dbname,
		#[SensitiveParameter]
		string $key
	): Response {
		if ( $key !== $this->config->get( ConfigNames::CacheUpdateKey ) ) {
			return $this->getResponseFactory()->createLocalizedHttpError(
				403, new MessageValue( 'managewiki-rest-invalidkey' )
			);
		}

		$this->config->get( ConfigNames::CacheDirectory )
		if ( file_exists( "$cacheDir/$dbname.php" ) ) {
			unlink( "$cacheDir/$dbname.php" );
		}

		return $this->getResponseFactory()->createNoContent();
	}

	public function needsWriteAccess(): bool {
		return true;
	}

	public function getParamSettings(): array {
		return [
			'dbname' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'key' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
