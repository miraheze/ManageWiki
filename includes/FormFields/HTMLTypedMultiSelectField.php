<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLMultiSelectField;

/**
 * Multi-select field that preserves original value types.
 */
class HTMLTypedMultiSelectField extends HTMLMultiSelectField {

	/**
	 * Convert request string values back to their original types
	 * based on the defined options array.
	 * @inheritDoc
	 */
	public function loadDataFromRequest( $request ) {
		$data = parent::loadDataFromRequest( $request );
		$options = $this->mParams['options'] ?? [];

		$flatOptions = self::flattenOptions( $options );
		$reverseLookup = [];

		foreach ( $flatOptions as $originalValue ) {
			$reverseLookup[(string)$originalValue] = $originalValue;
		}

		return array_map(
			static fn ( string $v ): mixed => $reverseLookup[$v] ?? $v,
			(array)$data
		);
	}
}
