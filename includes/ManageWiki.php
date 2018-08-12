<?php

class ManageWiki {
        public static function getTimezoneList() {
                $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

                $timeZoneList = [];
                
                if ( $identifiers !== false ) {
                        foreach ( $identifiers as $identifier ) {
                                $parts = explode( '/', $identifier, 2 );
                                if ( count( $parts ) !== 2 && $parts[0] !== 'UTC' ) {
                                        continue;
                                }
                                $timeZoneList[$identifier] = $identifier;
                        }
                }
                
                return $timeZoneList;
        }

	public static function handleMatrix( $conversion, $to ) {
		if ( $to == 'php' ) {
			// $to is php, therefore $conversion must be json
			$phpin = json_decode( $conversion, true );

			$phpout = [];

			foreach ( $phpin as $key => $value ) {
				$phpout[] = "$key-$value";
			}

			return $phpout;
		} elseif ( $to == 'phparray' ) {
			// $to is phparray therefore $conversion must be php as json will be already phparray'd
			$phparrayout = [];

			foreach ( $conversion as $phparray ) {
				$element = explode( '-', $phparray );
				$phparrayout[$element[0]] = $element[1];
			}

			return $phparrayout;
		} elseif ( $to == 'json' ) {
			// $to is json, therefore $conversion must be php
			return json_encode( $conversion );
		} else {
			return null;
		}
	}
}
