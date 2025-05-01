<?php

namespace Miraheze\ManageWiki;

class ManageWiki {

	public static function handleMatrix(
		array|string $conversion,
		string $to
	): array|string|null {
		if ( $to === 'php' ) {
			// $to is php, therefore $conversion must be json
			$phpin = json_decode( $conversion, true );

			$phpout = [];

			foreach ( $phpin as $key => $value ) {
				// We may have an array, may not - let's make it one
				foreach ( (array)$value as $val ) {
					$phpout[] = "$key-$val";
				}
			}

			return $phpout;
		} elseif ( $to === 'phparray' ) {
			// $to is phparray therefore $conversion must be php as json will be already phparray'd
			$phparrayout = [];

			foreach ( (array)$conversion as $phparray ) {
				$element = explode( '-', $phparray, 2 );
				$phparrayout[$element[0]][] = $element[1];
			}

			return $phparrayout;
		} elseif ( $to === 'json' ) {
			// $to is json, therefore $conversion must be php
			return json_encode( $conversion ) ?: null;
		}

		return null;
	}
}
