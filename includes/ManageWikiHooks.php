<?php

class ManageWikiHooks {
        public static function onRegistration() {
                global $wgLogTypes;

                if ( !in_array( 'farmer', $wgLogTypes ) ) {
                        $wgLogTypes[] = 'farmer';
                }
        }

	public static function fnNewSidebarItem( $skin, &$bar ) {
		if ( $skin->getUser()->isAllowed( 'managewiki' ) ) {
			$bar['administration'][] = [
				'text' => wfMessage( 'managewiki-settings-link' )->plain(),
				'id' => 'managewikilink',
				'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWiki' )->getFullURL() )
			];
			$bar['administration'][] = [
                                'text' => wfMessage( 'managewiki-extensions-link' )->plain(),
                                'id' => 'managewikiextensionslink',
                                'href' => htmlspecialchars( SpecialPage::getTitleFor( 'ManageWikiExtensions' )->getFullURL() )
                        ];
		}
	}
}
