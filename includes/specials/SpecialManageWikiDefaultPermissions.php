<?php

use MediaWiki\MediaWikiServices;

class SpecialManageWikiDefaultPermissions extends SpecialPage {
	/** @var Config */
	private $config;

	public function __construct() {
		parent::__construct( 'ManageWikiDefaultPermissions' );
		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();

		if ( !ManageWiki::checkSetup( 'permissions', true, $out ) || !( $this->config->get( 'CreateWikiGlobalWiki' ) == $this->config->get( 'DBname' ) ) ) {
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
		$mwPermissions = new ManageWikiPermissions( 'default' );
		$groups = array_keys( $mwPermissions->list() );
		$craftedGroups = [];

		foreach ( $groups as $group ) {
			$craftedGroups[UserGroupMembership::getGroupName( $group )] = $group;
		}

		$out->addWikiMsg( 'managewiki-header-permissions' );

		$groupSelector = [];

		$groupSelector['groups'] = [
			'label-message' => 'managewiki-permissions-select',
			'type' => 'select',
			'options' => $craftedGroups,
		];

		$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
		$selectForm->setMethod( 'post' )->setFormIdentifier( 'groupSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $permissionManager->userHasRight( $this->getContext()->getUser(), 'managewiki-editdefault' ) ) {
			$createDescriptor = [];

			$createDescriptor['groups'] = [
				'type' => 'text',
				'label-message' => 'managewiki-permissions-create',
				'validation-callback' => [ $this, 'validateNewGroupName' ],
			];

			$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

			$out->addWikiMsg( 'managewiki-permissions-resetgroups-header' );

			$resetForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
			$resetForm->setMethod( 'post' )->setFormIdentifier( 'resetform' )->setSubmitTextMsg( 'managewiki-permissions-resetgroups' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitResetForm' ] )->prepareForm()->show();
		}
	}

	public function onSubmitRedirectToPermissionsPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions' )->getFullURL() . '/' . $params['groups'] );

		return true;
	}

	public function onSubmitResetForm( $formData ) {
		$dbw = wfGetDB( DB_PRIMARY, [], $this->config->get( 'CreateWikiDatabase' ) );

		$dbw->delete(
			'mw_permissions',
			[
				'perm_dbname' => $this->config->get( 'DBname' )
			],
			__METHOD__
		);

		$cwConfig = new GlobalVarConfig( 'cw' );
		ManageWikiHooks::onCreateWikiCreation( $this->config->get( 'DBname' ), $cwConfig->get( 'Private' ) );

		return true;
	}

	public static function validateNewGroupName( $newGroup, $nullForm ) {
		if ( in_array( $newGroup, MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'ManageWikiPermissionsBlacklistGroups' ) ) ) {
			return 'Blacklisted Group.';
		}

		return true;
	}

	public function buildGroupView( $group ) {
		$out = $this->getOutput();

		$out->addModules( [ 'ext.managewiki.oouiform' ] );
		$out->addModuleStyles( [
			'ext.managewiki.oouiform.styles',
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );
		$out->addModuleStyles( 'oojs-ui-widgets.styles' );

		$remoteWiki = new RemoteWiki( $this->config->get( 'CreateWikiGlobalWiki' ) );

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( 'default', $remoteWiki, $this->getContext(), $this->config, 'permissions', $group );

		$htmlForm->show();
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
