<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki', 'managewiki' );
	}

	function execute( $par ) {
		global $wgEnableManageWiki, $wgCreateWikiDatabase, $wgDBname;

		$out = $this->getOutput();
		$this->setHeaders();

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
		global $wgCreateWikiCategories, $wgCreateWikiUseCategories, $wgManageWikiExtensions, $wgUser, $wgManageWikiSettings, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis;

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

		if ( $wgManageWikiExtensions ) {
			foreach ( $wgManageWikiExtensions as $name => $ext ) {
				if ( !$ext['conflicts'] ) {
					$formDescriptor["ext-$name"] = array(
						'type' => 'check',
						'label' => $ext['name'],
						'default' => $wiki->hasExtension( $name ),
						'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$ext['restricted'] ) ? 0 : 1,
						'help' => ( $ext['requires'] ) ? "Requires: {$ext['requires']}." : null,
					);
				} else {
					$formDescriptor["ext-$name"] = array(
						'type' => 'check',
						'label' => $ext['name'],
						'default' => $wiki->hasExtension ( $name ),
						'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$ext['restricted'] ) ? 0 : 1,
						'help' => ( $ext['requires'] ) ? "Requires: {$ext['requires']}." . " Conflicts: {$ext['conflicts']}." : "Conflicts: {$ext['conflicts']}.",
					);
				}
			}
		}

		if ( $wgManageWikiSettings ) {
			foreach ( $wgManageWikiSettings as $var => $det ) {
				if ( $det['requires'] && $wiki->hasExtension( $det['requires'] ) || !$det['requires'] ) {
					$formDescriptor["set-$var"] = array(
						'type' => $det['type'],
						'label' => $det['name'],
						'default' => $wiki->getSettingsValue( $var ),
					);
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
		global $wgDBname, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgUser, $wgManageWikiSettings, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis, $wgCreateWikiUseCategories, $wgCreateWikiCategories;

		$dbName = $wgDBname;

		if ( !$this->getUser()->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$this->getUser()->getName()}' without managewiki right tried to change wiki settings!" );
		}

		$wiki = RemoteWiki::newFromName( $params['dbname'] );

		$changedsettingsarray = [];
		$extensionsarray = [];
		foreach ( $wgManageWikiExtensions as $name => $ext ) {
			if ( $ext['conflicts'] && $params["ext-$name"] ) {
				if ( $params["ext-" . $name] === $params["ext-" . $ext['conflicts']] ) {
					return "Conflict with " . $ext['conflicts'] . ". The $name can not be enabled until " . $ext['conflicts'] . " has been disabled.";
				}
			}
			if ( $params["ext-$name"] ) {
				if ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) ) {
					$extensionsarray[] = $name;
				} elseif ( $ext['restricted'] && !$wgUser->isAllowed( 'managewiki-restricted' ) ) {
					if ( $wiki->hasExtension( $name ) ) {
						$extensionsarray[] = $name;
					} else {
						throw new MWException( "User without managewiki-restricted tried to change a restricted setting ($name)" );
					}
				} else {
					$extensionsarray[] = $name;
				}
			} elseif ( $ext['restricted'] && !$wgUser->isAllowed( 'managewiki-restricted' ) ) {
				if ( $wiki->hasExtension( $name ) ) {
					throw new MWException( "User without managewiki-restricted tried to change a restricted setting ($name)" );
				}
			}

			if ( $params["ext-$name"] != $wiki->hasExtension( $name ) ) {
				$changedsettingsarray[] = "ext-" . $name;
			}
		}

		// HACK: dummy extension name
		$extensionsarray[] = "zzzz";

		$extensions = implode( ",", $extensionsarray );

		$settingsarray = [];

		foreach( $wgManageWikiSettings as $var => $det ) {
			if ( $det['type'] != 'text' || $params["set-$var"] ) {
				$settingsarray[$var] = $params["set-$var"];

				if ( $settingsarray[$var] != $wiki->getSettingsValue( $var ) ) {
					$changedsettingsarray[] = "setting-" . $var;
				}
			}
		}

		$settingsjson = json_encode( $settingsarray );

		if ( $wgCreateWikiUsePrivateWikis ) {
			$private = ( $params['private'] == true ) ? 1 : 0;
		} else {
			$private = 0;
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			$closed = ( $params['closed'] == true ) ? 1 : 0;
		} else {
			$closed = 0;
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			$inactive = ( $params['inactive'] == true ) ? 1 : 0;
		} else {
			$inactive = 0;
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
			'wiki_inactive' => $inactive,
			'wiki_private' => $private,
			'wiki_category' => $category,
			'wiki_extensions' => $extensions,
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

		$dbw = wfGetDB( DB_MASTER, array(), $wgCreateWikiDatabase );
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
