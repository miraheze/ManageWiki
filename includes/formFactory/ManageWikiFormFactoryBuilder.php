<?php

use MediaWiki\MediaWikiServices;

class ManageWikiFormFactoryBuilder {
	public static function buildDescriptor(
		string $module,
		string $dbName,
		bool $ceMW,
		IContextSource $context,
		RemoteWiki $wiki,
		string $special,
		Config $config
	) {
		switch ( $module ) {
			case 'core':
				$formDescriptor = self::buildDescriptorCore( $dbName, $ceMW, $context, $wiki, $config );
				break;
			case 'extensions':
				$formDescriptor = self::buildDescriptorExtensions( $dbName, $ceMW, $wiki, $config );
				break;
			case 'settings':
				$formDescriptor = self::buildDescriptorSettings( $dbName, $ceMW, $context, $wiki, $config );
				break;
			case 'namespaces':
				$formDescriptor = self::buildDescriptorNamespaces( $dbName, $ceMW, $special, $wiki, $config );
				break;
			case 'permissions':
				$formDescriptor = self::buildDescriptorPermissions( $dbName, $ceMW, $special, $config );
				break;
			default:
				throw new MWException( "{$module} not recognised" );
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
		RemoteWiki $wiki,
		Config $config
	) {
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
				'type' => 'language',
				'default' => $wiki->getLanguage(),
				'disabled' => !$ceMW,
				'required' => true,
				'section' => 'main'
			]
		];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$addedModules = [
			'private' => [
				'if' => $config->get( 'CreateWikiUsePrivateWikis' ),
				'type' => 'check',
				'default' => (bool)$wiki->isPrivate(),
				'access' => !$ceMW
			],
			'closed' => [
				'if' => $config->get( 'CreateWikiUseClosedWikis' ),
				'type' => 'check',
				'default' => (bool)$wiki->isClosed(),
				'access' => !$ceMW
			],
			'inactive' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ),
				'type' => 'check',
				'default' => (bool)$wiki->isInactive(),
				'access' => !$ceMW
			],
			'inactive-exempt' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ),
				'type' => 'check',
				'default' => (bool)$wiki->isInactiveExempt(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' )
			],
			'server' => [
				'if' => $config->get( 'CreateWikiUseCustomDomains' ),
				'type' => 'text',
				'default' => $wiki->getServerName(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' )
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

		if ( $config->get( 'CreateWikiUseCategories' ) ) {
			$formDescriptor['category'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $config->get( 'CreateWikiCategories' ),
				'default' => $wiki->getCategory(),
				'disabled' => !$ceMW,
				'section' => 'main'
			];
		}

		if ( $config->get( 'CreateWikiDatabaseClusters' ) ) {
			$clusterList = array_merge( (array)$config->get( 'CreateWikiDatabaseClusters' ), (array)$config->get( 'CreateWikiDatabaseClustersInactive' ) );
			$formDescriptor['dbcluster'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-dbcluster',
				'options' => array_combine( $clusterList, $clusterList ),
				'default' => $wiki->getDBCluster(),
				'disabled' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
				'section' => 'main'
			];
		}

		if ( $ceMW && ( $config->get( 'DBname' ) == $config->get( 'CreateWikiGlobalWiki' ) ) ) {
			$mwActions = [
				( $wiki->isDeleted() ) ? 'undelete' : 'delete',
				( $wiki->isLocked() ) ? 'unlock' : 'lock'
			];

			foreach ( $mwActions as $mwAction ) {
				$formDescriptor[$mwAction] = [
					'type' => 'check',
					'label-message' => "managewiki-label-{$mwAction}wiki",
					'default' => false,
					'section' => 'handling'
				];
			}
		}

		return $formDescriptor;
	}

	private static function buildDescriptorExtensions(
		string $dbName,
		bool $ceMW,
		RemoteWiki $wiki,
		Config $config
	) {
		$mwExt = new ManageWikiExtensions( $dbName );
		$extList = $mwExt->list();

		$formDescriptor = [];

		foreach ( $config->get( 'ManageWikiExtensions' ) as $name => $ext ) {
			$mwRequirements = $ext['requires'] ? ManageWikiRequirements::process( $ext['requires'], $extList, false, $wiki ) : true;
			
			$help = [];
			$conflictLabel = wfMessage( 'managewiki-conflicts' )->text();
			$requiresLabel = wfMessage( 'managewiki-requires' )->text();


			if ( $ext['conflicts'] ) {
				$help[] = "{$conflictLabel} {$ext['conflicts']}<br>";
			}

			if ( $ext['requires'] ) {
				$requires = [];
				foreach ( $ext['requires'] as $require => $data ) {
					if ( is_array( $data ) ) {
						foreach ( $data as $index => $element ) {
							if ( is_array( $element ) ) {
								$data[$index] = '( ' . implode( ' OR ', $element ) . ' )';
							}
						}
					}

					$requires[] = ucfirst( $require ) . " - " . ( is_array( $data ) ? implode( ', ', $data ) : $data );
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
				'default' => in_array( $name, $extList ),
				'disabled' => ( $ceMW ) ? !$mwRequirements : true,
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
		RemoteWiki $wiki,
		Config $config
	) {
		$mwExt = new ManageWikiExtensions( $dbName );
		$extList = $mwExt->list();
		$mwSettings = new ManageWikiSettings( $dbName );
		$setList = $mwSettings->list();
		$mwPermissions = new ManageWikiPermissions( $dbName );
		$groupList = array_keys( $mwPermissions->list() );

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$formDescriptor = [];

		foreach ( $config->get( 'ManageWikiSettings' ) as $name => $set ) {
			$mwRequirements = $set['requires'] ? ManageWikiRequirements::process( $set['requires'], $extList, false, $wiki ) : true;

			$add = ( isset( $set['requires']['visibility'] ) ? $mwRequirements : true ) && ( ( $set['from'] == 'mediawiki' ) || ( in_array( $set['from'], $extList ) ) );

			$disabled = ( $ceMW ) ? !$mwRequirements : true;
			
			$msgName = wfMessage( "managewiki-setting-{$name}-name" );
			$msgHelp = wfMessage( "managewiki-setting-{$name}-help" );

			if ( $add ) {
				switch ( $set['type'] ) {
					case 'databases':
						$configs = [
							'class' => HTMLAutoCompleteSelectFieldWithOOUI::class,
							'default' => $setList[$name] ?? $set['overridedefault'],
							'require-match' => true
						];
						foreach ( $config->get( 'LocalDatabases' ) as $db ) {
							$configs['autocomplete'][$db] = $db;
						}
						break;
					case 'float':
						$configs = [
							'type' => 'float',
							'min' => $set['minfloat'],
							'max' => $set['maxfloat'],
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'integer':
						$configs = [
							'type' => 'int',
							'min' => $set['minint'],
							'max' => $set['maxint'],
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'language':
						$configs = [
							'type' => 'language',
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'list':
						$configs = [
							'type' => 'select',
							'options' => $set['options'],
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'list-multi':
						$configs = [
							'type' => 'multiselect',
							'options' => $set['options'],
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'list-multi-bool':
						$configs = [
							'type' => 'multiselect',
							'options' => $set['options'],
							'default' => ( isset( $setList[$name] ) && !is_null( $setList[$name] ) ) ? array_keys( $setList[$name], true ) : array_keys( $set['overridedefault'], true )
						];
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'matrix':
						$configs = [
							'type' => 'checkmatrix',
							'rows' => $set['rows'],
							'columns' => $set['cols'],
							'default' => ( isset( $setList[$name] ) && !is_null( $setList[$name] ) ) ? ManageWiki::handleMatrix( $setList[$name], 'php' ) : $set['overridedefault']
						];
						break;
					case 'namespace':
						$configs = [
							'type' => 'namespaceselect',
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'namespaces':
						$configs = [
							'type' => 'namespacesmultiselect',
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;	
					case 'preferences':
						$preferences = [];
						$excludedPrefs = [];
						$allPreferences = MediaWikiServices::getInstance()->getUserOptionsLookup()->getDefaultOptions();
						
						
						// Don't show preferences hidden by configuratiom
						if( !$config->get( 'AllowUserCssPrefs' ) ) {
							$excludedPrefs[] = 'underline';
							$excludedPrefs[] = 'editfont';
						}
							
						if( $config->get( 'DisableLangConversion' ) ) {
							$excludedPrefs[] = 'variant';
						} else {
							foreach( preg_grep( '/variant-[A-Za-z0-9]/', array_keys( $allPreferences ) ) as $pref => $value ) {
								$excludedPrefs[] = array_keys( $allPreferences )[$pref];
							}
						}
							
						if( $config->get( 'ForceHTTPS' ) || !$config->get( 'SecureLogin' ) ) {
							$excludedPrefs[] = 'prefershttps';
						}
							
						if( !$config->get( 'RCShowWatchingUsers' ) ) {
							$excludedPrefs[] = 'shownumberswatching';
						}
							
						if( !$config->get( 'RCWatchCategoryMembership' ) ) {
							$excludedPrefs[] = 'hidecategorization';
							$excludedPrefs[] = 'watchlisthidecategorization';
						}
							
						if( !$config->get( 'SearchMatchRedirectPreference' ) ) {
							$excludedPrefs[] = 'search-match-redirect';
						}
						
						if ( !$config->get( 'EnableEmail' ) ) {
							if ( !$config->get( 'AllowRequiringEmailForResets' ) ) {
								$excludedPrefs[] = 'requireemail';
							}
							if ( !$config->get( 'EnableUserEmail' ) ) {
								$excludedPrefs[] = 'disablemail';
								$excludedPrefs[] = 'email-allow-new-users';
								$excludedPrefs[] = 'ccmeonemails';
								if ( !$config->get( 'EnableUserEmailBlacklist' ) ) {
									$excludedPrefs[] = 'EnableUserEmailBlacklist';
								}
							}
							if ( !$config->get( 'EnotifWatchlist' ) ) {
								$excludedPrefs[] = 'enotifwatchlistpages';
							}
							if ( !$config->get( 'EnotifUserTalk' ) ) {
								$excludedPrefs[] = 'enotifusertalkpages';
							}
							if (!$config->get( 'EnotifUserTalk' ) && !$config->get( 'EnotifWatchlist' ) ) {
								if ( !$config->get( 'EnotifMinorEdits' ) ) {
									$excludedPrefs[] = 'enotifminoredits';
								}
								if ( !$config->get( 'EnotifRevealEditorAddress' ) ) {
									$excludedPrefs[] = 'enotifrevealaddr';
								}
							}
						}
						
						// Never show searchNs* prefs
						foreach( preg_grep( '/searchNs[0-9]/', array_keys( $allPreferences ) ) as $pref => $value ) {
							$excludedPrefs[] = array_keys( $allPreferences )[$pref];
						}
						
						// Blacklist echo-subscriptions preferences
						foreach( preg_grep( '/echo-subscriptions-(?s).*/', array_keys( $allPreferences ) ) as $pref => $value ) {
							$excludedPrefs[] = array_keys( $allPreferences )[$pref];
						}
						
						foreach( $allPreferences as $preference => $val ) {
							if ( !in_array( $preference, $excludedPrefs ) ) {
								$preferences[$preference] = $preference;
							}
						}
						
						$configs = [
							'type' => 'multiselect',
							'options' => $preferences,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'skin':
  						$enabledSkins = MediaWikiServices::getInstance()->getSkinFactory()->getSkinNames();

						unset( $enabledSkins['fallback'] );
						unset( $enabledSkins['apioutput'] );

						if ( !isset( $set['whitelistSkipSkins'] ) ) {
							foreach ( $config->get( 'SkipSkins' ) as $skip ) {
								unset( $enabledSkins[$skip] );
							}
						}

						$enabledSkins = array_flip( $enabledSkins );
						ksort( $enabledSkins );
						
						$configs = [
							'type' => 'select',
							'options' => isset( $set['options'] ) ? array_merge( $enabledSkins, $set['options'] ) : $enabledSkins,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'skins':
  						$enabledSkins = MediaWikiServices::getInstance()->getSkinFactory()->getSkinNames();

						unset( $enabledSkins['fallback'] );
						unset( $enabledSkins['apioutput'] );

						if ( !isset( $set['whitelistSkipSkins'] ) ) {
							foreach ( $config->get( 'SkipSkins' ) as $skip ) {
								unset( $enabledSkins[$skip] );
							}
						}

						$enabledSkins = array_flip( $enabledSkins );
						ksort( $enabledSkins );
						
						$configs = [
							'type' => 'multiselect',
							'options' => isset( $set['options'] ) ? array_merge( $enabledSkins, $set['options'] ) : $enabledSkins,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'timezone':
						$configs = [
							'type' => 'select',
							'options' => ManageWiki::getTimezoneList(),
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'user':
						$configs = [
							'type' => 'user',
							'exists' => true,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'users':
						$configs = [
							'type' => 'usersmultiselect',
							'exists' => true,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
					case 'usergroups':
						$groups = [];
						foreach( $groupList as $group ) {
							$groups[UserGroupMembership::getGroupName( $group )] = $group;
						}
						$configs = [
							'type' => 'multiselect',
							'options' => isset( $set['options'] ) ? array_merge( $groups, $set['options'] ) : $groups,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'userrights':
						$rights = [];
						foreach( MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions() as $right ) {
							$rights[$right] = $right;
						}
						$configs = [
							'type' => 'multiselect',
							'options' => isset( $set['options'] ) ? array_merge( $rights, $set['options'] ) : $rights,
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						if ( !$disabled ) {
							$configs['dropdown'] = true;
						}
						break;
					case 'wikipage':
						$configs = [
							'type' => 'title',
							'exists' => isset( $set['exists'] ) ? $set['exists'] : true,
							'default' => $setList[$name] ?? $set['overridedefault'],
							'required' => false
						];
						break;
					case 'wikipages':
						$configs = [
							'type' => 'titlesmultiselect',
							'exists' => isset( $set['exists'] ) ? $set['exists'] : true,
							'default' => $setList[$name] ?? $set['overridedefault'],
							'required' => false
						];
						break;
					default:
						$configs = [
							'type' => $set['type'],
							'default' => $setList[$name] ?? $set['overridedefault']
						];
						break;
				}

				$help = ( $msgHelp->exists() ) ? $msgHelp->text() : $set['help'];
				if ( $set['requires'] ) {
					$requires = [];
					$requiresLabel = wfMessage( 'managewiki-requires' )->text();

					foreach ( $set['requires'] as $require => $data ) {
						if ( is_array( $data ) ) {
							foreach ( $data as $index => $element ) {
								if ( is_array( $element ) ) {
									$data[$index] = '( ' . implode( ' OR ', $element ) . ' )';
								}
							}
						}

						$requires[] = ucfirst( $require ) . " - " . ( is_array( $data ) ? implode( ', ', $data ) : $data );
					}

					$help .= "<br />{$requiresLabel}: " . implode( ' & ', $requires );
				}

				$formDescriptor["set-$name"] = [
					'label' => ( ( $msgName->exists() ) ? $msgName->text() : $set['name'] ) . " (\${$name})",
					'disabled' => $disabled,
					'help' => $help,
					'cssclass' => 'createwiki-infuse',
					'section' => ( isset( $set['section'] ) ) ? $set['section'] : 'other'
				] + $configs;
			}
		}

		return $formDescriptor;
	}

	private static function buildDescriptorNamespaces(
		string $dbName,
		bool $ceMW,
		string $special,
		RemoteWiki $wiki,
		Config $config
	) {
		$mwNamespace = new ManageWikiNamespaces( $dbName );
		
		$mwExt = new ManageWikiExtensions( $dbName );
		$extList = $mwExt->list();
		
		$formDescriptor = [];

		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1
		];

		foreach ( $nsID as $name => $id ) {
			$namespaceData = $mwNamespace->list( $id );

			$formDescriptor += [
				"namespace-$name" => [
					'type' => 'text',
					'label-message' => "namespaces-$name",
					'default' => $namespaceData['name'],
					'disabled' => ( $namespaceData['core'] || !$ceMW ),
					'required' => true,
					'section' => $name
				],
				"content-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-content',
					'default' => $namespaceData['content'],
					'disabled' => !$ceMW,
					'section' => $name
				],
				"subpages-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-subpages',
					'default' => $namespaceData['subpages'],
					'disabled' => !$ceMW,
					'section' => $name
				],
				"search-$name" => [
					'type' => 'check',
					'label-message' => 'namespaces-search',
					'default' => $namespaceData['searchable'],
					'disabled' => !$ceMW,
					'section' => $name
				],
				"contentmodel-$name" => [
					'type' => 'select',
					'label-message' => 'namespaces-contentmodel',
					'cssclass' => 'createwiki-infuse',
					'default' => $namespaceData['contentmodel'],
					'options' => array_merge( [
						'CSS' => 'css',
						'JavaScript' => 'javascript',
						'JSON' => 'json',
						'Wikitext' => 'wikitext'
						], (array)$config->get( 'ManageWikiNamespacesExtraContentModels' ) ),
					'disabled' => !$ceMW,
					'section' => $name
				],
				"protection-$name" => [
					'type' => 'selectorother',
					'label-message' => 'namespaces-protection',
					'cssclass' => 'createwiki-infuse',
					'default' => $namespaceData['protection'],
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

			foreach( (array)$config->get( 'ManageWikiNamespacesAdditional' ) as $key => $a ) {
				$mwRequirements = $a['requires'] ? ManageWikiRequirements::process( $a['requires'], $extList, false, $wiki ) : true;

				$add = ( isset( $a['requires']['visibility'] ) ? $mwRequirements : true ) && ( ( $a['from'] == 'mediawiki' ) || ( in_array( $a['from'], $extList ) ) );

				$help = null;
				if ( $add && ( $a['main'] && $name == 'namespace' || $a['talk'] && $name == 'namespacetalk' ) && ( !in_array( $id, (array)$a['blacklisted'] ) ) ) {

					if ( $a['requires'] ) {
						$requires = [];
						$requiresLabel = wfMessage( 'managewiki-requires' )->text();

						foreach ( $a['requires'] as $require => $data ) {
							if ( is_array( $data ) ) {
								foreach ( $data as $index => $element ) {
									if ( is_array( $element ) ) {
										$data[$index] = '( ' . implode( ' OR ', $element ) . ' )';
									}
								}
							}

							$requires[] = ucfirst( $require ) . " - " . ( is_array( $data ) ? implode( ', ', $data ) : $data );
						}

						$help = "{$requiresLabel}: " . implode( ' & ', $requires );
					}
					
					if ( is_array( $a['overridedefault'] ) ) {
						$a['overridedefault'] = $a['overridedefault'][$id] ?? $a['overridedefault']['default'];
					}

					$formDescriptor["$key-$name"] = [
						'label' => $a['name'],
						'type' => $a['type'] === 'vestyle'  ? 'check' : $a['type'],
						'default' => $namespaceData['additional'][$key] ?? $a['overridedefault'],
						'disabled' => ( $ceMW ) ? !$mwRequirements : true,
						'help' => $help,
						'section' => $name
					];
				}
			}

			$formDescriptor["aliases-$name"] = [
				'type' => 'textarea',
				'label-message' => 'namespaces-aliases',
				'default' => implode( "\n", $namespaceData['aliases'] ),
				'disabled' => !$ceMW,
				'section' => $name
			];
		}

		if ( $ceMW && !$formDescriptor['namespace-namespace']['disabled'] ) {
			$craftedNamespaces = [];
			$canDelete = false;

			foreach ( $mwNamespace->list() as $id => $config ) {
				if ( $id % 2 ) {
					continue;
				}

				if ( $id !== $nsID['namespace'] ) {
					$craftedNamespaces[$config['name']] = $id;
				} else {
					// Existing namespace
					$canDelete = true;
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
					'cssclass' => 'createwiki-infuse',
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
		bool &$ceMW,
		string $group,
		Config $config
	) {
		if ( in_array( $group, $config->get( 'ManageWikiPermissionsBlacklistGroups' ) ) ) {
			$ceMW = false;
		}

		$mwPermissions = new ManageWikiPermissions( $wiki );
		$permList = $mwPermissions->list( $group );

		$matrixConstruct = [
			'wgAddGroups' => $permList['addgroups'],
			'wgRemoveGroups' => $permList['removegroups'],
			'wgGroupsAddToSelf' => $permList['addself'],
			'wgGroupsRemoveFromSelf' => $permList['removeself']
		];

		$groupData = [
			'allPermissions' => array_diff( MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions(), ( isset( $config->get( 'ManageWikiPermissionsBlacklistRights' )[$group] ) ) ? array_merge( $config->get( 'ManageWikiPermissionsBlacklistRights' )[$group], $config->get( 'ManageWikiPermissionsBlacklistRights' )['any'] ) : $config->get( 'ManageWikiPermissionsBlacklistRights' )['any'] ),
			'assignedPermissions' => $permList['permissions'] ?? [],
			'allGroups' => array_diff( array_keys( $mwPermissions->list() ), $config->get( 'ManageWikiPermissionsBlacklistGroups' ), User::getImplicitGroups() ),
			'groupMatrix' => ManageWiki::handleMatrix( json_encode( $matrixConstruct ), 'php' ),
			'autopromote' => $permList['autopromote'] ?? null
		];

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
				'disabled' => !$ceMW,
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
				'disabled' => !$ceMW,
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'section' => 'autopromote'
			],
			'once' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-once',
				'default' => is_int( array_search( 'once', (array)$aP ) ),
				'disabled' => !$ceMW,
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'section' => 'autopromote'
			],
			'editcount' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-editcount',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'min' => 0,
				'default' => $aPArray[APCOND_EDITCOUNT] ?? 0,
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			],
			'age' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-age',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'min' => 0,
				'default' => isset( $aPArray[APCOND_AGE] ) ? $aPArray[APCOND_AGE] / 86400 : 0,
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			],
			'emailconfirmed' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-email',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_EMAILCONFIRMED, (array)$aP ) ),
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			],
			'blocked' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-blocked',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_BLOCKED, (array)$aP ) ),
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			],
			'bot' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-bot',
				'hide-if' => [ '!==', 'wpenable', '1' ],
				'default' => is_int( array_search( APCOND_ISBOT, (array)$aP ) ),
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			],
			'groups' => [
				'type' => 'multiselect',
				'label-message' => 'managewiki-permissions-autopromote-groups',
				'options' => $rowsBuilt,
				'hide-if' => [ 'OR', ['!==', 'wpenable', '1' ], [ '===', 'wpconds', '|' ] ],
				'default' => isset( $aPArray[APCOND_INGROUPS] ) ? $aPArray[APCOND_INGROUPS] : [],
				'disabled' => !$ceMW,
				'section' => 'autopromote'
			]
		];

		if ( $ceMW && ( count( $permList['permissions'] ) > 0 ) ) {
			$formDescriptor['delete-checkbox'] = [
				'type' => 'check',
				'label-message' => 'permissions-delete-checkbox',
				'default' => 0,
				'section' => 'handling'
			];
		}

		return $formDescriptor;
	}

	public static function submissionHandler(
		array $formData,
		HTMLForm $form,
		string $module,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki,
		DBConnRef $dbw,
		Config $config,
		string $special = ''
	) {
		switch ( $module ) {
			case 'core':
				$mwReturn = self::submissionCore( $formData, $dbName, $context, $wiki, $dbw, $config );
				break;
			case 'extensions':
				$mwReturn = self::submissionExtensions( $formData, $dbName, $config );
				break;
			case 'settings':
				$mwReturn = self::submissionSettings( $formData, $dbName, $context, $wiki, $config );
				break;
			case 'namespaces':
				$mwReturn = self::submissionNamespaces( $formData, $dbName, $special, $config );
				break;
			case 'permissions':
				$mwReturn = self::submissionPermissions( $formData, $dbName, $special, $config );
				break;
			default:
				throw new MWException( "{$module} not recognised" );
		}

		if ( $mwReturn->changes ) {
			$mwReturn->commit();
		} else {
			return [ [ 'managewiki-changes-none' => null ] ];
		}

		if ( $module != 'permissions' ) {
			$mwReturn->logParams['4::wiki'] = $dbName;
		}

		$mwLogEntry = new ManualLogEntry( 'managewiki', $mwReturn->log );
		$mwLogEntry->setPerformer( $context->getUser() );
		$mwLogEntry->setTarget( $form->getTitle() );
		$mwLogEntry->setComment( $formData['reason'] );
		$mwLogEntry->setParameters( $mwReturn->logParams );
		$mwLogID = $mwLogEntry->insert();
		$mwLogEntry->publish( $mwLogID );

		return $mwReturn->errors ?? [];
	}

	private static function submissionCore(
		array $formData,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki,
		DBConnRef $dbw,
		Config $config
	) {
		$mwActions = [
			'delete',
			'undelete',
			'lock',
			'unlock'
		];

		foreach ( $mwActions as $mwAction ) {
			if ( isset( $formData[$mwAction] ) && $formData[$mwAction] ) {
				$wiki->$mwAction();

				return $wiki;
			}
		}

		if ( $config->get( 'CreateWikiUsePrivateWikis' ) && ( $wiki->isPrivate() != $formData['private'] ) ) {
				( $formData['private'] ) ? $wiki->markPrivate() : $wiki->markPublic();
		}

		if ( $config->get( 'CreateWikiUseClosedWikis' ) ) {
			$closed = (bool)$wiki->isClosed();
			$newClosed = $formData['closed'];

			if ( $newClosed && ( $closed != $newClosed ) ) {
				$wiki->markClosed();
			} elseif ( !$newClosed && ( $closed != $newClosed ) ) {
				$wiki->markActive();
			}
		}

		if ( $config->get( 'CreateWikiUseInactiveWikis' ) ) {
			$newInactive = $formData['inactive'];
			$inactive = (bool)$wiki->isInactive();
			$newInactiveExempt = $formData['inactive-exempt'];

			if ( $newInactive != $inactive ) {
				( $newInactive ) ? $wiki->markInactive() : $wiki->markActive();
			}

			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( ( $newInactiveExempt != $wiki->isInactiveExempt() ) && $permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ) ) {
				if ( $newInactiveExempt ) {
					$wiki->markExempt();
				} else {
					$wiki->unExempt();
				}
			}
		}

		if ( $config->get( 'CreateWikiUseCategories' ) && ( $formData['category'] != $wiki->getCategory() ) ) {
			$wiki->setCategory( $formData['category'] );
		}

		if ( $config->get( 'CreateWikiUseCustomDomains' ) && ( $formData['server'] != $wiki->getServerName() ) ) {
			$wiki->setServerName( $formData['server'] );
		}

		if ( $formData['sitename'] != $wiki->getSitename() ) {
			$wiki->setSitename( $formData['sitename'] );
		}

		if ( $formData['language'] != $wiki->getLanguage() ) {
			$wiki->setLanguage( $formData['language'] );
		}

		if ( $config->get( 'CreateWikiDatabaseClusters' ) && ( $formData['dbcluster'] != $wiki->getDBCluster() ) ) {
			$wiki->setDBCluster( $formData['dbcluster'] );
		}

		return $wiki;
	}

	private static function submissionExtensions(
		array $formData,
		string $dbName,
		Config $config
	) {
		$mwExt = new ManageWikiExtensions( $dbName );
		$newExtList = [];

		foreach ( $config->get( 'ManageWikiExtensions' ) as $name => $ext ) {
			if ( $formData["ext-{$name}"] ) {
				$newExtList[] = $name;
			}
		}

		$mwExt->overwriteAll( $newExtList );

		return $mwExt;
	}

	private static function submissionSettings(
		array $formData,
		string $dbName,
		IContextSource $context,
		RemoteWiki $wiki,
		Config $config
	) {
		$mwExt = new ManageWikiExtensions( $dbName );
		$extList = $mwExt->list();

		$mwSettings = new ManageWikiSettings( $dbName );
		$settingsList = $mwSettings->list();

		$settingsArray = [];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		foreach ( $config->get( 'ManageWikiSettings' ) as $name => $set ) {
			// No need to do anything if setting does not 'exist'
			if ( !isset( $formData["set-$name"] ) ) {
				continue;
			}

			$current = $settingsList[$name] ?? $set['overridedefault'];
			$mwAllowed = $set['requires'] ? ManageWikiRequirements::process( $set['requires'], $extList, false, $wiki ) : true;
			$type = $set['type'];

			$value = $formData["set-$name"];

			if ( $type == 'matrix' ) {
				$settingsArray[$name] = ( $mwAllowed ) ? ManageWiki::handleMatrix( $value, 'phparray' ) : ManageWiki::handleMatrix( $current, 'php' );
			} elseif ( $type == 'check' ) {
				$settingsArray[$name] = ( $mwAllowed ) ? $value : $current;
			} elseif( $type == 'list-multi' ||  $type == 'usergroups' ||  $type == 'userrights' ) {
				$settingsArray[$name] = $value;
			} elseif( $type == 'list-multi-bool' ) {
				foreach ( $set['allopts'] as $opt ) {
					$settingsArray[$name][$opt] = in_array( $opt, $value );
				}
			} elseif ( $type != 'text' || $value ) {
				$settingsArray[$name] = ( $mwAllowed ) ? $value : $current;
			} elseif ( !$mwAllowed ) {
					$settingsArray[$name] = $current;
			}
		}

		$mwSettings->overwriteAll( $settingsArray );

		return $mwSettings;
	}

	private static function submissionNamespaces(
		array $formData,
		string $dbName,
		string $special,
		Config $config
	) {
		$mwNamespaces = new ManageWikiNamespaces( $dbName );

		if ( $formData['delete-checkbox'] ) {
			$mwNamespaces->remove( $special, $formData['delete-migrate-to'] );
			$mwNamespaces->remove( (int)$special + 1, $formData['delete-migrate-to'] + 1 );
			return $mwNamespaces;
		}

		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1
		];

		foreach ( $nsID as $name => $id ) {
			$namespaceName = str_replace( ' ', '_', $formData["namespace-$name"] );

			$additionalBuilt = [];

			foreach ( (array)$config->get( 'ManageWikiNamespacesAdditional' ) as $key => $a ) {
				if ( isset( $formData["$key-$name"] ) ) {
					$additionalBuilt[$key] = $formData["$key-$name"];
				}
			}

			$build = [
				'name' => $namespaceName,
				'searchable' => (int)$formData["search-$name"],
				'subpages' => (int)$formData["subpages-$name"],
				'protection' => $formData["protection-$name"],
				'content' => (int)$formData["content-$name"],
				'contentmodel' => $formData["contentmodel-$name"],
				'aliases' => ( $formData["aliases-$name"] == '' ) ? [] : explode( "\n", $formData["aliases-$name"] ),
				'additional' => $additionalBuilt
			];

			$mwNamespaces->modify( $id, $build );
		}

		return $mwNamespaces;
	}

	private static function submissionPermissions(
		array $formData,
		string $wiki,
		string $group,
		Config $config
	) {
		$mwPermissions = new ManageWikiPermissions( $wiki );
		$permList = $mwPermissions->list( $group );
		$assignablePerms = array_diff( MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions(), ( isset( $config->get( 'ManageWikiPermissionsBlacklistRights' )[$group] ) ) ? array_merge( $config->get( 'ManageWikiPermissionsBlacklistRights' )[$group], $config->get( 'ManageWikiPermissionsBlacklistRights' )['any'] ) : $config->get( 'ManageWikiPermissionsBlacklistRights' )['any'] );

		// Early escape for deletion
		if ( isset( $formData['delete-checkbox'] ) && $formData['delete-checkbox'] ) {
			$mwPermissions->remove( $group );
			return $mwPermissions;
		}

		$permData = [];

		$addedPerms = [];
		$removedPerms = [];

		foreach ( $assignablePerms as $perm ) {
			if ( $formData["right-$perm"] && !is_int( array_search( $perm, $permList['permissions'] ) ) ) {
				$addedPerms[] = $perm;
			} elseif ( !$formData["right-$perm"] && is_int( array_search( $perm, $permList['permissions'] ) ) ) {
				$removedPerms[] = $perm;
			}
		}

		// Add permission changes to permData
		$permData['permissions'] = [
			'add' => $addedPerms,
			'remove' => $removedPerms
		];

		$newMatrix = ManageWiki::handleMatrix( $formData['group-matrix'], 'phparray' );

		$matrixNew = [
			'addgroups' => array_diff( $newMatrix['wgAddGroups'] ?? [], $permList['addgroups'] ),
			'removegroups' => array_diff( $newMatrix['wgRemoveGroups'] ?? [], $permList['removegroups'] ),
			'addself' => array_diff( $newMatrix['wgGroupsAddToSelf'] ?? [], $permList['addself'] ),
			'removeself' => array_diff( $newMatrix['wgGroupsRemoveFromSelf'] ?? [], $permList['removeself'] )
		];

		$matrixOld = [
			'addgroups' => array_diff( $permList['addgroups'], $newMatrix['wgAddGroups'] ?? [] ),
			'removegroups' => array_diff( $permList['removegroups'], $newMatrix['wgRemoveGroups'] ?? [] ),
			'addself' => array_diff( $permList['addself'], $newMatrix['wgGroupsAddToSelf'] ?? [] ),
			'removeself' => array_diff( $permList['removeself'], $newMatrix['wgGroupsRemoveFromSelf'] ?? [] )
		];

		foreach ( $matrixNew as $type => $array ) {
			$newArray = [];
			foreach ( $array as $name ) {
				$newArray[] = $name;
			}

			$permData[$type]['add'] = $newArray;
		}

		foreach ( $matrixOld as $type => $array ) {
			$newArray = [];

			foreach ( $array as $name ) {
				$newArray[] = $name;
			}

			$permData[$type]['remove'] = $newArray;
		}

		$aE = $formData['enable'];

		$aPBuild = $aE ? [
				$formData['conds']
		] : [];

		if ( count( $aPBuild ) != 0 ) {
			$loopBuild = [
				'once' => 'once',
				'editcount' => [ APCOND_EDITCOUNT, (int)$formData['editcount'] ],
				'age' => [ APCOND_AGE, (int)$formData['age'] * 86400 ],
				'emailconfirmed' => APCOND_EMAILCONFIRMED,
				'blocked' => APCOND_BLOCKED,
				'bot' => APCOND_ISBOT,
				'groups' => array_merge( [ APCOND_INGROUPS ], $formData['groups'] )
			];

			foreach ( $loopBuild as $type => $value ) {
				if ( $formData[$type] ) {
					$aPBuild[] = $value;
				}
			}
		}

		$permData['autopromote'] = ( count( $aPBuild ) <= 1 )  ? null : $aPBuild;

		if ( !in_array( $group, $config->get( 'ManageWikiPermissionsPermanentGroups' ) ) && ( count( $permData['permissions']['remove'] ) > 0 ) && ( count( $permList['permissions'] ) == count( $permData['permissions']['remove'] ) ) ) {
			$mwPermissions->remove( $group );
		} else {
			$mwPermissions->modify( $group, $permData );
		}

		return $mwPermissions;
	}
}
