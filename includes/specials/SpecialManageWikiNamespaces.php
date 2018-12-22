<?php
class SpecialManageWikiNamespaces extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWikiNamespaces' );
	}

	function execute( $par ) {
		global $wgEnableManageWiki, $wgManageWikiHelpUrl, $wgDBname;

		$out = $this->getOutput();
		$this->setHeaders();
		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		if ( !$wgEnableManageWiki ) {
			$out->addWikiMsg( 'managewiki-disabled' );
			return false;
		}

		if ( !is_null( $par ) && $par !== '' ) {
			$this->showNamespace( $par );
		} else {
			$this->showMain();
		}
	}

	function showMain() {
		global $wgUser;

		$out = $this->getOutput();
		$namespaces = ManageWikiNamespaces::configurableNamespaces( $id = true, $readable = true, $main = true );
		$craftedNamespaces = [];

		foreach( $namespaces as $id => $namespace ) {
			$craftedNamespaces[$namespace] = $id;
		}

		$out->addWikiMsg( 'managewiki-ns-header' );

		$namespaceSelector['namespaces'] = array(
			'label-message' => 'managewiki-ns-select',
			'type' => 'select',
			'options' => $craftedNamespaces,
		);

		$selectForm = HTMLForm::factory( 'ooui', $namespaceSelector, $this->getContext(), 'namespaceSelector' );
		$selectForm->setMethod('post' )->setFormIdentifier( 'namespaceSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToNamespacePage' ] )->prepareForm()->show();

		if ( $wgUser->isAllowed( 'managewiki' ) ) {
			$createDescriptor = [
				'info' => [
					'type' => 'info',
					'default' => wfMessage( 'managewiki-ns-createnamespaceinfo' )->text()
				],
				'submit' => [
                		        'type' => 'submit',
                		        'default' => wfMessage( 'managewiki-ns-createnamespace' )->text()
				]
                	];


			$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToNamespacePage' ] )->suppressDefaultSubmit()->prepareForm()->show();
		}
	}

	function onSubmitRedirectToNamespacePage( array $params ) {
		global $wgRequest;

		if ( isset( $params['namespaces'] ) ) {
			$namespaceID = $params['namespaces'];
		} else {
			$namespaceID = ManageWikiNamespaces::nextNamespaceID();
		}

		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiNamespaces' )->getFullUrl() . '/' . $namespaceID );

		return true;
	}

	function showNamespace( $id ) {
		global $wgDBname;

		$out = $this->getOutput();

		$out->addModules( 'ext.createwiki.oouiform' );

		if ( $id % 2 != 0 ) {
			$out->addWikiMsg( 'managewiki-ns-invalidid' );
			return false;
		}

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( $wgDBname, $this->getContext(), 'namespaces', $id );
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
//
	function validateNamespaceName( $namespace, $nullForm ) {
		global $wgCanonicalNamespaceNames;

		if ( in_array( str_replace( ' ', '_', ucfirst( $namespace ) ), $wgCanonicalNamespaceNames ) ) {
			return false;
		}

		return true;
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
