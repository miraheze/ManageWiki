<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\PermissionManager;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\ManageWikiOOUIForm;
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
		PermissionManager $permissionManager,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): ManageWikiOOUIForm {
		// Can the user modify ManageWiki?
		$ceMW = !(
			(
				$remoteWiki->isLocked() &&
				!$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' )
			) ||
			!$permissionManager->userHasRight( $context->getUser(), "managewiki-$module" )
		);

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
		$htmlForm
			->setSubmitCallback(
				function ( array $formData, HTMLForm $form ) use (
					$module, $ceMW, $remoteWiki, $special,
					$filtered, $dbw, $dbname, $config
				): void {
					$this->submitForm(
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
			)
			->setId( 'managewiki-form' )
			->setSubmitID( 'managewiki-submit' )
			->setSubmitTextMsg( 'managewiki-save' );

		if ( !$ceMW ) {
			$htmlForm->suppressDefaultSubmit();
		}

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
		if ( !$ceMW ) {
			throw new UnexpectedValueException( "User '{$form->getUser()->getName()}' without 'managewiki-$module' right tried to change wiki $module!" );
		}

		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )->loadDataFromRequest( $form->getRequest() );

		$context = $form->getContext();
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
					$errorOut[] = $form->msg( $msg, $params )->escaped();
				}
			}

			$form->getOutput()->addHTML(
				Html::warningBox(
					Html::rawElement(
						'p',
						[],
						'The following errors occurred: <br />' . implode( '<br />', $errorOut )
					),
					'mw-notify-error'
				)
			);
			return;
		}

		$form->getOutput()->addHTML(
			Html::successBox(
				Html::element(
					'p',
					[],
					$form->msg( 'managewiki-success' )->text()
				),
				'mw-notify-success'
			)
		);
	}
}
