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

			$wikiData = [];

			$wikiData['name'] = $wiki;

			if ( isset( $prop['sitename'] ) ) {
				$wikiData['sitename'] = $wikiObj->getSitename();
			}

			if ( isset( $prop['closed'] ) ) {
				$wikiData['closed'] = ( $wikiObj->isClosed() == true ) ? 1 : 0;
			}

			if ( isset( $prop['inactive'] ) ) {
				$wikiData['inactive'] = ( $wikiObj->isInactive() == true ) ? 1 : 0;
			}

			if ( isset( $prop['inactive-exempt'] ) ) {
				$wikiData['inactive-exempt'] = ( $wikiObj->isInactiveExempt() == true ) ? 1 : 0;
			}

			if ( isset( $prop['private'] ) ) {
				$wikiData['private'] = ( $wikiObj->isPrivate() == true ) ? 1 : 0;
			}

			if ( isset( $prop['settings'] ) ) {
				if ( $this->isAllowedToViewConfig( $wikiObj ) ) {
					$wikiData['settings'] = $wikiObj->getSettings();
				} else {
					$this->addWarning( [ 'apiwarn-wikiconfig-nopermission', $wiki ] );
				}
			}

			if ( isset( $prop['extensions'] ) ) {
				if ( $this->isAllowedToViewConfig( $wikiObj ) ) {
					$extensions = explode( ',', $wikiObj->getExtensions() );

					// Delete dummy entry from extensions
					$extensions = array_values( array_diff( $extensions, [ 'zzzz' ] ) );
					$wikiData['extensions'] = $extensions;
				} else {
					$this->addWarning( [ 'apiwarn-wikiconfig-nopermission', $wiki ] );
				}
			}

			if ( isset( $prop['permissions'] ) ) {
				if ( $this->isAllowedToViewConfig( $wikiObj ) ) {
					foreach ( ManageWiki::availableGroups( $wiki ) as $group ) {
						$wikiData['permissions'][$group] = ManageWiki::groupPermissions( $group, $wiki );
					}
				} else {
					$this->addWarning( [ 'apiwarn-wikiconfig-nopermission', $wiki ] );
				}
			}

			$data[] = $wikiData;
		}

		$result->setIndexedTagName( $data, 'wikiconfig' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	public function isAllowedToViewConfig( $wikiObj ) {
		global $wgDBname;

		$oldDB = $wgDBname;
		$username = $this->getUser()->getName();
		$allowed = true;

		if ( !$wikiObj->isPrivate() ) {
			return true;
		}

		if ( $this->getUser()->isAnon() ) {
			return false;
		}

		$wgDBname = $wikiObj->getDBname();

		if ( !User::newFromName( $username )->isAllowedAny( 'read' ) ) {
			$allowed = false;
		}

		$wgDBname = $oldDB;

		return $allowed;
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
					'permissions'
				],
				ApiBase::PARAM_DFLT => 'sitename|extensions|settings',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'wikis' => [
				ApiBase::PARAM_ISMULTI => true,
			],
		];
	}
}
