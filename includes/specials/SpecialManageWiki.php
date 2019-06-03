<?php
class SpecialManageWiki extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageWiki' );
	}

	public function execute( $par ) {
		global $wgManageWikiHelpUrl, $wgCreateWikiGlobalWiki, $wgDBname, $wgManageWiki, $wgManageWikiBackendModules;

		$par = explode( '/', $par, 3 );

		$out = $this->getOutput();
		$this->setHeaders();

		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		if ( in_array( $par[0], array_diff( array_keys( $wgManageWiki ), $wgManageWikiBackendModules ) ) ) {
			$module = $par[0];
		} else {
			$module = 'core';
		}

		$additional = $par[1] ?? '';

		if ( !ManageWiki::checkSetup( $module, true, $out ) ) {
			return false;
		}

		if ( $wgCreateWikiGlobalWiki !== $wgDBname ) {
			$this->showWikiForm( $wgDBname, $module, $additional );
		} elseif ( $par[0] == '' ) {
			$this->showInputBox();
		} elseif ( $module == 'core' ) {
			$dbName = $par[1] ?? $wgDBname;
			$this->showWikiForm( $dbName, 'core', '' );
		} else {
			$this->showWikiForm( $wgDBname, $module, $additional );
		}
	}

	public function showInputBox() {
		$formDescriptor = [
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'required' => true,
				'name' => 'mwDBname',
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'searchForm' );
		$htmlForm->setMethod( 'post' )
			->setSubmitCallback( [ $this, 'onSubmitRedirectToWikiForm' ] )
			->prepareForm()
			->show();

		return true;
	}

	public function onSubmitRedirectToWikiForm( array $params ) {
		if ( $params['dbname'] !== '' ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki', 'core' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	public function showWikiForm( $wiki, $module, $special ) {
		$out = $this->getOutput();

		$out->addModules( 'ext.createwiki.oouiform' );

		if ( !$special ) {
			$out->addWikiMsg( "managewiki-header-{$module}" );
		}

		if ( $module == 'permissions' && !$special ) {
			$groups = ManageWikiPermissions::availableGroups();

			foreach ( $groups as $group ) {
				$options[UserGroupMembership::getGroupName( $group )] = $group;
			}

			$this->reusableFormDescriptor( $module, $options );
		} elseif ( $module == 'namespaces' && !$special ) {
			$namespaces = ManageWikiNamespaces::configurableNamespaces( true, true, true );

			foreach ( $namespaces as $id => $namespace ) {
				$options[$namespace] = $id;
			}

			$this->reusableFormDescriptor( $module, $options );
		} else {
			$formFactory = new ManageWikiFormFactory();
			$htmlForm = $formFactory->getForm( $wiki, $this->getContext(), $module, $special );
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
	}

	private function reusableFormDescriptor( string $module, array $options ) {
		$hidden['module'] = [
			'type' => 'hidden',
			'default' => $module
		];

		$selector['out'] = [
			'type' => 'select',
			'label-message' => "managewiki-{$module}-select",
			'options' => $options
		];

		$selectForm = HTMLForm::factory( 'ooui', $hidden + $selector, $this->getContext(), 'selector' );
		$selectForm->setMethod( 'post' )->setFormIdentifier( 'selector' )->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )->prepareForm()->show();

		if ( $this->getContext()->getUser()->isAllowed( 'managewiki' ) ) {
			$create['out'] = [
				'type' => 'text',
				'label-message' => "managewiki-{$module}-create",
			];

			$createForm = HTMLForm::factory( 'ooui', $hidden + $create, $this->getContext(), 'create' );
			$createForm->setMethod( 'post' )->setFormIdentifier( 'create' )->setSubmitCallback( [ $this, 'reusableFormSubmission' ] )->prepareForm()->show();
		}
	}

	public function reusableFormSubmission( array $formData ) {
		$module = $formData['module'];
		$url = ( $module == 'namespaces' ) ? ManageWikiNamespaces::nextNamespaceID() : $formData['out'];

		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullUrl() . "/{$url}" );

		return true;
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
