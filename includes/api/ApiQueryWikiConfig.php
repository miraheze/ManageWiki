<?php
class ApiQueryWikiConfig extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'wcf' );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		$prop = array_flip( $params['prop'] );

		$data = [];

		foreach ( $params['wikis'] as $wiki ) {
			$wikiObj = RemoteWiki::newFromName( $wiki );

			if ( is_null( $wikiObj ) ) {
				$this->addWarning( [ 'apiwarn-wikiconfig-wikidoesnotexist', $wiki ] );
				continue;
			}

			$wikiData = [
				'name' => $wiki,
				'sitename' => $wikiObj->getSitename(),
				'closed' => (int)$wikiObj->isClosed(),
				'inactive' => (int)$wikiObj->isInactive(),
				'inactive-exempt' => (int)$wikiObj->isInactiveExempt(),
				'private' => (int)$wikiObj->isPrivate()
			];

			if ( isset( $prop['settings'] ) ) {
				$wikiData['settings'] = $wikiObj->getSettings();
			}

			if ( isset( $prop['extensions'] ) ) {
				$extensions = explode( ',', $wikiObj->getExtensions() );

				// Delete dummy entry from extensions
				$extensions = array_values( array_diff( $extensions, [ 'zzzz' ] ) );
				$wikiData['extensions'] = $extensions;
			}

			if ( isset( $prop['namespaces'] ) ) {
				$namespaces = ManageWikiNamespaces::configurableNamespaces( true, true, true );
				foreach ( $namespaces as $id => $namespace ) {
					$options[$namespace] = $id;
				}
			}

			if ( isset( $prop['permissions'] ) ) {
				foreach ( ManageWikiPermissions::availableGroups( $wiki ) as $group ) {
					$wikiData['permissions'][$group] = ManageWikiPermissions::groupPermissions( $group, $wiki );
				}
			}

			$data[] = $wikiData;
		}

		$result->setIndexedTagName( $data, 'wikiconfig' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => [
					'sitename',
					'inactive',
					'inactive-exempt',
					'closed',
					'private',
					'extensions',
					'settings',
					'namespaces',
					'permissions'
				],
				ApiBase::PARAM_DFLT => 'sitename|extensions|settings',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'wikis' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_REQUIRED => true
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=wikiconfig&wcfwikis=metawiki'
				=> 'apihelp-query+wikiconfig-example-1',
		];
	}
}
