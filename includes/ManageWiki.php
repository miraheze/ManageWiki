<?php

class ManageWiki {
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
