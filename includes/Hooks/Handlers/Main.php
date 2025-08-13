<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Content\FallbackContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Hook\SetupAfterCacheHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\DataStoreFactory;
use Miraheze\ManageWiki\Hooks\HookRunner;
use Throwable;
use function array_keys;
use function htmlspecialchars;
use function in_array;
use function var_dump;

class Main implements
	ContentHandlerForModelIDHook,
	GetPreferencesHook,
	SetupAfterCacheHook,
	SidebarBeforeOutputHook
{

	public function __construct(
		private readonly Config $config,
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly HookRunner $hookRunner,
		private readonly UserOptionsLookup $userOptionsLookup
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
		try {
			$dbname = $this->config->get( MainConfigNames::DBname );
			$dataStore = $this->dataStoreFactory->newInstance( $dbname );
			$dataStore->syncCache();
		} catch ( Throwable $t ) {
			var_dump( $t );
		}
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
