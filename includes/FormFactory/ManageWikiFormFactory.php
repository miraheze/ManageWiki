<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Output\OutputPage;
use MediaWiki\Status\Status;
use Miraheze\ManageWiki\Helpers\ModuleFactory;
use Miraheze\ManageWiki\ManageWikiOOUIForm;
use UnexpectedValueException;

class ManageWikiFormFactory {

	/**
	 * Generates and returns the form descriptor array for a ManageWiki module.
	 *
	 * Prepares the OOUI environment for the current skin and language direction, then builds the form descriptor using the provided context, module, and configuration.
	 *
	 * @return array Form descriptor suitable for OOUI form construction.
	 */
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

		return ManageWikiFormFactoryBuilder::buildDescriptor(
			$module, $dbname, $ceMW, $context, $moduleFactory,
			$special, $filtered, $config
		);
	}

	/**
	 * Creates and configures a ManageWiki form for a specific module and wiki.
	 *
	 * Determines user permissions, builds the form descriptor, and returns a ManageWikiOOUIForm instance
	 * with appropriate submit handling and UI configuration. The form's submit button is suppressed if the
	 * user lacks permission to edit the module.
	 *
	 * @param string $dbname Database name of the target wiki.
	 * @param string $module Name of the ManageWiki module.
	 * @param string $special Identifier for the special page context.
	 * @param string $filtered Filtered string for form customization.
	 * @return ManageWikiOOUIForm Configured form instance for the specified module and wiki.
	 */
	public function getForm(
		Config $config,
		ModuleFactory $moduleFactory,
		IContextSource $context,
		string $dbname,
		string $module,
		string $special,
		string $filtered
	): ManageWikiOOUIForm {
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

		$htmlForm = new ManageWikiOOUIForm( $formDescriptor, $context, $module );
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

	/**
	 * Processes the submission of a ManageWiki form, handling validation, permission checks, and feedback.
	 *
	 * Throws an UnexpectedValueException if the user lacks permission to edit the specified module. On validation errors, returns a fatal Status with formatted error messages. On success, displays a success message and returns false to keep the form visible after submission.
	 *
	 * @param array $formData Submitted form data.
	 * @param string $dbname Database name of the target wiki.
	 * @param string $module Name of the ManageWiki module being modified.
	 * @param string $special Identifier for the special page context.
	 * @param string $filtered Filtered string for form context.
	 * @param bool $ceMW Whether the user has permission to edit ManageWiki.
	 * @return Status|bool Fatal Status on error, or false on success to keep the form displayed.
	 * @throws UnexpectedValueException If the user does not have permission to edit the module.
	 */
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
		$mwReturn = ManageWikiFormFactoryBuilder::submissionHandler(
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
