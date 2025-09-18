<?php

namespace Miraheze\CreateWiki\RequestWiki\Rest;

use MediaWiki\Config\Config;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use SensitiveParameter
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class ResetWikiCacheHandler extends SimpleHandler {

	public function __construct(
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly Config $config
	) {
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

		$dataStore = $this->dataStoreFactory->newInstance( $dbname );
		$dataStore->resetWikiData( isNewChanges: true );
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
