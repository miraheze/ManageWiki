<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki', 'managewiki' );
	}

	public function execute( $par )  {
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

		$out->addWikiMsg( 'managewiki-header' );

		$pageSelector['manage'] = array(
			'type' => 'select',
			'options' => [
				'Additional Settings' => 'Settings',
				'Extensions/Skins' => 'Extensions',
				'Permissions' => 'Permissions',
			],
		);

		$selectForm = HTMLForm::factory( 'ooui', $pageSelector, $this->getContext(), 'pageSelector' );
		$selectForm->setMethod('post' )->setFormIdentifier( 'pageSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToManageWikiPage' ] )->prepareForm()->show();
	}

	function onSubmitRedirectToManageWikiPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki' )->getFullUrl() . $params['manage'] );
		return true;
	}
}
