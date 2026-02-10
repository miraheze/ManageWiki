<?php

namespace Miraheze\ManageWiki\FormFactory;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\RawMessage;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Registration\ExtensionProcessor;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\Helpers\Factories\RequirementsFactory;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\PermissionsModule;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\TypesBuilder;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Miraheze\ManageWiki\ICoreModule;
use Miraheze\ManageWiki\Traits\ConfigHelperTrait;
use Miraheze\ManageWiki\Traits\FormHelperTrait;
use Miraheze\ManageWiki\Traits\MatrixHandlerTrait;
use ObjectCacheFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;
use function array_column;
use function array_diff;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function count;
use function explode;
use function glob;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_array;
use function json_encode;
use function mb_strtolower;
use function nl2br;
use function preg_replace;
use function str_replace;
use function trim;
use const APCOND_AGE;
use const APCOND_BLOCKED;
use const APCOND_EDITCOUNT;
use const APCOND_EMAILCONFIRMED;
use const APCOND_INGROUPS;
use const APCOND_ISBOT;
use const MW_VERSION;
use const NS_MAIN;
use const NS_PROJECT;
use const NS_PROJECT_TALK;

class FormFactoryBuilder {

	use ConfigHelperTrait;
	use FormHelperTrait;
	use MatrixHandlerTrait;

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::Extensions,
		ConfigNames::NamespacesAdditional,
		ConfigNames::PermissionsDisallowedGroups,
		ConfigNames::PermissionsDisallowedRights,
		ConfigNames::PermissionsPermanentGroups,
		ConfigNames::Settings,
		MainConfigNames::ExtensionDirectory,
		MainConfigNames::MetaNamespace,
		MainConfigNames::MetaNamespaceTalk,
		MainConfigNames::StyleDirectory,
	];

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly HookRunner $hookRunner,
		private readonly LoggerInterface $logger,
		private readonly RequirementsFactory $requirementsFactory,
		private readonly TypesBuilder $typesBuilder,
		private readonly LinkRenderer $linkRenderer,
		private readonly ObjectCacheFactory $objectCacheFactory,
		private readonly PermissionManager $permissionManager,
		private readonly UserGroupManager $userGroupManager,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function buildDescriptor(
		ModuleFactory $moduleFactory,
		IContextSource $context,
		string $dbname,
		string $module,
		string $special,
		bool $ceMW
	): array {
		return match ( $module ) {
			'core' => $this->buildDescriptorCore( $dbname, $ceMW, $context, $moduleFactory ),
			'extensions' => $this->buildDescriptorExtensions( $dbname, $ceMW, $context, $moduleFactory ),
			'settings' => $this->buildDescriptorSettings( $dbname, $ceMW, $context, $moduleFactory, $special ),
			'namespaces' => $this->buildDescriptorNamespaces( $dbname, $ceMW, $context, $special, $moduleFactory ),
			'permissions' => $this->buildDescriptorPermissions( $dbname, $ceMW, $context, $special, $moduleFactory ),
		};
	}

	private function buildDescriptorCore(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		ModuleFactory $moduleFactory
	): array {
		$formDescriptor = [];
		$formDescriptor['dbname'] = [
			'label-message' => 'managewiki-label-dbname',
			'type' => 'text',
			'default' => $dbname,
			'disabled' => true,
			'section' => 'main',
		];

		$mwCore = $moduleFactory->core( $dbname );
		if ( $ceMW &&
			$this->databaseUtils->isCurrentWikiCentral() &&
			!$this->databaseUtils->isRemoteWikiCentral( $dbname )
		) {
			$mwActions = [
				$mwCore->isDeleted() ? 'undelete' : 'delete',
				$mwCore->isLocked() ? 'unlock' : 'lock',
			];

			foreach ( $mwActions as $mwAction ) {
				if ( !$mwCore->isEnabled( "action-$mwAction" ) ) {
					continue;
				}

				$formDescriptor[$mwAction] = [
					'type' => 'check',
					'label-message' => "managewiki-label-{$mwAction}wiki",
					'default' => false,
					'section' => 'main',
				];
			}
		}

		$canChangeRemotePrivate = $context->getAuthority()->isAllowed( 'managewiki-restricted' ) &&
			$context->getAuthority()->isAllowed( 'managewiki-privacy' );

		$addedModules = [
			'sitename' => [
				'if' => $mwCore->isEnabled( 'sitename' ),
				'type' => 'text',
				'default' => $mwCore->getSitename(),
				// Needs to be the same as the maxlength for the namespace name in mw_namespaces
				// due to MetaNamespace (NS_PROJECT) which will often use the sitename.
				// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_namespaces.sql#L4
				'maxlength' => 128,
				'required' => true,
				'access' => !$ceMW,
			],
			'language' => [
				'if' => $mwCore->isEnabled( 'language' ),
				'type' => 'language',
				'default' => $mwCore->getLanguage(),
				'required' => true,
				'access' => !$ceMW,
			],
			'private' => [
				'if' => $mwCore->isEnabled( 'private-wikis' ),
				'type' => 'check',
				'default' => $mwCore->isPrivate(),
				'access' => $this->databaseUtils->isCurrentWikiCentral() ? !$canChangeRemotePrivate : !$ceMW,
			],
			'closed' => [
				'if' => $mwCore->isEnabled( 'closed-wikis' ),
				'type' => 'check',
				'default' => $mwCore->isClosed(),
				'access' => !$ceMW,
			],
			'inactive' => [
				'if' => $mwCore->isEnabled( 'inactive-wikis' ),
				'type' => 'check',
				'default' => $mwCore->isInactive(),
				'access' => !$ceMW,
			],
			'inactive-exempt' => [
				'if' => $mwCore->isEnabled( 'inactive-wikis' ),
				'type' => 'check',
				'default' => $mwCore->isInactiveExempt(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
			],
			'inactive-exempt-reason' => [
				'if' => $mwCore->isEnabled( 'inactive-wikis' ) &&
					$mwCore->getInactiveExemptReasonOptions(),
				'hide-if' => [ '!==', 'inactive-exempt', '1' ],
				'type' => 'selectorother',
				'default' => $mwCore->getInactiveExemptReason(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
				'options' => $mwCore->getInactiveExemptReasonOptions(),
			],
			'server' => [
				'if' => $mwCore->isEnabled( 'server' ),
				'type' => 'text',
				'default' => $mwCore->getServerName(),
				'access' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
			],
			'experimental' => [
				'if' => $mwCore->isEnabled( 'experimental-wikis' ),
				'type' => 'check',
				'default' => $mwCore->isExperimental(),
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
					'cssclass' => 'ext-managewiki-infuse',
					'section' => 'main',
				];

				if ( $data['required'] ?? false ) {
					$formDescriptor[$name]['required'] = true;
				}

				if ( $data['maxlength'] ?? false ) {
					$formDescriptor[$name]['maxlength'] = $data['maxlength'] ?? 0;
				}

				if ( $data['hide-if'] ?? false ) {
					$formDescriptor[$name]['hide-if'] = $data['hide-if'] ?? [];
				}

				if ( $data['options'] ?? false ) {
					$formDescriptor[$name]['options'] = $data['options'] ?? [];
				}
			}
		}

		if ( $mwCore->getCategoryOptions() ) {
			$formDescriptor['category'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-category',
				'options' => $mwCore->getCategoryOptions(),
				'default' => $mwCore->getCategory(),
				'disabled' => !$ceMW,
				'cssclass' => 'ext-managewiki-infuse',
				'section' => 'main',
			];
		}

		if ( $mwCore->isEnabled( 'hooks' ) ) {
			$this->hookRunner->onManageWikiCoreAddFormFields(
				$context, $moduleFactory, $dbname, $ceMW, $formDescriptor
			);
		}

		if ( $mwCore->getDatabaseClusters() ) {
			$clusterOptions = array_merge(
				$mwCore->getDatabaseClusters(),
				$mwCore->getDatabaseClustersInactive()
			);

			$formDescriptor['dbcluster'] = [
				'type' => 'select',
				'label-message' => 'managewiki-label-dbcluster',
				'options' => $clusterOptions,
				'default' => $mwCore->getDBCluster(),
				'disabled' => !$context->getAuthority()->isAllowed( 'managewiki-restricted' ),
				'cssclass' => 'ext-managewiki-infuse',
				'section' => 'main',
			];
		}

		return $formDescriptor;
	}

	/**
	 * @return array{}|non-empty-array<string,array<string,mixed>>
	 */
	private function buildDescriptorExtensions(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		ModuleFactory $moduleFactory
	): array {
		$mwExtensions = $moduleFactory->extensions( $dbname );
		$extList = $mwExtensions->list();

		$manageWikiSettings = $this->options->get( ConfigNames::Settings );

		$cache = $this->objectCacheFactory->getLocalClusterInstance();
		$mwRequirements = $this->requirementsFactory->getRequirements( $dbname );

		$credits = $cache->getWithSetCallback(
			$cache->makeGlobalKey( ConfigNames::Extensions, MW_VERSION, 'credits' ),
			WANObjectCache::TTL_DAY,
			function (): array {
				$queue = array_fill_keys( array_merge(
					glob( $this->options->get( MainConfigNames::ExtensionDirectory ) . '/*/extension*.json' ),
					glob( $this->options->get( MainConfigNames::StyleDirectory ) . '/*/skin.json' )
				), true );

				$processor = new ExtensionProcessor();
				foreach ( $queue as $path => $_ ) {
					$processor->extractInfoFromFile( $path );
				}

				$data = $processor->getExtractedInfo();
				return $data['credits'];
			}
		);

		$formDescriptor = [];
		foreach ( $this->options->get( ConfigNames::Extensions ) as $name => $ext ) {
			$hasSettings = count( array_filter(
				$manageWikiSettings,
				static fn ( array $value ): bool => $value['from'] === $name
			) ) > 0;

			$disableIf = [];
			if (
				// Don't want to disable fields for extensions already enabled
				// otherwise it makes disabling them more complicated.
				!in_array( $name, $extList, true ) && (
					isset( $ext['requires']['extensions'] ) ||
					$ext['conflicts']
				)
			) {
				$disableIf = $this->buildDisableIf(
					$ext['requires']['extensions'] ?? [],
					$ext['conflicts'] ?: ''
				);
			}

			$help = [];
			$requirementsCheck = true;
			if ( $ext['requires'] ) {
				$requirementsCheck = $mwRequirements->check(
					// Don't check for extension requirements as we don't want
					// to disable the field, we use disable-if for that.
					array_diff_key( $ext['requires'], [ 'extensions' => true ] ),
					$extList
				);

				$help[] = $this->buildRequires( $context, $ext['requires'] ) . "\n";
			}

			if ( $ext['conflicts'] ?? false ) {
				$help[] = $context->msg( 'managewiki-conflicts', $ext['conflicts'] ?? '' )->parse() . "\n";
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
						'<strong>$1</strong>', $parsed
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
				$help[] = "\n" . $this->linkRenderer->makeExternalLink(
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
				'disabled' => $ceMW ? !$requirementsCheck : true,
				'disable-if' => $disableIf,
				'help' => nl2br( implode( ' ', $help ) ),
				'section' => $ext['section'],
			];
		}

		return $formDescriptor;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function buildDescriptorSettings(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $special
	): array {
		$mwExtensions = $moduleFactory->extensions( $dbname );
		$extList = $mwExtensions->list();
		$mwSettings = $moduleFactory->settings( $dbname );
		$settingsList = $mwSettings->listAll();

		// If we have filtered settings, use them, otherwise use all settings
		$manageWikiSettings = $this->options->get( ConfigNames::Settings );
		$filteredList = array_filter( $manageWikiSettings, static fn ( array $value ): bool =>
			$value['from'] === $special && (
				in_array( $value['from'], $extList, true ) ||
				( $value['global'] ?? false )
			)
		) ?: $manageWikiSettings;

		$mwRequirements = $this->requirementsFactory->getRequirements( $dbname );

		$formDescriptor = [];
		foreach ( $filteredList as $name => $set ) {
			if ( !isset( $set['requires'] ) ) {
				$this->logger->error( '\'requires\' is not set in {config} for {var}', [
					'config' => ConfigNames::Settings,
					'var' => $name,
				] );
				$requirementsCheck = true;
			} else {
				$requirementsCheck = $set['requires'] ?
					$mwRequirements->check( $set['requires'], $extList ) : true;
			}

			$hasVisibilityRequirement = isset( $set['requires']['visibility'] );
			$isGlobal = $set['global'] ?? false;
			$isInExtList = in_array( $set['from'], $extList, true );

			$add = ( !$hasVisibilityRequirement || $requirementsCheck ) && ( $isGlobal || $isInExtList );
			$disabled = $ceMW ? !$requirementsCheck : true;

			$msgName = $context->msg( "managewiki-setting-$name-name" );
			$msgHelp = $context->msg( "managewiki-setting-$name-help" );

			if ( $add ) {
				$value = $settingsList[$name] ?? null;
				if ( isset( $set['associativeKey'] ) ) {
					$value = $settingsList[$name][ $set['associativeKey'] ] ??
						$set['overridedefault'][ $set['associativeKey'] ];
				}

				$configs = $this->typesBuilder->build(
					dbname: $dbname,
					disabled: $disabled,
					options: $set,
					value: $value,
					name: $name
				);

				$help = [];
				if ( $set['requires'] ) {
					$help[] = $this->buildRequires( $context, $set['requires'] ) . "\n";
				}

				$rawMessage = new RawMessage( $set['help'] );
				$help[] = $msgHelp->exists() ? $msgHelp->escaped() : $rawMessage->parse();

				// Hack to prevent "implicit submission". See T275588 for more
				if ( ( $configs['type'] ?? '' ) === 'cloner' ) {
					$formDescriptor["fake-submit-$name"] = [
						'type' => 'submit',
						'disabled' => true,
						'section' => $set['section'],
						'cssclass' => 'ext-managewiki-fakesubmit',
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
					'cssclass' => 'ext-managewiki-infuse',
					'section' => $set['section'],
				] + $configs;
			}
		}

		return $formDescriptor;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 * @throws ErrorPageError
	 */
	private function buildDescriptorNamespaces(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		string $special,
		ModuleFactory $moduleFactory
	): array {
		$mwNamespaces = $moduleFactory->namespaces( $dbname );
		$mwExtensions = $moduleFactory->extensions( $dbname );
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

		$mwRequirements = $this->requirementsFactory->getRequirements( $dbname );
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
						$this->getConfigVar( MainConfigNames::MetaNamespace )
					)->text(),
					$this->options->get( MainConfigNames::MetaNamespace ),
				],
				NS_PROJECT_TALK => [
					$context->msg( 'parentheses',
						$this->getConfigVar( MainConfigNames::MetaNamespaceTalk )
					)->text(),
					str_replace(
						$this->options->get( MainConfigNames::MetaNamespace ),
						'$1',
						$this->options->get( MainConfigNames::MetaNamespaceTalk )
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
					$this->getConfigVar( MainConfigNames::ExtraNamespaces )
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
						$this->getConfigVar( MainConfigNames::ContentNamespaces ),
					],
					'default' => $namespaceData['content'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"subpages-$name" => [
					'type' => 'check',
					'label-message' => [
						'namespaces-subpages',
						$this->getConfigVar( MainConfigNames::NamespacesWithSubpages ),
					],
					'default' => $namespaceData['subpages'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"search-$name" => [
					'type' => 'check',
					'label-message' => [
						'namespaces-search',
						$this->getConfigVar( MainConfigNames::NamespacesToBeSearchedDefault ),
					],
					'default' => $namespaceData['searchable'],
					'disabled' => !$ceMW,
					'section' => $name,
				],
				"contentmodel-$name" => [
					'label-message' => [
						'namespaces-contentmodel',
						$this->getConfigVar( MainConfigNames::NamespaceContentModels ),
					],
					'cssclass' => 'ext-managewiki-infuse',
					'disabled' => !$ceMW,
					'section' => $name,
				] + $this->typesBuilder->build(
					dbname: $dbname,
					disabled: false,
					value: $namespaceData['contentmodel'],
					name: '',
					options: [
						'overridedefault' => false,
						'type' => 'contentmodel',
					]
				),
				"protection-$name" => [
					'type' => 'combobox',
					'label-message' => [
						'namespaces-protection',
						$this->getConfigVar( MainConfigNames::NamespaceProtection ),
					],
					'cssclass' => 'ext-managewiki-infuse',
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

			foreach ( $this->options->get( ConfigNames::NamespacesAdditional ) as $key => $a ) {
				$requirementsCheck = $a['requires'] ?
					$mwRequirements->check( $a['requires'], $extList ) : true;

				$hasVisibilityRequirement = isset( $a['requires']['visibility'] );
				$isFromMediaWiki = $a['from'] === 'mediawiki';
				$isInExtList = in_array( $a['from'], $extList, true );

				$add = ( !$hasVisibilityRequirement || $requirementsCheck ) && ( $isFromMediaWiki || $isInExtList );
				$disabled = $ceMW ? !$requirementsCheck : true;

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

					$configs = $this->typesBuilder->build(
						dbname: $dbname,
						disabled: $disabled,
						options: $a,
						value: $namespaceData['additional'][$key] ?? null,
						name: ''
					);

					$help = [];
					if ( $a['requires'] ?? false ) {
						$help[] = $this->buildRequires( $context, $a['requires'] ?? [] ) . "\n";
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
						'cssclass' => 'ext-managewiki-infuse',
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
				'cssclass' => 'ext-managewiki-fakesubmit',
			];

			$formDescriptor["aliases-$name"] = [
				'label-message' => [
					'namespaces-aliases',
					$this->getConfigVar( MainConfigNames::NamespaceAliases ),
				],
				'cssclass' => 'ext-managewiki-infuse',
				'disabled' => !$ceMW,
				'section' => $name,
			] + $this->typesBuilder->build(
				dbname: $dbname,
				disabled: false,
				value: $namespaceData['aliases'],
				name: '',
				options: [
					'overridedefault' => [],
					'type' => 'texts',
				]
			);
		}

		if ( $ceMW && !$mwNamespaces->list( $namespaceID )['core'] ) {
			$craftedNamespaces = [];
			$canDelete = $mwNamespaces->exists( $namespaceID );

			foreach ( $mwNamespaces->listAll() as $id => $config ) {
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
					'cssclass' => 'ext-managewiki-infuse',
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

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function buildDescriptorPermissions(
		string $dbname,
		bool $ceMW,
		IContextSource $context,
		string $group,
		ModuleFactory $moduleFactory
	): array {
		if ( in_array( $group, $this->options->get( ConfigNames::PermissionsDisallowedGroups ), true ) ) {
			$ceMW = false;
		}

		$mwPermissions = $moduleFactory->permissions( $dbname );
		$groupData = $mwPermissions->list( $group );

		$matrixConstruct = [
			$this->getConfigName( MainConfigNames::AddGroups ) => $groupData['addgroups'],
			$this->getConfigName( MainConfigNames::RemoveGroups ) => $groupData['removegroups'],
			$this->getConfigName( MainConfigNames::GroupsAddToSelf ) => $groupData['addself'],
			$this->getConfigName( MainConfigNames::GroupsRemoveFromSelf ) => $groupData['removeself'],
		];

		$assignedPermissions = $groupData['permissions'] ?? [];

		$disallowed = array_merge(
			$this->options->get( ConfigNames::PermissionsDisallowedRights )[$group] ?? [],
			$this->options->get( ConfigNames::PermissionsDisallowedRights )['any']
		);

		$allPermissions = $this->permissionManager->getAllPermissions();

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
				$mwPermissions->listGroups(),
				$this->options->get( ConfigNames::PermissionsDisallowedGroups ),
				$this->userGroupManager->listAllImplicitGroups()
			),
			'groupMatrix' => $this->handleMatrix( json_encode( $matrixConstruct ), 'php' ),
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

		$disallowedGroups = $this->options->get( ConfigNames::PermissionsDisallowedGroups );

		if (
			$ceMW &&
			$mwPermissions->exists( $group ) &&
			!in_array( $group, $this->options->get( ConfigNames::PermissionsPermanentGroups ), true )
		) {
			$formDescriptor += [
				'delete-checkbox' => [
					'type' => 'check',
					'label-message' => 'permissions-delete-checkbox',
					'default' => false,
					'section' => 'advanced',
				],
				'rename-checkbox' => [
					'type' => 'check',
					'label-message' => 'managewiki-permissions-rename-checkbox',
					'disable-if' => [ '===', 'delete-checkbox', '1' ],
					'section' => 'advanced',
				],
				'group-name' => [
					'type' => 'text',
					'label-message' => 'managewiki-permissions-label-group-name',
					'required' => true,
					// https://github.com/miraheze/ManageWiki/blob/4d96137/sql/mw_permissions.sql#L3
					'maxlength' => 64,
					'default' => $group,
					'section' => 'advanced',
					'disable-if' => [ '===', 'delete-checkbox', '1' ],
					'hide-if' => [ '!==', 'rename-checkbox', '1' ],
					// Make sure this is lowercase (multi-byte safe), and has no trailing spaces,
					// and that any remaining spaces are converted to underscores.
					'filter-callback' => static fn ( string $value ): string => mb_strtolower(
						str_replace( ' ', '_', trim( $value ) )
					),
					'validation-callback' => static fn ( string $value ): Message|true => match ( true ) {
						// We just use this to check if the group is valid for a title,
						// otherwise we can not edit it because the title will be
						// invalid for the ManageWiki permission subpage.
						// If this returns null, it is invalid.
						SpecialPage::getSafeTitleFor( 'ManageWiki', "permissions/$value" ) === null =>
							$context->msg( 'managewiki-permissions-group-invalid' ),

						// The entered group is in the disallowed groups config
						in_array( $value, $disallowedGroups, true ) =>
							$context->msg( 'managewiki-permissions-group-disallowed' ),

						// The entered group name already exists
						$mwPermissions->exists( $value ) =>
							$context->msg( 'managewiki-permissions-group-conflict' ),

						// Everything is all good to proceed with renaming this group
						default => true,
					},
				],
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
					$this->getConfigName( MainConfigNames::AddGroups ),
				$context->msg( 'managewiki-permissions-removeall' )->escaped() =>
					$this->getConfigName( MainConfigNames::RemoveGroups ),
				$context->msg( 'managewiki-permissions-addself' )->escaped() =>
					$this->getConfigName( MainConfigNames::GroupsAddToSelf ),
				$context->msg( 'managewiki-permissions-removeself' )->escaped() =>
					$this->getConfigName( MainConfigNames::GroupsRemoveFromSelf ),
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
				'default' => in_array( 'once', (array)$aP, true ),
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
				'default' => in_array( APCOND_EMAILCONFIRMED, (array)$aP, true ),
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'blocked' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-blocked',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => in_array( APCOND_BLOCKED, (array)$aP, true ),
				'disabled' => !$ceMW,
				'section' => 'advanced/autopromote',
			],
			'bot' => [
				'type' => 'check',
				'label-message' => 'managewiki-permissions-autopromote-bot',
				'hide-if' => [ '!==', 'enable', '1' ],
				'default' => in_array( APCOND_ISBOT, (array)$aP, true ),
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

	public function submissionHandler(
		array $formData,
		HTMLForm $form,
		string $module,
		string $dbname,
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $special
	): array {
		$mwReturn = match ( $module ) {
			'core' => $this->submissionCore( $formData, $dbname, $context, $moduleFactory ),
			'extensions' => $this->submissionExtensions( $formData, $dbname, $moduleFactory ),
			'settings' => $this->submissionSettings( $formData, $dbname, $special, $moduleFactory ),
			'namespaces' => $this->submissionNamespaces( $formData, $dbname, $special, $moduleFactory ),
			'permissions' => $this->submissionPermissions( $formData, $dbname, $special, $moduleFactory ),
		};

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

			if ( $mwReturn instanceof PermissionsModule || $mwReturn instanceof NamespacesModule ) {
				if ( $mwReturn->isDeleting( $special ) ) {
					$context->getRequest()->getSession()->set( 'manageWikiSaveSuccess', 1 );
					$context->getOutput()->redirect(
						SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL()
					);
				}

				if ( $mwReturn instanceof PermissionsModule && $mwReturn->isRenaming( $special ) ) {
					$context->getRequest()->getSession()->set( 'manageWikiSaveSuccess', 1 );
					$context->getOutput()->redirect(
						SpecialPage::getTitleFor( 'ManageWiki', "$module/{$formData['group-name']}" )->getFullURL()
					);
				}
			}
		} else {
			return $mwReturn->getErrors() ?:
				[ [ 'managewiki-changes-none' => null ] ];
		}

		return $mwReturn->getErrors();
	}

	private function submissionCore(
		array $formData,
		string $dbname,
		IContextSource $context,
		ModuleFactory $moduleFactory
	): ICoreModule {
		$mwActions = [
			'delete',
			'lock',
			'undelete',
			'unlock',
		];

		$mwCore = $moduleFactory->core( $dbname );
		foreach ( $mwActions as $mwAction ) {
			if ( !$mwCore->isEnabled( "action-$mwAction" ) ) {
				continue;
			}

			if ( $formData[$mwAction] ?? false ) {
				$mwCore->$mwAction();
				return $mwCore;
			}
		}

		if ( $mwCore->isEnabled( 'private-wikis' ) && $mwCore->isPrivate() !== $formData['private'] ) {
			$formData['private'] ? $mwCore->markPrivate() : $mwCore->markPublic();
		}

		if ( $mwCore->isEnabled( 'experimental-wikis' ) &&
			$mwCore->isExperimental() !== $formData['experimental']
		   ) {
			$formData['experimental'] ? $mwCore->markExperimental() : $mwCore->unMarkExperimental();
		}

		if ( $mwCore->isEnabled( 'closed-wikis' ) ) {
			$closed = $mwCore->isClosed();
			$newClosed = (bool)$formData['closed'];

			if ( $newClosed && $closed !== $newClosed ) {
				$mwCore->markClosed();
			} elseif ( !$newClosed && $closed !== $newClosed ) {
				$mwCore->markActive();
			}
		}

		if ( $mwCore->isEnabled( 'inactive-wikis' ) ) {
			$newInactive = $formData['inactive'];
			$inactive = $mwCore->isInactive();
			$newInactiveExempt = $formData['inactive-exempt'];

			if ( $newInactive !== $inactive ) {
				$newInactive ? $mwCore->markInactive() : $mwCore->markActive();
			}

			if ( $context->getAuthority()->isAllowed( 'managewiki-restricted' ) ) {
				if ( $newInactiveExempt !== $mwCore->isInactiveExempt() ) {
					if ( $newInactiveExempt ) {
						$mwCore->markExempt();
					} else {
						$mwCore->unExempt();
					}
				}

				$newInactiveExemptReason = $formData['inactive-exempt-reason'] ?? false;
				if ( $newInactiveExemptReason && $newInactiveExemptReason !== $mwCore->getInactiveExemptReason() ) {
					$mwCore->setInactiveExemptReason( $formData['inactive-exempt-reason'] );
				}
			}
		}

		if ( $mwCore->getCategoryOptions() && $formData['category'] !== $mwCore->getCategory() ) {
			$mwCore->setCategory( $formData['category'] );
		}

		if ( $mwCore->isEnabled( 'server' ) && $formData['server'] !== $mwCore->getServerName() ) {
			$mwCore->setServerName( $formData['server'] );
		}

		if ( $mwCore->isEnabled( 'sitename' ) && $formData['sitename'] !== $mwCore->getSitename() ) {
			$mwCore->setSitename( $formData['sitename'] );
		}

		if ( $mwCore->isEnabled( 'language' ) && $formData['language'] !== $mwCore->getLanguage() ) {
			$mwCore->setLanguage( $formData['language'] );
		}

		if ( $mwCore->getDatabaseClusters() && $formData['dbcluster'] !== $mwCore->getDBCluster() ) {
			$mwCore->setDBCluster( $formData['dbcluster'] );
		}

		if ( $mwCore->isEnabled( 'hooks' ) ) {
			$this->hookRunner->onManageWikiCoreFormSubmission(
				$context, $moduleFactory, $dbname, $formData
			);
		}

		return $mwCore;
	}

	private function submissionExtensions(
		array $formData,
		string $dbname,
		ModuleFactory $moduleFactory
	): ExtensionsModule {
		$mwExtensions = $moduleFactory->extensions( $dbname );

		$newExtList = [];
		foreach ( $this->options->get( ConfigNames::Extensions ) as $name => $_ ) {
			if ( $formData["ext-$name"] ) {
				$newExtList[] = $name;
			}
		}

		$mwExtensions->overwriteAll( $newExtList );
		return $mwExtensions;
	}

	private function submissionSettings(
		array $formData,
		string $dbname,
		string $special,
		ModuleFactory $moduleFactory
	): SettingsModule {
		$mwExtensions = $moduleFactory->extensions( $dbname );
		$extList = $mwExtensions->list();

		$mwSettings = $moduleFactory->settings( $dbname );
		$settingsList = $mwSettings->listAll();

		$mwRequirements = $this->requirementsFactory->getRequirements( $dbname );

		$settingsArray = [];
		foreach ( $this->options->get( ConfigNames::Settings ) as $name => $set ) {
			// No need to do anything if setting does not 'exist'
			if ( !isset( $formData["set-$name"] ) ) {
				continue;
			}

			$current = $settingsList[$name] ?? $set['overridedefault'];
			if ( isset( $set['associativeKey'] ) ) {
				$current = $settingsList[$name][ $set['associativeKey'] ] ??
					$set['overridedefault'][ $set['associativeKey'] ];
			}

			$requirementsCheck = $set['requires'] ?
				$mwRequirements->check( $set['requires'], $extList ) : true;

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
					$current = $this->handleMatrix( $current, 'php' );
					$value = $this->handleMatrix( $value, 'phparray' );
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

			if ( !$requirementsCheck ) {
				$value = $current;
			}

			if ( isset( $set['associativeKey'] ) ) {
				$settingsArray[$name] = $set['overridedefault'];
				$settingsArray[$name][ $set['associativeKey'] ] = $value;
			} else {
				$settingsArray[$name] = $value;
			}
		}

		$manageWikiSettings = $this->options->get( ConfigNames::Settings );
		$filteredList = array_filter( $manageWikiSettings, static fn ( array $value ): bool =>
			$value['from'] === $special && (
				in_array( $value['from'], $extList, true ) ||
				( $value['global'] ?? false )
			)
		);

		// Don't remove those not present in form if we are currently filtering.
		$mwSettings->overwriteAll( $settingsArray, remove: !$filteredList );
		return $mwSettings;
	}

	private function submissionNamespaces(
		array $formData,
		string $dbname,
		string $special,
		ModuleFactory $moduleFactory
	): NamespacesModule {
		$mwNamespaces = $moduleFactory->namespaces( $dbname );

		if ( $formData['delete-checkbox'] ) {
			$mwNamespaces->remove(
				(int)$special,
				$formData['delete-migrate-to'],
				maintainPrefix: false
			);
			$mwNamespaces->remove(
				(int)$special + 1,
				$formData['delete-migrate-to'] + 1,
				maintainPrefix: false
			);
			return $mwNamespaces;
		}

		$nsID = [
			'namespace' => (int)$special,
			'namespacetalk' => (int)$special + 1,
		];

		foreach ( $nsID as $name => $id ) {
			$namespaceName = str_replace( [ ' ', ':' ], '_', $formData["namespace-$name"] );

			$additionalBuilt = [];
			foreach ( $this->options->get( ConfigNames::NamespacesAdditional ) as $key => $_ ) {
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

			$mwNamespaces->modify( $id, $build, maintainPrefix: false );
		}

		return $mwNamespaces;
	}

	private function submissionPermissions(
		array $formData,
		string $dbname,
		string $group,
		ModuleFactory $moduleFactory
	): PermissionsModule {
		$mwPermissions = $moduleFactory->permissions( $dbname );
		$groupData = $mwPermissions->list( $group );

		$assignedPermissions = $groupData['permissions'] ?? [];

		$disallowed = array_merge(
			$this->options->get( ConfigNames::PermissionsDisallowedRights )[$group] ?? [],
			$this->options->get( ConfigNames::PermissionsDisallowedRights )['any']
		);

		$allPermissions = $this->permissionManager->getAllPermissions();
		$assignablePerms = array_diff( $allPermissions, $disallowed );

		$extraAssigned = array_filter(
			$assignedPermissions,
			static fn ( string $perm ): bool => !in_array( $perm, $assignablePerms, true ) &&
				!in_array( $perm, $disallowed, true )
		);

		$assignablePerms = array_unique( array_merge( $assignablePerms, $extraAssigned ) );
		$isRemovable = !in_array( $group, $this->options->get( ConfigNames::PermissionsPermanentGroups ), true );

		// Early escape for deletion
		if ( $isRemovable && ( $formData['delete-checkbox'] ?? false ) ) {
			$mwPermissions->remove( $group );
			return $mwPermissions;
		}

		// Early escape for rename
		if ( $isRemovable && !empty( $formData['group-name'] ) && $formData['group-name'] !== $group ) {
			$mwPermissions->rename( $group, $formData['group-name'] );
			return $mwPermissions;
		}

		$permData = [];
		$addedPerms = [];
		$removedPerms = [];

		foreach ( $assignablePerms as $perm ) {
			if ( $formData["right-$perm"] && !in_array( $perm, $groupData['permissions'], true ) ) {
				$addedPerms[] = $perm;
				continue;
			}

			if ( !$formData["right-$perm"] && in_array( $perm, $groupData['permissions'], true ) ) {
				$removedPerms[] = $perm;
			}
		}

		// Add permission changes to permData
		$permData['permissions'] = [
			'add' => $addedPerms,
			'remove' => $removedPerms,
		];

		$newMatrix = $this->handleMatrix( $formData['group-matrix'], 'phparray' );

		$matrixNew = [
			'addgroups' => array_diff(
				$newMatrix[$this->getConfigName( MainConfigNames::AddGroups )] ?? [],
				$groupData['addgroups']
			),
			'removegroups' => array_diff(
				$newMatrix[$this->getConfigName( MainConfigNames::RemoveGroups )] ?? [],
				$groupData['removegroups']
			),
			'addself' => array_diff(
				$newMatrix[$this->getConfigName( MainConfigNames::GroupsAddToSelf )] ?? [],
				$groupData['addself']
			),
			'removeself' => array_diff(
				$newMatrix[$this->getConfigName( MainConfigNames::GroupsRemoveFromSelf )] ?? [],
				$groupData['removeself']
			),
		];

		$matrixOld = [
			'addgroups' => array_diff(
				$groupData['addgroups'],
				$newMatrix[$this->getConfigName( MainConfigNames::AddGroups )] ?? []
			),
			'removegroups' => array_diff(
				$groupData['removegroups'],
				$newMatrix[$this->getConfigName( MainConfigNames::RemoveGroups )] ?? []
			),
			'addself' => array_diff(
				$groupData['addself'],
				$newMatrix[$this->getConfigName( MainConfigNames::GroupsAddToSelf )] ?? []
			),
			'removeself' => array_diff(
				$groupData['removeself'],
				$newMatrix[$this->getConfigName( MainConfigNames::GroupsRemoveFromSelf )] ?? []
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

		$aE = (bool)$formData['enable'];
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
