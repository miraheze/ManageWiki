<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki' );
	}

	function execute( $par ) {
		global $wgManageWikiHelpUrl, $wgCreateWikiGlobalWiki, $wgDBname;

		$out = $this->getOutput();
		$this->setHeaders();

		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		if ( !ManageWiki::checkSetup( 'core', true, $out ) ) {
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
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	function showWikiForm( $wiki ) {
		$out = $this->getOutput();

		$out->addModules( 'ext.createwiki.oouiform' );

		$dbName = $wiki;

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-header' );
		}

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( $dbName, $this->getContext(), 'core' );
		$sectionTitles = $htmlForm->getFormSections();

		$sectTabs = [];
		foreach ( $sectionTitles as $key ) {
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
