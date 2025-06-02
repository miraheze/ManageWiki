<?php

namespace Miraheze\ManageWiki\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use function array_flip;

class ApiQueryWikiConfig extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly ModuleFactory $moduleFactory
	) {
		parent::__construct( $query, $moduleName, 'wcf' );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$prop = array_flip( $params['prop'] );

		$data = [];
		foreach ( $params['wikis'] as $dbname ) {
			try {
				$mwCore = $this->moduleFactory->core( $dbname );
			} catch ( MissingWikiError ) {
				$this->addWarning( [ 'apiwarn-wikiconfig-wikidoesnotexist', $dbname ] );
				continue;
			}

			$wikiData = [
				'name' => $dbname,
				'sitename' => $mwCore->getSitename(),
				'closed' => $mwCore->isClosed(),
				'inactive' => $mwCore->isInactive(),
				'inactive-exempt' => $mwCore->isInactiveExempt(),
				'private' => $mwCore->isPrivate(),
			];

			if ( isset( $prop['settings'] ) ) {
				$mwSettings = $this->moduleFactory->settings( $dbname );
				$wikiData['settings'] = $mwSettings->listAll();

				foreach ( $this->getConfig()->get( ConfigNames::Settings ) as $setting => $options ) {
					if ( isset( $options['requires']['visibility']['permissions'] ) ) {
						unset( $wikiData['settings'][$setting] );
					}
				}
			}

			if ( isset( $prop['extensions'] ) ) {
				$mwExtensions = $this->moduleFactory->extensions( $dbname );
				$wikiData['extensions'] = $mwExtensions->list();
			}

			if ( isset( $prop['permissions'] ) ) {
				$mwPermissions = $this->moduleFactory->permissions( $dbname );
				foreach ( $mwPermissions->listAll() as $group => $data ) {
					$wikiData['permissions'][$group] = $data['permissions'];
				}
			}

			$data[] = $wikiData;
		}

		$result->setIndexedTagName( $data, 'wikiconfig' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/** @inheritDoc */
	protected function getAllowedParams(): array {
		return [
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'extensions|settings|sitename',
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'closed',
					'extensions',
					'inactive',
					'inactive-exempt',
					'permissions',
					'private',
					'settings',
					'sitename',
				],
			],
			'wikis' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages(): array {
		return [
			'action=query&list=wikiconfig&wcfwikis=metawiki'
				=> 'apihelp-query+wikiconfig-example-1',
		];
	}
}
