<?php

class ManageWikiFormFactory {
	public function getFormDescriptor(
		string $module,
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki,
		Database $dbw,
		string $special = ''
	) {
		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		return ManageWikiFormFactoryBuilder::buildDescriptor( $module, $dbName, $ceMW, $context, $wiki, $special, $dbw );
	}


	public function getForm(
		string $wiki = NULL,
		IContextSource $context,
		string $module = NULL,
		string $special = '',
		$formClass = CreateWikiOOUIForm::class
	) {
		global $wgCreateWikiDatabase;

		$remoteWiki = RemoteWiki::newFromName( $wiki );

		if ( $remoteWiki == NULL ) {
			$context->getOutput()->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$ceMW = ManageWiki::checkPermission( $remoteWiki, $context->getUser() );

		$formDescriptor = $this->getFormDescriptor( $module, $wiki, $ceMW, $context, $remoteWiki, $dbw, $special );

		$htmlForm = new $formClass( $formDescriptor, $context, $module );

		$htmlForm->setId( 'mw-baseform-' . $module );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $module, $ceMW, $remoteWiki, $special, $dbw ) {
				return $this->submitForm( $formData, $form, $module, $ceMW, $wiki, $remoteWiki, $dbw, $special );
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
		RemoteWiki $wiki,
		Database $dbw,
		string $special = ''
	) {
		$context = $form->getContext();
		$out = $context->getOutput();

		if ( !$ceMW ) {
			throw new MWException( "User '{$context->getUser()->getName()}' without 'managewiki' right tried to change wiki {$module}!" );
		}

		$mwReturn = ManageWikiFormFactoryBuilder::submissionHandler( $formData, $form, $module, $dbName, $context, $wiki, $dbw, $special );

		if ( is_array( $mwReturn ) ) {
			$out->addHTML( '<div class="errorbox">The following errors occured:' . implode( '<br>', $mwReturn ) . '</div>' );
			return;
		}

		$out->addHTML( '<div class="successbox">' . wfMessage( 'managewiki-success' )->escaped() . '</div>' );

		return true;
	}
}
