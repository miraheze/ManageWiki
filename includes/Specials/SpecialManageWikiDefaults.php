<?php

namespace Miraheze\ManageWiki\Specials;

use ErrorPageError;
use ManualLogEntry;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\FormFactory\ManageWikiFormFactory;
use Miraheze\ManageWiki\Helpers\ModuleFactory;
use Miraheze\ManageWiki\Hooks\Handlers\CreateWiki;
use Miraheze\ManageWiki\ManageWiki;

class SpecialManageWikiDefaults extends SpecialPage {

	/**
	 * Initializes the SpecialManageWikiDefaults page with required dependencies for managing default wiki settings and permissions.
	 */
	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly CreateWiki $hookHandler,
		private readonly ModuleFactory $moduleFactory
	) {
		parent::__construct( 'ManageWikiDefaults' );
	}

	/**
	 * @param ?string $par
	 */
	public function execute( $par ): void {
		$this->setHeaders();
		if ( !ManageWiki::checkSetup( 'permissions' ) ) {
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

	/**
	 * Displays the permissions form for a specified group using the ManageWiki form UI.
	 *
	 * @param string $group The name of the group whose permissions are being managed.
	 */
	private function buildGroupView( string $group ): void {
		$this->getOutput()->addModules( [ 'ext.managewiki.oouiform' ] );
		$this->getOutput()->addModuleStyles( [
			'ext.managewiki.oouiform.styles',
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'oojs-ui-widgets.styles',
		] );

		$formFactory = new ManageWikiFormFactory();
		$formFactory->getForm(
			config: $this->getConfig(),
			moduleFactory: $this->moduleFactory,
			context: $this->getContext(),
			dbname: 'default',
			module: 'permissions',
			special: $group,
			filtered: ''
		)->show();
	}

	/**
	 * Displays the main interface for managing default wiki permissions and settings.
	 *
	 * Depending on the user's permissions and whether the current wiki is central, this method shows forms for selecting or creating permission groups, or for resetting permissions, settings, and cache. Throws an error page if the user lacks permission on a non-central wiki.
	 */
	private function buildMainView(): void {
		$canModify = $this->canModify();

		if ( $this->databaseUtils->isCurrentWikiCentral() ) {
			$language = $this->getLanguage();
			$mwPermissions = $this->moduleFactory->permissionsDefault();
			$groups = array_keys( $mwPermissions->list( group: null ) );
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
					'validation-callback' => [ $this, 'validateNewGroupName' ],
					// Groups should typically be lowercase so we do that here.
					// Display names can be customized using interface messages.
					'filter-callback' => static fn ( string $value ): string => mb_strtolower( trim( $value ) ),
					// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_permissions.sql#L3
					'maxlength' => 64,
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

	/**
	 * Resets all default permissions for the current wiki.
	 *
	 * Deletes all permission entries for the current wiki from the database, triggers the creation hook to reinitialize permissions, logs the reset action, and displays a success message.
	 *
	 * @return bool Always returns false to prevent further form processing.
	 */
	public function onSubmitPermissionsResetForm( array $formData ): bool {
		$dbw = $this->databaseUtils->getGlobalPrimaryDB();
		$dbname = $this->getConfig()->get( MainConfigNames::DBname );
		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'mw_permissions' )
			->where( [ 'perm_dbname' => $dbname ] )
			->caller( __METHOD__ )
			->execute();

		$core = $this->moduleFactory->core( $dbname );
		$this->hookHandler->onCreateWikiCreation(
			$dbname, $core->isPrivate()
		);

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

	public function onSubmitSettingsResetForm( array $formData ): bool {
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

	public function onSubmitCacheResetForm( array $formData ): bool {
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

	public function validateNewGroupName( string $newGroup ): bool|Message {
		if ( in_array( $newGroup, $this->getConfig()->get( ConfigNames::PermissionsDisallowedGroups ), true ) ) {
			return $this->msg( 'managewiki-permissions-group-disallowed' );
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
		return 'wikimanage';
	}

	/** @inheritDoc */
	public function isListed(): bool {
		// Only appear on the central wiki or if the user can reset permissions on this wiki
		return $this->databaseUtils->isCurrentWikiCentral() || $this->canModify();
	}
}
