<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Settings\Config\GlobalConfigBuilder;
use MediaWiki\Settings\Config\PhpIniSink;
use MediaWiki\Settings\SettingsBuilder;
use MediaWiki\Settings\Source\PhpSettingsSource;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Hooks\ManageWikiHookRunner;

class Main implements
	GetPreferencesHook,
	MediaWikiServicesHook,
	SidebarBeforeOutputHook
{

	public function __construct(
		private readonly Config $config,
		private readonly ManageWikiHookRunner $hookRunner,
		private readonly UserOptionsLookup $userOptionsLookup
	) {
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['managewikisidebar'] = [
			'type' => 'toggle',
			'label-message' => 'managewiki-toggle-forcesidebar',
			'section' => 'rendering',
		];
	}

	public function onMediaWikiServices( $services ) {
		$dbname = $this->config->get( MainConfigNames::DBname );
		// If we don't have a cache file, let us exit here
		if ( !file_exists( "/srv/mediawiki/cache/$dbname.php" ) ) {
			return;
		}

		$currentDatabaseFile = "/srv/mediawiki/cache/$dbname.php";
		$settings = new SettingsBuilder(
			MW_INSTALL_PATH,
			ExtensionRegistry::getInstance(),
			new GlobalConfigBuilder( '' ),
			new PhpIniSink()
		);

		$settings->load( new PhpSettingsSource( $currentDatabaseFile ) );
		$settings->apply();
	}

	/** @inheritDoc */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$authority = $skin->getAuthority();
		$hideSidebar = !$this->config->get( ConfigNames::ForceSidebarLinks ) &&
			!$this->userOptionsLookup->getBoolOption( $authority->getUser(), 'managewikisidebar' );

		$modules = array_keys( $this->config->get( ConfigNames::ModulesEnabled ), true, true );
		foreach ( $modules as $module ) {
			$append = '';
			if ( !$authority->isAllowed( "managewiki-$module" ) ) {
				if ( $hideSidebar ) {
					continue;
				}

				$append = '-view';
			}

			$sidebar['managewiki-sidebar-header'][] = [
				'text' => $skin->msg( "managewiki-link-{$module}{$append}" )->text(),
				'id' => "managewiki{$module}link",
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki', $module )->getFullURL() ),
			];
		}

		if ( isset( $sidebar['managewiki-sidebar-header'] ) ) {
			$sidebarLinks = $sidebar['managewiki-sidebar-header'];
			$this->hookRunner->onManageWikiAfterSidebarLinks( $skin, $sidebarLinks );
			$sidebar['managewiki-sidebar-header'] = $sidebarLinks;
		}
	}
}
