<?php

namespace Miraheze\ManageWiki\Specials;

use ErrorPageError;
use ManualLogEntry;
use MediaWiki\Config\Config;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\FormFactory\ManageWikiFormFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Hooks;
use Miraheze\ManageWiki\ManageWiki;

class SpecialManageWikiDefaultPermissions extends SpecialPage {

	private Config $config;
	private CreateWikiDatabaseUtils $databaseUtils;
	private CreateWikiDataFactory $dataFactory;
	private RemoteWikiFactory $remoteWikiFactory;

	public function __construct(
		CreateWikiDatabaseUtils $databaseUtils,
		CreateWikiDataFactory $dataFactory,
		RemoteWikiFactory $remoteWikiFactory
	) {
		parent::__construct( 'ManageWikiDefaultPermissions' );

		$this->config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
		$this->databaseUtils = $databaseUtils;
		$this->dataFactory = $dataFactory;
		$this->remoteWikiFactory = $remoteWikiFactory;
	}

	public function canModify() {
		if ( !MediaWikiServices::getInstance()->getPermissionManager()->userHasRight( $this->getContext()->getUser(), 'managewiki-editdefault' ) ) {
			return false;
		}

		return true;
	}

	public function getDescription() {
		return $this->msg( $this->canModify() ? 'managewikidefaultpermissions' : 'managewikidefaultpermissions-view' )->text();
	}

	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();

		if ( !ManageWiki::checkSetup( 'permissions' ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-disabled', [ '1' => 'permissions' ] );
		}

		if ( $par && $this->databaseUtils->isCurrentWikiCentral() ) {
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->buildGroupView( $par );
		} else {
			$this->buildMainView();
		}
	}

	public function buildMainView() {
		$canModify = $this->canModify();

		$out = $this->getOutput();
		$out->addModules( [ 'mediawiki.special.userrights' ] );

		if ( $this->databaseUtils->isCurrentWikiCentral() ) {
			$language = RequestContext::getMain()->getLanguage();
			$mwPermissions = new ManageWikiPermissions( 'default' );
			$groups = array_keys( $mwPermissions->list() );
			$craftedGroups = [];

			foreach ( $groups as $group ) {
				$craftedGroups[$language->getGroupName( $group )] = $group;
			}

			$groupSelector = [];

			$groupSelector['info'] = [
				'default' => $this->msg( 'managewikidefaultpermissions-select-info' )->text(),
				'type' => 'info',
			];

			$groupSelector['groups'] = [
				'label-message' => 'managewiki-permissions-select',
				'type' => 'select',
				'options' => $craftedGroups,
			];

			$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
			$selectForm->setWrapperLegendMsg( 'managewiki-permissions-select-header' );
			$selectForm->setMethod( 'post' )->setFormIdentifier( 'groupSelector' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();

			if ( $canModify ) {
				$createDescriptor = [];

				$createDescriptor['info'] = [
					'type' => 'info',
					'default' => $this->msg( 'managewikidefaultpermissions-create-info' )->text(),
				];

				$createDescriptor['groups'] = [
					'type' => 'text',
					'label-message' => 'managewiki-permissions-create',
					'validation-callback' => [ $this, 'validateNewGroupName' ],
				];

				$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
				$createForm->setWrapperLegendMsg( 'managewiki-permissions-create-header' );
				$createForm->setMethod( 'post' )->setFormIdentifier( 'createForm' )->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )->prepareForm()->show();
			}
		} elseif ( !$this->databaseUtils->isCurrentWikiCentral() && !$canModify ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notcentralwiki' );
		}

		if ( !$this->databaseUtils->isCurrentWikiCentral() && $canModify ) {
			$out->setPageTitle( $this->msg( 'managewiki-permissions-resetgroups-title' )->plain() );

			$resetPermissionsDescriptor = [];

			$resetPermissionsDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaultpermissions-resetgroups-header' ),
			];

			$resetPermissionsForm = HTMLForm::factory( 'ooui', $resetPermissionsDescriptor, $this->getContext() );
			$resetPermissionsForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetgroups-title' );
			$resetPermissionsForm->setMethod( 'post' )->setFormIdentifier( 'resetpermissionsform' )->setSubmitTextMsg( 'managewikidefaultpermissions-resetgroups' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitPermissionsResetForm' ] )->prepareForm()->show();

			$resetSettingsDescriptor = [];

			$resetSettingsDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaultpermissions-resetsettings-header' ),
			];

			$resetSettingsForm = HTMLForm::factory( 'ooui', $resetSettingsDescriptor, $this->getContext() );
			$resetSettingsForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetsettings-title' );
			$resetSettingsForm->setMethod( 'post' )->setFormIdentifier( 'resetsettingsform' )->setSubmitTextMsg( 'managewikidefaultpermissions-resetsettings' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitSettingsResetForm' ] )->prepareForm()->show();

			$resetCacheDescriptor = [];

			$resetCacheDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaultpermissions-resetcache-header' ),
			];

			$resetCacheForm = HTMLForm::factory( 'ooui', $resetCacheDescriptor, $this->getContext() );
			$resetCacheForm->setWrapperLegendMsg( 'managewikidefaultpermissions-resetcache-title' );
			$resetCacheForm->setMethod( 'post' )->setFormIdentifier( 'resetcacheform' )->setSubmitTextMsg( 'managewikidefaultpermissions-resetcache' )->setSubmitDestructive()->setSubmitCallback( [ $this, 'onSubmitCacheResetForm' ] )->prepareForm()->show();

		}
	}

	public function onSubmitRedirectToPermissionsPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWikiDefaultPermissions' )->getFullURL() . '/' . $params['groups'] );

		return true;
	}

	public function onSubmitPermissionsResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		$dbw->delete(
			'mw_permissions',
			[
				'perm_dbname' => $this->config->get( 'DBname' )
			],
			__METHOD__
		);

		$cwConfig = new GlobalVarConfig( 'cw' );
		Hooks::onCreateWikiCreation( $this->config->get( 'DBname' ), $cwConfig->get( 'Private' ) );

		$logEntry = new ManualLogEntry( 'managewiki', 'rights-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}

	public function onSubmitSettingsResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		// Set the values to the defaults
		$dbw->update(
			'mw_settings',
			[
				's_settings' => '[]'
			],
			[
				's_dbname' => $this->config->get( 'DBname' )
			],
			__METHOD__
		);

		// Reset the cache or else the changes won't work
		$data = $this->dataFactory->newInstance( $this->config->get( 'DBname' ) );
		$data->resetWikiData( isNewChanges: true );

		$logEntry = new ManualLogEntry( 'managewiki', 'settings-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}

	public function onSubmitCacheResetForm( $formData ) {
		$out = $this->getOutput();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		// Reset the cache or else the changes won't work
		$data = $this->dataFactory->newInstance( $this->config->get( 'DBname' ) );
		$data->resetWikiData( isNewChanges: true );

		$logEntry = new ManualLogEntry( 'managewiki', 'cache-reset' );
		$logEntry->setPerformer( $this->getContext()->getUser() );
		$logEntry->setTarget( SpecialPage::getTitleValueFor( 'ManageWikiDefaultPermissions' ) );
		$logEntry->setParameters( [ '4::wiki' => $this->config->get( 'DBname' ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
			);

		return false;
	}

	public static function validateNewGroupName( $newGroup, $nullForm ) {
		if ( in_array( $newGroup, MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' )->get( 'ManageWikiPermissionsDisallowedGroups' ) ) ) {
			return 'The group you attempted to create is not allowed. Please select a different name and try again.';
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
		$out->addModuleStyles( [ 'oojs-ui-widgets.styles' ] );
		$out->addModules( [ 'mediawiki.special.userrights' ] );

		$remoteWiki = $this->remoteWikiFactory->newInstance( $this->databaseUtils->getCentralWikiID() );

		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm( 'default', $remoteWiki, $this->getContext(), $this->config, 'permissions', $group );

		$htmlForm->show();
	}

	public function isListed() {
		// Only appear on the central wiki or if the user can reset permissions on this wiki
		return $this->databaseUtils->isCurrentWikiCentral() || $this->canModify();
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
