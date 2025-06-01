<?php

namespace Miraheze\ManageWiki\Traits;

use function count;
use function explode;
use function is_array;
use function json_decode;

trait MatrixHandlerTrait {

	private function handleMatrix(
		array|string $conversion,
		string $to
	): array {
		return match ( $to ) {
			'php' => ( static function ( string $json ): array {
				$decoded = json_decode( $json, true );
				if ( !is_array( $decoded ) ) {
					return [];
				}

				$result = [];
				foreach ( $decoded as $key => $values ) {
					foreach ( (array)$values as $val ) {
						$result[] = "$key-$val";
					}
				}
				return $result;
			} )( $conversion ),

			'phparray' => (
				/** @return non-empty-array<string, non-empty-list<?string>> */
				static function ( array $flat ): array {
					$result = [];
					foreach ( $flat as $item ) {
						$parts = explode( '-', $item, 2 );
						if ( count( $parts ) === 2 ) {
							[ $row, $col ] = $parts;
							$result[$row][] = $col;
						}
					}
					return $result;
				}
			)( (array)$conversion ),

			default => [],
		};
	}
}
