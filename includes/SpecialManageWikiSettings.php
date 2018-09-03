<?php
class SpecialManageWikiSettings extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWikiSettings', 'managewiki' );
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
		global $wgUser, $wgManageWikiSettings;

		$out = $this->getOutput();

		$dbName = $wiki;

		$wiki = RemoteWiki::newFromName( $wiki );

		if ( $wiki == NULL ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-settings-header', $dbName );
		}

		$languages = Language::fetchLanguageNames( null, 'wmfile' );
		ksort( $languages );
		$options = array();
		foreach ( $languages as $code => $name ) {
			$options["$code - $name"] = $code;
		}

		$formDescriptor = array();

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
							break;
						case 'wikipage':
							$mwtype = 'title';
							break;
					}

					$formDescriptor["set-$var"] = array(
						'type' => $mwtype,
						'label' => $det['name'],
						'disabled' => ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] ) ? 0 : 1,
						'help' => ( $det['help'] ) ? $det['help'] : null,
					);

					if ( $mwtype != 'matrix' ) {
						$formDescriptor["set-$var"]['default'] = ( !is_null( $wiki->getSettingsValue( $var ) ) ) ? $wiki->getSettingsValue( $var ) : $det['overridedefault'];
					} else {
						$formDescriptor["set-$var"]['default'] = ( !is_null( $wiki->getSettingsValue( $var ) ) ) ? ManageWiki::handleMatrix( $wiki->getSettingsValue( $var ), 'php' ) : $det['overridedefault'];
					}

					if ( isset( $mwoptions ) ) {
						$formDescriptor["set-$var"]['options'] = $mwoptions;
					}

					if ( isset( $mwcols ) ) {
						$formDescriptor["set-$var"]['columns'] = $mwcols;
					}

					if ( isset( $mwrows ) ) {
						$formDescriptor["set-$var"]['rows'] = $mwrows;
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
		global $wgDBname, $wgCreateWikiDatabase, $wgUser, $wgManageWikiSettings;

		$dbw = wfGetDB( DB_MASTER, array(), $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		if ( !$this->getUser()->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$this->getUser()->getName()}' without managewiki right tried to change wiki settings!" );
		}

		$wiki = RemoteWiki::newFromName( $params['dbname'] );

		$changedsettingsarray = [];

		$settingsarray = [];

		foreach( $wgManageWikiSettings as $var => $det ) {
			$rmVar = $wiki->getSettingsValue( $var );
			
			if ( $det['type'] == 'matrix' ) {
				if ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] ) {
					$settingsarray[$var] = ManageWiki::handleMatrix( $params["set-$var"], 'phparray' );
				} else {
					$settingsarray[$var] = ManageWiki::handleMatrix( $rmVar, 'php' );
				}

				if ( $settingsarray[$var] != ManageWiki::handleMatrix( $rmVar, 'php' ) ) {
					$changedsettingsarray[] = "setting-" . $var;
				}
			} elseif ( $det['type'] != 'text' || $params["set-$var"] ) {
				if ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] ) {
					$settingsarray[$var] = $params["set-$var"];
				} else {
					$settingsarray[$var] = $rmVar;
				}

				if (  is_null( $rmVar) && $settingsarray[$var] != $det['overridedefault'] || !is_null( $rmVar) && $settingsarray[$var] != $rmVar ) {
					$changedsettingsarray[] = "setting-" . $var;
				}
			}
		}

		$settingsjson = json_encode( $settingsarray );

		$values = array(
			'wiki_settings' => $settingsjson,
		);

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

		$farmerLogEntry = new ManualLogEntry( 'managewiki', 'settings' );
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
