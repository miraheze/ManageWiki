<?php

namespace Miraheze\ManageWiki\Api;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use Miraheze\CreateWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ModuleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ApiQueryWikiConfig extends ApiQueryBase {

	/**
	 * Initializes the ApiQueryWikiConfig module with the given query, module name, and module factory.
	 *
	 * @param ApiQuery $query The API query object.
	 * @param string $moduleName The name of the API module.
	 * @param ModuleFactory $moduleFactory Factory for creating wiki-related modules.
	 */
	public function __construct(
		ApiQuery $query,
		string $moduleName,
		private readonly ModuleFactory $moduleFactory
	) {
		parent::__construct( $query, $moduleName, 'wcf' );
	}

	/**
	 * Executes the API query to retrieve configuration details for specified wikis.
	 *
	 * For each requested wiki, gathers information such as sitename, closed status, inactive status, inactive exemption, privacy, and optionally settings, extensions, and permissions based on the requested properties. If a wiki does not exist, a warning is added and it is skipped.
	 */
	public function execute(): void {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$prop = array_flip( $params['prop'] );

		$data = [];

		foreach ( $params['wikis'] as $wiki ) {
			try {
				$remoteWiki = $this->moduleFactory->core( $wiki );
			} catch ( MissingWikiError $e ) {
				$this->addWarning( [ 'apiwarn-wikiconfig-wikidoesnotexist', $wiki ] );
				continue;
			}

			$wikiData = [
				'name' => $wiki,
				'sitename' => $remoteWiki->getSitename(),
				'closed' => $remoteWiki->isClosed(),
				'inactive' => $remoteWiki->isInactive(),
				'inactive-exempt' => $remoteWiki->isInactiveExempt(),
				'private' => $remoteWiki->isPrivate(),
			];

			$mwSettings = $this->moduleFactory->settings( $wiki );
			if ( isset( $prop['settings'] ) ) {
				$wikiData['settings'] = $mwSettings->list( var: null );

				foreach ( $this->getConfig()->get( ConfigNames::Settings ) as $setting => $options ) {
					if ( isset( $options['requires']['visibility']['permissions'] ) ) {
						unset( $wikiData['settings'][$setting] );
					}
				}
			}

			$mwExtensions = $this->moduleFactory->extensions( $wiki );
			if ( isset( $prop['extensions'] ) ) {
				$wikiData['extensions'] = $mwExtensions->list();
			}

			$mwPermissions = $this->moduleFactory->permissions( $wiki );
			if ( isset( $prop['permissions'] ) ) {
				foreach ( $mwPermissions->list( group: null ) as $group => $data ) {
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
