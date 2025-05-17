<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Status\Status;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\OOUIHTMLFormTabs;
use UnexpectedValueException;

class FormFactory {

	private function getFormDescriptor(
		Config $config,
		ModuleFactory $moduleFactory,
		IContextSource $context,
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

		return FormFactoryBuilder::buildDescriptor(
			$module, $dbname, $ceMW, $context, $moduleFactory,
			$special, $filtered, $config
		);
	}

	public function getForm(
		Config $config,
		ModuleFactory $moduleFactory,
		IContextSource $context,
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): OOUIHTMLFormTabs {
		// Can the user modify ManageWiki?
		$ceMW = !(
			(
				$moduleFactory->core( $dbname )->isLocked() &&
				!$context->getAuthority()->isAllowed( 'managewiki-restricted' )
			) ||
			!$context->getAuthority()->isAllowed( "managewiki-$module" )
		);

		$formDescriptor = $this->getFormDescriptor(
			$config,
			$moduleFactory,
			$context,
			$dbname,
			$module,
			$special,
			$filtered,
			$ceMW
		);

		$htmlForm = new OOUIHTMLFormTabs( $formDescriptor, $context, $module );
		$htmlForm
			->setSubmitCallback( fn ( array $formData, HTMLForm $form ): Status|bool =>
				$this->submitForm(
					$config,
					$moduleFactory,
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
		Config $config,
		ModuleFactory $moduleFactory,
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
		$mwReturn = FormFactoryBuilder::submissionHandler(
			$formData,
			$form,
			$module,
			$dbname,
			$context,
			$moduleFactory,
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
