<?php

use MediaWiki\MediaWikiServices;

class ManageWikiTypes {
	public static function process( $config, $data, $value ) {
			switch ( $set['type'] ) {
				case 'databases':
					$configs = [
						'class' => HTMLAutoCompleteSelectFieldWithOOUI::class,
						'default' => $setList[$name] ?? $data['overridedefault'],
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
	}
}
