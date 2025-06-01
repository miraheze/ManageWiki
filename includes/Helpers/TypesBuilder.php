<?php

namespace Miraheze\ManageWiki\Helpers;

use Collator;
use DateTimeZone;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use Miraheze\ManageWiki\FormFields\HTMLTypedSelectField;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Traits\MatrixHandlerTrait;
use SkinFactory;
use function array_combine;
use function array_filter;
use function array_flip;
use function array_keys;
use function array_map;
use function array_merge;
use function htmlspecialchars;
use function implode;
use function in_array;
use function ksort;
use function preg_grep;
use function str_contains;
use function uksort;

class TypesBuilder {

	use MatrixHandlerTrait;

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::AllowUserCssPrefs,
		MainConfigNames::DisableLangConversion,
		MainConfigNames::EnableEmail,
		MainConfigNames::EnableUserEmail,
		MainConfigNames::EnableUserEmailMuteList,
		MainConfigNames::EnotifMinorEdits,
		MainConfigNames::EnotifRevealEditorAddress,
		MainConfigNames::EnotifUserTalk,
		MainConfigNames::EnotifWatchlist,
		MainConfigNames::ForceHTTPS,
		MainConfigNames::LocalDatabases,
		MainConfigNames::RCShowWatchingUsers,
		MainConfigNames::RCWatchCategoryMembership,
		MainConfigNames::SearchMatchRedirectPreference,
		MainConfigNames::SecureLogin,
		MainConfigNames::SkipSkins,
	];

	public function __construct(
		private readonly PermissionsFactory $permissionsFactory,
		private readonly IContentHandlerFactory $contentHandlerFactory,
		private readonly InterwikiLookup $interwikiLookup,
		private readonly PermissionManager $permissionManager,
		private readonly SkinFactory $skinFactory,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function build(
		string $dbname,
		string $name,
		mixed $value,
		bool $disabled,
		array $options
	): array {
		switch ( $options['type'] ) {
			case 'contentmodel':
			case 'vestyle':
				$configs = $this->buildNamespaceType(
					$options['type'],
					$options['overridedefault'],
					$value
				);
				break;
			case 'database':
				$configs = [
					'type' => 'text',
					'default' => $value ?? $options['overridedefault'],
					'validation-callback' => function (
						string $database,
						array $alldata,
						HTMLForm $form
					) use ( $name ): Message|true {
						if ( !in_array( $database, $this->options->get( MainConfigNames::LocalDatabases ), true ) ) {
							return $form->msg( 'managewiki-invalid-database', $database, $name );
						}

						return true;
					},
				];
				break;
			case 'float':
				$configs = [
					'type' => 'float',
					'min' => $options['minfloat'],
					'max' => $options['maxfloat'],
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'integer':
				$configs = [
					'type' => 'int',
					'min' => $options['minint'],
					'max' => $options['maxint'],
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'integers':
				$configs = [
					'type' => 'cloner',
					'fields' => [
						'value' => [
							'type' => 'int',
							'min' => $options['minint'] ?? null,
							'max' => $options['maxint'] ?? null,
						],
						'delete' => [
							'type' => 'submit',
							'buttonlabel-message' => 'htmlform-cloner-delete',
							'flags' => [ 'destructive' ],
						],
					],
					'default' => array_map(
						/** @return array{value: int} */
						static fn ( int $num ): array => [ 'value' => $num ],
						$value ?? $options['overridedefault']
					),
				];
				break;
			case 'interwiki':
				$interwikiPrefixes = [];
				$prefixes = $this->interwikiLookup->getAllPrefixes();

				foreach ( $prefixes as $row ) {
					$prefix = $row['iw_prefix'];
					$interwikiPrefixes[$prefix] = $prefix;
				}

				$configs = [
					'type' => 'multiselect',
					'options' => $interwikiPrefixes,
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'language':
				$configs = [
					'type' => 'language',
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'list':
				$configs = [
					'class' => HTMLTypedSelectField::class,
					'options' => $options['options'],
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'list-multi':
				$configs = [
					'type' => 'multiselect',
					'options' => $options['options'],
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'list-multi-bool':
				$configs = [
					'type' => 'multiselect',
					'options' => $options['options'],
					'default' => array_keys( $value ?? $options['overridedefault'], true, true ),
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'list-multi-int':
				$configs = [
					'type' => 'multiselect',
					'options' => $options['options'],
					// multiselect only accepts string values, so we use string here and convert
					// the values to int on submission, otherwise the field breaks.
					'default' => array_map( 'strval', $value ?? $options['overridedefault'] ),
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'matrix':
				$configs = [
					'type' => 'checkmatrix',
					'rows' => $options['rows'],
					'columns' => $options['cols'],
					'default' => $value !== null ?
						$this->handleMatrix( $value, 'php' ) :
						$options['overridedefault'],
				];
				break;
			case 'preferences':
				$preferences = [];
				$excludedPrefs = [];
				$allPreferences = $this->userOptionsLookup->getDefaultOptions();

				// Don't show preferences hidden by configuratiom
				if ( !$this->options->get( MainConfigNames::AllowUserCssPrefs ) ) {
					$excludedPrefs[] = 'underline';
					$excludedPrefs[] = 'editfont';
				}

				if ( $this->options->get( MainConfigNames::DisableLangConversion ) ) {
					$excludedPrefs[] = 'variant';
				} else {
					foreach ( preg_grep( '/variant-[A-Za-z0-9]/', array_keys( $allPreferences ) ) as $pref => $_ ) {
						$excludedPrefs[] = array_keys( $allPreferences )[$pref];
					}
				}

				if ( $this->options->get( MainConfigNames::ForceHTTPS ) ||
					!$this->options->get( MainConfigNames::SecureLogin )
				) {
					$excludedPrefs[] = 'prefershttps';
				}

				if ( !$this->options->get( MainConfigNames::RCShowWatchingUsers ) ) {
					$excludedPrefs[] = 'shownumberswatching';
				}

				if ( !$this->options->get( MainConfigNames::RCWatchCategoryMembership ) ) {
					$excludedPrefs[] = 'hidecategorization';
					$excludedPrefs[] = 'watchlisthidecategorization';
				}

				if ( !$this->options->get( MainConfigNames::SearchMatchRedirectPreference ) ) {
					$excludedPrefs[] = 'search-match-redirect';
				}

				if ( !$this->options->get( MainConfigNames::EnableEmail ) ) {
					$excludedPrefs[] = 'requireemail';

					if ( !$this->options->get( MainConfigNames::EnableUserEmail ) ) {
						$excludedPrefs[] = 'disablemail';
						$excludedPrefs[] = 'email-allow-new-users';
						$excludedPrefs[] = 'ccmeonemails';

						if ( !$this->options->get( MainConfigNames::EnableUserEmailMuteList ) ) {
							$excludedPrefs[] = 'email-blacklist';
						}
					}

					if ( !$this->options->get( MainConfigNames::EnotifWatchlist ) ) {
						$excludedPrefs[] = 'enotifwatchlistpages';
					}

					if ( !$this->options->get( MainConfigNames::EnotifUserTalk ) ) {
						$excludedPrefs[] = 'enotifusertalkpages';
					}

					if (
						!$this->options->get( MainConfigNames::EnotifUserTalk ) &&
						!$this->options->get( MainConfigNames::EnotifWatchlist )
					) {
						if ( !$this->options->get( MainConfigNames::EnotifMinorEdits ) ) {
							$excludedPrefs[] = 'enotifminoredits';
						}

						if ( !$this->options->get( MainConfigNames::EnotifRevealEditorAddress ) ) {
							$excludedPrefs[] = 'enotifrevealaddr';
						}
					}
				}

				// Exclude searchNs* preferences
				foreach ( preg_grep( '/searchNs[0-9]/', array_keys( $allPreferences ) ) as $pref => $_ ) {
					$excludedPrefs[] = array_keys( $allPreferences )[$pref];
				}

				// Exclude echo-subscriptions-* preferences
				foreach ( preg_grep( '/echo-subscriptions-(?s).*/', array_keys( $allPreferences ) ) as $pref => $_ ) {
					$excludedPrefs[] = array_keys( $allPreferences )[$pref];
				}

				// Exclude downloaduserdata preference
				$excludedPrefs[] = 'downloaduserdata';

				// Exclude forcesafemode preference
				$excludedPrefs[] = 'forcesafemode';

				foreach ( $allPreferences as $pref => $_ ) {
					if ( !in_array( $pref, $excludedPrefs, true ) ) {
						$preferences[$pref] = $pref;
					}
				}

				ksort( $preferences );

				$configs = [
					'type' => 'multiselect',
					'options' => $preferences,
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'skin':
				$installedSkins = $this->skinFactory->getInstalledSkins();

				unset(
					$installedSkins['apioutput'],
					$installedSkins['authentication-popup'],
					$installedSkins['fallback'],
					$installedSkins['json']
				);

				if ( $options['excludeSkipSkins'] ?? false ) {
					foreach ( $this->options->get( MainConfigNames::SkipSkins ) as $skip ) {
						unset( $installedSkins[$skip] );
					}
				}

				$installedSkins = array_flip( $installedSkins );
				ksort( $installedSkins );

				$configs = [
					'type' => 'select',
					'options' => array_merge( $installedSkins, $options['options'] ?? [] ),
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'skins':
				$installedSkins = $this->skinFactory->getInstalledSkins();

				unset(
					$installedSkins['apioutput'],
					$installedSkins['authentication-popup'],
					$installedSkins['fallback'],
					$installedSkins['json']
				);

				if ( $options['excludeSkipSkins'] ?? false ) {
					foreach ( $this->options->get( MainConfigNames::SkipSkins ) as $skip ) {
						unset( $installedSkins[$skip] );
					}
				}

				$installedSkins = array_flip( $installedSkins );
				ksort( $installedSkins );

				$configs = [
					'type' => 'multiselect',
					'options' => array_merge( $installedSkins, $options['options'] ?? [] ),
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'texts':
				$configs = [
					'type' => 'cloner',
					'fields' => [
						'value' => [
							'type' => 'text',
						],
						'delete' => [
							'type' => 'submit',
							'buttonlabel-message' => 'htmlform-cloner-delete',
							'flags' => [ 'destructive' ],
						],
					],
					'default' => array_map(
						/** @return array{value: string} */
						static fn ( string $text ): array => [ 'value' => $text ],
						$value ?? $options['overridedefault']
					),
				];
				break;
			case 'timezone':
				$identifiers = DateTimeZone::listIdentifiers( DateTimeZone::ALL );
				$timezones = array_filter(
					$identifiers,
					static fn ( string $id ): bool =>
						str_contains( $id, '/' ) || $id === 'UTC'
				);

				$timezones = array_combine( $timezones, $timezones );

				$configs = [
					'type' => 'select',
					'options' => $timezones,
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'user':
				$configs = [
					'type' => 'user',
					'exists' => true,
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'users':
				$configs = [
					'type' => 'usersmultiselect',
					'exists' => true,
					'default' => implode( "\n", $value ?? $options['overridedefault'] ),
				];
				break;
			case 'usergroups':
				$mwPermissions = $this->permissionsFactory->newInstance( $dbname );
				$groupList = $mwPermissions->listGroups();

				$language = RequestContext::getMain()->getLanguage();
				$groups = [];
				foreach ( $groupList as $group ) {
					$lowerCaseGroupName = $language->lc( $group );
					$groups[htmlspecialchars( $language->getGroupName( $lowerCaseGroupName ) )] = $lowerCaseGroupName;
				}

				$configs = [
					'type' => 'multiselect',
					'options' => array_merge( $groups, $options['options'] ?? [] ),
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'userrights':
				$permissions = $this->permissionManager->getAllPermissions();
				$rights = array_combine( $permissions, $permissions );

				$configs = [
					'type' => 'multiselect',
					'options' => array_merge( $rights, $options['options'] ?? [] ),
					'default' => $value ?? $options['overridedefault'],
				];

				if ( !$disabled ) {
					$configs['dropdown'] = true;
				}
				break;
			case 'wikipage':
				$configs = [
					'type' => 'title',
					'exists' => $options['exists'] ?? true,
					'default' => $value ?? $options['overridedefault'],
					'required' => false,
				];
				break;
			case 'wikipages':
				$configs = [
					'type' => 'titlesmultiselect',
					'exists' => $options['exists'] ?? true,
					'default' => implode( "\n", $value ?? $options['overridedefault'] ),
					'required' => false,
				];
				break;
			default:
				$configs = [
					'type' => $options['type'],
					'default' => $value ?? $options['overridedefault'],
				];
		}

		return $configs;
	}

	private function buildNamespaceType(
		string $type,
		mixed $overrideDefault,
		mixed $value
	): array {
		if ( $type === 'contentmodel' ) {
			$models = $this->contentHandlerFactory->getContentModels();
			$language = RequestContext::getMain()->getLanguage();
			$contentModels = [];
			foreach ( $models as $model ) {
				$contentModels[$language->ucfirst( ContentHandler::getLocalizedName( $model ) )] = $model;
			}

			// Use collator to make sure we do this in a way that works multilingual
			$collator = new Collator( $language->getCode() );
			uksort( $contentModels,
				static fn ( string $a, string $b ): false|int =>
					$collator->compare( $a, $b )
			);

			return [
				'type' => 'select',
				'options' => $contentModels,
				'default' => $value,
			];
		}

		if ( $type === 'vestyle' ) {
			return [
				'type' => 'check',
				'default' => $value ?? $overrideDefault,
			];
		}

		return [];
	}
}
