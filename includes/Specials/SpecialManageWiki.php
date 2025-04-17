<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\FormFactory\ManageWikiFormFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\ManageWiki;
use OOUI\FieldLayout;
use OOUI\SearchInputWidget;

class SpecialManageWiki extends SpecialPage {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly PermissionManager $permissionManager,
		private readonly RemoteWikiFactory $remoteWikiFactory
	) {
		parent::__construct( 'ManageWiki' );
	}

	/**
	 * @param ?string $par
	 */
	public function execute( $par ): void {
		$par = explode( '/', $par ?? '', 3 );
		$this->setHeaders();

		if ( $this->getConfig()->get( ConfigNames::HelpUrl ) ) {
			$this->getOutput()->addHelpLink(
				$this->getConfig()->get( ConfigNames::HelpUrl ),
				true
			);
		}

		$session = $this->getRequest()->getSession();
		if ( $session->get( 'manageWikiSaveSuccess' ) ) {
			// Remove session data for the success message
			$session->remove( 'manageWikiSaveSuccess' );
			$this->getOutput()->addModuleStyles( [
				'mediawiki.codex.messagebox.styles',
				'mediawiki.notification.convertmessagebox.styles',
			] );

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
		}

		$module = 'core';
		if ( array_key_exists( $par[0], $this->getConfig()->get( ConfigNames::ManageWiki ) ) ) {
			$module = $par[0];
		}

		if ( !$this->getUser()->isAllowed( "managewiki-$module" ) ) {
			$this->getOutput()->setPageTitleMsg(
				$this->msg( "managewiki-link-$module-view" )
			);

			if ( $module !== 'permissions' && $module !== 'namespaces' ) {
				$this->getOutput()->addWikiMsg( "managewiki-header-$module-view" );
			}
		} else {
			$this->getOutput()->setPageTitleMsg(
				$this->msg( "managewiki-link-$module" )
			);

			if ( $module !== 'permissions' && $module !== 'namespaces' ) {
				$this->getOutput()->addWikiMsg( "managewiki-header-$module" );
			}
		}

		$additional = $par[1] ?? '';
		$filtered = $par[2] ?? $par[1] ?? '';

		if ( !ManageWiki::checkSetup( $module ) ) {
			$this->getOutput()->addWikiMsg( 'managewiki-disabled', $module );
			return;
		}

		if ( $module === 'permissions' && $additional ) {
			$this->getOutput()->addSubtitle(
				$this->msg( 'editing', $additional )
			);
		}

		// We are not on the central wiki
		if ( !$this->databaseUtils->isCurrentWikiCentral() ) {
			$this->showWikiForm(
				$this->getConfig()->get( MainConfigNames::DBname ),
				$module,
				$additional,
				$filtered
			);
			return;
		}

		// No specific module (on the central wiki)
		// Show dbname input box to select wiki to
		// manage remote ManageWiki core from.
		if ( $par[0] === '' ) {
			$this->showInputBox();
			return;
		}

		// ManageWiki core (on the central wiki) — remote wiki management
		if ( $module === 'core' ) {
			$dbname = $par[1] ?? $this->getConfig()->get( MainConfigNames::DBname );
			$this->showWikiForm(
				strtolower( $dbname ), $module, '', ''
			);
			return;
		}

		// All other modules (on the central wiki)
		$this->showWikiForm(
			$this->getConfig()->get( MainConfigNames::DBname ),
			$module,
			$additional,
			$filtered
		);
	}

	/** @inheritDoc */
	public function getSubpagesForPrefixSearch(): array {
		return [
			'core',
			'extensions',
			'namespaces',
			'permissions',
			'settings',
		];
	}

	private function showInputBox(): void {
		$formDescriptor = [
			'info' => [
				'type' => 'info',
				'default' => $this->msg( 'managewiki-core-info' )->text(),
			],
			'dbname' => [
				'type' => 'text',
				'label-message' => 'managewiki-label-dbname',
				'required' => true,
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'searchForm' );
		$htmlForm
			->setSubmitCallback( [ $this, 'onSubmitRedirectToWikiForm' ] )
			->setMethod( 'post' )
			->setWrapperLegendMsg( 'managewiki-core-header' )
			->prepareForm()
			->show();
	}

	public function onSubmitRedirectToWikiForm( array $formData ): void {
		$this->getOutput()->redirect(
			SpecialPage::getTitleFor( 'ManageWiki', "core/{$formData['dbname']}" )->getFullURL()
		);
	}

	private function showWikiForm(
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): void {
		if ( $special !== '' || in_array( $module, [ 'core', 'extensions', 'settings' ] ) ) {
			$this->getOutput()->addModules( [
				'ext.managewiki.oouiform',
				'mediawiki.special.userrights',
			] );

			$this->getOutput()->addModuleStyles( [
				'ext.managewiki.oouiform.styles',
				'mediawiki.widgets.TagMultiselectWidget.styles',
				'oojs-ui-widgets.styles',
			] );
		}

		$remoteWiki = $this->remoteWikiFactory->newInstance( $dbname );

		if ( $remoteWiki->isLocked() ) {
			$this->getOutput()->addHTML(
				Html::errorBox(
					$this->msg( 'managewiki-mwlocked' )->escaped()
				)
			);
		}

		$options = [];

		// Check permissions
		if ( $module !== 'core' ) {
			if ( !$this->getUser()->isAllowed( "managewiki-$module" ) ) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission' )->escaped()
					)
				);
			}
		} else {
			if (
				!$this->getUser()->isAllowed( "managewiki-$module" ) &&
				!$this->databaseUtils->isCurrentWikiCentral()
			) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission' )->escaped()
					)
				);
			} elseif ( !$this->getUser()->isAllowed( "managewiki-$module" ) ) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission-remote' )->escaped()
					)
				);
			}
		}

		// Handle permissions module when we are not editing a specific group.
		if ( $module === 'permissions' && !$special ) {
			$language = $this->getLanguage();
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$groups = array_keys( $mwPermissions->list( group: null ) );

			foreach ( $groups as $group ) {
				$lowerCaseGroupName = strtolower( $group );
				$options[$language->getGroupName( $lowerCaseGroupName )] = $lowerCaseGroupName;
			}

			$this->reusableFormDescriptor( $module, $options );
			return;
		}

		// Handle namespaces module when we are not editing a specific namespace.
		if ( $module === 'namespaces' && !$special ) {
			$mwNamespaces = new ManageWikiNamespaces( $dbname );
			$namespaces = $mwNamespaces->list( id: null );

			foreach ( $namespaces as $id => $namespace ) {
				if ( $mwNamespaces->isTalk( $id ) ) {
					continue;
				}

				$options[$namespace['name']] = $id;
			}

			$this->reusableFormDescriptor( $module, $options );
			return;
		}

		// Handle all other modules or when we are editing specific namespaces/groups.
		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm(
			config: $this->getConfig(),
			context: $this->getContext(),
			dbw: $this->databaseUtils->getGlobalPrimaryDB(),
			permissionManager: $this->permissionManager,
			remoteWiki: $remoteWiki,
			dbname: $dbname,
			module: $module,
			special: strtolower( $special ),
			filtered: $filtered
		);

		$this->getOutput()->addHTML( new FieldLayout(
			new SearchInputWidget( [
				'placeholder' => $this->msg( 'managewiki-search' )->text(),
			] ),
			[
				'classes' => [ 'managewiki-search' ],
				'label' => $this->msg( 'managewiki-search' )->text(),
				'invisibleLabel' => true,
				'infusable' => true,
			]
		) );

		$htmlForm->show();
	}

	private function reusableFormDescriptor( string $module, array $options ): void {
		$hidden = [];
		$selector = [];
		$create = [];

		$hidden['module'] = [
			'type' => 'hidden',
			'default' => $module,
		];

		$selector['info'] = [
			'type' => 'info',
			'default' => $this->msg( "managewiki-$module-select-info" )->text(),
		];

		$selector['out'] = [
			'type' => 'select',
			'label-message' => "managewiki-$module-select",
			'options' => $options,
		];

		$selectForm = HTMLForm::factory( 'ooui', $hidden + $selector, $this->getContext(), 'selector' );
		$selectForm
			->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )
			->setFormIdentifier( 'selector' )
			->setMethod( 'post' )
			->setWrapperLegendMsg( "managewiki-$module-select-header" )
			->prepareForm()
			->show();

		if ( $this->permissionManager->userHasRight( $this->getUser(), "managewiki-$module" ) ) {
			$create['info'] = [
				'type' => 'info',
				'default' => $this->msg( "managewiki-$module-create-info" )->text(),
			];

			$create['out'] = [
				'type' => 'text',
				'label-message' => "managewiki-$module-create",
			];

			$createForm = HTMLForm::factory( 'ooui', $hidden + $create, $this->getContext(), 'create' );
			$createForm
				->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )
				->setFormIdentifier( 'create' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( "managewiki-$module-create-header" )
				->setSubmitTextMsg( "managewiki-$module-create-submit" )
				->prepareForm()
				->show();
		}
	}

	public function reusableFormSubmission( array $formData, HTMLForm $form ): void {
		$module = $formData['module'];
		$createNamespace = $form->getSubmitText() === $this->msg( 'managewiki-namespaces-create-submit' )->text() ? '' : $formData['out'];
		$special = $module === 'namespaces' ? ManageWiki::namespaceID( $createNamespace ) : $formData['out'];

		if ( $module === 'namespaces' ) {
			// Save the name of the namespace we are creating to the current session so that
			// we can autofill the input boxes for the namespace in the next form.
			$form->getRequest()->getSession()->set( 'create', $formData['out'] );
		}

		$this->getOutput()->redirect(
			SpecialPage::getTitleFor( 'ManageWiki', "$module/$special" )->getFullURL()
		);
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wikimanage';
	}
}
