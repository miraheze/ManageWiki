<?php
class SpecialManageWikiExtensions extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWikiExtensions' );
	}

	function execute( $par ) {
		global $wgManageWikiHelpUrl, $wgCreateWikiGlobalWiki, $wgDBname;

		$out = $this->getOutput();

		$this->setHeaders();

		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		if ( !ManageWiki::checkSetup( 'extensions', true, $out ) ) {
			return false;
		}

		if ( $wgCreateWikiGlobalWiki !== $wgDBname ) {
			$this->showWikiForm( $wgDBname );
		} elseif ( !is_null( $par ) && $par !== '' ) {
			$this->showWikiForm( $par );
		} else {
			$this->showInputBox();
		}
	}

	function showInputBox() {
		$formDescriptor = [
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'required' => true,
				'name' => 'mwDBname',
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'searchForm' );
		$htmlForm->setMethod( 'post' )
			->setSubmitCallback( [ $this, 'onSubmitRedirectToWikiForm' ] )
			->prepareForm()
			->show();

		return true;
	}

	function onSubmitRedirectToWikiForm( array $params ) {
		if ( $params['dbname'] !== '' ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiExtensions' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	function showWikiForm( $wiki ) {
		global $wgCreateWikiGlobalWiki;

		$out = $this->getOutput();

		$out->addModules( 'ext.createwiki.oouiform' );

		$dbName = $wiki;

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-extensions-header', $dbName );
		}

		if ( $dbName == 'default' ) {
			// If wiki is default, we mean it but we can't use RM this way
			$remoteWiki = RemoteWiki::newFromName( $wgCreateWikiGlobalWiki );
		} else {
			$remoteWiki = RemoteWiki::newFromName( $dbName );
		}

		if ( $remoteWiki == NULL ) {
			$this->getContext()->getOutput()->addHTML(
				'<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( $dbName, $remoteWiki, $this->getContext(), 'extensions' );
		$sectionTitles = $htmlForm->getFormSections();

		$sectTabs = [];
		foreach( $sectionTitles as $key ) {
			$sectTabs[] = [
				'name' => $key,
				'label' => $htmlForm->getLegend( $key )
			];
		}

		$out->addJsConfigVars( 'wgCreateWikiOOUIFormTabs', $sectTabs );

		$htmlForm->show();
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
