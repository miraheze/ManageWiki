<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
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

		$out = $this->getOutput();
		$this->setHeaders();

		if ( $this->getConfig()->get( 'ManageWikiHelpUrl' ) ) {
			$out->addHelpLink( $this->getConfig()->get( 'ManageWikiHelpUrl' ), true );
		}

		$module = 'core';
		if ( array_key_exists( $par[0], $this->getConfig()->get( 'ManageWiki' ) ) ) {
			$module = $par[0];
		}

		if ( !$this->getContext()->getUser()->isAllowed( "managewiki-$module" ) ) {
			$out->setPageTitleMsg( $this->msg( "managewiki-link-$module-view" ) );
			if ( $module !== 'permissions' || $module !== 'namespaces' ) {
				$out->addWikiMsg( "managewiki-header-$module-view" );
			}
		} else {
			$out->setPageTitleMsg( $this->msg( "managewiki-link-$module" ) );
			if ( $module !== 'permissions' || $module !== 'namespaces' ) {
				$out->addWikiMsg( "managewiki-header-$module" );
			}
		}

		$additional = $par[1] ?? '';
		$filtered = $par[2] ?? $par[1] ?? '';

		if ( !ManageWiki::checkSetup( $module, true, $out ) ) {
			return;
		}

		if ( $module === 'permissions' && $additional ) {
			$out->addSubtitle( $out->msg( 'editing' )->params( $additional ) );
		}

		$isCentralWiki = $this->databaseUtils->isCurrentWikiCentral();

		if ( !$isCentralWiki ) {
			$this->showWikiForm( $this->getConfig()->get( MainConfigNames::DBname ), $module, $additional, $filtered );
		} elseif ( $par[0] === '' ) {
			$this->showInputBox();
		} elseif ( $module === 'core' ) {
			$dbname = $par[1] ?? $this->getConfig()->get( MainConfigNames::DBname );
			$this->showWikiForm( strtolower( $dbname ), 'core', '', '' );
		} else {
			$this->showWikiForm( $this->getConfig()->get( MainConfigNames::DBname ), $module, $additional, $filtered );
		}
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
				'default' => $this->msg( 'managewiki-core-info' )->text(),
				'type' => 'info',
			],
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'required' => true,
			]
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
		$out = $this->getOutput();

		if ( $special !== '' || in_array( $module, [ 'core', 'extensions', 'settings' ] ) ) {
			$out->addModules( [
				'ext.managewiki.oouiform',
				'mediawiki.special.userrights',
			] );

			$out->addModuleStyles( [
				'ext.managewiki.oouiform.styles',
				'mediawiki.widgets.TagMultiselectWidget.styles',
				'oojs-ui-widgets.styles',
			] );
		}

		$remoteWiki = $this->remoteWikiFactory->newInstance( $dbname );

		if ( $remoteWiki->isLocked() ) {
			$out->addHTML( Html::errorBox( $this->msg( 'managewiki-mwlocked' )->escaped() ) );
		}

		$options = [];

		if ( $module !== 'core' ) {
			if ( !$this->getContext()->getUser()->isAllowed( 'managewiki-' . $module ) ) {
				$out->addHTML(
					Html::errorBox( $this->msg( 'managewiki-error-nopermission' )->escaped() )
				);
			}
		} else {
			if ( !$this->getContext()->getUser()->isAllowed( 'managewiki-' . $module ) && !( $this->databaseUtils->isCurrentWikiCentral() ) ) {
				$out->addHTML(
					Html::errorBox( $this->msg( 'managewiki-error-nopermission' )->escaped() )
				);
			} elseif ( !$this->getContext()->getUser()->isAllowed( 'managewiki-' . $module ) ) {
				$out->addHTML(
					Html::errorBox( $this->msg( 'managewiki-error-nopermission-remote' )->escaped() )
				);
			}
		}

		if ( $module === 'permissions' && !$special ) {
			$language = $this->getContext()->getLanguage();
			$mwPermissions = new ManageWikiPermissions( $dbname );
			$groups = array_keys( $mwPermissions->list() );

			foreach ( $groups as $group ) {
				$lowerCaseGroupName = strtolower( $group );
				$options[$language->getGroupName( $lowerCaseGroupName )] = $lowerCaseGroupName;
			}

			$this->reusableFormDescriptor( $module, $options );
		} elseif ( $module === 'namespaces' && $special === '' ) {
			$mwNamespaces = new ManageWikiNamespaces( $dbname );
			$namespaces = $mwNamespaces->list();

			foreach ( $namespaces as $id => $namespace ) {
				if ( $mwNamespaces->isTalk( $id ) ) {
					continue;
				}

				$options[$namespace['name']] = $id;
			}

			$this->reusableFormDescriptor( $module, $options );
		} else {
			$formFactory = new ManageWikiFormFactory();
			$htmlForm = $formFactory->getForm(
				config: $this->getConfig(),
				context: $this->getContext(),
				dbw: $this->databaseUtils->getGlobalPrimaryDB(),
				remoteWiki: $remoteWiki,
				dbname: $dbname,
				module: $module,
				special: strtolower( $special ),
				filtered: $filtered
			);

			$out->addHTML( new FieldLayout(
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
			'default' => $this->msg( "managewiki-{$module}-select-info" )->text(),
		];

		$selector['out'] = [
			'type' => 'select',
			'label-message' => "managewiki-{$module}-select",
			'options' => $options,
		];

		$selectForm = HTMLForm::factory( 'ooui', $hidden + $selector, $this->getContext(), 'selector' );
		$selectForm
			->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )
			->setFormIdentifier( 'selector' )
			->setMethod( 'post' )
			->setWrapperLegendMsg( "managewiki-{$module}-select-header" )
			->prepareForm()
			->show();

		if ( $this->permissionManager->userHasRight( $this->getContext()->getUser(), 'managewiki-' . $module ) ) {
			$create['info'] = [
				'type' => 'info',
				'default' => $this->msg( "managewiki-{$module}-create-info" )->text(),
			];

			$create['out'] = [
				'type' => 'text',
				'label-message' => "managewiki-{$module}-create",
			];

			$createForm = HTMLForm::factory( 'ooui', $hidden + $create, $this->getContext(), 'create' );
			$createForm
				->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )
				->setFormIdentifier( 'create' )
				->setMethod( 'post' )
				->setWrapperLegendMsg( "managewiki-{$module}-create-header" )
				->setSubmitTextMsg( "managewiki-{$module}-create-submit" )
				->prepareForm()
				->show();
		}
	}

	public function reusableFormSubmission( array $formData, HTMLForm $form ): void {
		$module = $formData['module'];
		$createNamespace = ( $form->getSubmitText() === $this->msg( 'managewiki-namespaces-create-submit' )->text() ) ? '' : $formData['out'];
		$url = $module === 'namespaces' ? ManageWiki::namespaceID( $createNamespace ) : $formData['out'];

		if ( $module === 'namespaces' ) {
			$form->getRequest()->getSession()->set( 'create', $formData['out'] );
		}

		$this->getOutput()->redirect(
			SpecialPage::getTitleFor( 'ManageWiki', "$module/$url" )->getFullURL()
		);
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wikimanage';
	}
}
