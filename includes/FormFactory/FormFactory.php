<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Status\Status;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\OOUIHTMLFormTabs;
use function implode;

class FormFactory {

	public function __construct(
		private readonly FormFactoryBuilder $formFactoryBuilder
	) {
	}

	public function getForm(
		ModuleFactory $moduleFactory,
		IContextSource $context,
		string $dbname,
		string $module,
		string $special
	): OOUIHTMLFormTabs {
		$context->getOutput()->enableOOUI();
		// Can the user modify ManageWiki?
		$ceMW = !(
			(
				$moduleFactory->core( $dbname )->isLocked() &&
				!$context->getAuthority()->isAllowed( 'managewiki-restricted' )
			) ||
			!$context->getAuthority()->isAllowed( "managewiki-$module" )
		);

		$formDescriptor = $this->formFactoryBuilder->buildDescriptor(
			$moduleFactory, $context, $dbname, $module,
			$special, $ceMW
		);

		$htmlForm = new OOUIHTMLFormTabs( $formDescriptor, $context, $module );
		$htmlForm
			->setSubmitCallback( fn ( array $formData, HTMLForm $form ): Status|false =>
				$this->submitForm(
					$moduleFactory,
					$form,
					$formData,
					$dbname,
					$module,
					$special
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
		ModuleFactory $moduleFactory,
		HTMLForm $form,
		array $formData,
		string $dbname,
		string $module,
		string $special
	): Status|false {
		$context = $form->getContext();
		if ( !$context->getAuthority()->isAllowed( "managewiki-$module" ) ) {
			throw new PermissionsError( "managewiki-$module" );
		}

		$isLocked = $moduleFactory->core( $dbname )->isLocked();
		if ( $isLocked && !$context->getAuthority()->isAllowed( 'managewiki-restricted' ) ) {
			throw new PermissionsError( 'managewiki-restricted' );
		}

		// Avoid 'no field named reason' error
		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )
			->loadDataFromRequest( $form->getRequest() );

		$mwReturn = $this->formFactoryBuilder->submissionHandler(
			$formData, $form, $module, $dbname, $context,
			$moduleFactory, $special
		);

		if ( $mwReturn ) {
			$errorOut = [];
			foreach ( $mwReturn as $errors ) {
				foreach ( $errors as $msg => $params ) {
					$errorOut[] = $form->msg( $msg, $params )->escaped();
				}
			}

			return Status::newFatal(
				new RawMessage( implode( Html::element( 'br' ), $errorOut ) )
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
