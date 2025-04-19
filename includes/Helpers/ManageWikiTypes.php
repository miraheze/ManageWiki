<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\Config;
use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use Miraheze\ManageWiki\ManageWiki;

class ManageWikiTypes {

	public static function process(
		Config $config,
		bool $disabled,
		array $groupList,
		string $module,
		array $options,
		mixed $value,
		string $name,
		mixed $overrideDefault = false,
		string $type = ''
	): array {
		if ( $module === 'namespaces' ) {
			if ( $overrideDefault ) {
				$options['overridedefault'] = $overrideDefault;
			}

			if ( $type ) {
				$options['type'] = $type;
			}

			return self::namespaces( $overrideDefault, $type, $value ) ?:
				self::common( $config, $disabled, $groupList, $name, $options, $value );
		}

		return self::common( $config, $disabled, $groupList, $name, $options, $value );
	}

	private static function common(
		Config $config,
		bool $disabled,
		array $groupList,
		string $name,
		array $options,
		mixed $value
	): array {
		switch ( $options['type'] ) {
			case 'database':
				$configs = [
					'type' => 'text',
					'default' => $value ?? $options['overridedefault'],
					'validation-callback' => static function (
						string $database,
						array $alldata,
						HTMLForm $form
					) use ( $config, $name ): bool|Message {
						if ( !in_array( $database, $config->get( MainConfigNames::LocalDatabases ), true ) ) {
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
						static fn ( int $num ): array => [ 'value' => $num ],
						$value ?? $options['overridedefault']
					),
				];
				break;
			case 'interwiki':
				$interwikiPrefixes = [];

				$interwikiLookup = MediaWikiServices::getInstance()->getInterwikiLookup();
				$prefixes = $interwikiLookup->getAllPrefixes();

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
					'type' => 'select',
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
			case 'matrix':
				$configs = [
					'type' => 'checkmatrix',
					'rows' => $options['rows'],
					'columns' => $options['cols'],
					'default' => $value !== null ? ManageWiki::handleMatrix( $value, 'php' ) : $options['overridedefault'],
				];
				break;
			case 'preferences':
				$preferences = [];
				$excludedPrefs = [];
				$allPreferences = MediaWikiServices::getInstance()->getUserOptionsLookup()->getDefaultOptions();

				// Don't show preferences hidden by configuratiom
				if ( !$config->get( MainConfigNames::AllowUserCssPrefs ) ) {
					$excludedPrefs[] = 'underline';
					$excludedPrefs[] = 'editfont';
				}

				if ( $config->get( MainConfigNames::DisableLangConversion ) ) {
					$excludedPrefs[] = 'variant';
				} else {
					foreach ( preg_grep( '/variant-[A-Za-z0-9]/', array_keys( $allPreferences ) ) as $pref => $val ) {
						$excludedPrefs[] = array_keys( $allPreferences )[$pref];
					}
				}

				if ( $config->get( MainConfigNames::ForceHTTPS ) || !$config->get( MainConfigNames::SecureLogin ) ) {
					$excludedPrefs[] = 'prefershttps';
				}

				if ( !$config->get( MainConfigNames::RCShowWatchingUsers ) ) {
					$excludedPrefs[] = 'shownumberswatching';
				}

				if ( !$config->get( MainConfigNames::RCWatchCategoryMembership ) ) {
					$excludedPrefs[] = 'hidecategorization';
					$excludedPrefs[] = 'watchlisthidecategorization';
				}

				if ( !$config->get( MainConfigNames::SearchMatchRedirectPreference ) ) {
					$excludedPrefs[] = 'search-match-redirect';
				}

				if ( !$config->get( MainConfigNames::EnableEmail ) ) {
					$excludedPrefs[] = 'requireemail';

					if ( !$config->get( MainConfigNames::EnableUserEmail ) ) {
						$excludedPrefs[] = 'disablemail';
						$excludedPrefs[] = 'email-allow-new-users';
						$excludedPrefs[] = 'ccmeonemails';

						if ( !$config->get( MainConfigNames::EnableUserEmailMuteList ) ) {
							$excludedPrefs[] = 'email-blacklist';
						}
					}

					if ( !$config->get( MainConfigNames::EnotifWatchlist ) ) {
						$excludedPrefs[] = 'enotifwatchlistpages';
					}

					if ( !$config->get( MainConfigNames::EnotifUserTalk ) ) {
						$excludedPrefs[] = 'enotifusertalkpages';
					}

					if ( !$config->get( MainConfigNames::EnotifUserTalk ) && !$config->get( MainConfigNames::EnotifWatchlist ) ) {
						if ( !$config->get( MainConfigNames::EnotifMinorEdits ) ) {
							$excludedPrefs[] = 'enotifminoredits';
						}

						if ( !$config->get( MainConfigNames::EnotifRevealEditorAddress ) ) {
							$excludedPrefs[] = 'enotifrevealaddr';
						}
					}
				}

				// Exclude searchNs* preferences
				foreach ( preg_grep( '/searchNs[0-9]/', array_keys( $allPreferences ) ) as $pref => $val ) {
					$excludedPrefs[] = array_keys( $allPreferences )[$pref];
				}

				// Exclude echo-subscriptions-* preferences
				foreach ( preg_grep( '/echo-subscriptions-(?s).*/', array_keys( $allPreferences ) ) as $pref => $val ) {
					$excludedPrefs[] = array_keys( $allPreferences )[$pref];
				}

				// Exclude downloaduserdata preference
				$excludedPrefs[] = 'downloaduserdata';

				foreach ( $allPreferences as $pref => $val ) {
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
				$enabledSkins = MediaWikiServices::getInstance()->getSkinFactory()->getInstalledSkins();

				unset(
					$enabledSkins['apioutput'],
					$enabledSkins['authentication-popup'],
					$enabledSkins['fallback'],
					$enabledSkins['json']
				);

				if ( $options['excludeSkipSkins'] ?? false ) {
					foreach ( $config->get( MainConfigNames::SkipSkins ) as $skip ) {
						unset( $enabledSkins[$skip] );
					}
				}

				$enabledSkins = array_flip( $enabledSkins );
				ksort( $enabledSkins );

				$configs = [
					'type' => 'select',
					'options' => array_merge( $enabledSkins, $options['options'] ?? [] ),
					'default' => $value ?? $options['overridedefault'],
				];
				break;
			case 'skins':
				$enabledSkins = MediaWikiServices::getInstance()->getSkinFactory()->getInstalledSkins();

				unset(
					$enabledSkins['apioutput'],
					$enabledSkins['authentication-popup'],
					$enabledSkins['fallback'],
					$enabledSkins['json']
				);

				if ( $options['excludeSkipSkins'] ?? false ) {
					foreach ( $config->get( MainConfigNames::SkipSkins ) as $skip ) {
						unset( $enabledSkins[$skip] );
					}
				}

				$enabledSkins = array_flip( $enabledSkins );
				ksort( $enabledSkins );

				$configs = [
					'type' => 'multiselect',
					'options' => isset( $options['options'] ) ? array_merge( $enabledSkins, $options['options'] ) : $enabledSkins,
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
						static fn ( string $text ): array => [ 'value' => $text ],
						$value ?? $options['overridedefault']
					),
				];
				break;
			case 'timezone':
				$configs = [
					'type' => 'select',
					'options' => ManageWiki::getTimezoneList(),
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
				$language = RequestContext::getMain()->getLanguage();
				$groups = [];
				foreach ( $groupList as $group ) {
					$lowerCaseGroupName = strtolower( $group );
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
				$rights = [];
				foreach ( MediaWikiServices::getInstance()->getPermissionManager()->getAllPermissions() as $right ) {
					$rights[$right] = $right;
				}

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

	private static function namespaces(
		mixed $overrideDefault,
		string $type,
		mixed $value
	): array {
		if ( $type === 'contentmodel' ) {
			$contentHandlerFactory = MediaWikiServices::getInstance()->getContentHandlerFactory();

			$models = $contentHandlerFactory->getContentModels();
			$contentModels = [];
			foreach ( $models as $model ) {
				$contentModels[ucfirst( ContentHandler::getLocalizedName( $model ) )] = $model;
			}

			uksort( $contentModels, 'strcasecmp' );

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
