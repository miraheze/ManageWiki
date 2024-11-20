<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiOOUIForm;
use Miraheze\ManageWiki\ManageWiki;
use UnexpectedValueException;
use Wikimedia\Rdbms\IDatabase;

class ManageWikiFormFactory {

	public function getFormDescriptor(
		string $module,
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		Config $config,
		string $special = '',
		string $filtered = ''
	) {
		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		return ManageWikiFormFactoryBuilder::buildDescriptor( $module, $dbName, $ceMW, $context, $remoteWiki, $special, $filtered, $config );
	}

	public function getForm(
		string $wiki,
		RemoteWikiFactory $remoteWiki,
		IContextSource $context,
		Config $config,
		string $module,
		string $special = '',
		string $filtered = '',
		string $formClass = ManageWikiOOUIForm::class
	) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()
			->getPrimaryDatabase( 'virtual-createwiki' );

		$ceMW = ManageWiki::checkPermission( $remoteWiki, $context->getUser(), $module );

		$formDescriptor = $this->getFormDescriptor( $module, $wiki, $ceMW, $context, $remoteWiki, $config, $special, $filtered );

		$htmlForm = new $formClass( $formDescriptor, $context, $module );

		if ( !$ceMW ) {
			$htmlForm->suppressDefaultSubmit();
		}

		$htmlForm->setSubmitTextMsg( 'managewiki-save' );

		$htmlForm->setId( 'managewiki-form' );
		$htmlForm->setSubmitID( 'managewiki-submit' );

		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $module, $ceMW, $remoteWiki, $special, $filtered, $dbw, $wiki, $config ) {
				return $this->submitForm( $formData, $form, $module, $ceMW, $wiki, $remoteWiki, $dbw, $config, $special, $filtered );
			}
		);

		return $htmlForm;
	}

	protected function submitForm(
		array $formData,
		HTMLForm $form,
		string $module,
		bool $ceMW,
		string $dbName,
		RemoteWikiFactory $remoteWiki,
		IDatabase $dbw,
		Config $config,
		string $special = '',
		string $filtered = ''
	) {
		$context = $form->getContext();
		$out = $context->getOutput();

		if ( !$ceMW ) {
			throw new UnexpectedValueException( "User '{$context->getUser()->getName()}' without 'managewiki-{$module}' right tried to change wiki {$module}!" );
		}

		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )->loadDataFromRequest( $form->getRequest() );

		$mwReturn = ManageWikiFormFactoryBuilder::submissionHandler( $formData, $form, $module, $dbName, $context, $remoteWiki, $dbw, $config, $special, $filtered );

		if ( !empty( $mwReturn ) ) {
			$errorOut = [];
			foreach ( $mwReturn as $errors ) {
				foreach ( $errors as $msg => $params ) {
					$errorOut[] = wfMessage( $msg, $params )->plain();
				}
			}

			$out->addHTML(
				Html::warningBox(
					Html::element(
						'p',
						[],
						'The following errors occurred:<br>' . implode( '<br>', $errorOut )
					),
					'mw-notify-error'
				)
			);
			return null;
		}

		$out->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					wfMessage( 'managewiki-success' )->plain()
				),
				'mw-notify-success'
			)
		);
	}
}
