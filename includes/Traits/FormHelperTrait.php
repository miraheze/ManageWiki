<?php

namespace Miraheze\ManageWiki\Traits;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use function array_merge;
use function count;
use function implode;
use function is_array;
use function is_int;

trait FormHelperTrait {

	private function buildRequires(
		IContextSource $context,
		array $config
	): string {
		$requires = [];
		$language = $context->getLanguage();

		$or = $context->msg( 'managewiki-requires-or' )->text();
		$space = $context->msg( 'word-separator' )->text();
		$colon = $context->msg( 'colon-separator' )->text();

		foreach ( $config as $require => $data ) {
			$flat = [];
			foreach ( (array)$data as $key => $element ) {
				// $key/$colon can be removed here if visibility becomes its own system
				if ( is_array( $element ) ) {
					$flat[] = $context->msg( 'parentheses',
						$space . ( !is_int( $key ) ? $key . $colon : '' ) . implode(
							$space . $language->uc( $or ) . $space,
							$element
						) . $space
					)->text();
					continue;
				}

				$flat[] = ( !is_int( $key ) ? $key . $colon : '' ) . $element;
			}

			$requires[] = $language->ucfirst( $require ) . $colon . $language->commaList( $flat );
		}

		return $context->msg( 'managewiki-requires', $language->listToText( $requires ) )->parse();
	}

	/**
	 * @return array{0: string, 1?: string|array, 2?: string}
	 */
	private function buildDisableIf( array $requires, string $conflict ): array {
		$conditions = [];
		foreach ( $requires as $entry ) {
			if ( is_array( $entry ) ) {
				// OR logic for this group
				$orConditions = [];
				foreach ( $entry as $ext ) {
					$orConditions[] = [ '!==', "ext-$ext", '1' ];
				}

				$conditions[] = count( $orConditions ) === 1 ?
					$orConditions[0] :
					array_merge( [ 'AND' ], $orConditions );
			} else {
				// Simple AND logic
				$conditions[] = [ '!==', "ext-$entry", '1' ];
			}
		}

		$finalCondition = count( $conditions ) === 1 ?
			$conditions[0] :
			array_merge( [ 'OR' ], $conditions );

		if ( $conflict !== '' ) {
			$finalCondition = [
				'OR',
				$finalCondition,
				[ '===', "ext-$conflict", '1' ]
			];
		}

		return $finalCondition;
	}

	/**
	 * Build a notice warning that toggling an extension is one-way for this user,
	 * as they are missing the permissions needed to reverse the change.
	 *
	 * @param IContextSource $context
	 * @param list<string> $permissions The permissions required to reverse the change.
	 * @param bool $isEnabled Whether the extension is currently enabled.
	 *
	 * @return string
	 */
	private function buildOneWayNotice(
		IContextSource $context,
		array $permissions,
		bool $isEnabled
	): string {
		$message = $context->msg(
			$isEnabled ?
				'managewiki-extension-oneway-disable' :
				'managewiki-extension-oneway-enable',
			$context->getLanguage()->listToText( $permissions ),
			count( $permissions )
		)->parse();

		return Html::rawElement( 'span',
			[ 'class' => 'ext-managewiki-save-warning' ],
			$message
		);
	}
}
