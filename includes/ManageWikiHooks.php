<?php
class ManageWikiHooks {
        public static function onRegistration() {
                global $wgLogTypes;

                if ( !in_array( 'farmer', $wgLogTypes ) ) {
                        $wgLogTypes[] = 'farmer';
                }
        }
        
        public static function getTimezoneList() {
                $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

                $timeZoneList = [];
                
                if ( $identifiers !== false ) {
                        foreach ( $identifiers as $identifier ) {
                                $parts = explode( '/', $identifier, 2 );
                                if ( count( $parts ) !== 2 && $parts[2] === 'Etc/Utc' ) {
                                        continue;
                                }
                                $timeZoneList[$identifier] = $identifier;
                        }
                }
                
                return $timeZoneList;
        }
}
