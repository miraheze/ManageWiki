<?php

namespace Miraheze\ManageWiki\Api;

use ApiBase;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\RemoteWiki;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\ParamValidator\ParamValidator;

class ApiModifyServer extends ApiBase {
	public function execute() {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$this->useTransactionalTimeLimit();

		if ( !$config->get( 'CreateWikiUseCustomDomains' ) ) {
			$this->dieWithError( [ 'managewiki-custom-domains-disabled' ] );
		}

		if ( !ManageWiki::checkSetup( 'core' ) ) {
			$this->dieWithError( [ 'managewiki-disabled', 'core' ] );
		}

		$params = $this->extractRequestParams();
		$user = $this->getUser();

		if ( $user->getBlock() || $user->getGlobalBlock() || !$permissionManager->userHasRight( $user, 'managewiki-restricted' ) ) {
			return;
		}

		$this->setServer( $params['wiki'], $params['server'] );

		$this->getResult()->addValue( null, $this->getModuleName(), $params );
	}

	private function setServer( string $wiki, string $server ) {
		$wiki = new RemoteWiki( $wiki );

		$wiki->setServerName( $server );

		return true;
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
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

	public function needsToken() {
		return 'csrf';
	}

	protected function getExamplesMessages() {
		return [
			'action=modifyserver&wiki=wiki&server=example.domain.tld&token=123ABC'
				=> 'apihelp-modifyserver-example',
		];
	}
}
