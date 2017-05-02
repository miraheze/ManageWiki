<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki', 'managewiki' );
	}

	function execute( $par ) {
		global $wgEnableManageWiki, $wgManageWikiMainDatabase, $wgManageWikiGlobalWiki, $wgDBname;

		if ( !$wgManageWikiMainDatabase ) {
			throw new MWException( '$wgManageWikiMainDatabase was not set!' );
		}

		$out = $this->getOutput();
		$this->setHeaders();

		if ( !$wgEnableManageWiki ) {
			$out->addWikiMsg( 'managewiki-disabled' );
			return false;
		}

		$this->checkPermissions();

		if ( $wgManageWikiGlobalWiki !== $wgDBname ) {
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
		$out = $this->getOutput();

		$dbName = $wiki;

		$wiki = RemoteWiki::newFromName( $wiki );

		if ( $wiki == NULL ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-header', $wiki->wiki_dbname );
		}

		$languages = Language::fetchLanguageNames( null, 'mwfile' );
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
			'closed' => array(
				'type' => 'check',
				'label-message' => 'managewiki-label-closed',
				'name' => 'cwClosed',
				'default' => $wiki->isClosed() ? 1 : 0,
			),
			'private' => array(
				'type' => 'check',
				'label-message' => 'managewiki-label-private',
				'name' => 'cwPrivate',
				'default' => $wiki->isPrivate() ? 1 : 0,
			),
			'reason' => array(
				'label-message' => 'managewiki-label-reason',
				'type' => 'text',
				'size' => 45,
				'required' => true,
			),
		);

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'changeForm' );
		$htmlForm->setMethod( 'post' )
			->setSubmitCallback( array( $this, 'onSubmitInput' ))
			->prepareForm()
			->show();

	}

	function onSubmitInput( array $params ) {
		global $wgDBname, $wgManageWikiMainDatabase;

		$dbName = $wgDBname;

		if ( !$this->getUser()->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$this->getUser()->getName()}' without managewiki right tried to change wiki settings!" );
		}

		$values = array(
			'wiki_sitename' => $params['sitename'],
			'wiki_language' => $params['language'],
			'wiki_closed' => ( $params['closed'] == true ) ? 1 : 0,
			'wiki_private' => ( $params['private'] == true ) ? 1 : 0,
		);

		$dbw = wfGetLB( $wgManageWikiMainDatabase ) ->getConnectionRef( DB_MASTER, array(), $wgManageWikiMainDatabase );
		$dbw->selectDB( $wgManageWikiMainDatabase );

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
