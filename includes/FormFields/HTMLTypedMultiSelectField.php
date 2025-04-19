<?php

namespace Miraheze\ManageWiki\FormFields;

use MediaWiki\HTMLForm\Field\HTMLMultiSelectField;

/**
 * Multi-select field that preserves original value types.
 */
class HTMLTypedMultiSelectField extends HTMLMultiSelectField {

	private array $actualOptions;

	public function __construct( array $params ) {
		$this->actualOptions = $params['options'] ?? [];

		// Replace options with stringified version for rendering and HTMLForm internals
		// Otherwise the form fields break.
		$params['options'] = [];
		foreach ( $this->actualOptions as $label => $value ) {
			$params['options'][$label] = (string)$value;
		}

		parent::__construct( $params );
	}

	/**
	 * Convert request string values back to their original types
	 * based on the defined options array.
	 * @inheritDoc
	 */
	public function loadDataFromRequest( $request ) {
		$data = parent::loadDataFromRequest( $request );
		$flatOptions = self::flattenOptions( $this->actualOptions );

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
