<?php

namespace Miraheze\ManageWiki\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\ParamValidator\ParamValidator;

class ApiModifyServer extends ApiBase {

	public function execute(): void {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$this->useTransactionalTimeLimit();

		if ( !$config->get( 'ManageWikiUseCustomDomains' ) ) {
			$this->dieWithError( [ 'managewiki-custom-domains-disabled' ] );
		}

		if ( !ManageWiki::checkSetup( 'core' ) ) {
			$this->dieWithError( [ 'managewiki-disabled', 'core' ] );
		}

		$params = $this->extractRequestParams();

		if ( !$permissionManager->userHasRight( $this->getUser(), 'managewiki-restricted' ) ) {
			return;
		}

		if ( !self::validDatabase( $params['wiki'] ) ) {
			$this->dieWithError( [ 'managewiki-invalid-wiki' ] );
		}

		if ( !filter_var( $params['server'], FILTER_VALIDATE_URL ) ) {
			$this->dieWithError( [ 'managewiki-invalid-server' ] );
		}

		$this->setServer( $params['wiki'], $params['server'] );

		$this->getResult()->addValue( null, $this->getModuleName(), $params );
	}

	private function setServer( string $wiki, string $server ): void {
		$remoteWikiFactory = MediaWikiServices::getInstance()->get( 'RemoteWikiFactory' );
		$remoteWiki = $remoteWikiFactory->newInstance( $wiki );
		$remoteWiki->setServerName( $server );
		$remoteWiki->commit();
	}

	private static function validDatabase( string $wiki ): bool {
		$localDatabases = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' )->get( MainConfigNames::LocalDatabases );
		return in_array( $wiki, $localDatabases );
	}

	/** @inheritDoc */
	public function mustBePosted(): bool {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'server' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'wiki' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	public function needsToken(): string {
		return 'csrf';
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=modifyserver&wiki=database_name&server=https://example.com&token=123ABC'
				=> 'apihelp-modifyserver-example',
		];
	}
}
