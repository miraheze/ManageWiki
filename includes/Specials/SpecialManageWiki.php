<?php

namespace Miraheze\ManageWiki\Specials;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\FormFactory\ManageWikiFormFactory;
use Miraheze\ManageWiki\Helpers\ModuleFactory;
use Miraheze\ManageWiki\ManageWiki;
use OOUI\FieldLayout;
use OOUI\SearchInputWidget;

class SpecialManageWiki extends SpecialPage {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly ModuleFactory $moduleFactory,
		private readonly NamespaceInfo $namespaceInfo
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

		$module = 'core';
		if ( array_key_exists( $par[0], $this->getConfig()->get( ConfigNames::ModulesEnabled ) ) ) {
			$module = $par[0];
		}

		if ( !$this->getAuthority()->isAllowed( "managewiki-$module" ) ) {
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
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );

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

	/** @inheritDoc */
	public function getAssociatedNavigationLinks(): array {
		return [
			$this->getPageTitle( 'core' )->getPrefixedText(),
			$this->getPageTitle( 'extensions' )->getPrefixedText(),
			$this->getPageTitle( 'namespaces' )->getPrefixedText(),
			$this->getPageTitle( 'permissions' )->getPrefixedText(),
			$this->getPageTitle( 'settings' )->getPrefixedText(),
		];
	}

	/** @inheritDoc */
	public function getShortDescription( string $path = '' ): string {
		$core = $this->getPageTitle( 'core' )->getText();
		$extensions = $this->getPageTitle( 'extensions' )->getText();
		$namespaces = $this->getPageTitle( 'namespaces' )->getText();
		$permissions = $this->getPageTitle( 'permissions' )->getText();
		$settings = $this->getPageTitle( 'settings' )->getText();

		return match ( $path ) {
			$core => $this->msg( 'managewiki-nav-core' )->text(),
			$extensions => $this->msg( 'managewiki-nav-extensions' )->text(),
			$namespaces => $this->msg( 'managewiki-nav-namespaces' )->text(),
			$permissions => $this->msg( 'managewiki-nav-permissions' )->text(),
			$settings => $this->msg( 'managewiki-nav-settings' )->text(),
			default => '',
		};
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
			$this->getPageTitle( "core/{$formData['dbname']}" )->getFullURL()
		);
	}

	private function showWikiForm(
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): void {
		$this->getOutput()->addModules( [
			'ext.managewiki.oouiform',
			'mediawiki.special.userrights',
		] );

		$this->getOutput()->addModuleStyles( [
			'ext.managewiki.oouiform.styles',
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'oojs-ui-widgets.styles',
		] );

		$session = $this->getRequest()->getSession();
		if ( $session->get( 'manageWikiSaveSuccess' ) ) {
			// Remove session data for the success message
			$session->remove( 'manageWikiSaveSuccess' );
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

		$core = $this->moduleFactory->core( $dbname );
		if ( $core->isLocked() ) {
			$this->getOutput()->addHTML(
				Html::errorBox(
					$this->msg( 'managewiki-mwlocked' )->escaped()
				)
			);
		}

		$options = [];

		// Check permissions
		if ( $module !== 'core' ) {
			if ( !$this->getAuthority()->isAllowed( "managewiki-$module" ) ) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission' )->escaped()
					)
				);
			}
		} else {
			if (
				!$this->getAuthority()->isAllowed( "managewiki-$module" ) &&
				!$this->databaseUtils->isCurrentWikiCentral()
			) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission' )->escaped()
					)
				);
			} elseif ( !$this->getAuthority()->isAllowed( "managewiki-$module" ) ) {
				$this->getOutput()->addHTML(
					Html::errorBox(
						$this->msg( 'managewiki-error-nopermission-remote' )->escaped()
					)
				);
			}
		}

		if ( $special !== '' ) {
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle( $module ) );
		}

		// Handle permissions module when we are not editing a specific group.
		if ( $module === 'permissions' && $special === '' ) {
			$language = $this->getLanguage();
			$groups = array_keys( $this->moduleFactory->permissions( $dbname )->list( group: null ) );

			foreach ( $groups as $group ) {
				$lowerCaseGroupName = $language->lc( $group );
				$options[$language->getGroupName( $lowerCaseGroupName )] = $lowerCaseGroupName;
			}

			$this->reusableFormDescriptor( $dbname, $module, $options );
			return;
		}

		// Handle namespaces module when we are not editing a specific namespace.
		if ( $module === 'namespaces' && $special === '' ) {
			$mwNamespaces = $this->moduleFactory->namespaces( $dbname );
			$namespaces = $mwNamespaces->list( id: null );
			foreach ( $namespaces as $id => $namespace ) {
				if ( $mwNamespaces->isTalk( $id ) ) {
					continue;
				}

				$name = $this->namespaceInfo->getCanonicalName( $id ) ?: $namespace['name'];
				$options[$name] = $id;
			}

			$this->reusableFormDescriptor( $dbname, $module, $options );
			return;
		}

		// Handle all other modules or when we are editing specific namespaces/groups.
		$formFactory = new ManageWikiFormFactory();
		$htmlForm = $formFactory->getForm(
			config: $this->getConfig(),
			moduleFactory: $this->moduleFactory,
			context: $this->getContext(),
			dbname: $dbname,
			module: $module,
			special: mb_strtolower( $special ),
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

	private function reusableFormDescriptor(
		string $dbname,
		string $module,
		array $options
	): void {
		$hidden = [];
		$selector = [];
		$create = [];

		$hidden['dbname'] = [
			'type' => 'hidden',
			'default' => $dbname,
		];

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

		if ( $this->getAuthority()->isAllowed( "managewiki-$module" ) ) {
			$create['info'] = [
				'type' => 'info',
				'default' => $this->msg( "managewiki-$module-create-info" )->text(),
			];

			$create['out'] = [
				'type' => 'text',
				'label-message' => "managewiki-$module-create",
				'required' => true,
			];

			if ( $module === 'permissions' ) {
				// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_permissions.sql#L3
				$create['out']['maxlength'] = 64;
				// Make sure this is lowercase (multi-byte safe), and has no trailing spaces,
				// and that any remaining spaces are converted to underscores.
				$create['out']['filter-callback'] = static fn ( string $value ): string => mb_strtolower(
					str_replace( ' ', '_', trim( $value ) )
				);

				$create['out']['validation-callback'] = [ $this, 'validateNewGroupName' ];
			}

			if ( $module === 'namespaces' ) {
				// Handle namespace validation and normalization
				// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_namespaces.sql#L4
				$create['out']['maxlength'] = 128;
				// Multibyte-safe version of ucfirst
				$create['out']['filter-callback'] = static fn ( string $value ): string =>
					preg_replace_callback(
						'/^(\s*[_:]*)(.)(.*)$/us',
						static fn ( array $m ): string => $m[1] . mb_strtoupper( $m[2], 'UTF-8' ) . $m[3],
						trim( $value )
					) ?? '';

				$create['out']['validation-callback'] = function ( string $value ) use ( $dbname ): bool|Message {
					$disallowed = array_map( 'mb_strtolower',
						$this->getConfig()->get( ConfigNames::NamespacesDisallowedNames )
					);

					if ( in_array( mb_strtolower( $value ), $disallowed, true ) ) {
						return $this->msg( 'managewiki-error-disallowednamespace', $value );
					}

					$mwNamespaces = $this->moduleFactory->namespaces( $dbname );
					if ( $mwNamespaces->nameExists( $value, checkMetaNS: true ) ) {
						return $this->msg( 'managewiki-namespace-conflicts', $value );
					}

					return true;
				};
			}

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
		$isCreateNamespace = $form->getSubmitText() ===
			$this->msg( 'managewiki-namespaces-create-submit' )->text();
		$createNamespace = $isCreateNamespace ? '' : $formData['out'];

		$module = $formData['module'];
		$special = $module === 'namespaces' ?
			ManageWiki::namespaceID( $formData['dbname'], $createNamespace ) :
			$formData['out'];

		if ( $module === 'namespaces' ) {
			// Save the name of the namespace we are creating to the current session so that
			// we can autofill the input boxes for the namespace in the next form.
			$form->getRequest()->getSession()->set( 'create', $formData['out'] );
		}

		$this->getOutput()->redirect(
			$this->getPageTitle( "$module/$special" )->getFullURL()
		);
	}

	public function validateNewGroupName( string $newGroup, array $alldata ): bool|Message {
		$disallowed = $this->getConfig()->get( ConfigNames::PermissionsDisallowedGroups );
		if ( in_array( $newGroup, $disallowed, true ) ) {
			return $this->msg( 'managewiki-permissions-group-disallowed' );
		}

		// We just use this to check if the group is valid for a title,
		// otherwise we can not edit it because the title will be
		// invalid for the ManageWiki permission subpage.
		if ( !$this->getPageTitle( "permissions/$newGroup" )->isValid() ) {
			return $this->msg( 'managewiki-permissions-group-invalid' );
		}

		$mwPermissions = $this->moduleFactory->permissions( $alldata['dbname'] );
		if ( $mwPermissions->exists( $newGroup ) ) {
			return $this->msg( 'managewiki-permissions-group-conflict' );
		}

		return true;
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'wikimanage';
	}
}
