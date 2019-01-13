<?php

class ManageWikiFormFactory {
	public function getFormDescriptor(
		string $dbName = NULL,
		IContextSource $context,
		string $module = NULL,
		string $special = "",
		bool $ceMW = false,
		RemoteWiki $wiki
	) {
		global $wgManageWikiExtensions, $wgManageWikiSettings, $wgUser, $wgCreateWikiDatabase;

		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		$formDescriptor = [
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'hidden',
				'size' => 20,
				'default' => $dbName,
				'disabled' => true,
				'name' => 'mwDBname',
			]
		];

		if ( $module == 'extensions' ) {
			foreach ( $wgManageWikiExtensions as $name => $ext ) {
				$requiresExt = ( (bool)!$ext['requires'] || (bool)$ext['requires'] && $wiki->hasExtension( $ext['requires'] ) );
				$disabled = !( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) && $requiresExt || !$ext['restricted'] && $requiresExt );
				if ( !$ext['conflicts'] ) {
					$formDescriptor["ext-$name"] = [
						'type' => 'check',
						'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
						'default' => $wiki->hasExtension( $name ),
						'disabled' => ( $ceMW ) ? $disabled : 1,
						'help' => ( (bool)$ext['requires'] ) ? "Requires: {$ext['requires']}." : null,
						'section' => ( isset( $ext['section'] ) ) ? $ext['section'] : 'other',
					];
				} else {
					$formDescriptor["ext-$name"] = [
						'type' => 'check',
						'label-message' => ['managewiki-extension-name', $ext['linkPage'], $ext['name']],
						'default' => $wiki->hasExtension ( $name ),
						'disabled' => ( $ext['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) && $requiresExt || !$ext['restricted'] && $requiresExt || $ceMW ) ? 0 : 1,
						'help' => ( (bool)$ext['requires'] ) ? "Requires: {$ext['requires']}." . " Conflicts: {$ext['conflicts']}." : "Conflicts: {$ext['conflicts']}.",
						'section' => ( isset( $ext['section'] ) ) ? $ext['section'] : 'other',
					];
				}
			}
		} elseif ( $module == 'settings' ) {
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

					$disabled = !( $det['restricted'] && $wgUser->isAllowed( 'managewiki-restricted' ) || !$det['restricted'] );

					$formDescriptor["set-$var"] = [
						'type' => $mwtype,
						'label' => $det['name'],
						'disabled' => ( $ceMW ) ? $disabled : 1,
						'help' => ( $det['help'] ) ? $det['help'] : null,
						'section' => ( isset( $det['section'] ) ) ? $det['section'] : 'other',
					];

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
		} elseif ( $module == 'namespaces' ) {
			$dbr = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );

			$nsID = [
				'namespace' => (int)$special,
				'namespacetalk' => (int)$special + 1
			];

			foreach( [ 'namespace', 'namespacetalk' ] as $name ) {
				$nsData = $dbr->selectRow(
					'mw_namespaces',
					[
						'ns_namespace_name',
						'ns_content',
						'ns_subpages',
						'ns_searchable',
						'ns_protection',
						'ns_aliases',
						'ns_core'
					],
					[
						'ns_dbname' => $dbName,
						'ns_namespace_id' => $nsID[$name]
					],
					__METHOD__
				);

				$formDescriptor += [
					"namespace-$name" => [
						'type' => 'text',
						'label-message' => "namespaces-$name",
						'default' => ( $nsData ) ? $nsData->ns_namespace_name : NULL,
						'disabled' => ( !$ceMW || ( $nsData && (bool)$nsData->ns_core ) ),
						'required' => true,
						'section' => "$name"
					],
					"content-$name" => [
						'type' => 'check',
						'label-message' => 'namespaces-content',
						'default' => ( $nsData ) ? $nsData->ns_content : 0,
						'disabled' => !$ceMW,
						'section' => "$name"
					],
					"subpages-$name" => [
						'type' => 'check',
						'label-message' => 'namespaces-subpages',
						'default' => ( $nsData ) ? $nsData->ns_subpages : 0,
						'disabled' => !$ceMW,
						'section' => "$name"
					],
					"search-$name" => [
						'type' => 'check',
						'label-message' => 'namespaces-search',
						'default' => ( $nsData ) ? $nsData->ns_searchable : 0,
						'disabled' => !$ceMW,
						'section' => "$name"
					],
					"protection-$name" => [
						'type' => 'selectorother',
						'label-message' => 'namespaces-protection',
						'section' => "$name",
						'default' => ( $nsData ) ? $nsData->ns_protection : '',
						'disabled' => !$ceMW,
						'options' => [
							'None' => '',
							'editinterface' => 'editinterface',
							'editsemiprotected' => 'editsemiprotected',
							'editprotected' => 'editprotected'
						]
					],
					"aliases-$name" => [
						'type' => 'textarea',
						'label-message' => 'namespaces-aliases',
						'default' => ( $nsData ) ? implode( "\n", json_decode( $nsData->ns_aliases, true ) ) : NULL,
						'disabled' => !$ceMW,
						'section' => "$name"
					]
				];
			}
			if ( $ceMW && !$formDescriptor['namespace-namespace']['disabled'] ) {
				$namespaces = ManageWikiNamespaces::configurableNamespaces( $id = true, $readable = true, $main = true );
				$craftedNamespaces = [];
				$canDelete = false;

				foreach( $namespaces as $id => $namespace ) {
					if ( $id !== $nsID['namespace'] ) {
						$craftedNamespaces[$namespace] = $id;
					} else {
						$canDelete = true; //existing namespace
					}
				}

				$formDescriptor += [
					'delete-checkbox' => [
						'type' => 'check',
						'label-message' => 'namespaces-delete-checkbox',
						'section' => 'delete',
						'default' => 0,
						'disabled' => !$canDelete
					],
					'delete-migrate-to' => [
						'type' => 'select',
						'label-message' => 'namespaces-migrate-to',
						'options' => $craftedNamespaces,
						'section' => 'delete',
						'default' => 0,
						'disabled' => !$canDelete
					]
				];
			}
		} else {
			// nothing yet
		}

		if ( $ceMW ) {
			$formDescriptor['reason'] = [
					'type' => 'text',
					'section' => 'handling',
					'label-message' => 'managewiki-label-reason',
					'size' => 45,
					'required' => true,
			];

			$formDescriptor['submit'] = [
				'type' => 'submit',
				'default' => wfMessage( 'htmlform-submit' )->text(),
				'section' => 'handling'
			];
		}

		return $formDescriptor;
	}


	public function getForm(
		string $wiki = NULL,
		IContextSource $context,
		string $module = NULL,
		string $special = "",
		$formClass = CreateWikiOOUIForm::class
	) {
		$remoteWiki = RemoteWiki::newFromName( $wiki );

		if ( $remoteWiki == NULL ) {
			$context->getOutput()->addHTML( '<div class="errorbox">' . wfMessage( 'managewiki-missing' )->escaped() . '</div>' );
			return false;
		}

		$ceMW = ManageWiki::checkPermission( $remoteWiki, $context->getUser() );

		$formDescriptor = $this->getFormDescriptor( $wiki, $context, $module, $special, $ceMW, $remoteWiki );

		$htmlForm = new $formClass( $formDescriptor, $context, $module );

		$htmlForm->setId( 'mw-baseform-' . $module );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $module, $ceMW, $remoteWiki, $special ) {
				return $this->submitForm( $formData, $form, $module, $ceMW, $remoteWiki, $special );
			}
		);

		return $htmlForm;
	}

	protected function submitForm(
		array $formData,
		HTMLForm $form,
		string $module = NULL,
		bool $ceMW = false,
		RemoteWiki $wiki,
		string $special = ""
	) {
		global $wgDBname, $wgCreateWikiDatabase, $wgManageWikiExtensions, $wgManageWikiSettings, $wgUser;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
		$dbName = $wgDBname;

		if ( !$ceMW ) {
			throw new MWException( "User '{$wgUser->getName()}' without 'managewiki' right tried to change wiki {$module}!" );
		}

		$ceRes = $wgUser->isAllowed( 'managewiki-restricted' );

		$changedsettingsarray = [];
		$errors = [];
		$mwStore = 'cw_wikis';

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
		} elseif ( $module == 'namespaces' ) {
			$mwStore = 'mw_namespaces';
			$build = [];

			$nsID = [
				'namespace' => (int)$special,
				'namespacetalk' => (int)$special + 1
			];

			$existingNamespace = $dbw->selectRow(
				'mw_namespaces',
				'ns_namespace_name',
				[
					'ns_dbname' => $wgDBname,
					'ns_namespace_id' => $nsID['namespace']
				],
				__METHOD__
			);

			foreach ( [ 'namespace', 'namespacetalk' ] as $name ) {
				$build[$name] = [
					'ns_dbname' => $wgDBname,
					'ns_namespace_id' => $nsID[$name],
					'ns_namespace_name' => str_replace( ' ', '_', $formData["namespace-$name"] ),
					'ns_searchable' => (int)$formData["search-$name"],
					'ns_subpages' => (int)$formData["subpages-$name"],
					'ns_aliases' => $formData["aliases-$name"] == "" ? "[]" : json_encode( explode( "\n", $formData["aliases-$name"] ) ),
					'ns_protection' => $formData["protection-$name"],
					'ns_content' => (int)$formData["content-$name"]
				];
			}
		} else {
			// nothing yet
		}

		if ( !empty( $errors ) ) {
			return 'The following errors occured: ' . implode( ', ', $errors );
		}

		$dbw->selectDB( $wgCreateWikiDatabase );

		if ( $mwStore == 'cw_wikis' ) {
			$mwLog = 'settings';
			$changedSettings = implode( ", ", $changedsettingsarray );
			$logData = [
				'4::wiki' => $formData['dbname'],
				'5::changes' => $changedSettings
			];

			$dbw->update( 'cw_wikis',
				[
					"wiki_{$module}" => $moduledata,
				],
				[
					'wiki_dbname' => $formData['dbname'],
				],
				__METHOD__
			);
		} elseif( $mwStore == 'mw_namespaces' ) {
			$mwLog = 'namespaces';
			$logData = [
				'4::wiki' => $formData['dbname'],
				'5::namespace' => $build['namespace']['ns_namespace_name']
			];
			if ( isset( $formData['delete-checkbox'] ) && $formData['delete-checkbox'] ) {
				$mwLog .= '-delete';
				foreach ( [ 'namespace', 'namespacetalk' ] as $name ) {
					$dbw->delete( 'mw_namespaces',
						[
							'ns_dbname' => $build[$name]['ns_dbname'],
							'ns_namespace_name' => $build[$name]['ns_namespace_name']
						],
						__METHOD__
					);

					$jobParams = array(
						'action' => 'delete',
						'nsID' => $build[$name]['ns_namespace_id'],
						'nsName' => $build[$name]['ns_namespace_name'],
						'nsNew' => $formData['delete-migrate-to']
					);
					$job = new NamespaceMigrationJob( Title::newFromText( 'Special:ManageWikiNamespaces' ), $jobParams );
					JobQueueGroup::singleton()->push( $job );
				}
			} else {
				foreach ( [ 'namespace', 'namespacetalk' ] as $name ) {
					if ( $existingNamespace ) {
						$dbw->update( 'mw_namespaces',
							$build[$name],
							[
								'ns_dbname' => $build[$name]['ns_dbname'],
								'ns_namespace_id' => $build[$name]['ns_namespace_id']
							],
							__METHOD__
						);

						$jobParams = array(
							'action' => 'rename',
							'nsName' => $build[$name]['ns_namespace_name'],
							'nsID' => $build[$name]['ns_namespace_id']
						);
						$job = new NamespaceMigrationJob( Title::newFromText( 'Special:ManageWikiNamespaces' ), $jobParams );
						JobQueueGroup::singleton()->push( $job );
					} else {
						$dbw->insert( 'mw_namespaces',
							$build[$name],
							__METHOD__
						);

						$jobParams = array(
							'action' => 'create',
							'nsName' => $build[$name]['ns_namespace_name'],
							'nsID' => $build[$name]['ns_namespace_id']
						);
						$job = new NamespaceMigrationJob( Title::newFromText( 'Special:ManageWikiNamespaces' ), $jobParams );
						JobQueueGroup::singleton()->push( $job );
					}
				}
			}

			ManageWikiCDB::changes( 'namespaces' );
		}

		$dbw->selectDB( $dbName ); // $dbw->close() errors?

		$farmerLogEntry = new ManualLogEntry( 'managewiki', $mwLog );
		$farmerLogEntry->setPerformer( $form->getContext()->getUser() );
		$farmerLogEntry->setTarget( $form->getTitle() );
		$farmerLogEntry->setComment( $formData['reason'] );
		$farmerLogEntry->setParameters( $logData );
		$farmerLogID = $farmerLogEntry->insert();
		$farmerLogEntry->publish( $farmerLogID );

		$form->getContext()->getOutput()->addHTML( '<div class="successbox">' . wfMessage( 'managewiki-success' )->escaped() . '</div>' );

		return true;
	}
}
