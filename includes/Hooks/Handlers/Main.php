<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Content\FallbackContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Hook\UserGetReservedNamesHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Hooks\HookRunner;
use function array_keys;
use function htmlspecialchars;
use function in_array;

class Main implements
	ContentHandlerForModelIDHook,
	GetPreferencesHook,
	SidebarBeforeOutputHook,
	UserGetReservedNamesHook
{

	public function __construct(
		private readonly Config $config,
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
	public function onUserGetReservedNames( &$reservedUsernames ) {
		$reservedUsernames[] = 'ManageWiki';
	}
}
