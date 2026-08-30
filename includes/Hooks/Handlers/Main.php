<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Content\FallbackContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Skin\SkinTemplate;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Miraheze\ManageWiki\Hooks\HookRunner;
use function array_keys;
use function htmlspecialchars;
use function in_array;

class Main implements
	ContentHandlerForModelIDHook,
	GetPreferencesHook,
	SetupAfterCacheHook,
	SidebarBeforeOutputHook,
	SkinTemplateNavigation__UniversalHook
{

	private const MODULE_ICONS = [
		'core' => 'labFlask',
		'extensions' => 'edit',
		'namespaces' => 'listBullet',
		'permissions' => 'userGroup',
		'settings' => 'settings',
	];

	public function __construct(
		private readonly Config $config,
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly HookRunner $hookRunner,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/** @inheritDoc */
	public function onContentHandlerForModelID( $modelName, &$handler ) {
		if ( in_array( $modelName, $this->config->get( ConfigNames::HandledUnknownContentModels ), true ) ) {
			$handler = new FallbackContentHandler( $modelName );
		}
	}

	/**
	 * @inheritDoc
	 * @param User $user @phan-unused-param
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['managewikisidebar'] = [
			'type' => 'toggle',
			'label-message' => 'managewiki-toggle-forcesidebar',
			'section' => 'rendering',
		];
	}

	/** @inheritDoc */
	public function onSetupAfterCache() {
		$dbname = $this->config->get( MainConfigNames::DBname );
		$dataStore = $this->dataStoreFactory->newInstance( $dbname );
		$dataStore->syncCache();

		// Safety Catch!
		global $wgGroupPermissions;
		if ( $dataStore->isPrivate() ) {
			$wgGroupPermissions['*']['read'] = false;
			$wgGroupPermissions['sysop']['read'] = true;
			return;
		}

		$wgGroupPermissions['*']['read'] = true;
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

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
        // only on citizen
		if ( $sktemplate->getSkinName() !== 'citizen' ) {
			return;
		}

		if ( !isset( $links['associated-pages'] ) || $links['associated-pages'] === [] ) {
			return;
		}

		foreach ( self::MODULE_ICONS as $module => $icon ) {
			$href = SpecialPage::getTitleFor( 'ManageWiki', $module )->getLocalURL();
			foreach ( $links['associated-pages'] as $key => $link ) {
				if ( ( $link['href'] ?? null ) === $href ) {
					$links['associated-pages'][$key]['icon'] ??= $icon;
				}
			}
		}
	}
}
