<?php

namespace Miraheze\ManageWiki\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Permissions\PermissionManager;
use Miraheze\CreateWiki\Services\CreateWikiValidator;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\ParamValidator\ParamValidator;

class ApiModifyServer extends ApiBase {

	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		private readonly CreateWikiValidator $validator,
		private readonly PermissionManager $permissionManager,
		private readonly RemoteWikiFactory $remoteWikiFactory
	) {
		parent::__construct( $mainModule, $moduleName );
	}

	public function execute(): void {
		$this->useTransactionalTimeLimit();

		if ( !$this->getConfig()->get( ConfigNames::UseCustomDomains ) ) {
			$this->dieWithError( [ 'managewiki-custom-domains-disabled' ] );
		}

		if ( !ManageWiki::checkSetup( 'core' ) ) {
			$this->dieWithError( [ 'managewiki-disabled', 'core' ] );
		}

		$params = $this->extractRequestParams();

		if ( !$this->permissionManager->userHasRight( $this->getUser(), 'managewiki-restricted' ) ) {
			return;
		}

		if ( !$this->validator->databaseExists( $params['wiki'] ) ) {
			$this->dieWithError( [ 'managewiki-invalid-wiki' ] );
		}

		if ( !filter_var( $params['server'], FILTER_VALIDATE_URL ) ) {
			$this->dieWithError( [ 'managewiki-invalid-server' ] );
		}

		$this->setServer( $params['wiki'], $params['server'] );

		$this->getResult()->addValue( null, $this->getModuleName(), $params );
	}

	private function setServer( string $wiki, string $server ): void {
		$remoteWiki = $this->remoteWikiFactory->newInstance( $wiki );
		$remoteWiki->setServerName( $server );
		$remoteWiki->commit();
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
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'wiki' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
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
