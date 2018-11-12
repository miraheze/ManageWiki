<?php

class ManageWikiFormFactory {
	public function getFormDescriptor(
		string $dbName = NULL,
		IContextSource $context,
		string $module = NULL,
		RemoteWiki $wiki
	) {
		global $wgManageWikiExtensions, $wgManageWikiSettings, $wgUser;

		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		$formDescriptor = [];

		$formDescriptor['dbname'] = array(
			'label-message' => 'managewiki-label-dbname',
			'type' => 'hidden',
			'size' => 20,
			'default' => $dbName,
			'disabled' => true,
			'name' => 'mwDBname',
		);

		if ( $module == 'extensions' ) {
			foreach ( $wgManageWikiExtensions as $name => $ext ) {
				$requires_ext = ( (bool)!$ext['requires'] || (bool)$ext['requires'] && $wiki->hasExtension( $ext['requires'] ) );
				if ( !$ext['conflicts'] ) {
					$formDescriptor["ext-$name"] = array(
						'type' => 'check',
						'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
						'default' => $wiki->hasExtension( $name ),
						'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) && $requires_ext || !$ext['restricted'] && $requires_ext ) ? 0 : 1,
						'help' => ( (bool)$ext['requires'] ) ? "Requires: {$ext['requires']}." : null,
						'section' => ( isset( $ext['section'] ) ) ? $ext['section'] : 'other',
					);
				} else {
					$formDescriptor["ext-$name"] = array(
						'type' => 'check',
						'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
						'default' => $wiki->hasExtension ( $name ),
						'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) && $requires_ext || !$ext['restricted'] && $requires_ext ) ? 0 : 1,
						'help' => ( (bool)$ext['requires'] ) ? "Requires: {$ext['requires']}." . " Conflicts: {$ext['conflicts']}." : "Conflicts: {$ext['conflicts']}.",
						'section' => ( isset( $ext['section'] ) ) ? $ext['section'] : 'other',
					);
				}
			}
		} elseif ( $module == 'settings' ) {
			if ( $wgManageWikiSettings ) {
				foreach ( $wgManageWikiSettings as $var => $det ) {

					if ( (bool)!$det['requires'] || (bool)$det['requires'] && $wiki->hasExtension( $det['requires'] ) ) {
						switch ( $det['type'] ) {
							case 'check':
							case 'text':
							case 'url':
								$mwtype = $det['type'];
								break;
							case 'list':
								$mwtype = 'select';
								$mwoptions = $det['options'];
								break;
							case 'list-multi':
								$mwtype = 'multiselect';
								$mwoptions = $det['options'];
								break;
							case 'matrix':
								$mwtype = 'checkmatrix';
								$mwcols = $det['cols'];
								$mwrows = $det['rows'];
								break;
							case 'timezone':
								$mwtype = 'select';
								$mwoptions = ManageWiki::getTimezoneList();
								break;
							case 'wikipage':
								$mwtype = 'title';
								break;
						}

						$formDescriptor["set-$var"] = array(
							'type' => $mwtype,
							'label' => $det['name'],
							'disabled' => ( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] ) ? 0 : 1,
							'help' => ( $det['help'] ) ? $det['help'] : null,
							'section' => ( isset( $det['section'] ) ) ? $det['section'] : 'other',
						);

						if ( $mwtype != 'matrix' ) {
							$formDescriptor["set-$var"]['default'] = ( !is_null( $wiki->getSettingsValue( $var ) ) ) ? $wiki->getSettingsValue( $var ) : $det['overridedefault'];
						} else {
							$formDescriptor["set-$var"]['default'] = ( !is_null( $wiki->getSettingsValue( $var ) ) ) ? ManageWiki::handleMatrix( $wiki->getSettingsValue( $var ), 'php' ) : $det['overridedefault'];
						}

						if ( isset( $mwoptions ) ) {
							$formDescriptor["set-$var"]['options'] = $mwoptions;
						}

						if ( isset( $mwcols ) ) {
							$formDescriptor["set-$var"]['columns'] = $mwcols;
						}

						if ( isset( $mwrows ) ) {
							$formDescriptor["set-$var"]['rows'] = $mwrows;
						}
					}
				}
			}
		} else {
			// nothing yet
		}

		$formDescriptor['reason'] = array(
				'type' => 'text',
				'section' => 'handling',
				'label-message' => 'managewiki-label-reason',
				'size' => 45,
				'required' => true,
		);

		$formDescriptor['submit'] = array(
			'type' => 'submit',
			'default' => wfMessage( 'htmlform-submit' )->text(),
			'section' => 'handling'
		);

		return $formDescriptor;
	}


	public function getForm(
		string $wiki = NULL,
		IContextSource $context,
		string $module = NULL,
		$formClass = CreateWikiOOUIForm::class
	) {
		$remoteWiki = RemoteWiki::newFromName( $wiki );

		if ( $remoteWiki == NULL ) {
			$context->getOutput()->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$formDescriptor = $this->getFormDescriptor( $wiki, $context, $module, $remoteWiki );

		$htmlForm = new $formClass( $formDescriptor, $context, $module );

		$htmlForm->setId( 'mw-baseform-' . $module );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $module ) {
				return $this->submitForm( $formData, $form, $module, $remoteWiki );
			}
		);

		return $htmlForm;
	}

	// Currently this is only able to handle straight cw_wikis changes, so isn't a true FormFactory (except for our 3 appropriate forms)
	protected function submitForm(
		array $formData,
		HTMLForm $form,
		string $module = NULL
	) {
		global $wgDBname, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgManageWikiSettings, $wgUser;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		if ( !$wgUser->isAllowed( 'managewiki' ) ) {
			throw new MWException( "User '{$wgUser->getName()}' without 'managewiki' right tried to change wiki {$module}!" );
		}

		$ceRes = $wgUser->isAllowed( 'managewiki-restricted' );

		$wiki = RemoteWiki::newFromName( $formData['dbname'] );

		$changedsettingsarray = [];
		$errors = [];

		if ( $module == 'extensions' ) {
			$extensionsarray = [];
			foreach ( $wgManageWikiExtensions as $name => $ext ) {
				$mwAllowed = ( $ext['restricted'] && $ceRes || !$ext['restricted'] );
				$value = $formData["ext-$name"];
				$current = $wiki->hasExtension( $name );

				if ( $ext['conflicts'] && $value ) {
					if ( $formData["ext-" . $name] === $formData["ext-" . $ext['conflicts']] ) {
						$errors[] = "Conflict with " . $ext['conflicts'] . ". The $name can not be enabled until " . $ext['conflicts'] . " has been disabled.";
					}
				}

				if ( $value && !(bool)$ext['requires'] || $value && (bool)$ext['requires'] && $wiki->hasExtension( $ext['requires'] ) ) {
					if ( $mwAllowed ) {
						// new extension being added
						$installed = ( !isset( $ext['install'] ) ) ? true : ManageWikiInstaller::process( $formData['dbname'], 'install', $ext['install'] );

						if ( $installed ) {
							$extensionsarray[] = $name;
						} else {
							$errors[] = "$name was not installed successfully.";
						}
					} elseif ( $current ) {
						// should already be installed
						$extensionsarray[] = $name;
					}
				} else {
					if ( $mwAllowed ) {
						// they're cool
					} elseif ( $current ) {
						// should already be installed
						$extensionsarray[] = $name;
					}
				}

				if ( $formData["ext-$name"] != $current ) {
					$changedsettingsarray[] = "ext-" . $name;
				}
			}

	                // HACK: dummy extension name - kinda wanna get rid of soon... now that maybe we can handle this better... JSON!?!
	                $extensionsarray[] = "zzzz";

	                $moduledata = implode( ",", $extensionsarray );
		} elseif ( $module == 'settings' ) {
			$settingsarray = [];

			foreach( $wgManageWikiSettings as $var => $det ) {
				$rmVar = $wiki->getSettingsValue( $var );
				$mwAllowed = ( $det['restricted'] && $ceRes || !$det['restricted'] );
				$type = $det['type'];

				if ( !(bool)$det['requires'] || (bool)$det['requires'] && $wiki->hasExtension( $det['requires'] ) ) {
					$value = $formData["set-$var"];

					if ( $type == 'matrix' ) {
						// we have a matrix
						if ( $mwAllowed ) {
							$settingsarray[$var] = ManageWiki::handleMatrix( $value, 'phparray' );
						} else {
							$settingsarray[$var] = ManageWiki::handleMatrix( $rmVar, 'php' );
						}

						if ( $settingsarray[$var] != ManageWiki::handleMatrix( $rmVar, 'php' ) ) {
							$changedsettingsarray[] = "setting-" . $var;
						}
					} elseif ( $type == 'check' ) {
						// we have a check box
						if ( $mwAllowed ) {
							$settingsarray[$var] = $value;
						} else {
							$settingsarray[$var] = $rmVar;
						}

						if ( $settingsarray[$var] != $rmVar ) {
							$changedsettingsarray[] = "setting-" . $var;
						}
					} elseif ( $type != 'text' || $value ) {
						// we don't have a matrix, we don't have text in all cases, there's a value so let's handle it
						if ( $mwAllowed ) {
							$settingsarray[$var] = $value;
						} else {
							$settingsarray[$var] = $rmVar;
						}

						if (  is_null( $rmVar) && $settingsarray[$var] != $det['overridedefault'] || !is_null( $rmVar) && $settingsarray[$var] != $rmVar ) {
							$changedsettingsarray[] = "setting-" . $var;
						}
					} else {
						// we definitely have text and we don't have a value
						if ( $mwAllowed ) {
							// no need to manipulate, it's good
							continue;
						} else {
							// not good, let's not remove it
							if ( !is_null( $rmVar ) ) {
								$settingsarray[$var] = $rmVar;
							}
						}

						if ( $rmVar != $value ) {
							$changedsettingsarray[] = "setting-" . $var;
						}
					}
				}
			}

			$moduledata = json_encode( $settingsarray );
		} else {
			// nothing yet
		}

		if ( !empty( $errors ) ) {
			return 'The following errors occured: ' . implode( ', ', $errors );
		}

		$changedsettings = implode( ", ", $changedsettingsarray );

		$dbw->selectDB( $wgCreateWikiDatabase );

		$dbw->update( 'cw_wikis',
			array(
				"wiki_{$module}" => $moduledata,
			),
			array(
				'wiki_dbname' => $formData['dbname'],
			),
			__METHOD__
		);

		$dbw->selectDB( $dbName ); // $dbw->close() errors?

		$farmerLogEntry = new ManualLogEntry( 'managewiki', 'settings' );
		$farmerLogEntry->setPerformer( $form->getContext()->getUser() );
		$farmerLogEntry->setTarget( $form->getTitle() );
		$farmerLogEntry->setComment( $formData['reason'] );
		$farmerLogEntry->setParameters(
			array(
				'4::wiki' => $formData['dbname'],
				'5::changes' => $changedsettings,
			)
		);
		$farmerLogID = $farmerLogEntry->insert();
		$farmerLogEntry->publish( $farmerLogID );

		$form->getContext()->getOutput()->addHTML( '<div class="successbox">' . wfMessage( 'managewiki-success' )->escaped() . '</div>' );

		return true;
	}
}
