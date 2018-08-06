<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki', 'managewiki' );
	}

	function execute( $par ) {
		global $wgEnableManageWiki, $wgManageWikiHelpUrl, $wgCreateWikiDatabase, $wgDBname;

		$out = $this->getOutput();
		$this->setHeaders();
		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		if ( !$wgEnableManageWiki ) {
			$out->addWikiMsg( 'managewiki-disabled' );
			return false;
		}

		$this->checkPermissions();

		if ( $wgCreateWikiDatabase !== $wgDBname ) {
			$this->showWikiForm( $wgDBname );
		} elseif ( !is_null( $par ) && $par !== '' ) {
			$this->showWikiForm( $par );
		} else {
			$this->showInputBox();
		}
	}

	function showInputBox() {
		$formDescriptor = array(
			'dbname' => array(
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'required' => true,
				'name' => 'mwDBname',
			)
		);

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'searchForm' );
		$htmlForm->setMethod( 'post' )
			->setSubmitCallback( array( $this, 'onSubmitRedirectToWikiForm' ) )
			->prepareForm()
			->show();

		return true;
	}

	function onSubmitRedirectToWikiForm( array $params ) {
		global $wgRequest;

		if ( $params['dbname'] !== '' ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	function showWikiForm( $wiki ) {
		global $wgCreateWikiCategories, $wgCreateWikiUseCategories, $wgUser, $wgManageWikiSettings, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis;

		$out = $this->getOutput();

		$dbName = $wiki;

		$wiki = RemoteWiki::newFromName( $wiki );

		if ( $wiki == NULL ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-header', $dbName );
		}

		$languages = Language::fetchLanguageNames( null, 'wmfile' );
		ksort( $languages );
		$options = array();
		foreach ( $languages as $code => $name ) {
			$options["$code - $name"] = $code;
		}

		$formDescriptor = array(
			'dbname' => array(
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'default' => $dbName,
				'disabled' => true,
				'name' => 'mwDBname',
			),
			'sitename' => array(
				'label-message' => 'managewiki-label-sitename',
				'type' => 'text',
				'size' => 20,
				'default' => $wiki->getSitename(),
				'required' => true,
				'name' => 'mwSitename',
			),
			'language' => array(
				'label-message' => 'managewiki-label-language',
				'type' => 'select',
				'default' => $wiki->getLanguage(),
				'options' => $options,
				'name' => 'mwLanguage',
			),
		);

		if ( $wgCreateWikiUsePrivateWikis ) {
			$formDescriptor['private'] = array(
				'type' => 'check',
				'label-message' => 'managewiki-label-private',
				'name' => 'cwPrivate',
				'default' => $wiki->isPrivate() ? 1 : 0,
			);
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			$formDescriptor['closed'] = array(
				'type' => 'check',
				'label-message' => 'managewiki-label-closed',
				'name' => 'cwClosed',
				'default' => $wiki->isClosed() ? 1 : 0,
			);
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			$formDescriptor['inactive'] = array(
				'type' => 'check',
				'label-message' => 'managewiki-label-inactive',
				'name' => 'cwInactive',
				'default' => $wiki->isInactive() ? 1 : 0,
			);
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			$formDescriptor['category'] = array(
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $wgCreateWikiCategories,
				'default' => $wiki->getCategory(),
			);
		}

		if ( $wgManageWikiSettings ) {
			foreach ( $wgManageWikiSettings as $var => $det ) {

				if ( $det['requires'] && $wiki->hasExtension( $det['requires'] ) || !$det['requires'] ) {
					switch ( $det['type'] ) {
						case 'check':
						case 'text':
						case 'url':
							$mwtype = $det['type'];
							break;
						case 'list':
							$mwtype = 'select';
							$mwoptions = $det['options'];
							break;
						case 'list-multi':
							$mwtype = 'multiselect';
							$mwoptions = $det['options'];
							break;
						case 'matrix':
							$mwtype = 'checkmatrix';
							$mwcols = $det['cols'];
							$mwrows = $det['rows'];
							break;
						case 'timezone':
							$mwtype = 'select';
							$mwoptions = ManageWiki::getTimezoneList();
						case 'wikipage':
							$mwtype = 'title';
							break;
							
					}

					$formDescriptor["set-$var"] = array(
						'type' => $mwtype,
						'label' => $det['name'],
						'default' => ( !is_null( $wiki->getSettingsValue( $var ) ) ) ? $wiki->getSettingsValue( $var ) : $det['overridedefault'],
						'disabled' => ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] ) ? 0 : 1,
						'help' => ( $det['help'] ) ? $det['help'] : null,
					);

					if ( isset( $mwoptions ) ) {
						$formDescriptor["set-$var"]['options'] = $mwoptions;
					}
				}
			}
		}

		$formDescriptor['reason'] = array(
				'type' => 'text',
				'label-message' => 'managewiki-label-reason',
				'size' => 45,
				'required' => true,
		);

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'changeForm' );
		$htmlForm->setMethod( 'post' )
			->setSubmitCallback( array( $this, 'onSubmitInput' ))
			->prepareForm()
			->show();

	}

	function onSubmitInput( array $params ) {
		global $wgDBname, $wgCreateWikiDatabase, $wgUser, $wgManageWikiSettings, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis, $wgCreateWikiUseCategories, $wgCreateWikiCategories;

		$dbw = wfGetDB( DB_MASTER, array(), $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		if ( !$this->getUser()->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$this->getUser()->getName()}' without managewiki right tried to change wiki settings!" );
		}

		$wiki = RemoteWiki::newFromName( $params['dbname'] );

		$changedsettingsarray = [];

		$settingsarray = [];

		foreach( $wgManageWikiSettings as $var => $det ) {
			if ( $det['type'] != 'text' || $params["set-$var"] ) {
				if ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) ) {
					$settingsarray[$var] = $params["set-$var"];
				} elseif ( $det['restricted'] && !$wgUser->isAllowed( 'managewiki-restricted' ) ) {
					if ( $wiki->getSettingsValue( $var ) ) {
						$settingsarray[$var] = $params["set-$var"];
					} else {
						throw new MWException( "User without managewiki-restricted tried to change a restricted setting ($var)" );
					}
				} else {
					$settingsarray[$var] = $params["set-$var"];
				}
			} elseif ( $det['restricted'] && !$wgUser->isAllowed( 'managewiki-restricted' ) ) {
				if ( $wiki->getSettingsValue( $var ) ) {
					throw new MWException( "User without managewiki-restricted tried to change a restricted extension setting ($var)" );
				}
			}

			if ( $settingsarray[$var] != $wiki->getSettingsValue( $var ) ) {
				$changedsettingsarray[] = "setting-" . $var;
			}
		}

		$settingsjson = json_encode( $settingsarray );

		if ( $wgCreateWikiUsePrivateWikis ) {
			$private = ( $params['private'] == true ) ? 1 : 0;
		} else {
			$private = 0;
		}

		if ( $wgCreateWikiUseClosedWikis && $params['closed'] ) {
			$closed = 1;
			$closedate = $dbw->timestamp();
		} else {
			$closed = 0;
			$closedate = null;
		}

		if ( $wgCreateWikiUseInactiveWikis && $params['inactive'] ) {
			$inactive = 1;
			$inactivedate = $dbw->timestamp();
		} else {
			$inactive = 0;
			$inactivedate = null;
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			$category = $params['category'];
		} else {
			$category = 'uncategorised';
		}

		$values = array(
			'wiki_sitename' => $params['sitename'],
			'wiki_language' => $params['language'],
			'wiki_closed' => $closed,
			'wiki_closed_timestamp' => $closedate,
			'wiki_inactive' => $inactive,
			'wiki_inactive_timestamp' => $inactivedate,
			'wiki_private' => $private,
			'wiki_category' => $category,
			'wiki_settings' => $settingsjson,
		);

		if ( $params['sitename'] != $wiki->getSitename() ) {
			$changedsettingsarray[] = 'sitename';
		}

		if ( $params['language'] != $wiki->getLanguage() ) {
			$changedsettingsarray[] = 'language';
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			if ( $params['closed'] != $wiki->isClosed() ) {
				$changedsettingsarray[] = 'closed';
			}
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			if ( $params['inactive'] != $wiki->isInactive() ) {
				$changedsettingsarray[] = 'inactive';
			}
		}

		if ( $wgCreateWikiUsePrivateWikis ) {
			if ( $params['private'] != $wiki->isPrivate() ) {
				$changedsettingsarray[] = 'private';
			}
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			if ( $params['category'] != $wiki->getCategory() ) {
				$changedsettingsarray[] = 'category';
			}
		}

		$changedsettings = implode( ", ", $changedsettingsarray );

		$dbw->selectDB( $wgCreateWikiDatabase );

		$dbw->update( 'cw_wikis',
			$values,
			array(
				'wiki_dbname' => $params['dbname'],
			),
			__METHOD__
		);

		$dbw->selectDB( $dbName ); // $dbw->close() errors?

		$farmerLogEntry = new ManualLogEntry( 'farmer', 'managewiki' );
		$farmerLogEntry->setPerformer( $this->getUser() );
		$farmerLogEntry->setTarget( $this->getTitle() );
		$farmerLogEntry->setComment( $params['reason'] );
		$farmerLogEntry->setParameters(
			array(
				'4::wiki' => $params['dbname'],
				'5::changes' => $changedsettings,
			)
		);
		$farmerLogID = $farmerLogEntry->insert();
		$farmerLogEntry->publish( $farmerLogID );

		$this->getOutput()->addHTML( '<div class="successbox">' . wfMessage( 'managewiki-success' )->escaped() . '</div>' );

		return true;
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
