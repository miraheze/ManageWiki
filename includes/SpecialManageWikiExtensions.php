<?php
class SpecialManageWikiExtensions extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWikiExtensions', 'managewiki' );
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
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiExtensions' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	function showWikiForm( $wiki ) {
		global $wgManageWikiExtensions, $wgUser;

		$out = $this->getOutput();

		if ( !$wgManageWikiExtensions ) {
			$out->addWikiMsg( 'managewiki-extensions-disabled' );
			return false;
		}

		$dbName = $wiki;

		$wiki = RemoteWiki::newFromName( $wiki );

		if ( $wiki == NULL ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-extensions-header', $dbName );
		}

		$formDescriptor['dbname'] = array(
			'label-message' => 'managewiki-label-dbname',
			'type' => 'text',
			'size' => 20,
			'default' => $dbName,
			'disabled' => true,
			'name' => 'mwDBname',
		);

		foreach ( $wgManageWikiExtensions as $name => $ext ) {
			if ( !$ext['conflicts'] ) {
				$formDescriptor["ext-$name"] = array(
					'type' => 'check',
					'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
					'default' => $wiki->hasExtension( $name ),
					'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$ext['restricted'] ) ? 0 : 1,
					'help' => ( $ext['requires'] ) ? "Requires: {$ext['requires']}." : null,
				);
			} else {
				$formDescriptor["ext-$name"] = array(
					'type' => 'check',
					'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
					'default' => $wiki->hasExtension ( $name ),
					'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$ext['restricted'] ) ? 0 : 1,
					'help' => ( $ext['requires'] ) ? "Requires: {$ext['requires']}." . " Conflicts: {$ext['conflicts']}." : "Conflicts: {$ext['conflicts']}.",
				);
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
		global $wgDBname, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgUser;

		$dbw = wfGetDB( DB_MASTER, array(), $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		if ( !$this->getUser()->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$this->getUser()->getName()}' without managewiki right tried to change wiki extensions!" );
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
					throw new MWException( "User without managewiki-restricted tried to change a restricted extension setting ($name)" );
				}
			}

			if ( $params["ext-$name"] != $wiki->hasExtension( $name ) ) {
				$changedsettingsarray[] = "ext-" . $name;
			}
		}

		// HACK: dummy extension name
		$extensionsarray[] = "zzzz";

		$extensions = implode( ",", $extensionsarray );

		$changedsettings = implode( ", ", $changedsettingsarray );

		$dbw->selectDB( $wgCreateWikiDatabase );

		$dbw->update( 'cw_wikis',
			array(
				'wiki_extensions' => $extensions,
			),
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
