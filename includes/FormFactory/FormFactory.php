<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Status\Status;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\ManageWikiOOUIForm;
use UnexpectedValueException;
use Wikimedia\Rdbms\IDatabase;

class FormFactory {

	public function __construct(
		private readonly FormFactoryBuilder $builder,
		private readonly RemoteWikiFactory $remoteWikiFactory
	) {
	}

	public function getForm(
		IContextSource $context,
		IDatabase $dbw,
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): ManageWikiOOUIForm {
		$remoteWiki = $this->remoteWikiFactory->newInstance( $dbname );
		// Can the user modify ManageWiki?
		$ceMW = !(
			(
				$remoteWiki->isLocked() &&
				!$context->getAuthority()->isAllowed( 'managewiki-restricted' )
			) ||
			!$context->getAuthority()->isAllowed( "managewiki-$module" )
		);

		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		$formDescriptor = $this->builder->buildDescriptor(
			$context, $module, $dbname, $ceMW, $special, $filtered
		);

		$htmlForm = new ManageWikiOOUIForm( $formDescriptor, $context, $module );
		$htmlForm
			->setSubmitCallback( fn ( array $formData, HTMLForm $form ): Status|bool =>
				$this->submitForm(
					$dbw,
					$form,
					$formData,
					$dbname,
					$module,
					$special,
					$filtered,
					$ceMW
				)
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
		IDatabase $dbw,
		HTMLForm $form,
		array $formData,
		string $dbname,
		string $module,
		string $special,
		string $filtered,
		bool $ceMW
	): Status|bool {
		if ( !$ceMW ) {
			throw new UnexpectedValueException(
				"User '{$form->getUser()->getName()}' without 'managewiki-$module' " .
				"right tried to change wiki $module!"
			);
		}

		// Avoid 'no field named reason' error
		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )
			->loadDataFromRequest( $form->getRequest() );

		$context = $form->getContext();
		$mwReturn = $this->builder->submissionHandler(
			$context,
			$dbw,
			$formData,
			$form,
			$module,
			$dbname,
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

			return Status::newFatal(
				new RawMessage( implode( '<br />', $errorOut ) )
			);
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

		// Even though it's successful we still return false so
		// that the form does not dissappear when submitted.
		return false;
	}
}
