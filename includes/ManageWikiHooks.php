<?php

class ManageWikiHooks {
        public static function onRegistration() {
                global $wgLogTypes;

                if ( !in_array( 'farmer', $wgLogTypes ) ) {
                        $wgLogTypes[] = 'farmer';
                }
        }

	public static function onSetupAfterCache() {
		global $wgManageWikiPermissionsManagement, $wgGroupPermissions, $wgAddGroups, $wgRemoveGroups;

		// Safe guard if - should not remove all existing settigs if we're not managing permissions with in.
		if ( $wgManageWikiPermissionsManagement ) {
			$wgGroupPermissions = [];
			$wgAddGroups = [];
			$wgRemoveGroups = [];
		}
	}

	public static function fnNewSidebarItem( $skin, &$bar ) {
		global $wgManageWikiSidebarLinks;
		
		if (
			$skin->getUser()->isAllowed( 'managewiki' ) &&
			$wgManageWikiSidebarLinks
		) {
			$bar['Administration'][] = [
				'text' => wfMessage( 'managewiki-settings-link' )->plain(),
				'id' => 'managewikilink',
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki' )->getFullURL() )
			];
			$bar['Administration'][] = [
                                'text' => wfMessage( 'managewiki-extensions-link' )->plain(),
                                'id' => 'managewikiextensionslink',
                                'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWikiExtensions' )->getFullURL() )
                        ];
		}
	}
}
