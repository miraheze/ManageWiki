<?php
class SpecialManageWiki extends SpecialPage {
	function __construct() {
		parent::__construct( 'ManageWiki' );
	}

	function execute( $par ) {
		global $wgManageWikiHelpUrl, $wgCreateWikiGlobalWiki, $wgDBname;

		$out = $this->getOutput();
		$this->setHeaders();

		if ( $wgManageWikiHelpUrl ) {
			$this->getOutput()->addHelpLink( $wgManageWikiHelpUrl, true );
		}

		ManageWiki::checkSetup( 'core', true, $out );

		if ( $wgCreateWikiGlobalWiki !== $wgDBname ) {
			$this->showWikiForm( $wgDBname );
		} elseif ( !is_null( $par ) && $par !== '' ) {
			$this->showWikiForm( $par );
		} else {
			$this->showInputBox();
		}
	}

	function showInputBox() {
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

	function onSubmitRedirectToWikiForm( array $params ) {
		global $wgRequest;

		if ( $params['dbname'] !== '' ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki' )->getFullUrl() . '/' . $params['dbname'] );
		} else {
			return 'Invalid url.';
		}

		return true;
	}

	function showWikiForm( $wiki ) {
		global $wgCreateWikiCategories, $wgCreateWikiUseCategories, $wgUser,
			$wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis;

		$out = $this->getOutput();

		$dbName = $wiki;

		$wiki = RemoteWiki::newFromName( $wiki );

		if ( $wiki == NULL ) {
			$out->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$ceMW = ManageWiki::checkPermission( $wiki, $this->getUser() );

		if ( !$this->getRequest()->wasPosted() ) {
			$out->addWikiMsg( 'managewiki-header', $dbName );
		}

		$languages = Language::fetchLanguageNames( null, 'wmfile' );
		ksort( $languages );
		$options = [];
		foreach ( $languages as $code => $name ) {
			$options["$code - $name"] = $code;
		}

		$formDescriptor = [
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'size' => 20,
				'default' => $dbName,
				'disabled' => true,
				'name' => 'mwDBname',
			],
			'sitename' => [
				'label-message' => 'managewiki-label-sitename',
				'type' => 'text',
				'size' => 20,
				'default' => $wiki->getSitename(),
				'disabled' => !$ceMW,
				'required' => true,
				'name' => 'mwSitename',
			],
			'language' => [
				'label-message' => 'managewiki-label-language',
				'type' => 'select',
				'default' => $wiki->getLanguage(),
				'disabled' => !$ceMW,
				'options' => $options,
				'name' => 'mwLanguage',
			],
		];

		if ( $wgCreateWikiUsePrivateWikis ) {
			$formDescriptor['private'] = [
				'type' => 'check',
				'label-message' => 'managewiki-label-private',
				'name' => 'cwPrivate',
				'default' => $wiki->isPrivate() ? 1 : 0,
				'disabled' => !$ceMW
			];
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			$formDescriptor['closed'] = [
				'type' => 'check',
				'label-message' => 'managewiki-label-closed',
				'name' => 'cwClosed',
				'default' => $wiki->isClosed() ? 1 : 0,
				'disabled' => !$ceMW
			];
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			$formDescriptor['inactive'] = [
				'type' => 'check',
				'label-message' => 'managewiki-label-inactive',
				'name' => 'cwInactive',
				'default' => $wiki->isInactive() ? 1 : 0,
				'disabled' => !$ceMW
			];
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			$formDescriptor['category'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $wgCreateWikiCategories,
				'default' => $wiki->getCategory(),
				'disabled' => !$ceMW
			];
		}

		if ( $ceMW ) {
			$formDescriptor += [
				'reason' => [
					'type' => 'text',
					'label-message' => 'managewiki-label-reason',
					'size' => 45,
					'required' => true
				],
				'submit' => [
					'type' => 'submit',
					'default' => wfMessage( 'htmlform-submit' )->text()
				]
			];
		}

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext(), 'changeForm' );
		$htmlForm->setMethod( 'post' )
			->setFormIdentifier( 'wikiForm' )
			->setSubmitCallback( [ $this, 'onSubmitInput' ] )
			->suppressDefaultSubmit()
			->prepareForm()
			->show();

		$landingOpts = [];

		foreach ( (array)ManageWiki::listModules() as $mod ) {
			$landingOpts[ucfirst($mod)] = ucfirst($mod);
		}

		if ( count( $landingOpts ) > 0 ) {
			$out->addWikiMsg( 'managewiki-header' );

			$pageSelector['manage'] = [
				'type' => 'select',
				'options' => $landingOpts,
			];

			$selectForm = HTMLForm::factory( 'ooui', $pageSelector, $this->getContext(), 'pageSelector' );
			$selectForm->setMethod('post' )
				->setFormIdentifier( 'pageSelector' )
				->setSubmitCallback( [ $this, 'onSubmitRedirectToManageWikiPage' ] )
				->prepareForm()
				->show();
		}
	}

	function onSubmitInput( array $params ) {
		global $wgDBname, $wgCreateWikiDatabase, $wgUser, $wgManageWikiSettings, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis, $wgCreateWikiUseCategories, $wgCreateWikiCategories;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		$wiki = RemoteWiki::newFromName( $params['dbname'] );

		ManageWiki::checkPermission( $wiki, $this->getUser(), true );

		$changedsettingsarray = [];

		if ( $wgCreateWikiUsePrivateWikis ) {
			$private = ( $params['private'] == true ) ? 1 : 0;

			$previousPrivate = $wiki->isPrivate();

			if ( $previousPrivate != $private ) {
				// state changed
				if ( $private == 1 ) {
					Hooks::run( 'CreateWikiStatePrivate', [ $params['dbname'] ] );
				} elseif ( $private == 0 ) {
					Hooks::run( 'CreateWikiStatePublic', [ $params['dbname'] ] );
				}
			}
		} else {
			$private = 0;
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			$previousClosed = $wiki->isClosed();

			if ( $params['closed'] && $previousClosed != $params['closed'] ) {
				$closed = 1;
				$closedate = $dbw->timestamp();

				Hooks::run( 'CreateWikiStateClosed', [ $params['dbname'] ] );
			} elseif ( !$params['closed'] && $previousClosed != $params['closed'] ) {
				$closed = 0;
				$closedate = null;

				Hooks::run( 'CreateWikiStateOpen', [ $params['dbname'] ] );
			} else {
				$closed = $previousClosed;
				$closedate = $wiki->closureDate();
			}
		} else {
			$closed = 0;
			$closedate = null;
		}

		if ( $wgCreateWikiUseInactiveWikis && $params['inactive'] ) {
			$inactive = 1;
			$inactivedate = $dbw->timestamp();
		} else {
			$inactive = 0;
			$inactivedate = null;
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			$category = $params['category'];
		} else {
			$category = 'uncategorised';
		}

		$values = [
			'wiki_sitename' => $params['sitename'],
			'wiki_language' => $params['language'],
			'wiki_closed' => $closed,
			'wiki_closed_timestamp' => $closedate,
			'wiki_inactive' => $inactive,
			'wiki_inactive_timestamp' => $inactivedate,
			'wiki_private' => $private,
			'wiki_category' => $category,
		];

		if ( $params['sitename'] != $wiki->getSitename() ) {
			$changedsettingsarray[] = 'sitename';
		}

		if ( $params['language'] != $wiki->getLanguage() ) {
			$changedsettingsarray[] = 'language';
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			if ( $params['closed'] != $wiki->isClosed() ) {
				$changedsettingsarray[] = 'closed';
			}
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			if ( $params['inactive'] != $wiki->isInactive() ) {
				$changedsettingsarray[] = 'inactive';
			}
		}

		if ( $wgCreateWikiUsePrivateWikis ) {
			if ( $params['private'] != $wiki->isPrivate() ) {
				$changedsettingsarray[] = 'private';
			}
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			if ( $params['category'] != $wiki->getCategory() ) {
				$changedsettingsarray[] = 'category';
			}
		}

		$changedsettings = implode( ", ", $changedsettingsarray );

		$dbw->selectDB( $wgCreateWikiDatabase );

		$dbw->update( 'cw_wikis',
			$values,
			[
				'wiki_dbname' => $params['dbname'],
			],
			__METHOD__
		);

		$dbw->selectDB( $dbName ); // $dbw->close() errors?

		$farmerLogEntry = new ManualLogEntry( 'managewiki', 'settings' );
		$farmerLogEntry->setPerformer( $this->getUser() );
		$farmerLogEntry->setTarget( $this->getTitle() );
		$farmerLogEntry->setComment( $params['reason'] );
		$farmerLogEntry->setParameters(
			[
				'4::wiki' => $params['dbname'],
				'5::changes' => $changedsettings,
			]
		);
		$farmerLogID = $farmerLogEntry->insert();
		$farmerLogEntry->publish( $farmerLogID );

		$this->getOutput()->addHTML( '<div class="successbox">' . wfMessage( 'managewiki-success' )->escaped() . '</div>' );

		return true;
	}

	function onSubmitRedirectToManageWikiPage( array $params ) {
		header( 'Location: ' . SpecialPage::getTitleFor( 'ManageWiki' )->getFullUrl() . $params['manage'] );
		return true;
	}

	protected function getGroupName() {
		return 'wikimanage';
	}
}
