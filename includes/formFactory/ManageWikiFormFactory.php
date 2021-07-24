<?php

class ManageWikiFormFactory {
	public function getFormDescriptor(
		string $module,
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki,
		Config $config,
		string $special = ''
	) {
		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		$dbr = wfGetDB( DB_REPLICA, [], $config->get( 'CreateWikiDatabase' ) );

		$check = $dbr->selectRow(
			'cw_wikis',
			'wiki_dbname', [
				'wiki_dbname' => $wiki
			],
			__METHOD__
		);

		if ( !(bool)$check ) {
			return Html::errorBox( wfMessage( 'managewiki-error-dbnotexists' )->parse() );
		}

		return ManageWikiFormFactoryBuilder::buildDescriptor( $module, $dbName, $ceMW, $context, $wiki, $special, $config );
	}


	public function getForm(
		string $wiki,
		RemoteWiki $remoteWiki,
		IContextSource $context,
		Config $config,
		string $module,
		string $special = '',
		$formClass = ManageWikiOOUIForm::class
	) {
		$dbw = wfGetDB( DB_PRIMARY, [], $config->get( 'CreateWikiDatabase' ) );

		$ceMW = ManageWiki::checkPermission( $remoteWiki, $context->getUser() );

		$formDescriptor = $this->getFormDescriptor( $module, $wiki, $ceMW, $context, $remoteWiki, $config, $special );

		$htmlForm = new $formClass( $formDescriptor, $context, $module );

		if ( !$ceMW ) {
			$htmlForm->suppressDefaultSubmit();
		}

		$htmlForm->setSubmitTextMsg( 'managewiki-save' );

		$htmlForm->setId( 'mw-baseform-' . $module );
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $module, $ceMW, $remoteWiki, $special, $dbw, $wiki, $config ) {
				return $this->submitForm( $formData, $form, $module, $ceMW, $wiki, $remoteWiki, $dbw, $config, $special );
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
		DBConnRef $dbw,
		Config $config,
		string $special = ''
	) {
		$context = $form->getContext();
		$out = $context->getOutput();

		if ( !$ceMW ) {
			throw new MWException( "User '{$context->getUser()->getName()}' without 'managewiki' right tried to change wiki {$module}!" );
		}

		$form->getButtons();
		$formData['reason'] = $form->getField( 'reason' )->loadDataFromRequest( $form->getRequest() );

		$mwReturn = ManageWikiFormFactoryBuilder::submissionHandler( $formData, $form, $module, $dbName, $context, $wiki, $dbw, $config, $special );

		if ( !empty( $mwReturn ) ) {
			$errorOut = [];
			foreach ( $mwReturn as $errors ) {
				foreach ( $errors as $msg => $params ) {
					$errorOut[] = wfMessage( $msg, $params )->inContentLanguage()->escaped();
				}
			}

			$out->addHTML( Html::errorBox( 'The following errors occurred:<br>' . implode( '<br>', $errorOut ) ) );
			return null;
		}

		$out->addHTML( Html::successBox( wfMessage( 'managewiki-success' )->escaped() ) );
	}
}
