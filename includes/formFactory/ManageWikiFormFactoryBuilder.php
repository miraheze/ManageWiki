<?php

class ManageWikiFormFactoryBuilder {
	public static function buildDescriptor(
		string $module,
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki,
		string $special,
		Database $dbw
	) {
		switch ( $module ) {
			case 'extensions':
				$formDescriptor = self::buildDescriptorExtensions( $dbName, $ceMW, $context, $wiki );
				break;
			case 'settings':
				$formDescriptor = self::buildDescriptorSettings( $ceMW, $context, $wiki );
				break;
			case 'namespaces':
				$formDescriptor = self::buildDescriptorNamespaces( $dbName, $ceMW, $special, $dbw );
				break;
			case 'permissions':
				$formDescriptor = self::buildDescriptorPermissions( $dbName, $ceMW, $special );
				break;
		}

		if ( $ceMW ) {
			$formDescriptor += [
				'reason' => [
					'type' => 'text',
					'section' => 'handling',
					'label-message' => 'managewiki-label-reason',
					'size' => 45,
					'required' => true
				],
				'submit' => [
					'type' => 'submit',
					'default' => wfMessage( 'htmlform-submit' )->text(),
					'section' => 'handling'
				]
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorExtensions(
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgManageWikiExtensions;

		$formDescriptor = [];

		foreach ( $wgManageWikiExtensions as $name => $ext ) {
			$help = [];

			if ( $ext['conflicts'] ) {
				$help[] = "Conflicts with {$ext['conflicts']}";
			}

			if ( $ext['requires'] ) {
				$requires = [];
				foreach ( $ext['requires'] as $require => $data ) {
					$requires[] = ucfirst( $require ) . " - " . implode( ', ', $data );
				}

				$help[] = "Requires: " . implode( ' & ', $requires );
			}

			$formDescriptor["ext-$name"] = [
				'type' => 'check',
				'label-message' => [
					'managewiki-extension-name',
					$ext['linkPage'],
					$ext['name']
				],
				'default' => $wiki->hasExtension( $name ),
				'disabled' => ( $ceMW ) ? !ManageWikiRequirements::process( $dbName, $ext['requires'], $context ) : 1,
				'help' => (string)implode( ' ', $help ),
				'section' => ( isset( $ext['section'] ) ) ? $ext['section'] : 'other',
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorSettings(
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgManageWikiSettings;

		$formDescriptor = [];

		foreach ( $wgManageWikiSettings as $name => $set ) {
			$add = ( $set['from'] == 'mediawiki' ) ? true : $wiki->hasExtension( $set['from'] );

			if ( $add ) {
				switch ( $set['type'] ) {
					case 'check':
					case 'text':
					case 'url':
						$mwType = $set['type'];
						break;
					case 'list':
						$mwType = 'select';
						$mwOptions = $set['options'];
						break;
					case 'list-multi':
						$mwType = 'multiselect';
						$mwOptions = $set['options'];
						break;
					case 'list-multi-bool':
						$mwType = 'multiselect';
						$mwOptions = $set['options'];
						break;
					case 'matrix':
						$mwType = 'checkmatrix';
						$mwCols = $set['cols'];
						$mwRows = $set['rows'];
						break;
					case 'integer':
						$mwType = 'int';
						$mwMin = $set['minint'];
						$mwMax = $set['maxint'];
						break;
					case 'timezone':
						$mwType = 'select';
						$mwOptions = ManageWiki::getTimezoneList();
						break;
					case 'wikipage':
						$mwType = 'title';
						break;
				}

				$disabled = !( !$set['restricted'] || ( $set['restricted'] && $context->getUser()->isAllowed( 'managewiki-restricted' ) ) );

				$formDescriptor["set-$name"] = [
					'type' => $mwType,
					'label' => $set['name'],
					'disabled' => ( $ceMW ) ? $disabled : 1,
					'help' => ( $set['help'] ) ? $set['help'] : NULL,
					'section' => ( isset( $set['section'] ) ) ? $set['section'] : 'other'
				];

				if ( $mwType == 'matrix' ) {
					$formDescriptor["set-$name"]['default'] = ( !is_null( $wiki->getSettingsValue( $name ) ) ) ? ManageWiki::handleMatrix( $wiki->getSettingsValue ( $name ), 'php' ) : $set['overridedefault'];
				} elseif( $mwType == 'multiselect' ) {
					$formDescriptor["set-$name"]['default'] = ( !is_null( $wiki->getSettingsValue( $name ) ) ) ? array_keys( $wiki->getSettingsValue( $name ) ) : array_keys( $set['overridedefault'] );
				} else {
					$formDescriptor["set-$name"]['default'] = $wiki->getSettingsValue( $name ) ?? $set['overridedefault'];
				}

				if ( isset( $mwOptions ) ) {
					$formDescriptor["set-$name"]['options'] = $mwOptions;
				}

				if ( isset( $mwCols ) ) {
					$formDescriptor["set-$name"]['columns'] = $mwCols;
				}

				if ( isset( $mwRows ) ) {
					$formDescriptor["set-$name"]['rows'] = $mwRows;
				}

				if ( isset( $mwMin ) ) {
					$formDescriptor["set-$name"]['min'] = $mwMin;
				}

				if ( isset( $mwMax ) ) {
					$formDescriptor["set-$name"]['max'] = $mwMax;
				}

			}
		}

		return $formDescriptor;
	}

	private static function buildDescriptorNamespaces(
		string $dbName,
		bool $ceMW,
		string $special,
		Database $dbw
	) {
		$formDescriptor = [];

		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1
		];

		foreach ( $nsID as $name => $id ) {
			$nsData = $dbw->selectRow(
				'mw_namespaces',
				'*',
				[
					'ns_dbname' => $dbName,
					'ns_namespace_id' => $id
				]
			);

			$formDescriptor += [
				"namespace-$name" => [
					'type' => 'text',
					'label-message' => "namespaces-$name",
					'default' => ( $nsData ) ? $nsData->ns_namespace_name : NULL,
					'disabled' => ( ( $nsData && $nsData->ns_core ) || !$ceMW ),
					'required' => true,
					'section' => $name
				],
				"content-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-content',
					'default' => ( $nsData ) ? $nsData->ns_content : 0,
					'disabled' => !$ceMW,
					'section' => $name
				],
				"subpages-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-subpages',
					'default' => ( $nsData ) ? $nsData->ns_subpages : 0,
					'disabled' => !$ceMW,
					'section' => $name
				],
				"search-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-search',
					'default' => ( $nsData ) ? $nsData->ns_searchable : 0,
					'disabled' => !$ceMW,
					'section' => $name
				],
				"protection-$name" => [
					'type' => 'selectorother',
					'label-message' => 'namespaces-protection',
					'default' => ( $nsData ) ? $nsData->ns_protection : '',
					'options' => [
						'None' => '',
						'editinterface' => 'editinterface',
						'editsemiprotected' => 'editsemiprotected',
						'editprotected' => 'editprotected'
					],
					'disabled' => !$ceMW,
					'section' => $name
				],
				"aliases-$name" => [
					'type' => 'textarea',
					'label-message' => 'namespaces-aliases',
					'default' => ( $nsData ) ? implode( "\n", json_decode( $nsData->ns_aliases, true ) ) : NULL,
					'disabled' => !$ceMW,
					'section' => $name
				]
			];
		}

		if ( $ceMW && !$formDescriptor['namespace-namespace']['disabled'] ) {
			$namespaces = ManageWikiNamespaces::configurableNamespaces( $id = true, $readable = true, $main = true );
			$craftedNamespaces = [];
			$canDelete = false;

			foreach ( $namespaces as $id => $namespace ) {
				if ( $id !== $nsID['namespace'] ) {
					$craftedNamespaces[$namespace] = $id;
				} else {
					$canDelete = true; // existing namespace
				}
			}

			$formDescriptor += [
				'delete-checkbox' => [
					'type' => 'check',
					'label-message' => 'namespaces-delete-checkbox',
					'default' => 0,
					'disabled' => !$canDelete,
					'section' => 'delete'
				],
				'delete-migrate-to' => [
					'type' => 'select',
					'label-message' => 'namespaces-migrate-to',
					'options' => $craftedNamespaces,
					'default' => 0,
					'disabled' => !$canDelete,
					'section' => 'delete'
				]
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorPermissions(
		string $wiki,
		bool $ceMW,
		string $group
	) {
		$groupData = ManageWikiPermissions::groupAssignBuilder( $group, $wiki );

		$formDescriptor = [
			'assigned' => [
				'type' => 'info',
				'default' => wfMessage( 'managewiki-permissions-assigned' )->text(),
				'section' => 'assigned'
			],
			'unassigned' => [
				'type' => 'info',
				'default' => wfMessage( 'managewiki-permissions-unassigned' )->text(),
				'section' => 'unassigned'
			],
			'group' => [
				'type' => 'info',
				'default' => wfMessage( 'managewiki-permissions-group' )->text(),
				'section' => 'group'
			]
		];

		foreach ( $groupData['allPermissions'] as $perm ) {
			$assigned = in_array( $perm, $groupData['assignedPermissions'] );

			$formDescriptor["right-{$perm}"] = [
				'type' => 'check',
				'label' => $perm,
				'help' => User::getRightDescription( $perm ),
				'section' => ( $assigned ) ? 'assigned' : 'unassigned',
				'default' => $assigned,
				'disabled' => !$ceMW
			];
		}

		$rowsBuilt = [];

		foreach ( $groupData['allGroups'] as $group ) {
			$rowsBuilt[UserGroupMembership::getGroupName( $group )] = $group;
		}

		$formDescriptor['group-matrix'] = [
			'type' => 'checkmatrix',
			'columns' => [
				wfMessage( 'managewiki-permissions-addall' )->text() => 'wgAddGroups',
				wfMessage( 'managewiki-permissions-removeall' )->text() => 'wgRemoveGroups'
			],
			'rows' => $rowsBuilt,
			'section' => 'group',
			'default' => $groupData['groupMatrix'],
			'disabled' => !$ceMW
		];

		return $formDescriptor;
	}

	public static function submissionHandler(
		array $formData,
		HTMLForm $form,
		string $module,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki,
		Database $dbw,
		string $special = ''
	) {
		switch ( $module ) {
			case 'extensions':
				$mwReturn = self::submissionExtensions( $formData, $dbName, $context, $wiki );
				break;
			case 'settings':
				$mwReturn = self::submissionSettings( $formData, $context, $wiki );
				break;
			case 'namespaces':
				$mwReturn = self::submissionNamespaces( $formData, $dbName, $special, $dbw );
				break;
			case 'permissions':
				$mwReturn = self::submissionPermissions( $formData, $dbName, $special );
				break;
		}

		if ( $mwReturn['errors'] ) {
			return $mwReturn['errors'];
		}

		$mwLogParams = [
			'4::wiki' => $dbName
		];

		if ( $mwReturn['table'] == 'cw_wikis' ) {
			$dbw->update(
				'cw_wikis',
				[
					"wiki_{$module}" => $mwReturn['data']
				],
				[
					'wiki_dbname' => $dbName
				]
			);

			$mwLogParams['5::changes'] = $mwReturn['changes'];
		} elseif ( $mwReturn['table'] == 'mw_namespaces' ) {
			if ( isset( $formData['delete-checkbox'] ) && $formData['delete-checkbox'] ) {
				$mwReturn['log'] .= '-delete';

				foreach ( [ 'namespace', 'namespace-talk' ] as $name ) {
					$dbw->delete(
						'mw_namespaces',
						[
							'ns_dbname' => $mwReturn['data'][$name]['ns_dbname'],
							'ns_namespace_id' => $mwReturn['data'][$name]['ns_namespace_id']
						]
					);

					$jobParams = [
						'action' => 'delete',
						'nsID' => $mwReturn['data'][$name]['ns_namespace_id'],
						'nsName' => $mwReturn['data'][$name]['ns_namespace_name'],
						'nsNew' => $formData['delete-migrate-to']
					];

					$job = new NamespaceMigrationJob( SpecialPage::getTitleFor( 'ManageWikiNamespaces' ), $jobParams );

					JobQueueGroup::singleton()->push( $job );
				}
			} else {
				foreach ( [ 'namespace', 'namespacetalk' ] as $name ) {
					$jobParams = [
						'nsName' => $mwReturn['data'][$name]['ns_namespace_name'],
						'nsID' => $mwReturn['data'][$name]['ns_namespace_id']
					];

					if ( $mwReturn['changes'] ) {
						$dbw->update(
							'mw_namespaces',
							$mwReturn['data'][$name],
							[
								'ns_dbname' => $mwReturn['data'][$name]['ns_dbname'],
								'ns_namespace_id' => $mwReturn['data'][$name]['ns_namespace_id']
							]
						);

						$jobParams['action'] = 'rename';
					} else {
						$dbw->insert(
							'mw_namespaces',
							$mwReturn['data'][$name]
						);

						$jobParams['action'] = 'create';
					}

					$job = new NamespaceMigrationJob( SpecialPage::getTitleFor( 'ManageWikiNamespaces' ), $jobParams );
					JobQueueGroup::singleton()->push( $job );
				}
			}

			$mwLogParams['5::namespace'] = $mwReturn['data']['namespace']['ns_namespace_name'];
		} elseif ( $mwReturn['table'] == 'mw_permissions' ) {
			$state = $mwReturn['data']['state'];

			$rows = [
				'perm_dbname' => $dbName,
				'perm_group' => $special,
				'perm_permissions' => $mwReturn['data']['permissions'],
				'perm_addgroups' => json_encode( $mwReturn['data']['groups']['wgAddGroups'] ),
				'perm_removegroups' => json_encode( $mwReturn['data']['groups']['wgRemoveGroups'] )
			];

			if ( $state == 'update' ) {
				$dbw->update(
					'mw_permissions',
					$rows,
					[
						'perm_dbname' => $dbName,
						'perm_group' => $special
					]
				);
			} elseif ( $state == 'delete' ) {
				$dbw->delete(
					'mw_permissions',
					[
						'perm_dbname' => $dbName,
						'perm_group' => $special
					]
				);
			} elseif ( $state == 'create' ) {
				$dbw->insert(
					'mw_permissions',
					$rows
				);
			}

			$logNULL = wfMessage( 'rightsnone' )->inContentLanguage()->text();

			$mwLogParams = [
				'4::ar' => $mwReturn['changes']['added']['permissions'] ?? $logNULL,
				'5::rr' => $mwReturn['changes']['removed']['permissions'] ?? $logNULL,
				'6::aag' => $mwReturn['changes']['added']['ag'] ?? $logNULL,
				'7::rag' => $mwReturn['changes']['removed']['ag'] ?? $logNULL,
				'8::arg' => $mwReturn['changes']['added']['rg'] ?? $logNULL,
				'9::rrg' => $mwReturn['changes']['removed']['rg'] ?? $logNULL
			];
		} else {
			return [ 'Error processing.' ];
		}

		if ( $mwReturn['cdb' ] ) {
			ManageWikiCDB::changes( $mwReturn['cdb'] );
		}

		$mwLogEntry = new ManualLogEntry( 'managewiki', $mwReturn['log'] );
		$mwLogEntry->setPerformer( $context->getUser() );
		$mwLogEntry->setTarget( $form->getTitle() );
		$mwLogEntry->setComment( $formData['reason'] );
		$mwLogEntry->setParameters( $mwLogParams );
		$mwLogID = $mwLogEntry->insert();
		$mwLogEntry->publish( $mwLogID );
	}

	private static function submissionExtensions(
		array $formData,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgManageWikiExtensions;

		$extensionsArray = [];
		$errors = [];
		$changedExtensions = [];

		foreach ( $wgManageWikiExtensions as $name => $ext ) {
			$requiresMet = ManageWikiRequirements::process( $dbName, $ext['requires'], $context );
			$value = $formData["ext-$name"];
			$current = $wiki->hasExtension( $name );

			if ( $ext['conflicts'] && $value ) {
				if ( $formData["ext-{$ext['conflicts']}"] ) {
					$errors[] = "Conflict with {$ext['conflicts']}. The extension $name can not be enabled until this is disabled.";
					continue;
				}
			}

			if ( $value ) {
				if ( $requiresMet ) {
					$installed = ( !isset( $ext['install'] ) ) ? true : ManageWikiInstaller::process( $dbName, 'install', $ext['install'] );

					if ( $installed ) {
						$extensionsArray[] = $name;
					} else {
						$errors[] = "Extension $name was not installed.";
					}
				} else {
					$errors[] = "Extension $name was not added because of failed requirements.";
				}
			}

			if ( $formData["ext-$name"] != $current ) {
				$changedExtensions[] = "ext-$name";
			}

		}

		// HACK - Should convert either to JSON or singular param management
		$extensionsArray[] = 'zzzz';

		return [
			'cdb' => false,
			'changes' => implode( ', ', $changedExtensions ),
			'data' => implode( ',', $extensionsArray ),
			'errors' => $errors,
			'log' => 'settings',
			'table' => 'cw_wikis'
		];
	}

	private static function submissionSettings(
		array $formData,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgManageWikiSettings;

		$settingsArray = [];
		$changedSettings = [];
		$errors = [];

		foreach ( $wgManageWikiSettings as $name => $set ) {
			$current = $wiki->getSettingsValue( $name );
			$mwAllowed = ( $set['restricted'] && $context->getUser()->isAllowed( 'managewiki-restricted' ) || !$set['restricted'] );
			$type = $set['type'];
			$fromMet = ( $set['from'] != 'mediawiki' ) ? $wiki->hasExtension( $set['from'] ) : true;

			if ( $fromMet ) {
				$value = $formData["set-$name"];

				if ( $type == 'matrix' ) {
					$settingsArray[$name] = ( $mwAllowed ) ? ManageWiki::handleMatrix( $value, 'phparray' ) : ManageWiki::handleMatrix( $current, 'php' );

					if ( $settingsArray[$name] != ManageWiki::handleMatrix( $current, 'php' ) ) {
						$changedSettings[] = "setting-$name";
					}
				} elseif ( $type == 'check' ) {
					$settingsArray[$name] = ( $mwAllowed ) ? $value : $current;

					if ( is_null( $current ) && $settingsArray[$name] != $set['overridedefault'] || !is_null( $current ) && $settingsArray[$name] != $current  ) {
						$changedSettings[] = "setting-$name";
					}
				} elseif( $type == 'list-multi' ) {
					$settingsArray[$name] = $value;

					if ( is_null( $current ) && $settingsArray[$name] != $set['overridedefault'] || !is_null( $current ) && $settingsArray[$name] != $current ) {
						$changedSettings[] = "setting-$name";
					}
				} elseif( $type == 'list-multi-bool' ) {
					foreach ( $set['allopts'] as $opt ) {
						$settingsArray[$name][$opt] = in_array( $opt, $value );
					}

					if ( is_null( $current ) && $settingsArray[$name] != $set['overridedefault'] || !is_null( $current ) && $settingsArray[$name] != $current ) {
						$changedSettings[] = "setting-$name";
					}
				} elseif ( $type != 'text' || $value ) {
					$settingsArray[$name] = ( $mwAllowed ) ? $value : $current;

					if ( is_null( $current ) && $settingsArray[$name] != $set['overridedefault'] || !is_null( $current ) && ( $settingsArray[$name] != $current ) ) {
						$changedSettings[] = "setting-$name";
					}
				} else {
					if ( !$mwAllowed && !is_null( $current ) ) {
						$settingsArray[$name] = $current;
					}

					if ( $current != $value ) {
						$changedSettings[] = "setting-$name";
					}
				}

			}
		}

		return [
			'cdb' => false,
			'changes' => implode( ', ', $changedSettings ),
			'data' => json_encode( $settingsArray ),
			'errors' => false,
			'log' => 'settings',
			'table' => 'cw_wikis'
		];
	}

	private static function submissionNamespaces(
		array $formData,
		string $dbName,
		string $special,
		Database $dbw
	) {
		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1
		];

		$existingNamespace = $dbw->selectRow(
			'mw_namespaces',
			'ns_namespace_name',
			[
				'ns_dbname' => $dbName,
				'ns_namespace_id' => $nsID['namespace']
			]
		);

		foreach ( $nsID as $name => $id ) {
			$namespaceName = str_replace( ' ', '_', $formData["namespace-$name"] );

			$existingName = $dbw->selectRow(
				'mw_namespaces',
				'ns_namespace_id',
				[
					'ns_dbname' => $dbName,
					'ns_namespace_name' => $namespaceName
				]
			);

			if ( $existingName && ( $existingName->ns_namespace_id != $id ) ) {
				return false;
			}

			$build[$name] = [
				'ns_dbname' => $dbName,
				'ns_namespace_id' => $id,
				'ns_namespace_name' => $namespaceName,
				'ns_searchable' => (int)$formData["search-$name"],
				'ns_subpages' => (int)$formData["subpages-$name"],
				'ns_protection' => $formData["protection-$name"],
				'ns_content' => (int)$formData["content-$name"],
				'ns_aliases' => ( $formData["aliases-$name"] == '' ) ? '[]' : json_encode( explode( "\n", $formData["aliases-$name"] ) )
			];

		}

		return [
			'cdb' => 'namespaces',
			'changes' => $existingNamespace,
			'data' => $build,
			'errors' => false,
			'log' => 'namespaces',
			'table' => 'mw_namespaces'
		];
	}

	private static function submissionPermissions(
		array $formData,
		string $wiki,
		string $special
	) {
		$groupData = ManageWikiPermissions::groupAssignBuilder( $special, $wiki );

		$addedPerms = [];
		$removedPerms = [];

		foreach ( $groupData['allPermissions'] as $perm ) {
			if ( !$formData["right-$perm"] && is_int( array_search( $perm, $groupData['assignedPermissions'] ) ) ) {
				$removedPerms[] = $perm;
			}
		}

		$newPerms = array_diff( $groupData['assignedPermissions'], $removedPerms );

		$newMatrix = ManageWiki::handleMatrix( array_diff( $formData['group-matrix'], $groupData['groupMatrix'] ), 'phparray' );
		$oldMatrix = ManageWiki::handleMatrix( array_diff( $groupData['groupMatrix'], $formData['group-matrix'] ), 'phparray' );

		$matrixToShort = [
			'wgAddGroups' => 'ag',
			'wgRemoveGroups' => 'rg'
		];

		$logBuild = [
			'added' => [
				'permissions' => ( $addedPerms ) ? implode( ', ', $addedPerms ) : NULL
			],
			'removed' => [
				'permissions' => ( $removedPerms ) ? implode( ', ', $removedPerms ) : NULL
			]
		];

		foreach ( $newMatrix as $type => $array ) {
			$newArray = [];

			foreach ( $array as $name ) {
				$newArray[] = $name;
			}

			$logBuild['added'][$matrixToShort[$type]] = implode( ', ', $newArray );
		}

		foreach ( $oldMatrix as $type => $array ) {
			$newArray = [];

			foreach ( $array as $name ) {
				$newArray[] = $name;
			}

			$logBuild['removed'][$matrixToShort[$type]] = implode( ', ', $newArray );
		}

		$setPerms

		$dataArray = [
			'permissions' => json_encode( $newPerms ),
			'groups' => ManageWiki::handleMatrix( $formData['group-matrix'], 'phparray' )
		];

		if ( count( $newPerms ) == 0 ) {
			$dataArray['state'] = 'delete';
		} elseif ( count( $groupData['assignedPermissions'] ) == 0 ) {
			$dataArray['state'] = 'create';
		} else {
			$dataArray['state'] = 'update';
		}

		return [
			'cdb' => 'permissions',
			'changes' => $logBuild,
			'data' => $dataArray,
			'errors' => false,
			'log' => 'rights',
			'table' => 'mw_permissions'
		];
	}
}
