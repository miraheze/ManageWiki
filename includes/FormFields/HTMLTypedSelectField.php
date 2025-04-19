<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLSelectField;

/**
 * Select field that preserves original value types.
 */
class HTMLTypedSelectField extends HTMLSelectField {

	/**
	 * Skip strict validation — we’ll restore types ourselves.
	 * @inheritDoc
	 */
	public function validate( $value, $alldata ) {
		return true;
	}

	/**
	 * Convert request string values back to their original types
	 * based on the defined options array.
	 * @inheritDoc
	 */
	public function loadDataFromRequest( $request ) {
		$data = parent::loadDataFromRequest( $request );
		$options = $this->mParams['options'] ?? [];

		// Flatten options in case of grouped ones
		$flatOptions = self::flattenOptions( $options );

		foreach ( $flatOptions as $originalValue ) {
			if ( (string)$originalValue === (string)$data ) {
				return $originalValue;
			}
		}

		return $data;
	}
}
