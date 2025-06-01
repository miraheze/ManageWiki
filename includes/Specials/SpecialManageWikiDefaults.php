<?php

namespace Miraheze\ManageWiki\Specials;

use ErrorPageError;
use ManualLogEntry;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\FormFactory\FormFactory;
use Miraheze\ManageWiki\Helpers\DefaultPermissions;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use function in_array;
use function mb_strtolower;
use function str_replace;
use function trim;

class SpecialManageWikiDefaults extends SpecialPage {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly DefaultPermissions $defaultPermissions,
		private readonly FormFactory $formFactory,
		private readonly ModuleFactory $moduleFactory
	) {
		parent::__construct( 'ManageWikiDefaults' );
	}

	/**
	 * @param ?string $par
	 */
	public function execute( $par ): void {
		$this->setHeaders();
		if ( !$this->moduleFactory->isEnabled( 'permissions' ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-disabled', [ 'permissions' ] );
		}

		$this->getOutput()->addModules( [ 'mediawiki.special.userrights' ] );

		if ( $par && $this->databaseUtils->isCurrentWikiCentral() ) {
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->buildGroupView( $par );
			return;
		}

		$this->buildMainView();
	}

	private function buildGroupView( string $group ): void {
		$this->getOutput()->addModules( [ 'ext.managewiki.oouiform' ] );
		$this->getOutput()->addModuleStyles( [
			'ext.managewiki.oouiform.styles',
			'oojs-ui-widgets.styles',
		] );

		$this->formFactory->getForm(
			moduleFactory: $this->moduleFactory,
			context: $this->getContext(),
			dbname: ModuleFactory::DEFAULT_DBNAME,
			module: 'permissions',
			special: $group
		)->show();
	}

	private function buildMainView(): void {
		$canModify = $this->canModify();

		if ( $this->databaseUtils->isCurrentWikiCentral() ) {
			$language = $this->getLanguage();
			$mwPermissions = $this->moduleFactory->permissionsDefault();
			$groups = $mwPermissions->listGroups();
			$craftedGroups = [];

			foreach ( $groups as $group ) {
				$lowerCaseGroupName = $language->lc( $group );
				$craftedGroups[$language->getGroupName( $lowerCaseGroupName )] = $lowerCaseGroupName;
			}

			$groupSelector = [];
			$groupSelector['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaults-selectgroup-info' )->text(),
			];

			$groupSelector['group'] = [
				'label-message' => 'managewiki-permissions-select',
				'type' => 'select',
				'options' => $craftedGroups,
			];

			$selectForm = HTMLForm::factory( 'ooui', $groupSelector, $this->getContext(), 'groupSelector' );
			$selectForm
				->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )
				->setFormIdentifier( 'groupSelector' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( 'managewiki-permissions-select-header' )
				->prepareForm()
				->show();

			if ( $canModify ) {
				$createDescriptor = [];
				$createDescriptor['info'] = [
					'type' => 'info',
					'default' => $this->msg( 'managewikidefaults-creategroup-info' )->text(),
				];

				$createDescriptor['group'] = [
					'type' => 'text',
					'label-message' => 'managewiki-permissions-create',
					// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_permissions.sql#L3
					'maxlength' => 64,
					// Make sure this is lowercase (multi-byte safe), and has no trailing spaces,
					// and that any remaining spaces are converted to underscores.
					'filter-callback' => static fn ( string $value ): string => mb_strtolower(
						str_replace( ' ', '_', trim( $value ) )
					),
					'validation-callback' => [ $this, 'validateNewGroupName' ],
				];

				$createForm = HTMLForm::factory( 'ooui', $createDescriptor, $this->getContext() );
				$createForm
					->setSubmitCallback( [ $this, 'onSubmitRedirectToPermissionsPage' ] )
					->setFormIdentifier( 'createForm' )
					->setMethod( 'post' )
					->setWrapperLegendMsg( 'managewiki-permissions-create-header' )
					->prepareForm()
					->show();
			}
		} elseif ( !$this->databaseUtils->isCurrentWikiCentral() && !$canModify ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-unavailable-notcentralwiki' );
		}

		if ( !$this->databaseUtils->isCurrentWikiCentral() && $canModify ) {
			$this->getOutput()->setPageTitleMsg(
				$this->msg( 'managewiki-permissions-resetgroups-title' )
			);

			$resetPermissionsDescriptor = [];
			$resetPermissionsDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaults-resetgroups-header' )->text(),
			];

			$resetPermissionsForm = HTMLForm::factory( 'ooui', $resetPermissionsDescriptor, $this->getContext() );
			$resetPermissionsForm
				->setSubmitCallback( [ $this, 'onSubmitPermissionsResetForm' ] )
				->setFormIdentifier( 'resetpermissionsform' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( 'managewikidefaults-resetgroups-title' )
				->setSubmitTextMsg( 'managewikidefaults-resetgroups' )
				->setSubmitDestructive()
				->prepareForm()
				->show();

			$resetSettingsDescriptor = [];
			$resetSettingsDescriptor['info'] = [
				'type' => 'info',
				'raw' => true,
				'default' => $this->msg( 'managewikidefaults-resetsettings-header' )->parse(),
			];

			$resetSettingsForm = HTMLForm::factory( 'ooui', $resetSettingsDescriptor, $this->getContext() );
			$resetSettingsForm
				->setSubmitCallback( [ $this, 'onSubmitSettingsResetForm' ] )
				->setFormIdentifier( 'resetsettingsform' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( 'managewikidefaults-resetsettings-title' )
				->setSubmitTextMsg( 'managewikidefaults-resetsettings' )
				->setSubmitDestructive()
				->prepareForm()
				->show();

			$resetCacheDescriptor = [];
			$resetCacheDescriptor['info'] = [
				'type' => 'info',
				'default' => $this->msg( 'managewikidefaults-resetcache-header' )->text(),
			];

			$resetCacheForm = HTMLForm::factory( 'ooui', $resetCacheDescriptor, $this->getContext() );
			$resetCacheForm
				->setSubmitCallback( [ $this, 'onSubmitCacheResetForm' ] )
				->setFormIdentifier( 'resetcacheform' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( 'managewikidefaults-resetcache-title' )
				->setSubmitTextMsg( 'managewikidefaults-resetcache' )
				->setSubmitDestructive()
				->prepareForm()
				->show();
		}
	}

	private function canModify(): bool {
		return $this->getAuthority()->isAllowed( 'managewiki-editdefault' );
	}

	public function onSubmitRedirectToPermissionsPage( array $formData ): void {
		$this->getOutput()->redirect(
			$this->getPageTitle( $formData['group'] )->getFullURL()
		);
	}

	public function onSubmitPermissionsResetForm( array $formData ): false {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbname = $this->getConfig()->get( MainConfigNames::DBname );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();

		$mwCore = $this->moduleFactory->core( $dbname );
		$this->defaultPermissions->populatePermissions( $dbname, $mwCore->isPrivate() );

		$logEntry = new ManualLogEntry( 'managewiki', 'rights-reset' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $this->getPageTitle()->getTitleValue() );
		$logEntry->setParameters( [ '4::wiki' => $dbname ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$this->getOutput()->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->text()
				),
				'mw-notify-success'
			)
		);

		return false;
	}

	public function onSubmitSettingsResetForm( array $formData ): false {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbname = $this->getConfig()->get( MainConfigNames::DBname );
		// Set the values to the defaults
		$dbw->newUpdateQueryBuilder()
			->update( 'mw_settings' )
			->set( [ 's_settings' => '[]' ] )
			->where( [ 's_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();

		// Reset the cache or else the changes won't work
		$data = $this->dataFactory->newInstance( $dbname );
		$data->resetWikiData( isNewChanges: true );

		$logEntry = new ManualLogEntry( 'managewiki', 'settings-reset' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $this->getPageTitle()->getTitleValue() );
		$logEntry->setParameters( [ '4::wiki' => $dbname ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$this->getOutput()->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->text()
				),
				'mw-notify-success'
			)
		);

		return false;
	}

	public function onSubmitCacheResetForm( array $formData ): false {
		// Reset the cache or else the changes won't work
		$data = $this->dataFactory->newInstance( $this->getConfig()->get( MainConfigNames::DBname ) );
		$data->resetWikiData( isNewChanges: true );

		$logEntry = new ManualLogEntry( 'managewiki', 'cache-reset' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $this->getPageTitle()->getTitleValue() );
		$logEntry->setParameters( [ '4::wiki' => $this->getConfig()->get( MainConfigNames::DBname ) ] );
		$logID = $logEntry->insert();
		$logEntry->publish( $logID );

		$this->getOutput()->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$this->msg( 'managewiki-success' )->text()
				),
				'mw-notify-success'
			)
		);

		return false;
	}

	public function validateNewGroupName( string $newGroup ): Message|true {
		if ( in_array( $newGroup, $this->getConfig()->get( ConfigNames::PermissionsDisallowedGroups ), true ) ) {
			return $this->msg( 'managewiki-permissions-group-disallowed' );
		}

		// We just use this to check if the group is valid for a title,
		// otherwise we can not edit it because the title will be
		// invalid for the ManageWiki permission subpage.
		if ( !$this->getPageTitle( $newGroup )->isValid() ) {
			return $this->msg( 'managewiki-permissions-group-invalid' );
		}

		$mwPermissions = $this->moduleFactory->permissionsDefault();
		if ( $mwPermissions->exists( $newGroup ) ) {
			return $this->msg( 'managewiki-permissions-group-conflict' );
		}

		return true;
	}

	/** @inheritDoc */
	public function getDescription(): string {
		return $this->msg( $this->canModify() ? 'managewikidefaults' : 'managewikidefaults-view' )->text();
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wiki';
	}

	/** @inheritDoc */
	public function isListed(): bool {
		// Only appear on the central wiki or if the user can reset permissions on this wiki
		return $this->databaseUtils->isCurrentWikiCentral() || $this->canModify();
	}
}
