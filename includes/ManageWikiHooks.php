<?php
class ManageWikiHooks {
        public static function onRegistration() {
                global $wgLogTypes;

                if ( !in_array( 'farmer', $wgLogTypes ) ) {
                        $wgLogTypes[] = 'farmer';
                }
        }
}
