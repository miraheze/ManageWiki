<?php

namespace Miraheze\ManageWiki\FormFactory;

use ErrorPageError;
use InvalidArgumentException;
use ManualLogEntry;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
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
use Wikimedia\ObjectCache\WANObjectCache;
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
				$formDescriptor = self::buildDescriptorCore(
					$dbname, $ceMW, $context, $remoteWiki, $config
				);
				break;
			case 'extensions':
				$formDescriptor = self::buildDescriptorExtensions(
					$dbname, $ceMW, $context, $config
				);
				break;
			case 'settings':
				$formDescriptor = self::buildDescriptorSettings(
					$dbname, $ceMW, $context, $config, $filtered
				);
				break;
			case 'namespaces':
				$formDescriptor = self::buildDescriptorNamespaces(
					$dbname, $ceMW, $context, $special, $config
				);
				break;
			case 'permissions':
				$formDescriptor = self::buildDescriptorPermissions(
					$dbname, $ceMW, $context, $special, $config
				);
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
				// https://github.com/miraheze/CreateWiki/blob/20c2f47/sql/cw_wikis.sql#L3
				'maxlength' => 128,
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
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
			],
			'inactive-exempt-reason' => [
				'if' => $config->get( 'CreateWikiUseInactiveWikis' ) &&
					$config->get( ConfigNames::InactiveExemptReasonOptions ),
				'hide-if' => [ '!==', 'inactive-exempt', '1' ],
				'type' => 'selectorother',
				'default' => $remoteWiki->getInactiveExemptReason(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
				'options' => $config->get( ConfigNames::InactiveExemptReasonOptions ),
			],
			'server' => [
				'if' => $config->get( ConfigNames::UseCustomDomains ),
				'type' => 'text',
				'default' => $remoteWiki->getServerName(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
			],
			'experimental' => [
				'if' => $config->get( 'CreateWikiUseExperimental' ),
				'type' => 'check',
				'default' => $remoteWiki->isExperimental(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
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
			$clusterOptions = array_merge(
				$config->get( 'CreateWikiDatabaseClusters' ),
				$config->get( ConfigNames::DatabaseClustersInactive )
			);

			$formDescriptor['dbcluster'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-dbcluster',
				'options' => $clusterOptions,
				'default' => $remoteWiki->getDBCluster(),
				'disabled' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
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
		Config $config
	): array {
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();

		$manageWikiSettings = $config->get( ConfigNames::Settings );

		$objectCacheFactory = MediaWikiServices::getInstance()->getObjectCacheFactory();
		$cache = $objectCacheFactory->getLocalClusterInstance();

		$credits = $cache->getWithSetCallback(
			$cache->makeGlobalKey( 'ManageWikiExtensions', 'credits' ),
			WANObjectCache::TTL_DAY,
			static function () use ( $config ): array {
				$queue = array_fill_keys( array_merge(
					glob( $config->get( MainConfigNames::ExtensionDirectory ) . '/*/extension*.json' ),
					glob( $config->get( MainConfigNames::StyleDirectory ) . '/*/skin.json' )
				), true );

				$processor = new ExtensionProcessor();

				foreach ( $queue as $path => $_ ) {
					$json = file_get_contents( $path );
					$info = json_decode( $json, true );
					$version = $info['manifest_version'] ?? 2;

					$processor->extractInfo( $path, $info, $version );
				}

				$data = $processor->getExtractedInfo();
				return $data['credits'];
			}
		);

		$formDescriptor = [];
		foreach ( $config->get( ConfigNames::Extensions ) as $name => $ext ) {
			$filteredList = array_filter(
				$manageWikiSettings,
				static fn ( array $value ): bool => $value['from'] === $name
			);

			$hasSettings = count( array_diff_assoc( $filteredList, array_keys( $manageWikiSettings ) ) ) > 0;

			$disableIf = [];
			if (
				// Don't want to disable fields for extensions already enabled
				// otherwise it makes disabling them more complicated.
				!in_array( $name, $extList, true ) && (
					isset( $ext['requires']['extensions'] ) ||
					$ext['conflicts']
				)
			) {
				$disableIf = self::buildDisableIf(
					$ext['requires']['extensions'] ?? [],
					$ext['conflicts'] ?: ''
				);
			}

			$help = [];
			$mwRequirements = true;
			if ( $ext['requires'] ) {
				$mwRequirements = ManageWikiRequirements::process(
					// Don't check for extension requirements as we don't want
					// to disable the field, we use disable-if for that.
					array_diff_key( $ext['requires'], [ 'extensions' => true ] ),
					$extList
				);

				$help[] = self::buildRequires( $context, $ext['requires'] ) . "\n";
			}

			if ( $ext['conflicts'] ) {
				$help[] = $context->msg( 'managewiki-conflicts', $ext['conflicts'] )->parse() . "\n";
			}

			$descriptionmsg = array_column( $credits, 'descriptionmsg', 'name' )[ $ext['name'] ] ?? false;
			$description = array_column( $credits, 'description', 'name' )[ $ext['name'] ] ?? null;

			$namemsg = array_column( $credits, 'namemsg', 'name' )[ $ext['name'] ] ?? false;
			$extname = array_column( $credits, 'name', 'name' )[ $ext['name'] ] ?? null;

			$extDescription = null;
			if ( !empty( $ext['description'] ) ) {
				$msg = $context->msg( $ext['description'] );
				$extDescription = $msg->exists() ? $msg->parse() : $ext['description'];
			}

			$extDisplayName = null;
			if ( !empty( $ext['displayname'] ) ) {
				$msg = $context->msg( $ext['displayname'] );
				$extDisplayName = $msg->exists() ? $msg->parse() : $ext['displayname'];
			}

			$descriptionFallback = null;
			if ( $descriptionmsg ) {
				$msg = $context->msg( $descriptionmsg );
				$descriptionFallback = $descriptionmsg;
				if ( $msg->exists() ) {
					$parsed = $msg->parse();
					// Remove and only bold links that don't exist. Likely for extensions that
					// have not been enabled. We don't want to display redlinks for them.
					$parsed = preg_replace(
						'#<a[^>]+class="[^"]*\bnew\b[^"]*"[^>]*>(.*?)</a>#i',
						'<b>$1</b>', $parsed
					);

					$descriptionFallback = $parsed;
				}
			}

			$help[] = $extDescription ?? $descriptionFallback ?? $description;

			if ( $ext['help'] ?? false ) {
				$rawMessage = new RawMessage( $ext['help'] );
				$help[] = "\n" . $rawMessage->parse();
			}

			if ( $hasSettings && in_array( $name, $extList, true ) ) {
				$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
				$help[] = "\n" . $linkRenderer->makeExternalLink(
					SpecialPage::getTitleFor( 'ManageWiki', "settings/$name" )->getFullURL(),
					$context->msg( 'managewiki-extension-settings' ),
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
				'default' => in_array( $name, $extList, true ),
				'disabled' => $ceMW ? !$mwRequirements : true,
				'disable-if' => $disableIf,
				'help' => nl2br( implode( ' ', $help ) ),
				'section' => $ext['section'],
			];
		}

		return $formDescriptor;
	}

	private static function buildDescriptorSettings(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
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
				in_array( $value['from'], $extList, true ) ||
				( $value['global'] ?? false )
			)
		);

		$formDescriptor = [];
		$filteredSettings = array_diff_assoc( $filteredList, array_keys( $manageWikiSettings ) ) ?: $manageWikiSettings;

		foreach ( $filteredSettings as $name => $set ) {
			if ( !isset( $set['requires'] ) ) {
				$logger = LoggerFactory::getInstance( 'ManageWiki' );
				$logger->error( '\'requires\' is not set in {config} for {var}', [
					'config' => ConfigNames::Settings,
					'var' => $name,
				] );
				$mwRequirements = true;
			} else {
				$mwRequirements = $set['requires'] ?
					ManageWikiRequirements::process( $set['requires'], $extList ) : true;
			}

			$hasVisibilityRequirement = isset( $set['requires']['visibility'] );
			$isGlobal = $set['global'] ?? false;
			$isInExtList = in_array( $set['from'], $extList, true );

			$add = ( $hasVisibilityRequirement ? $mwRequirements : true ) && ( $isGlobal || $isInExtList );
			$disabled = $ceMW ? !$mwRequirements : true;

			$msgName = $context->msg( "managewiki-setting-$name-name" );
			$msgHelp = $context->msg( "managewiki-setting-$name-help" );

			if ( $add ) {
				$value = $settingsList[$name] ?? null;
				if ( isset( $set['associativeKey'] ) ) {
					$value = $settingsList[$name][ $set['associativeKey'] ] ??
						$set['overridedefault'][ $set['associativeKey'] ];
				}

				$configs = ManageWikiTypes::process(
					config: $config,
					disabled: $disabled,
					groupList: $groupList,
					module: 'settings',
					options: $set,
					value: $value,
					name: $name,
					overrideDefault: false,
					type: ''
				);

				$help = [];
				if ( $set['requires'] ) {
					$help[] = self::buildRequires( $context, $set['requires'] ) . "\n";
				}

				$rawMessage = new RawMessage( $set['help'] );
				$help[] = $msgHelp->exists() ? $msgHelp->escaped() : $rawMessage->parse();

				// Hack to prevent "implicit submission". See T275588 for more
				if ( ( $configs['type'] ?? '' ) === 'cloner' ) {
					$formDescriptor["fake-submit-$name"] = [
						'type' => 'submit',
						'disabled' => true,
						'section' => $set['section'],
						'cssclass' => 'managewiki-fakesubmit',
					];
				}

				$varName = $context->msg( 'parentheses', "\${$name}" );
				if ( isset( $set['associativeKey'] ) ) {
					$varName = $context->msg( 'parentheses',
						"\${$name}['{$set['associativeKey']}']"
					);
				}

				$formDescriptor["set-$name"] = [
					'label-message' => [
						'managewiki-setting-label',
						$msgName->exists() ? $msgName->text() : $set['name'],
						$varName,
					],
					'disabled' => $disabled,
					'help' => nl2br( implode( ' ', $help ) ),
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
		Config $config
	): array {
		$mwNamespaces = new ManageWikiNamespaces( $dbname );
		$mwExtensions = new ManageWikiExtensions( $dbname );
		$extList = $mwExtensions->list();

		$namespaceID = (int)$special;
		if ( $namespaceID < 0 || $mwNamespaces->isTalk( $namespaceID ) ) {
			throw new ErrorPageError( 'managewiki-unavailable', 'managewiki-ns-invalidid' );
		}

		$formDescriptor = [];
		$nsID = [];

		$nsID['namespace'] = $namespaceID;

		if ( !$mwNamespaces->exists( $nsID['namespace'] ) ) {
			$context->getOutput()->addBodyClasses(
				[ 'ext-managewiki-create-namespace' ]
			);
		}

		if (
			$mwNamespaces->list( $namespaceID + 1 )['name'] ||
			!$mwNamespaces->list( $namespaceID )['name']
		) {
			$nsID['namespacetalk'] = $namespaceID + 1;
		}

		$session = $context->getRequest()->getSession();

		foreach ( $nsID as $name => $id ) {
			$namespaceData = $mwNamespaces->list( $id );

			$create = $session->get( 'create' );
			if ( $session->get( 'create' ) && $mwNamespaces->isTalk( $id ) ) {
				$create .= ' talk';
			}

			[ $namespaceVar, $defaultName ] = match ( $id ) {
				NS_PROJECT => [
					$context->msg( 'parentheses',
						self::getConfigVar( MainConfigNames::MetaNamespace )
					)->text(),
					$config->get( MainConfigNames::MetaNamespace ),
				],
				NS_PROJECT_TALK => [
					$context->msg( 'parentheses',
						self::getConfigVar( MainConfigNames::MetaNamespaceTalk )
					)->text(),
					str_replace(
						$config->get( MainConfigNames::MetaNamespace ),
						'$1',
						$config->get( MainConfigNames::MetaNamespaceTalk )
					),
				],
				default => [
					'',
					$namespaceData['name'] ?: $create,
				],
			};

			if ( !$namespaceData['core'] ) {
				// Core namespaces are not set with ExtraNamespaces
				$namespaceVar = $context->msg( 'parentheses',
					self::getConfigVar( MainConfigNames::ExtraNamespaces )
				)->text();
			}

			$canEditName = !$namespaceData['core'] ||
				$id === NS_PROJECT || $id === NS_PROJECT_TALK;

			$formDescriptor += [
				"namespace-$name" => [
					'type' => 'text',
					'label-message' => [ "managewiki-namespaces-$name-label", $namespaceVar ],
					'default' => $defaultName,
					// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_namespaces.sql#L4
					'maxlength' => 128,
					'disabled' => !$canEditName || !$ceMW,
					'required' => true,
					'section' => $name,
				],
				"content-$name" => [
					'type' => 'check',
					'label-message' => [
						'namespaces-content',
						self::getConfigVar( MainConfigNames::ContentNamespaces ),
					],
					'default' => $namespaceData['content'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"subpages-$name" => [
					'type' => 'check',
					'label-message' => [
						'namespaces-subpages',
						self::getConfigVar( MainConfigNames::NamespacesWithSubpages ),
					],
					'default' => $namespaceData['subpages'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"search-$name" => [
					'type' => 'check',
					'label-message' => [
						'namespaces-search',
						self::getConfigVar( MainConfigNames::NamespacesToBeSearchedDefault ),
					],
					'default' => $namespaceData['searchable'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"contentmodel-$name" => [
					'label-message' => [
						'namespaces-contentmodel',
						self::getConfigVar( MainConfigNames::NamespaceContentModels ),
					],
					'cssclass' => 'managewiki-infuse',
					'disabled' => !$ceMW,
					'section' => $name,
				] + ManageWikiTypes::process(
					config: $config,
					disabled: false,
					groupList: [],
					module: 'namespaces',
					options: [],
					value: $namespaceData['contentmodel'],
					name: '',
					overrideDefault: false,
					type: 'contentmodel'
				),
				"protection-$name" => [
					'type' => 'combobox',
					'label-message' => [
						'namespaces-protection',
						self::getConfigVar( MainConfigNames::NamespaceProtection ),
					],
					'cssclass' => 'managewiki-infuse',
					'default' => $namespaceData['protection'],
					'options-messages' => [
						'rightsnone' => '',
						'right-editinterface' => 'editinterface',
						'right-editsemiprotected' => 'editsemiprotected',
						'right-editprotected' => 'editprotected',
					],
					'options-messages-parse' => true,
					'disabled' => !$ceMW,
					'section' => $name,
				],
			];

			foreach ( $config->get( ConfigNames::NamespacesAdditional ) as $key => $a ) {
				$mwRequirements = $a['requires'] ?
					ManageWikiRequirements::process( $a['requires'], $extList ) : true;

				$hasVisibilityRequirement = isset( $a['requires']['visibility'] );
				$isFromMediaWiki = $a['from'] === 'mediawiki';
				$isInExtList = in_array( $a['from'], $extList, true );

				$add = ( $hasVisibilityRequirement ? $mwRequirements : true ) && ( $isFromMediaWiki || $isInExtList );
				$disabled = $ceMW ? !$mwRequirements : true;

				$msgName = $context->msg( "managewiki-namespaces-$key-name" );
				$msgHelp = $context->msg( "managewiki-namespaces-$key-help" );

				if (
					$add &&
					(
						( $a['main'] && $name === 'namespace' ) ||
						( $a['talk'] && $name === 'namespacetalk' )
					) &&
					!in_array( $id, (array)( $a['excluded'] ?? [] ), true ) &&
					in_array( $id, (array)( $a['only'] ?? [ $id ] ), true )
				) {
					if ( is_array( $a['overridedefault'] ) ) {
						$a['overridedefault'] = $a['overridedefault'][$id] ?? $a['overridedefault']['default'];
					}

					$configs = ManageWikiTypes::process(
						config: $config,
						disabled: $disabled,
						groupList: [],
						module: 'namespaces',
						options: $a,
						value: $namespaceData['additional'][$key] ?? null,
						name: '',
						overrideDefault: $a['overridedefault'],
						type: $a['type']
					);

					$help = [];
					if ( $a['requires'] ) {
						$help[] = self::buildRequires( $context, $a['requires'] ) . "\n";
					}

					$rawMessage = new RawMessage( $a['help'] );
					$help[] = $msgHelp->exists() ? $msgHelp->escaped() : $rawMessage->parse();

					$formDescriptor["$key-$name"] = [
						'label-message' => [
							'managewiki-setting-label',
							$msgName->exists() ? $msgName->text() : $a['name'],
							$context->msg( 'parentheses', "\${$key}" ),
						],
						'help' => nl2br( implode( ' ', $help ) ),
						'cssclass' => 'managewiki-infuse',
						'disabled' => $disabled,
						'section' => $name,
					] + $configs;
				}
			}

			// Hack to prevent "implicit submission". See T275588 for more
			$formDescriptor["fake-submit-aliases-$name"] = [
				'type' => 'submit',
				'disabled' => true,
				'section' => $name,
				'cssclass' => 'managewiki-fakesubmit',
			];

			$formDescriptor["aliases-$name"] = [
				'label-message' => [
					'namespaces-aliases',
					self::getConfigVar( MainConfigNames::NamespaceAliases ),
				],
				'cssclass' => 'managewiki-infuse',
				'disabled' => !$ceMW,
				'section' => $name,
			] + ManageWikiTypes::process(
				config: $config,
				disabled: false,
				groupList: [],
				module: 'namespaces',
				options: [],
				value: $namespaceData['aliases'],
				name: '',
				overrideDefault: [],
				type: 'texts'
			);
		}

		if ( $ceMW && !$mwNamespaces->list( $namespaceID )['core'] ) {
			$craftedNamespaces = [];
			$canDelete = $mwNamespaces->exists( $namespaceID );

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

		$context->getRequest()->getSession()->remove( 'create' );
		return $formDescriptor;
	}

	private static function buildDescriptorPermissions(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		string $group,
		Config $config
	): array {
		if ( in_array( $group, $config->get( ConfigNames::PermissionsDisallowedGroups ), true ) ) {
			$ceMW = false;
		}

		$mwPermissions = new ManageWikiPermissions( $dbname );
		$groupData = $mwPermissions->list( $group );

		$matrixConstruct = [
			self::getConfigName( MainConfigNames::AddGroups ) => $groupData['addgroups'],
			self::getConfigName( MainConfigNames::RemoveGroups ) => $groupData['removegroups'],
			self::getConfigName( MainConfigNames::GroupsAddToSelf ) => $groupData['addself'],
			self::getConfigName( MainConfigNames::GroupsRemoveFromSelf ) => $groupData['removeself'],
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
		];

		if (
			$ceMW &&
			$mwPermissions->exists( $group ) &&
			!in_array( $group, $config->get( ConfigNames::PermissionsPermanentGroups ), true )
		) {
			$formDescriptor['delete-checkbox'] = [
				'type' => 'check',
				'label-message' => 'permissions-delete-checkbox',
				'default' => false,
				'section' => 'advanced',
			];
		}

		foreach ( $groupData['allPermissions'] as $perm ) {
			$assigned = in_array( $perm, $groupData['assignedPermissions'], true );
			$formDescriptor["right-$perm"] = [
				'type' => 'check',
				'label' => $perm,
				'help' => htmlspecialchars( User::getRightDescription( $perm ) ),
				'section' => $assigned ? 'assigned' : 'unassigned',
				'default' => $assigned,
				'disabled' => !$ceMW,
			];
		}

		$rowsBuilt = [];
		$language = $context->getLanguage();
		foreach ( $groupData['allGroups'] as $groupName ) {
			$lowerCaseGroupName = $language->lc( $groupName );
			$rowsBuilt[htmlspecialchars( $language->getGroupName( $lowerCaseGroupName ) )] = $lowerCaseGroupName;
		}

		$formDescriptor['group-matrix'] = [
			'type' => 'checkmatrix',
			'columns' => [
				$context->msg( 'managewiki-permissions-addall' )->escaped() =>
					self::getConfigName( MainConfigNames::AddGroups ),
				$context->msg( 'managewiki-permissions-removeall' )->escaped() =>
					self::getConfigName( MainConfigNames::RemoveGroups ),
				$context->msg( 'managewiki-permissions-addself' )->escaped() =>
					self::getConfigName( MainConfigNames::GroupsAddToSelf ),
				$context->msg( 'managewiki-permissions-removeself' )->escaped() =>
					self::getConfigName( MainConfigNames::GroupsRemoveFromSelf ),
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
			'autopromote' => [
				'type' => 'info',
				'default' => $context->msg( 'managewiki-permissions-autopromote' )->text(),
				'section' => 'advanced/autopromote',
			],
			'enable' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-enable',
				'default' => $aP !== null,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
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
				'section' => 'advanced/autopromote',
			],
			'once' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-once',
				'default' => array_search( 'once', (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'hide-if' => [ '!==', 'enable', '1' ],
				'section' => 'advanced/autopromote',
			],
			'editcount' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-editcount',
				'hide-if' => [ '!==', 'enable', '1' ],
				'min' => 0,
				'default' => $aPArray[APCOND_EDITCOUNT] ?? 0,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'age' => [
				'type' => 'int',
				'label-message' => 'managewiki-permissions-autopromote-age',
				'hide-if' => [ '!==', 'enable', '1' ],
				'min' => 0,
				'default' => isset( $aPArray[APCOND_AGE] ) ? $aPArray[APCOND_AGE] / 86400 : 0,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'emailconfirmed' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-email',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_EMAILCONFIRMED, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'blocked' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-blocked',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_BLOCKED, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'bot' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-bot',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => array_search( APCOND_ISBOT, (array)$aP, true ) !== false,
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'groups' => [
				'type' => 'multiselect',
				'label-message' => 'managewiki-permissions-autopromote-groups',
				'options' => $rowsBuilt,
				'hide-if' => [ 'OR', [ '!==', 'enable', '1' ], [ '===', 'conds', '|' ] ],
				'default' => $aPArray[APCOND_INGROUPS] ?? [],
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
		];

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
				$mwReturn = self::submissionSettings( $formData, $dbname, $filtered, $context, $config );
				break;
			case 'namespaces':
				$mwReturn = self::submissionNamespaces( $formData, $dbname, $special, $config );
				break;
			case 'permissions':
				$mwReturn = self::submissionPermissions( $formData, $dbname, $special, $config );
				break;
			default:
				throw new InvalidArgumentException( "$module not recognized" );
		}

		/**
		 * We check for errors in multiple places here because modules may add them at different stages.
		 * Some errors can be set even when there are no changes, such as validation failures.
		 * Others might occur during commit(), or after commit logic that reveals issues late.
		 * This approach ensures all potential errors—regardless of when they're added—are caught.
		 */

		if ( $mwReturn->hasChanges() ) {
			$mwReturn->commit();
			if ( $mwReturn->getErrors() ) {
				return $mwReturn->getErrors();
			}

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
			return $mwReturn->getErrors() ?:
				[ [ 'managewiki-changes-none' => null ] ];
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

		if ( $config->get( 'CreateWikiUseExperimental' ) &&
			$remoteWiki->isExperimental() !== $formData['experimental']
		   ) {
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

			if ( $context->getAuthority()->isAllowed( 'managewiki-restricted' ) ) {
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

		if ( $config->get( 'CreateWikiCategories' ) && $formData['category'] !== $remoteWiki->getCategory() ) {
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
				$current = $settingsList[$name][ $set['associativeKey'] ] ??
					$set['overridedefault'][ $set['associativeKey'] ];
			}

			$mwAllowed = $set['requires'] ?
				ManageWikiRequirements::process( $set['requires'], $extList ) : true;

			$type = $set['type'];
			$value = $formData["set-$name"];

			switch ( $type ) {
				case 'float':
					$value = (float)$value;
					break;
				case 'integer':
					$value = (int)$value;
					break;
				case 'integers':
					$value = array_column( $value, 'value' );
					$value = array_filter( $value );
					$value = array_map( 'intval', $value );
					break;
				case 'list-multi-bool':
					$setValue = [];
					foreach ( $set['allopts'] as $opt ) {
						$setValue[$opt] = in_array( $opt, $value, true );
					}

					$value = $setValue;
					break;
				case 'list-multi-int':
					$value = array_map( 'intval', $value );
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
				in_array( $value['from'], $extList, true ) ||
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
			'addgroups' => array_diff(
				$newMatrix[self::getConfigName( MainConfigNames::AddGroups )] ?? [],
				$groupData['addgroups']
			),
			'removegroups' => array_diff(
				$newMatrix[self::getConfigName( MainConfigNames::RemoveGroups )] ?? [],
				$groupData['removegroups']
			),
			'addself' => array_diff(
				$newMatrix[self::getConfigName( MainConfigNames::GroupsAddToSelf )] ?? [],
				$groupData['addself']
			),
			'removeself' => array_diff(
				$newMatrix[self::getConfigName( MainConfigNames::GroupsRemoveFromSelf )] ?? [],
				$groupData['removeself']
			),
		];

		$matrixOld = [
			'addgroups' => array_diff(
				$groupData['addgroups'],
				$newMatrix[self::getConfigName( MainConfigNames::AddGroups )] ?? []
			),
			'removegroups' => array_diff(
				$groupData['removegroups'],
				$newMatrix[self::getConfigName( MainConfigNames::RemoveGroups )] ?? []
			),
			'addself' => array_diff(
				$groupData['addself'],
				$newMatrix[self::getConfigName( MainConfigNames::GroupsAddToSelf )] ?? []
			),
			'removeself' => array_diff(
				$groupData['removeself'],
				$newMatrix[self::getConfigName( MainConfigNames::GroupsRemoveFromSelf )] ?? []
			),
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

	private static function buildRequires(
		IContextSource $context,
		array $config
	): string {
		$requires = [];
		$language = $context->getLanguage();

		$or = $context->msg( 'managewiki-requires-or' )->text();
		$space = $context->msg( 'word-separator' )->text();
		$colon = $context->msg( 'colon-separator' )->text();

		foreach ( $config as $require => $data ) {
			$flat = [];
			foreach ( (array)$data as $key => $element ) {
				// $key/$colon can be removed here if visibility becomes its own system
				if ( is_array( $element ) ) {
					$flat[] = $context->msg( 'parentheses',
						$space . ( !is_int( $key ) ? $key . $colon : '' ) . implode(
							$space . $language->uc( $or ) . $space,
							$element
						) . $space
					)->text();
					continue;
				}

				$flat[] = ( !is_int( $key ) ? $key . $colon : '' ) . $element;
			}

			$requires[] = $language->ucfirst( $require ) . $colon . $language->commaList( $flat );
		}

		return $context->msg( 'managewiki-requires', $language->listToText( $requires ) )->parse();
	}

	private static function buildDisableIf( array $requires, string $conflict ): array {
		$conditions = [];
		foreach ( $requires as $entry ) {
			if ( is_array( $entry ) ) {
				// OR logic for this group
				$orConditions = [];
				foreach ( $entry as $ext ) {
					$orConditions[] = [ '!==', "ext-$ext", '1' ];
				}

				$conditions[] = count( $orConditions ) === 1 ?
					$orConditions[0] :
					array_merge( [ 'AND' ], $orConditions );
			} else {
				// Simple AND logic
				$conditions[] = [ '!==', "ext-$entry", '1' ];
			}
		}

		$finalCondition = count( $conditions ) === 1 ?
			$conditions[0] :
			array_merge( [ 'OR' ], $conditions );

		if ( $conflict ) {
			$finalCondition = [
				'OR',
				$finalCondition,
				[ '===', "ext-$conflict", '1' ]
			];
		}

		return $finalCondition;
	}

	private static function getConfigName( string $name ): string {
		return "wg$name";
	}

	private static function getConfigVar( string $name ): string {
		return "\$wg$name";
	}
}
