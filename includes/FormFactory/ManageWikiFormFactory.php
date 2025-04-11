<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Output\OutputPage;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiOOUIForm;
use Miraheze\ManageWiki\ManageWiki;
use UnexpectedValueException;
use Wikimedia\Rdbms\IDatabase;

class ManageWikiFormFactory {

	private function getFormDescriptor(
		Config $config,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		string $module,
		string $special,
		string $filtered,
		bool $ceMW
	): array {
		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		return ManageWikiFormFactoryBuilder::buildDescriptor( $module, $dbname, $ceMW, $context, $remoteWiki, $special, $filtered, $config );
	}

	public function getForm(
		Config $config,
		IContextSource $context,
		IDatabase $dbw,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): ManageWikiOOUIForm {
		$ceMW = ManageWiki::checkPermission( $remoteWiki, $context->getUser(), $module );

		$formDescriptor = $this->getFormDescriptor(
			$config,
			$context,
			$remoteWiki,
			$dbname,
			$module,
			$special,
			$filtered,
			$ceMW
		);

		$htmlForm = new ManageWikiOOUIForm( $formDescriptor, $context, $module );

		if ( !$ceMW ) {
			$htmlForm->suppressDefaultSubmit();
		}

		$htmlForm->setSubmitTextMsg( 'managewiki-save' );

		$htmlForm->setId( 'managewiki-form' );
		$htmlForm->setSubmitID( 'managewiki-submit' );

		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use (
				$module, $ceMW, $remoteWiki, $special,
				$filtered, $dbw, $dbname, $config
			): void {
				return $this->submitForm(
					$config,
					$dbw,
					$form,
					$remoteWiki,
					$formData,
					$dbname,
					$module,
					$special,
					$filtered,
					$ceMW
				);
			}
		);

		return $htmlForm;
	}

	protected function submitForm(
		Config $config,
		IDatabase $dbw,
		HTMLForm $form,
		RemoteWikiFactory $remoteWiki,
		array $formData,
		string $dbname,
		string $module,
		string $special,
		string $filtered,
		bool $ceMW
	): void {
		$context = $form->getContext();
		$out = $context->getOutput();

		if ( !$ceMW ) {
			throw new UnexpectedValueException( "User '{$context->getUser()->getName()}' without 'managewiki-{$module}' right tried to change wiki {$module}!" );
		}

		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )->loadDataFromRequest( $form->getRequest() );

		$mwReturn = ManageWikiFormFactoryBuilder::submissionHandler(
			$formData,
			$form,
			$module,
			$dbname,
			$context,
			$remoteWiki,
			$dbw,
			$config,
			$special,
			$filtered
		);

		if ( $mwReturn ) {
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
			return;
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
