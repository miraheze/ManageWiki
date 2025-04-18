<?php

namespace Miraheze\ManageWiki\FormFactory;

use InvalidArgumentException;
use ManualLogEntry;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionProcessor;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Helpers\ManageWikiRequirements;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;
use Miraheze\ManageWiki\Helpers\ManageWikiTypes;
use Miraheze\ManageWiki\ManageWiki;
use Wikimedia\Rdbms\IDatabase;

class ManageWikiFormFactoryBuilder {

	public static function buildDescriptor(
		string $module,
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		string $special,
		string $filtered,
		Config $config
	): array {
		switch ( $module ) {
			case 'core':
				$formDescriptor = self::buildDescriptorCore( $dbname, $ceMW, $context, $remoteWiki, $config );
				break;
			case 'extensions':
				$formDescriptor = self::buildDescriptorExtensions( $dbname, $ceMW, $context, $remoteWiki, $config );
				break;
			case 'settings':
				$formDescriptor = self::buildDescriptorSettings( $dbname, $ceMW, $context, $remoteWiki, $config, $filtered );
				break;
			case 'namespaces':
				$formDescriptor = self::buildDescriptorNamespaces( $dbname, $ceMW, $context, $special, $remoteWiki, $config );
				break;
			case 'permissions':
				$formDescriptor = self::buildDescriptorPermissions( $dbname, $ceMW, $context, $special, $config );
				break;
			default:
				throw new InvalidArgumentException( "$module not recognized" );
		}

		return $formDescriptor;
	}

	private static function buildDescriptorCore(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		Config $config
	): array {
		$formDescriptor = [];
		$formDescriptor['dbname'] = [
			'label-message' => 'managewiki-label-dbname',
			'type' => 'text',
			'default' => $dbname,
			'disabled' => true,
			'section' => 'main',
		];

		$databaseUtils = MediaWikiServices::getInstance()->get( 'CreateWikiDatabaseUtils' );
		if ( $ceMW && $databaseUtils->isCurrentWikiCentral() && !$databaseUtils->isRemoteWikiCentral( $dbname ) ) {
			$mwActions = [
				$remoteWiki->isDeleted() ? 'undelete' : 'delete',
				$remoteWiki->isLocked() ? 'unlock' : 'lock',
			];

			foreach ( $mwActions as $mwAction ) {
				$formDescriptor[$mwAction] = [
					'type' => 'check',
					'label-message' => "managewiki-label-{$mwAction}wiki",
					'default' => false,
					'section' => 'main',
				];
			}
		}

		$formDescriptor += [
			'sitename' => [
				'label-message' => 'managewiki-label-sitename',
				'type' => 'text',
				'default' => $remoteWiki->getSitename(),
				'disabled' => !$ceMW,
				'required' => true,
				'section' => 'main',
			],
			'language' => [
				'label-message' => 'managewiki-label-language',
				'type' => 'language',
				'default' => $remoteWiki->getLanguage(),
				'disabled' => !$ceMW,
				'required' => true,
				'cssclass' => 'managewiki-infuse',
				'section' => 'main',
			],
		];

		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();

		$addedModules = [
			'private' => [
				'if' => $config->get( 'CreateWikiUsePrivateWikis' ),
				'type' => 'check',
				'default' => $remoteWiki->isPrivate(),
				'access' => !$ceMW,
			],
			'closed' => [
				'if' => $config->get( 'CreateWikiUseClosedWikis' ),
				'type' => 'check',
				'default' => $remoteWiki->isClosed(),
				'access' => !$ceMW,
			],
			'inactive' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ),
				'type' => 'check',
				'default' => $remoteWiki->isInactive(),
				'access' => !$ceMW,
			],
			'inactive-exempt' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ),
				'type' => 'check',
				'default' => $remoteWiki->isInactiveExempt(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
			],
			'inactive-exempt-reason' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ) && $config->get( ConfigNames::InactiveExemptReasonOptions ),
				'hide-if' => [ '!==', 'inactive-exempt', '1' ],
				'type' => 'selectorother',
				'default' => $remoteWiki->getInactiveExemptReason(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
				'options' => $config->get( ConfigNames::InactiveExemptReasonOptions ),
			],
			'server' => [
				'if' => $config->get( ConfigNames::UseCustomDomains ),
				'type' => 'text',
				'default' => $remoteWiki->getServerName(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
			],
			'experimental' => [
				'if' => $config->get( 'CreateWikiUseExperimental' ),
				'type' => 'check',
				'default' => $remoteWiki->isExperimental(),
				'access' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
			],
		];

		foreach ( $addedModules as $name => $data ) {
			if ( $data['if'] ) {
				$formDescriptor[$name] = [
					'type' => $data['type'],
					'label-message' => "managewiki-label-$name",
					'default' => $data['default'],
					'disabled' => $data['access'],
					'cssclass' => 'managewiki-infuse',
					'section' => 'main',
				];

				if ( $data['hide-if'] ?? false ) {
					$formDescriptor[$name]['hide-if'] = $data['hide-if'] ?? [];
				}

				if ( $data['options'] ?? false ) {
					$formDescriptor[$name]['options'] = $data['options'] ?? [];
				}
			}
		}

		if ( $config->get( 'CreateWikiCategories' ) ) {
			$formDescriptor['category'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $config->get( 'CreateWikiCategories' ),
				'default' => $remoteWiki->getCategory(),
				'disabled' => !$ceMW,
				'cssclass' => 'managewiki-infuse',
				'section' => 'main',
			];
		}

		$hookRunner = MediaWikiServices::getInstance()->get( 'ManageWikiHookRunner' );
		$hookRunner->onManageWikiCoreAddFormFields(
			$context, $remoteWiki, $dbname, $ceMW, $formDescriptor
		);

		if ( $config->get( 'CreateWikiDatabaseClusters' ) ) {
			$clusterList = array_merge(
				$config->get( 'CreateWikiDatabaseClusters' ),
				$config->get( ConfigNames::DatabaseClustersInactive )
			);

			$formDescriptor['dbcluster'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-dbcluster',
				'options' => array_combine( $clusterList, $clusterList ),
				'default' => $remoteWiki->getDBCluster(),
				'disabled' => !$permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ),
				'cssclass' => 'managewiki-infuse',
				'section' => 'main',
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorExtensions(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		Config $config
	): array {
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();

		$manageWikiSettings = $config->get( ConfigNames::Settings );

		$queue = array_fill_keys( array_merge(
				glob( $config->get( MainConfigNames::ExtensionDirectory ) . '/*/extension*.json' ),
				glob( $config->get( MainConfigNames::StyleDirectory ) . '/*/skin.json' )
			),
		true );

		$processor = new ExtensionProcessor();

		foreach ( $queue as $path => $mtime ) {
			$json = file_get_contents( $path );
			$info = json_decode( $json, true );
			$version = $info['manifest_version'] ?? 2;

			$processor->extractInfo( $path, $info, $version );
		}

		$data = $processor->getExtractedInfo();
		$credits = $data['credits'];

		$formDescriptor = [];

		foreach ( $config->get( ConfigNames::Extensions ) as $name => $ext ) {
			$filteredList = array_filter(
				$manageWikiSettings,
				static fn ( array $value ): bool => $value['from'] === $name
			);

			$hasSettings = count( array_diff_assoc( $filteredList, array_keys( $manageWikiSettings ) ) ) > 0;

			$mwRequirements = $ext['requires'] ? ManageWikiRequirements::process( $ext['requires'], $extList, false, $remoteWiki ) : true;

			$help = [];
			$conflictLabel = $context->msg( 'managewiki-conflicts' )->escaped();
			$requiresLabel = $context->msg( 'managewiki-requires' )->escaped();

			if ( $ext['conflicts'] ) {
				$help[] = "$conflictLabel {$ext['conflicts']}<br />";
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

					$requires[] = ucfirst( $require ) . ' - ' . ( is_array( $data ) ? implode( ', ', $data ) : $data );
				}

				$help[] = "$requiresLabel: " . implode( ' & ', $requires ) . '<br />';
			}

			$descriptionmsg = array_column( $credits, 'descriptionmsg', 'name' )[ $ext['name'] ] ?? false;
			$description = array_column( $credits, 'description', 'name' )[ $ext['name'] ] ?? null;

			$namemsg = array_column( $credits, 'namemsg', 'name' )[ $ext['name'] ] ?? false;
			$extname = array_column( $credits, 'name', 'name' )[ $ext['name'] ] ?? null;

			$extDescription = ( $ext['description'] ?? false ) ? ( $context->msg( $ext['description'] )->exists() ? $context->msg( $ext['description'] )->parse() : $ext['description'] ) : null;
			$extDisplayName = ( $ext['displayname'] ?? false ) ? ( $context->msg( $ext['displayname'] )->exists() ? $context->msg( $ext['displayname'] )->parse() : $ext['displayname'] ) : null;

			$help[] = $extDescription ?? ( $descriptionmsg ? ( $context->msg( $descriptionmsg )->exists() ? $context->msg( $descriptionmsg )->parse() : $descriptionmsg ) : null ) ?? $description;

			if ( $ext['help'] ?? false ) {
				$help[] = "<br />{$ext['help']}";
			}

			if ( $hasSettings && in_array( $name, $extList ) ) {
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
				$help[] = '<br />' . $linkRenderer->makeExternalLink(
					SpecialPage::getTitleFor( 'ManageWiki', "settings/$name" )->getFullURL(),
					$context->msg( 'managewiki-extension-settings' )->text(),
					SpecialPage::getTitleFor( 'ManageWiki', 'settings' )
				);
			}

			$formDescriptor["ext-$name"] = [
				'type' => 'check',
				'label-message' => [
					'managewiki-extension-name',
					$ext['linkPage'],
					$extDisplayName ?? ( $namemsg ? $context->msg( $namemsg )->text() : $extname ) ?? $ext['name'],
				],
				'default' => in_array( $name, $extList ),
				'disabled' => $ceMW ? !$mwRequirements : true,
				'help' => implode( ' ', $help ),
				'section' => $ext['section'],
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorSettings(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		Config $config,
		string $filtered
	): array {
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();
		$mwSettings = new ManageWikiSettings( $dbname );
		$settingsList = $mwSettings->list( var: null );
		$mwPermissions = new ManageWikiPermissions( $dbname );
		$groupList = array_keys( $mwPermissions->list( group: null ) );

		$manageWikiSettings = $config->get( ConfigNames::Settings );
		$filteredList = array_filter( $manageWikiSettings, static fn ( array $value ): bool =>
			$value['from'] === strtolower( $filtered ) && (
				in_array( $value['from'], $extList ) ||
				( $value['global'] ?? false )
			)
		);

		$formDescriptor = [];
		$filteredSettings = array_diff_assoc( $filteredList, array_keys( $manageWikiSettings ) ) ?: $manageWikiSettings;

		foreach ( $filteredSettings as $name => $set ) {
			if ( !isset( $set['requires'] ) ) {
				$logger = LoggerFactory::getInstance( 'ManageWiki' );
				$logger->error( '\'requires\' is not set in ManageWikiSettings for {setting}', [
					'setting' => $name,
				] );
				$mwRequirements = true;
			} else {
				$mwRequirements = $set['requires'] ?
					ManageWikiRequirements::process( $set['requires'], $extList, false, $remoteWiki ) : true;
			}

			$add = ( isset( $set['requires']['visibility'] ) ? $mwRequirements : true ) &&
				( (bool)( $set['global'] ?? false ) || in_array( $set['from'], $extList ) );

			$disabled = $ceMW ? !$mwRequirements : true;

			$msgName = $context->msg( "managewiki-setting-$name-name" );
			$msgHelp = $context->msg( "managewiki-setting-$name-help" );

			if ( $add ) {
				$value = $settingsList[$name] ?? null;
				if ( isset( $set['associativeKey'] ) ) {
					$value = $settingsList[$name][ $set['associativeKey'] ] ?? $set['overridedefault'][ $set['associativeKey'] ];
				}

				$configs = ManageWikiTypes::process( $config, $disabled, $groupList, 'settings', $set, $value, $name );

				$help = $msgHelp->exists() ? $msgHelp->escaped() : $set['help'];
				if ( $set['requires'] ) {
					$requires = [];
					$requiresLabel = $context->msg( 'managewiki-requires' )->escaped();

					foreach ( $set['requires'] as $require => $data ) {
						if ( is_array( $data ) ) {
							foreach ( $data as $index => $element ) {
								if ( is_array( $element ) ) {
									$data[$index] = '( ' . implode( ' OR ', $element ) . ' )';
								}
							}
						}

						$requires[] = ucfirst( $require ) . ' - ' . ( is_array( $data ) ? implode( ', ', $data ) : $data );
					}

					$help .= "<br />$requiresLabel: " . implode( ' & ', $requires );
				}

				// Hack to prevent "implicit submission". See T275588 for more
				if ( ( $configs['type'] ?? '' ) === 'cloner' ) {
					$formDescriptor["fake-submit-$name"] = [
						'type' => 'submit',
						'disabled' => true,
						'section' => $set['section'],
						'cssclass' => 'managewiki-fakesubmit',
					];
				}

				$varName = " (\${$name})";
				if ( isset( $set['associativeKey'] ) ) {
					$varName = " (\${$name}['{$set['associativeKey']}'])";
				}

				$formDescriptor["set-$name"] = [
					'label' => ( $msgName->exists() ? $msgName->text() : $set['name'] ) . $varName,
					'disabled' => $disabled,
					'help' => $help,
					'cssclass' => 'managewiki-infuse',
					'section' => $set['section'],
				] + $configs;
			}
		}

		return $formDescriptor;
	}

	private static function buildDescriptorNamespaces(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		string $special,
		RemoteWikiFactory $remoteWiki,
		Config $config
	): array {
		$mwNamespaces = new ManageWikiNamespaces( $dbname );
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();

		$formDescriptor = [];
		$nsID = [];

		$nsID['namespace'] = (int)$special;

		if (
			$mwNamespaces->list( (int)$special + 1 )['name'] ||
			!$mwNamespaces->list( (int)$special )['name']
		) {
			$nsID['namespacetalk'] = (int)$special + 1;
		}

		$session = $context->getRequest()->getSession();

		foreach ( $nsID as $name => $id ) {
			$namespaceData = $mwNamespaces->list( $id );
			$create = ucfirst( $session->get( 'create' ) ) .
				( $name === 'namespacetalk' && $session->get( 'create' ) ? '_talk' : null );

			$formDescriptor += [
				"namespace-$name" => [
					'type' => 'text',
					'label' => $context->msg( "namespaces-$name" )->text() . ' ($wgExtraNamespaces)',
					'default' => $namespaceData['name'] ?: $create,
					'disabled' => $namespaceData['core'] || !$ceMW,
					'required' => true,
					'section' => $name,
				],
				"content-$name" => [
					'type' => 'check',
					'label' => $context->msg( 'namespaces-content' )->text() . ' ($wgContentNamespaces)',
					'default' => $namespaceData['content'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"subpages-$name" => [
					'type' => 'check',
					'label' => $context->msg( 'namespaces-subpages' )->text() . ' ($wgNamespacesWithSubpages)',
					'default' => $namespaceData['subpages'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"search-$name" => [
					'type' => 'check',
					'label' => $context->msg( 'namespaces-search' )->text() . ' ($wgNamespacesToBeSearchedDefault)',
					'default' => $namespaceData['searchable'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"contentmodel-$name" => [
					'label' => $context->msg( 'namespaces-contentmodel' )->text() . ' ($wgNamespaceContentModels)',
					'cssclass' => 'managewiki-infuse',
					'disabled' => !$ceMW,
					'section' => $name,
				] + ManageWikiTypes::process( $config, false, [], 'namespaces', [], $namespaceData['contentmodel'], null, false, 'contentmodel' ),
				"protection-$name" => [
					'type' => 'combobox',
					'label' => $context->msg( 'namespaces-protection' )->text() . ' ($wgNamespaceProtection)',
					'cssclass' => 'managewiki-infuse',
					'default' => $namespaceData['protection'],
					'options' => [
						'None' => '',
						'editinterface' => 'editinterface',
						'editsemiprotected' => 'editsemiprotected',
						'editprotected' => 'editprotected',
					],
					'disabled' => !$ceMW,
					'section' => $name,
				],
			];

			foreach ( $config->get( ConfigNames::NamespacesAdditional ) as $key => $a ) {
				$mwRequirements = $a['requires'] ? ManageWikiRequirements::process( $a['requires'], $extList, false, $remoteWiki ) : true;

				$add = ( isset( $a['requires']['visibility'] ) ? $mwRequirements : true ) && ( ( $a['from'] === 'mediawiki' ) || ( in_array( $a['from'], $extList ) ) );
				$disabled = $ceMW ? !$mwRequirements : true;

				$msgName = $context->msg( "managewiki-namespaces-$key-name" );
				$msgHelp = $context->msg( "managewiki-namespaces-$key-help" );

				if (
					$add &&
					(
						( $a['main'] && $name === 'namespace' ) ||
						( $a['talk'] && $name === 'namespacetalk' )
					) &&
					!in_array( $id, (array)( $a['excluded'] ?? [] ) ) &&
					in_array( $id, (array)( $a['only'] ?? [ $id ] ) )
				) {
					if ( is_array( $a['overridedefault'] ) ) {
						$a['overridedefault'] = $a['overridedefault'][$id] ?? $a['overridedefault']['default'];
					}

					$configs = ManageWikiTypes::process( $config, $disabled, [], 'namespaces', $a, $namespaceData['additional'][$key] ?? null, null, $a['overridedefault'], $a['type'] );

					$help = $msgHelp->exists() ? $msgHelp->escaped() : $a['help'];
					if ( $a['requires'] ) {
						$requires = [];
						$requiresLabel = $context->msg( 'managewiki-requires' )->escaped();

						foreach ( $a['requires'] as $require => $data ) {
							if ( is_array( $data ) ) {
								foreach ( $data as $index => $element ) {
									if ( is_array( $element ) ) {
										$data[$index] = '( ' . implode( ' OR ', $element ) . ' )';
									}
								}
							}

							$requires[] = ucfirst( $require ) . ' - ' . ( is_array( $data ) ? implode( ', ', $data ) : $data );
						}

						$help .= "<br />{$requiresLabel}: " . implode( ' & ', $requires );
					}

					$formDescriptor["$key-$name"] = [
						'label' => ( $msgName->exists() ? $msgName->text() : $a['name'] ) . " (\${$key})",
						'help' => $help,
						'cssclass' => 'managewiki-infuse',
						'disabled' => $disabled,
						'section' => $name,
					] + $configs;
				}
			}

			$formDescriptor["aliases-$name"] = [
				'label' => $context->msg( 'namespaces-aliases' )->text() . ' ($wgNamespaceAliases)',
				'cssclass' => 'managewiki-infuse',
				'disabled' => !$ceMW,
				'section' => $name,
			] + ManageWikiTypes::process( $config, false, [], 'namespaces', [], $namespaceData['aliases'], null, [], 'texts' );
		}

		if ( $ceMW && !$formDescriptor['namespace-namespace']['disabled'] ) {
			$craftedNamespaces = [];
			$canDelete = $mwNamespaces->exists( (int)$special );

			foreach ( $mwNamespaces->list( id: null ) as $id => $config ) {
				if ( $mwNamespaces->isTalk( $id ) ) {
					continue;
				}

				if ( $id !== $nsID['namespace'] ) {
					$craftedNamespaces[$config['name']] = $id;
					continue;
				}
			}

			$formDescriptor += [
				'delete-checkbox' => [
					'type' => 'check',
					'label-message' => 'namespaces-delete-checkbox',
					'default' => false,
					'disabled' => !$canDelete,
					'section' => 'delete',
				],
				'delete-migrate-to' => [
					'type' => 'select',
					'label-message' => 'namespaces-migrate-to',
					'cssclass' => 'managewiki-infuse',
					'options' => $craftedNamespaces,
					'default' => NS_MAIN,
					'disabled' => !$canDelete,
					'hide-if' => [ '!==', 'delete-checkbox', '1' ],
					'section' => 'delete',
				],
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorPermissions(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		string $group,
		Config $config
	): array {
		if ( in_array( $group, $config->get( ConfigNames::PermissionsDisallowedGroups ) ) ) {
			$ceMW = false;
		}

		$mwPermissions = new ManageWikiPermissions( $dbname );
		$groupData = $mwPermissions->list( $group );

		$matrixConstruct = [
			'wgAddGroups' => $groupData['addgroups'],
			'wgRemoveGroups' => $groupData['removegroups'],
			'wgGroupsAddToSelf' => $groupData['addself'],
			'wgGroupsRemoveFromSelf' => $groupData['removeself'],
		];

		$userGroupManager = MediaWikiServices::getInstance()->getUserGroupManager();
		$assignedPermissions = $groupData['permissions'] ?? [];

		$disallowed = array_merge(
			$config->get( ConfigNames::PermissionsDisallowedRights )[$group] ?? [],
			$config->get( ConfigNames::PermissionsDisallowedRights )['any']
		);

		$allPermissions = MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions();

		// Start with all allowed permissions
		$allPermissions = array_diff( $allPermissions, $disallowed );

		/**
		 * Include any assigned permissions that aren’t in
		 * current available permissions and aren’t disallowed.
		 * This may include permissions for extensions that have
		 * since been disabled. We do this so that the permissions
		 * can be removed from them, otherwise the groups that use
		 * these permissions become undeletable.
		 */
		$extraAssigned = array_filter(
			$assignedPermissions,
			static fn ( string $perm ): bool => !in_array( $perm, $allPermissions, true ) &&
				!in_array( $perm, $disallowed, true )
		);

		// Merge and deduplicate
		$allPermissions = array_unique( array_merge( $allPermissions, $extraAssigned ) );

		$groupData = [
			'allPermissions' => $allPermissions,
			'assignedPermissions' => $assignedPermissions,
			'allGroups' => array_diff(
				array_keys( $mwPermissions->list( group: null ) ),
				$config->get( ConfigNames::PermissionsDisallowedGroups ),
				$userGroupManager->listAllImplicitGroups()
			),
			'groupMatrix' => ManageWiki::handleMatrix( json_encode( $matrixConstruct ), 'php' ),
			'autopromote' => $groupData['autopromote'] ?? null,
		];

		$formDescriptor = [
			'assigned' => [
				'type' => 'info',
				'default' => $context->msg( 'managewiki-permissions-assigned' )->text(),
				'section' => 'assigned',
			],
			'unassigned' => [
				'type' => 'info',
				'default' => $context->msg( 'managewiki-permissions-unassigned' )->text(),
				'section' => 'unassigned',
			],
			'group' => [
				'type' => 'info',
				'default' => $context->msg( 'managewiki-permissions-group' )->text(),
				'section' => 'group',
			],
			'autopromote' => [
				'type' => 'info',
				'default' => $context->msg( 'managewiki-permissions-autopromote' )->text(),
				'section' => 'autopromote',
			],
		];

		foreach ( $groupData['allPermissions'] as $perm ) {
			$assigned = in_array( $perm, $groupData['assignedPermissions'] );
			$formDescriptor["right-$perm"] = [
				'type' => 'check',
				'label' => $perm,
				'help' => htmlspecialchars( User::getRightDescription( $perm ) ),
				'section' => $assigned ? 'assigned' : 'unassigned',
				'default' => $assigned,
				'disabled' => !$ceMW,
			];
		}

		$language = $context->getLanguage();
		$rowsBuilt = [];

		foreach ( $groupData['allGroups'] as $groupName ) {
			$lowerCaseGroupName = strtolower( $groupName );
			$rowsBuilt[htmlspecialchars( $language->getGroupName( $lowerCaseGroupName ) )] = $lowerCaseGroupName;
		}

		$formDescriptor['group-matrix'] = [
			'type' => 'checkmatrix',
			'columns' => [
				$context->msg( 'managewiki-permissions-addall' )->escaped() => 'wgAddGroups',
				$context->msg( 'managewiki-permissions-removeall' )->escaped() => 'wgRemoveGroups',
				$context->msg( 'managewiki-permissions-addself' )->escaped() => 'wgGroupsAddToSelf',
				$context->msg( 'managewiki-permissions-removeself' )->escaped() => 'wgGroupsRemoveFromSelf',
			],
			'rows' => $rowsBuilt,
			'section' => 'group',
			'default' => $groupData['groupMatrix'],
			'disabled' => !$ceMW,
		];

		// This is not a good method but it is a method.
		$aP = $groupData['autopromote'];
		$aPArray = [];
		foreach ( (array)$aP as $element ) {
			if ( is_array( $element ) ) {
				$aPArray[$element[0]] = $element[0] === APCOND_INGROUPS ?
					array_slice( $element, 1 ) : $element[1];
			}
		}

		$formDescriptor += [
			'enable' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-enable',
				'default' => $aP !== null,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'conds' => [
				'type' => 'select',
				'label-message' => 'managewiki-permissions-autopromote-conds',
				'options-messages' => [
					'managewiki-permissions-autopromote-conds-and' => '&',
					'managewiki-permissions-autopromote-conds-or' => '|',
					'managewiki-permissions-autopromote-conds-not' => '!',
				],
				'default' => $aP === null ? '&' : $aP[0],
				'disabled' => !$ceMW,
				'hide-if' => [ '!==', 'enable', '1' ],
				'section' => 'autopromote',
			],
			'once' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-once',
				'default' => array_search( 'once', (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'hide-if' => [ '!==', 'enable', '1' ],
				'section' => 'autopromote',
			],
			'editcount' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-editcount',
				'hide-if' => [ '!==', 'enable', '1' ],
				'min' => 0,
				'default' => $aPArray[APCOND_EDITCOUNT] ?? 0,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'age' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-age',
				'hide-if' => [ '!==', 'enable', '1' ],
				'min' => 0,
				'default' => isset( $aPArray[APCOND_AGE] ) ? $aPArray[APCOND_AGE] / 86400 : 0,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'emailconfirmed' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-email',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_EMAILCONFIRMED, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'blocked' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-blocked',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_BLOCKED, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'bot' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-bot',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_ISBOT, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
			'groups' => [
				'type' => 'multiselect',
				'label-message' => 'managewiki-permissions-autopromote-groups',
				'options' => $rowsBuilt,
				'hide-if' => [ 'OR', [ '!==', 'enable', '1' ], [ '===', 'conds', '|' ] ],
				'default' => $aPArray[APCOND_INGROUPS] ?? [],
				'disabled' => !$ceMW,
				'section' => 'autopromote',
			],
		];

		if (
			$ceMW &&
			$mwPermissions->exists( $group ) &&
			!in_array( $group, $config->get( ConfigNames::PermissionsPermanentGroups ) )
		) {
			$formDescriptor['delete-checkbox'] = [
				'type' => 'check',
				'label-message' => 'permissions-delete-checkbox',
				'default' => false,
				'section' => 'delete',
			];
		}

		return $formDescriptor;
	}

	public static function submissionHandler(
		array $formData,
		HTMLForm $form,
		string $module,
		string $dbname,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		IDatabase $dbw,
		Config $config,
		string $special,
		string $filtered
	): array {
		switch ( $module ) {
			case 'core':
				$mwReturn = self::submissionCore( $formData, $dbname, $context, $remoteWiki, $dbw, $config );
				break;
			case 'extensions':
				$mwReturn = self::submissionExtensions( $formData, $dbname, $config );
				break;
			case 'settings':
				$mwReturn = self::submissionSettings( $formData, $dbname, $filtered, $context, $remoteWiki, $config );
				break;
			case 'namespaces':
				$mwReturn = self::submissionNamespaces( $formData, $dbname, $special, $config );
				$form->getRequest()->getSession()->remove( 'create' );
				break;
			case 'permissions':
				$mwReturn = self::submissionPermissions( $formData, $dbname, $special, $config );
				break;
			default:
				throw new InvalidArgumentException( "$module not recognized" );
		}

		if ( $mwReturn->hasChanges() ) {
			$mwReturn->commit();

			if ( $module !== 'permissions' ) {
				$mwReturn->addLogParam( '4::wiki', $dbname );
			}

			$mwLogEntry = new ManualLogEntry( 'managewiki', $mwReturn->getLogAction() );
			$mwLogEntry->setPerformer( $context->getUser() );
			$mwLogEntry->setTarget( $form->getTitle() );
			$mwLogEntry->setComment( $formData['reason'] );
			$mwLogEntry->setParameters( $mwReturn->getLogParams() );
			$mwLogID = $mwLogEntry->insert();
			$mwLogEntry->publish( $mwLogID );

			if ( $module === 'permissions' || $module === 'namespaces' ) {
				if ( $mwReturn->isDeleting( $special ) ) {
					$context->getRequest()->getSession()->set( 'manageWikiSaveSuccess', 1 );
					$context->getOutput()->redirect(
						SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL()
					);
				}
			}
		} else {
			return [ [ 'managewiki-changes-none' => null ] ];
		}

		return $mwReturn->getErrors();
	}

	private static function submissionCore(
		array $formData,
		string $dbname,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		IDatabase $dbw,
		Config $config
	): RemoteWikiFactory {
		$mwActions = [
			'delete',
			'lock',
			'undelete',
			'unlock',
		];

		foreach ( $mwActions as $mwAction ) {
			if ( $formData[$mwAction] ?? false ) {
				$remoteWiki->$mwAction();
				return $remoteWiki;
			}
		}

		if ( $config->get( 'CreateWikiUsePrivateWikis' ) && $remoteWiki->isPrivate() !== $formData['private'] ) {
			$formData['private'] ? $remoteWiki->markPrivate() : $remoteWiki->markPublic();
		}

		if ( $config->get( 'CreateWikiUseExperimental' ) && $remoteWiki->isExperimental() !== $formData['experimental'] ) {
			$formData['experimental'] ? $remoteWiki->markExperimental() : $remoteWiki->unMarkExperimental();
		}

		if ( $config->get( 'CreateWikiUseClosedWikis' ) ) {
			$closed = $remoteWiki->isClosed();
			$newClosed = $formData['closed'];

			if ( $newClosed && $closed !== $newClosed ) {
				$remoteWiki->markClosed();
			} elseif ( !$newClosed && $closed !== $newClosed ) {
				$remoteWiki->markActive();
			}
		}

		if ( $config->get( 'CreateWikiUseInactiveWikis' ) ) {
			$newInactive = $formData['inactive'];
			$inactive = $remoteWiki->isInactive();
			$newInactiveExempt = $formData['inactive-exempt'];

			if ( $newInactive !== $inactive ) {
				$newInactive ? $remoteWiki->markInactive() : $remoteWiki->markActive();
			}

			$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
			if ( $permissionManager->userHasRight( $context->getUser(), 'managewiki-restricted' ) ) {
				if ( $newInactiveExempt !== $remoteWiki->isInactiveExempt() ) {
					if ( $newInactiveExempt ) {
						$remoteWiki->markExempt();
					} else {
						$remoteWiki->unExempt();
					}
				}

				$newInactiveExemptReason = $formData['inactive-exempt-reason'] ?? false;
				if ( $newInactiveExemptReason && $newInactiveExemptReason !== $remoteWiki->getInactiveExemptReason() ) {
					$remoteWiki->setInactiveExemptReason( $formData['inactive-exempt-reason'] );
				}
			}
		}

		if ( $config->get( 'CreateWikiCategories' ) && isset( $formData['category'] ) && $formData['category'] !== $remoteWiki->getCategory() ) {
			$remoteWiki->setCategory( $formData['category'] );
		}

		if ( $config->get( ConfigNames::UseCustomDomains ) && $formData['server'] !== $remoteWiki->getServerName() ) {
			$remoteWiki->setServerName( $formData['server'] );
		}

		if ( $formData['sitename'] !== $remoteWiki->getSitename() ) {
			$remoteWiki->setSitename( $formData['sitename'] );
		}

		if ( $formData['language'] !== $remoteWiki->getLanguage() ) {
			$remoteWiki->setLanguage( $formData['language'] );
		}

		if ( $config->get( 'CreateWikiDatabaseClusters' ) && $formData['dbcluster'] !== $remoteWiki->getDBCluster() ) {
			$remoteWiki->setDBCluster( $formData['dbcluster'] );
		}

		$hookRunner = MediaWikiServices::getInstance()->get( 'ManageWikiHookRunner' );
		$hookRunner->onManageWikiCoreFormSubmission(
			$context, $dbw, $remoteWiki, $dbname, $formData
		);

		return $remoteWiki;
	}

	private static function submissionExtensions(
		array $formData,
		string $dbname,
		Config $config
	): ManageWikiExtensions {
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$newExtList = [];

		foreach ( $config->get( ConfigNames::Extensions ) as $name => $ext ) {
			if ( $formData["ext-$name"] ) {
				$newExtList[] = $name;
			}
		}

		$mwExtensions->overwriteAll( $newExtList );
		return $mwExtensions;
	}

	private static function submissionSettings(
		array $formData,
		string $dbname,
		string $filtered,
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		Config $config
	): ManageWikiSettings {
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();

		$mwSettings = new ManageWikiSettings( $dbname );
		$settingsList = $mwSettings->list( var: null );

		$settingsArray = [];

		foreach ( $config->get( ConfigNames::Settings ) as $name => $set ) {
			// No need to do anything if setting does not 'exist'
			if ( !isset( $formData["set-$name"] ) ) {
				continue;
			}

			$current = $settingsList[$name] ?? $set['overridedefault'];
			if ( isset( $set['associativeKey'] ) ) {
				$current = $settingsList[$name][ $set['associativeKey'] ] ?? $set['overridedefault'][ $set['associativeKey'] ];
			}

			$mwAllowed = $set['requires'] ? ManageWikiRequirements::process( $set['requires'], $extList, false, $remoteWiki ) : true;
			$type = $set['type'];

			$value = $formData["set-$name"];

			switch ( $type ) {
				case 'integers':
					$value = array_column( $value, 'value' );
					$value = array_filter( $value );
					$value = array_map( 'intval', $value );
					break;
				case 'list-multi-bool':
					$setValue = [];
					foreach ( $set['allopts'] as $opt ) {
						$setValue[$opt] = in_array( $opt, $value );
					}

					$value = $setValue;
					break;
				case 'matrix':
					$current = ManageWiki::handleMatrix( $current, 'php' );
					$value = ManageWiki::handleMatrix( $value, 'phparray' );
					break;
				case 'text':
					if ( !$value ) {
						$value = $set['overridedefault'];
					}
					break;
				case 'texts':
					$value = array_column( $value, 'value' );
					$value = array_filter( $value );
					break;
				case 'users':
				case 'wikipages':
					$value = $value ? explode( "\n", $value ) : [];
					break;
			}

			if ( !$mwAllowed ) {
				$value = $current;
			}

			if ( isset( $set['associativeKey'] ) ) {
				$settingsArray[$name] = $set['overridedefault'];
				$settingsArray[$name][ $set['associativeKey'] ] = $value;
			} else {
				$settingsArray[$name] = $value;
			}
		}

		$manageWikiSettings = $config->get( ConfigNames::Settings );
		$filteredList = array_filter( $manageWikiSettings, static fn ( array $value ): bool =>
			$value['from'] === strtolower( $filtered ) && (
				in_array( $value['from'], $extList ) ||
				( $value['global'] ?? false )
			)
		);

		$remove = !( count( array_diff_assoc( $filteredList, array_keys( $manageWikiSettings ) ) ) > 0 );

		$mwSettings->overwriteAll( $settingsArray, $remove );
		return $mwSettings;
	}

	private static function submissionNamespaces(
		array $formData,
		string $dbname,
		string $special,
		Config $config
	): ManageWikiNamespaces {
		$mwNamespaces = new ManageWikiNamespaces( $dbname );

		if ( $formData['delete-checkbox'] ) {
			$mwNamespaces->remove( (int)$special, $formData['delete-migrate-to'] );
			$mwNamespaces->remove( (int)$special + 1, $formData['delete-migrate-to'] + 1 );
			return $mwNamespaces;
		}

		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1,
		];

		foreach ( $nsID as $name => $id ) {
			$namespaceName = str_replace( [ ' ', ':' ], '_', $formData["namespace-$name"] );

			$additionalBuilt = [];

			foreach ( $config->get( ConfigNames::NamespacesAdditional ) as $key => $a ) {
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
				'aliases' => array_filter( array_column( $formData["aliases-$name"], 'value' ) ),
				'additional' => $additionalBuilt,
			];

			$mwNamespaces->modify( $id, $build );
		}

		return $mwNamespaces;
	}

	private static function submissionPermissions(
		array $formData,
		string $dbname,
		string $group,
		Config $config
	): ManageWikiPermissions {
		$mwPermissions = new ManageWikiPermissions( $dbname );
		$groupData = $mwPermissions->list( $group );

		$assignedPermissions = $groupData['permissions'] ?? [];

		$disallowed = array_merge(
			$config->get( ConfigNames::PermissionsDisallowedRights )[$group] ?? [],
			$config->get( ConfigNames::PermissionsDisallowedRights )['any']
		);

		$allPermissions = MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions();
		$assignablePerms = array_diff( $allPermissions, $disallowed );

		$extraAssigned = array_filter(
			$assignedPermissions,
			static fn ( string $perm ): bool => !in_array( $perm, $assignablePerms, true ) &&
				!in_array( $perm, $disallowed, true )
		);

		$assignablePerms = array_unique( array_merge( $assignablePerms, $extraAssigned ) );
		$isRemovable = !in_array( $group, $config->get( ConfigNames::PermissionsPermanentGroups ), true );

		// Early escape for deletion
		if ( $isRemovable && ( $formData['delete-checkbox'] ?? false ) ) {
			$mwPermissions->remove( $group );
			return $mwPermissions;
		}

		$permData = [];
		$addedPerms = [];
		$removedPerms = [];

		foreach ( $assignablePerms as $perm ) {
			if ( $formData["right-$perm"] && array_search( $perm, $groupData['permissions'], true ) === false ) {
				$addedPerms[] = $perm;
				continue;
			}

			if ( !$formData["right-$perm"] && array_search( $perm, $groupData['permissions'], true ) !== false ) {
				$removedPerms[] = $perm;
			}
		}

		// Add permission changes to permData
		$permData['permissions'] = [
			'add' => $addedPerms,
			'remove' => $removedPerms,
		];

		$newMatrix = ManageWiki::handleMatrix( $formData['group-matrix'], 'phparray' );

		$matrixNew = [
			'addgroups' => array_diff( $newMatrix['wgAddGroups'] ?? [], $groupData['addgroups'] ),
			'removegroups' => array_diff( $newMatrix['wgRemoveGroups'] ?? [], $groupData['removegroups'] ),
			'addself' => array_diff( $newMatrix['wgGroupsAddToSelf'] ?? [], $groupData['addself'] ),
			'removeself' => array_diff( $newMatrix['wgGroupsRemoveFromSelf'] ?? [], $groupData['removeself'] ),
		];

		$matrixOld = [
			'addgroups' => array_diff( $groupData['addgroups'], $newMatrix['wgAddGroups'] ?? [] ),
			'removegroups' => array_diff( $groupData['removegroups'], $newMatrix['wgRemoveGroups'] ?? [] ),
			'addself' => array_diff( $groupData['addself'], $newMatrix['wgGroupsAddToSelf'] ?? [] ),
			'removeself' => array_diff( $groupData['removeself'], $newMatrix['wgGroupsRemoveFromSelf'] ?? [] ),
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
		$aPBuild = $aE ? [ $formData['conds'] ] : [];

		if ( count( $aPBuild ) !== 0 ) {
			$loopBuild = [
				'once' => 'once',
				'editcount' => [ APCOND_EDITCOUNT, (int)$formData['editcount'] ],
				'age' => [ APCOND_AGE, (int)$formData['age'] * 86400 ],
				'emailconfirmed' => APCOND_EMAILCONFIRMED,
				'blocked' => APCOND_BLOCKED,
				'bot' => APCOND_ISBOT,
				'groups' => array_merge( [ APCOND_INGROUPS ], $formData['groups'] ),
			];

			foreach ( $loopBuild as $type => $value ) {
				if ( $formData[$type] ) {
					$aPBuild[] = $value;
				}
			}
		}

		$permData['autopromote'] = count( $aPBuild ) > 1 ? $aPBuild : null;

		$allPermissionsRemoved = count( $permData['permissions']['remove'] ?? [] ) > 0 &&
			count( $permData['permissions']['add'] ?? [] ) === 0 &&
			count( $groupData['permissions'] ?? [] ) === count( $permData['permissions']['remove'] );

		if ( $isRemovable && $allPermissionsRemoved ) {
			$mwPermissions->remove( $group );
		} else {
			$mwPermissions->modify( $group, $permData );
		}

		return $mwPermissions;
	}
}
