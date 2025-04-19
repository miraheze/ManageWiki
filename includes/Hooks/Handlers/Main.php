<?php

namespace Miraheze\ManageWiki\Hooks\Handlers;

use MediaWiki\Config\Config;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Content\TextContentHandler;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use Miraheze\ManageWiki\ConfigNames;

class Main implements
	ContentHandlerForModelIDHook,
	GetPreferencesHook,
	SidebarBeforeOutputHook
{

	public function __construct(
		private readonly Config $config,
		private readonly PermissionManager $permissionManager,
		private readonly UserOptionsLookup $userOptionsLookup
	) {
	}

	/** @inheritDoc */
	public function onContentHandlerForModelID( $modelName, &$handler ) {
		$handler = new TextContentHandler( $modelName );
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		$preferences['managewikisidebar'] = [
			'type' => 'toggle',
			'label-message' => 'managewiki-toggle-forcesidebar',
			'section' => 'rendering',
		];
	}

	/** @inheritDoc */
	public function onSidebarBeforeOutput( $skin, &$sidebar ) {
		$user = $skin->getUser();

		$hideSidebar = !$this->config->get( ConfigNames::ForceSidebarLinks ) &&
			!$this->userOptionsLookup->getBoolOption( $user, 'managewikisidebar' );

		$modules = array_keys( $this->config->get( ConfigNames::ManageWiki ), true );
		foreach ( $modules as $module ) {
			$append = '';
			if ( !$this->permissionManager->userHasRight( $user, "managewiki-$module" ) ) {
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
	}
}
