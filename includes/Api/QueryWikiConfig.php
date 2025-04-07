<?php

namespace Miraheze\ManageWiki\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\MediaWikiServices;
use Miraheze\CreateWiki\Exceptions\MissingWikiError;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;
use Wikimedia\ParamValidator\ParamValidator;

class QueryWikiConfig extends ApiQueryBase {

	public function __construct(
		ApiQuery $query,
		string $moduleName
	) {
		parent::__construct( $query, $moduleName, 'wcf' );
	}

	public function execute(): void {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$prop = array_flip( $params['prop'] );

		$data = [];

		$remoteWikiFactory = MediaWikiServices::getInstance()->get( 'RemoteWikiFactory' );

		foreach ( $params['wikis'] as $wiki ) {
			try {
				$remoteWiki = $remoteWikiFactory->newInstance( $wiki );
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

			$mwSet = new ManageWikiSettings( $wiki );
			if ( isset( $prop['settings'] ) ) {
				$wikiData['settings'] = $mwSet->list();

				$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'ManageWiki' );

				foreach ( $config->get( 'ManageWikiSettings' ) as $setting => $options ) {
					if ( isset( $options['requires']['visibility']['permissions'] ) ) {
						unset( $wikiData['settings'][$setting] );
					}
				}
			}

			$mwExt = new ManageWikiExtensions( $wiki );
			if ( isset( $prop['extensions'] ) ) {
				$wikiData['extensions'] = $mwExt->list();
			}

			$mwPerms = new ManageWikiPermissions( $wiki );
			if ( isset( $prop['permissions'] ) ) {
				foreach ( $mwPerms->list() as $group => $data ) {
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
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'sitename',
					'inactive',
					'inactive-exempt',
					'closed',
					'private',
					'extensions',
					'settings',
					'permissions'
				],
				ParamValidator::PARAM_DEFAULT => 'sitename|extensions|settings',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'wikis' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => true
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
