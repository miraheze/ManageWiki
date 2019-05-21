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
			case 'core':
				$formDescriptor = self::buildDescriptorCore( $dbName, $ceMW, $context, $wiki );
				break;
			case 'extensions':
				$formDescriptor = self::buildDescriptorExtensions( $dbName, $ceMW, $context, $wiki );
				break;
			case 'settings':
				$formDescriptor = self::buildDescriptorSettings( $dbName, $ceMW, $context, $wiki );
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

	private static function buildDescriptorCore(
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgCreateWikiCategories, $wgCreateWikiUseCategories, $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis,
			$wgCreateWikiUseInactiveWikis, $wgCreateWikiGlobalWiki, $wgDBname;

		$languages = Language::fetchLanguageNames( NULL, 'wmfile' );
		ksort( $languages );
		$options = [];
		foreach ( $languages as $code => $name ) {
			$options["$code - $name"] = $code;
		}

		$formDescriptor = [
			'dbname' => [
				'label-message' => 'managewiki-label-dbname',
				'type' => 'text',
				'default' => $dbName,
				'disabled' => true,
				'section' => 'main'
			],
			'sitename' => [
				'label-message' => 'managewiki-label-sitename',
				'type' => 'text',
				'default' => $wiki->getSitename(),
				'disabled' => !$ceMW,
				'required' => true,
				'section' => 'main'
			],
			'language' => [
				'label-message' => 'managewiki-label-language',
				'type' => 'select',
				'default' => $wiki->getLanguage(),
				'options' => $options,
				'disabled' => !$ceMW,
				'required' => true,
				'section' => 'main'
			]
		];

		$addedModules = [
			'private' => [
				'if' => $wgCreateWikiUsePrivateWikis,
				'type' => 'check',
				'default' => $wiki->isPrivate(),
				'access' => !$ceMW
			],
			'closed' => [
				'if' => $wgCreateWikiUseClosedWikis,
				'type' => 'check',
				'default' => $wiki->isClosed(),
				'access' => !$ceMW
			],
			'inactive' => [
				'if' => $wgCreateWikiUseInactiveWikis,
				'type' => 'check',
				'default' => $wiki->isInactive(),
				'access' => !$ceMW
			],
			'inactive-exempt' => [
				'if' => $wgCreateWikiUseInactiveWikis,
				'type' => 'check',
				'default' => $wiki->isInactiveExempt(),
				'access' => !$context->getUser()->isAllowed( 'managewiki-restricted' )
			]
		];

		foreach ( $addedModules as $name => $data ) {
			if ( $data['if'] ) {
				$formDescriptor[$name] = [
					'type' => $data['type'],
					'label-message' => "managewiki-label-$name",
					'default' => $data['default'],
					'disabled' => $data['access'],
					'section' => 'main'
				];
			}
		}

		if ( $wgCreateWikiUseCategories && $wgCreateWikiCategories ) {
			$formDescriptor['category'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $wgCreateWikiCategories,
				'default' => $wiki->getCategory(),
				'disabled' => !$ceMW,
				'section' => 'main'
			];
		}

		if ( $ceMW && ( $wgDBname == $wgCreateWikiGlobalWiki ) && !$wiki->isInactiveExempt() ) {
			$mwAction = ( $wiki->isDeleted() ) ? 'undelete' : 'delete';

			$formDescriptor[$mwAction] = [
				'type' => 'check',
				'label-message' => "managewiki-label-{$mwAction}wiki",
				'default' => false,
				'section' => 'handling'
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
			$conflictLabel = wfMessage( 'managewiki-conflicts' )->text();
			$requiresLabel = wfMessage( 'managewiki-requires' )->text();


			if ( $ext['conflicts'] ) {
				$help[] = "{$conflictLabel} {$ext['conflicts']}";
			}

			if ( $ext['requires'] ) {
				$requires = [];
				foreach ( $ext['requires'] as $require => $data ) {
					$requires[] = ucfirst( $require ) . " - " . implode( ', ', $data );
				}

				$help[] = "{$requiresLabel}: " . implode( ' & ', $requires );
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
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki
	) {
		global $wgManageWikiSettings;

		$formDescriptor = [];

		foreach ( $wgManageWikiSettings as $name => $set ) {
			$add = ( $set['from'] == 'mediawiki' ) ? true : $wiki->hasExtension( $set['from'] );
			$sType = $set['type'];

			if ( $add ) {
				switch ( $sType ) {
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
					case 'usergroups':
						$mwType = 'multiselect';
						$groups = [];
						foreach( ManageWikiPermissions::availableGroups( $dbName ) as $group ) {
							$groups[UserGroupMembership::getGroupName( $group )] = $group;
						}
						$mwOptions = isset( $set['options'] ) ? array_merge( $groups, $set['options'] ) : $groups;
						break;
				}

				$disabled = !( !$set['restricted'] || ( $set['restricted'] && $context->getUser()->isAllowed( 'managewiki-restricted' ) ) );

				$msgName = wfMessage( "managewiki-setting-{$name}-name" );
				$msgHelp = wfMessage( "managewiki-setting-{$name}-help" );

				$formDescriptor["set-$name"] = [
					'type' => $mwType,
					'label' => ( $msgName->exists() ) ? $msgName->text() : $set['name'],
					'disabled' => ( $ceMW ) ? $disabled : 1,
					'help' => ( $msgHelp->exists() ) ? $msgHelp->text() : $set['help'],
					'section' => ( isset( $set['section'] ) ) ? $set['section'] : 'other'
				];

				if ( $mwType == 'matrix' ) {
					$formDescriptor["set-$name"]['default'] = ( !is_null( $wiki->getSettingsValue( $name ) ) ) ? ManageWiki::handleMatrix( $wiki->getSettingsValue ( $name ), 'php' ) : $set['overridedefault'];
				} elseif( $sType == 'list-multi-bool' ) {
					$formDescriptor["set-$name"]['default'] = ( !is_null( $wiki->getSettingsValue( $name ) ) ) ? array_keys( $wiki->getSettingsValue( $name ), true ) : array_keys( $set['overridedefault'], true );
				} elseif( $sType == 'list-multi' || $sType == 'usergroups' ) {
					$formDescriptor["set-$name"]['default'] = ( !is_null( $wiki->getSettingsValue( $name ) ) ) ? $wiki->getSettingsValue( $name ) : $set['overridedefault'];
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
		global $wgManageWikiNamespacesAdditional, $wgManageWikiNamespacesExtraContentModels;

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
				"contentmodel-$name" => [
					'type' => 'select',
					'label-message' => 'namespaces-contentmodel',
					'default' => ( $nsData ) ? $nsData->ns_content_model : 'wikitext',
					'options' => array_merge( [
						'CSS' => 'css',
						'JavaScript' => 'javascript',
						'JSON' => 'json',
						'Wikitext' => 'wikitext'
						], (array)$wgManageWikiNamespacesExtraContentModels ),
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
				]
			];

			$additionalArray = ( $nsData ) ? json_decode( $nsData->ns_additional, true ) : [];

			foreach( (array)$wgManageWikiNamespacesAdditional as $key => $a ) {
				if ( $a['main'] && $name == 'namespace' || $a['talk'] && $name == 'namespacetalk' ) {
					$formDescriptor["$key-$name"] = [
						'type' => 'check',
						'label' => $a['name'],
						'default' => ( isset( $additionalArray[$key] ) ) ? $additionalArray[$key] : $a['overridedefault'],
						'disabled' => !$ceMW,
						'section' => $name
					];
				}
			}

			$formDescriptor["aliases-$name"] = [
				'type' => 'textarea',
				'label-message' => 'namespaces-aliases',
				'default' => ( $nsData ) ? implode( "\n", json_decode( $nsData->ns_aliases, true ) ) : NULL,
				'disabled' => !$ceMW,
				'section' => $name
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
			],
			'autopromote' => [
				'type' => 'info',
				'default' => wfMessage( 'managewiki-permissions-autopromote' )->text(),
				'section' => 'autopromote'
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
				wfMessage( 'managewiki-permissions-removeall' )->text() => 'wgRemoveGroups',
				wfMessage( 'managewiki-permissions-addself' )->text() => 'wgGroupsAddToSelf',
				wfMessage( 'managewiki-permissions-removeself' )->text() => 'wgGroupsRemoveFromSelf'
			],
			'rows' => $rowsBuilt,
			'section' => 'group',
			'default' => $groupData['groupMatrix'],
			'disabled' => !$ceMW
		];

		// This is not a good method but it is a method.
		$aP = $groupData['autopromote'];
		$aPArray = [];
		foreach ( (array)$aP as $element ) {
			if ( is_array( $element ) ) {
				$aPArray[$element[0]] = $element[1];
			}
		}

		$formDescriptor += [
			'enable' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-enable',
				'default' => !is_null( $aP ),
				'section' => 'autopromote'
			],
			'conds' => [
				'type' => 'select',
				'label-message' => 'managewiki-permissions-autopromote-conds',
				'options' => [
					wfMessage( 'managewiki-permissions-autopromote-conds-and' )->text() => '&',
					wfMessage( 'managewiki-permissions-autopromote-conds-or' )->text() => '|',
					wfMessage( 'managewiki-permissions-autopromote-conds-not' )->text() => '!'
				],
				'default' => ( is_null( $aP ) ) ? '&' : $aP[0],
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'section' => 'autopromote'
			],
			'once' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-once',
				'default' => is_int( array_search( 'once', (array)$aP ) ),
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'section' => 'autopromote'
			],
			'editcount' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-editcount',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'min' => 0,
				'default' => $aPArray[APCOND_EDITCOUNT] ?? 0,
				'section' => 'autopromote'
			],
			'age' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-age',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'min' => 0,
				'default' => isset( $aPArray[APCOND_AGE] ) ? $aPArray[APCOND_AGE] / 86400 : 0,
				'section' => 'autopromote'
			],
			'emailconfirmed' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-email',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_EMAILCONFIRMED, (array)$aP ) ),
				'section' => 'autopromote'
			],
			'blocked' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-blocked',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_BLOCKED, (array)$aP ) ),
				'section' => 'autopromote'
			],
			'bot' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-bot',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_ISBOT, (array)$aP ) ),
				'section' => 'autopromote'
			],
			'groups' => [
				'type' => 'multiselect',
				'label-message' => 'managewiki-permissions-autopromote-groups',
				'options' => $rowsBuilt,
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => isset( $aPArray[APCOND_INGROUPS] ) ? $aPArray[AP_INGROUPS] : [],
				'section' => 'autopromote'
			]
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
			case 'core':
				$mwReturn = self::submissionCore( $formData, $dbName, $context, $wiki, $dbw );
				break;
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

		if ( $mwReturn === 'deleted' || $mwReturn === 'undeleted' ) {
			$delete = ( $mwReturn === 'deleted' );

			$rows = [
				'wiki_deleted' => (int)$delete,
				'wiki_deleted_timestamp' => ( $delete ) ? $dbw->timestamp() : NULL
			];

			$dbw->update(
				'cw_wikis',
				$rows,
				[
					'wiki_dbname' => $dbName
				]
			);

			$logAction = ( $delete ) ? 'delete' : 'undelete';

			$deleteLog = new ManualLogEntry( 'managewiki', $logAction );
			$deleteLog->setPerformer( $context->getUser() );
			$deleteLog->setTarget( $form->getTitle() );
			$deleteLog->setComment( $formData['reason'] );
			$deleteLog->setParameters( [ '4::wiki' => $dbName ] );
			$logID = $deleteLog->insert();
			$deleteLog->publish( $logID );

			return "Wiki has been {$mwReturn}";
		}

		if ( $mwReturn['errors'] ) {
			return $mwReturn['errors'];
		}

		$mwLogParams = [
			'4::wiki' => $dbName
		];

		if ( $mwReturn['table'] == 'cw_wikis' ) {
			if ( is_array( $mwReturn['data'] ) ) {
				$rows = $mwReturn['data'];
			} else {
				$rows = [
					"wiki_{$module}" => $mwReturn['data']
				];
			}

			$dbw->update(
				'cw_wikis',
				$rows,
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
				'perm_removegroups' => json_encode( $mwReturn['data']['groups']['wgRemoveGroups'] ),
				'perm_addgroupstoself' => json_encode( $mwReturn['data']['groups']['wgGroupsAddToSelf'] ),
				'perm_removegroupsfromself' => json_encode( $mwReturn['data']['groups']['wgGroupsRemoveFromSelf'] ),
				'perm_autopromote' => $mwReturn['data']['autopromote']
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
			$logAP = $mwReturn['changes']['modified']['autopromote'] ? 'htmlform-yes' : 'htmlform-no';

			$mwLogParams = [
				'4::ar' => $mwReturn['changes']['added']['permissions'] ?? $logNULL,
				'5::rr' => $mwReturn['changes']['removed']['permissions'] ?? $logNULL,
				'6::aag' => $mwReturn['changes']['added']['ag'] ?? $logNULL,
				'7::rag' => $mwReturn['changes']['removed']['ag'] ?? $logNULL,
				'8::arg' => $mwReturn['changes']['added']['rg'] ?? $logNULL,
				'9::rrg' => $mwReturn['changes']['removed']['rg'] ?? $logNULL,
				'10::aags' => $mwReturn['changes']['added']['ags'] ?? $logNULL,
				'11::rags' => $mwReturn['changes']['removed']['ags'] ?? $logNULL,
				'12::args' => $mwReturn['changes']['added']['rgs'] ?? $logNULL,
				'13::rrgs' => $mwReturn['changes']['removed']['rgs'] ?? $logNULL,
				'14::ap' => strtolower( wfMessage( $logAP )->inContentLanguage()->text() )
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

	private static function submissionCore(
		array $formData,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki,
		Database $dbw
	) {
		global $wgCreateWikiUsePrivateWikis, $wgCreateWikiUseClosedWikis, $wgCreateWikiUseInactiveWikis, $wgCreateWikiUseCategories, $wgCreateWikiCategories;

		if ( isset( $formData['delete'] ) && $formData['delete'] ) {
			return 'deleted';
		} elseif ( isset( $formData['undelete'] ) && $formData['undelete'] ) {
			return 'undeleted';
		}

		$changedArray = [];

		if ( $wgCreateWikiUsePrivateWikis ) {
			$private = ( $formData['private'] == true ) ? 1 : 0;
			if ( $wiki->isPrivate() != $formData['private'] ) {
				if ( $formData['private'] ) {
					Hooks::run( 'CreateWikiStatePrivate', [ $dbName ] );
					$changedArray[] = 'private';
				} else {
					Hooks::run( 'CreateWikiStatePublic', [ $dbName ] );
					$changedArray[] = 'public';
				}
			}
		} else {
			$private = 0;
		}

		if ( $wgCreateWikiUseClosedWikis ) {
			$closed = $wiki->isClosed();
			$closedDate = $wiki->getClosureDate();
			$newClosed = $formData['closed'];

			if ( $newClosed && ( $closed != $newClosed ) ) {
				$closed = 1;
				$closedDate = $dbw->timestamp();

				Hooks::run( 'CreateWikiStateClosed', [ $dbName ] );

				$changedArray[] = 'closed';
			} elseif ( !$newClosed && ( $closed != $newClosed ) ) {
				$closed = 0;
				$closedDate = NULL;

				Hooks::run( 'CreateWikiStateOpen', [ $dbName ] );

				$changedArray[] = 'opened';
			} else {
				$closed = $closed;
				$closedDate = $wiki->closureDate();
			}

		} else {
			$closed = 0;
			$closedDate = NULL;
		}

		if ( $wgCreateWikiUseInactiveWikis ) {
			$newInactive = $formData['inactive'];
			$inactive = $wiki->isInactive();
			$inactiveDate = $wiki->getInactiveDate();
			$newInactiveExempt = $formData['inactive-exempt'];

			if ( $newInactive != $inactive ) {
				$inactive = $newInactive;
				$inactiveDate = $dbw->timestamp();

				$changedArray[] = ( $newInactive ) ? 'inactive' : 'active';
			}

			if ( $newInactiveExempt && $context->getUser()->isAllowed( 'managewiki-restricted' ) ) {
				$inactiveExempt = 1;

				$changedArray[] = 'inactive-exempt';
			} else {
				$inactiveExempt = $wiki->isInactiveExempt();
			}
		} else {
			$inactive = 0;
			$inactiveDate = NULL;
			$inactiveExempt = 0;
		}

		$category = ( $wgCreateWikiUseCategories ) ? $formData['category'] : 'uncategorised';

		if ( $category != $wiki->getCategory() ) {
			$changedArray[] = 'category';
		}

		$data = [
			'wiki_sitename' => $formData['sitename'],
			'wiki_language' => $formData['language'],
			'wiki_closed' => $closed,
			'wiki_closed_timestamp' => $closedDate,
			'wiki_inactive' => $inactive,
			'wiki_inactive_timestamp' => $inactiveDate,
			'wiki_inactive_exempt' => $inactiveExempt,
			'wiki_private' => $private,
			'wiki_category' => $category
		];

		return [
			'cdb' => false,
			'changes' => implode( ', ', $changedArray ),
			'data' => $data,
			'errors' => false,
			'log' => 'settings',
			'table' => 'cw_wikis'
		];
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
			$requiresMet = ManageWikiRequirements::process( $dbName, $ext['requires'], $context, $formData );
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
					$installed = ( !isset( $ext['install'] ) || $current ) ? true : ManageWikiInstaller::process( $dbName, 'install', $ext['install'] );

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
				} elseif( $type == 'list-multi' ||  $type == 'usergroups' ) {
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
		global $wgManageWikiNamespacesAdditional;

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

			$additionalBuilt = [];

			foreach ( (array)$wgManageWikiNamespacesAdditional as $key => $a ) {
				$additionalBuilt[$key] = $formData["$key-$name"];
			}

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
				'ns_content_model' => $formData["contentmodel-$name"],
				'ns_aliases' => ( $formData["aliases-$name"] == '' ) ? '[]' : json_encode( explode( "\n", $formData["aliases-$name"] ) ),
				'ns_additional' => json_encode( $additionalBuilt )
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
		$newPerms = [];

		foreach ( $groupData['allPermissions'] as $perm ) {
			if ( $formData["right-$perm"] ) {
				if ( !is_int( array_search( $perm, $groupData['assignedPermissions'] ) ) ) {
					$addedPerms[] = $perm;
				}

				$newPerms[] = $perm;
			} else {
				if ( is_int( array_search( $perm, $groupData['assignedPermissions'] ) ) ) {
					$removedPerms[] = $perm;
				}
			}
		}

		$newMatrix = ManageWiki::handleMatrix( array_diff( $formData['group-matrix'], $groupData['groupMatrix'] ), 'phparray' );
		$oldMatrix = ManageWiki::handleMatrix( array_diff( $groupData['groupMatrix'], $formData['group-matrix'] ), 'phparray' );

		$matrixToShort = [
			'wgAddGroups' => 'ag',
			'wgRemoveGroups' => 'rg',
			'wgGroupsAddToSelf' => 'ags',
			'wgGroupRemoveFromSelf' => 'rgs'
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

		$matrixOut = ManageWiki::handleMatrix( $formData['group-matrix'], 'phparray' );
		$aE = $formData['enable'];

		$dataArray = [
			'permissions' => json_encode( $newPerms ),
			'groups' => [
				'wgAddGroups' => $matrixOut['wgAddGroups'] ?? [],
				'wgRemoveGroups' => $matrixOut['wgRemoveGroups'] ?? [],
				'wgGroupsAddToSelf' => $matrixOut['wgGroupsAddToSelf'] ?? [],
				'wgGroupsRemoveFromSelf' => $matrixOut['wgGroupsRemoveFromSelf'] ?? []
			]
		];

		$aPBuild = $aE ? [
				$formData['conds']
		] : [];

		if ( count( $aPBuild ) != 0 ) {
			if ( $formData['once'] ) {
				$aPBuild[] = 'once';
			}

			if ( $formData['editcount'] ) {
				$aPBuild[] = [ APCOND_EDITCOUNT, (int)$formData['editcount'] ];
			}

			if ( $formData['age'] ) {
				$aPBuild[] = [ APCOND_AGE, (int)$formData['age'] * 86400 ];
			}

			if ( $formData['emailconfirmed'] ) {
				$aPBuild[] = APCOND_EMAILCONFIRMED;
			}

			if ( $formData['blocked'] ) {
				$aPBuild[] = APCOND_BLOCKED;
			}

			if ( $formData['bot'] ) {
				$aPBuild[] = APCOND_ISBOT;
			}

			if ( $formData['groups'] ) {
				$aPBuild[] = [ APCOND_INGROUPS, $formData['groups'] ];
			}
		}

		$dataArray['autopromote'] = ( count( $aPBuild ) <= 1 ) ? NULL : json_encode( $aPBuild );

		$logBuild['modified']['autopromote'] = ( $groupData['autopromote'] != $aE );

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
