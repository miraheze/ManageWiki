<?php

class SpecialManageWikiDefaultPermissions extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageWikiDefaultPermissions' );
	}

	public function execute( $par ) {
		global $wgDBname, $wgCreateWikiGlobalWiki;

		$this->setHeaders();
		$out = $this->getOutput();

		if ( !ManageWiki::checkSetup( 'permissions', true, $out ) || !( $wgCreateWikiGlobalWiki == $wgDBname ) ) {
			return false;
		}

		if ( $par != '' ) {
			$this->buildGroupView( $par );
		} else {
			$this->buildMainView();
		}
	}

	public function buildMainView() {
		$out = $this->getOutput();
		$groups = ManageWikiPermissions::availableGroups( 'default' );
		$craftedGroups = [];

		foreach( $groups as $group ) {
			$craftedGroups[UserGroupMembership::getGroupName( $group )] = $group;
		}

		$out->addWikiMsg( 'managewiki-permissions-header' );

		$groupSelector['groups'] = [
			'label-message' => 'managewiki-permissions-select',
			'type' => 'select',
			'options' => $craftedGroups,
		];

		$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
		$selectForm->setMethod('post' )->setFormIdentifier( 'groupSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

		$mwService = MediaWikiServices::getInstance()->getPermissionManager();
 		if ( $mwService->userHasRight( $this->getContext()->getUser(), 'managewiki-editdefault' ) ) {
			$createDescriptor['groups'] = [
				'type' => 'text',
				'label-message' => 'managewiki-permissions-creategroup',
				'validation-callback' => [ $this, 'validateNewGroupName' ],
			];

			$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] ) ->prepareForm()->show();

			$out->addWikiMsg( 'managewiki-permissions-resetgroups-header' );

			$resetForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
			$resetForm->setMethod( 'post' )->setFormIdentifier( 'resetform' )->setSubmitTextMsg( 'managewiki-permissions-resetgroups' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitResetForm' ] )->prepareForm()->show();
		}
	}

	public function onSubmitRedirectToPermissionsPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions' )->getFullUrl() . '/' . $params['groups'] );

		return true;
	}

	public function onSubmitResetForm( $formData ) {
		global $wgDBname, $wmgPrivateWiki, $wgCreateWikiDatabase;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$dbw->delete(
			'mw_permissions',
			[
				'perm_dbname' => $wgDBname
			],
			__METHOD__
		);

		ManageWikiHooks::onCreateWikiCreation( $wgDBname, $wmgPrivateWiki );

		return true;
	}

	public static function validateNewGroupName( $newGroup, $nullForm ) {
		global $wgManageWikiPermissionsBlacklistGroups;

		if ( in_array( $newGroup, $wgManageWikiPermissionsBlacklistGroups ) ) {
			return 'Blacklisted Group.';
		}

		return true;
	}

	public function buildGroupView( $group ) {
		global $wgCreateWikiGlobalWiki;

		$out = $this->getOutput();

		$out->addModules( 'ext.createwiki.oouiform' );

		$remoteWiki = RemoteWiki::newFromName( $wgCreateWikiGlobalWiki );
		if ( $remoteWiki == null ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( 'default', $remoteWiki, $this->getContext(), 'permissions', $group );
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
